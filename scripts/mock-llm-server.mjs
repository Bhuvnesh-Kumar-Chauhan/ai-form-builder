/*
 * Mock OpenAI-compatible chat completions server for local development/tests.
 *
 * Usage:
 *   node scripts/mock-llm-server.mjs
 *
 * Point AI_SERVICE_URL=http://localhost:8001 (and set AI_JSON_MODE=false if your
 * client sends response_format) in .env, start a queue worker, and generate forms.
 *
 * Endpoint: POST /chat/completions  -> OpenAI-shaped response with a form schema.
 * Special keywords in the last user prompt exercise edge cases:
 *   - "garbage"    -> first attempt returns invalid JSON (tests the retry loop)
 *   - "bogus types"-> returns hallucinated field types (tests coercion)
 *   - "hindi"      -> returns Devanagari labels (tests "translate labels to Hindi")
 *   - "emergency"  -> adds an emergency contact section (tests edit mode)
 *   - "phone required" -> sets the phone field required (tests edit mode)
 */

import http from 'node:http';

const PORT = process.env.PORT || 8001;

const server = http.createServer((req, res) => {
  if (req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('mock llm server is running');
    return;
  }

  if (req.method !== 'POST' || req.url !== '/chat/completions') {
    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'not found' }));
    return;
  }

  let body = '';
  req.on('data', (chunk) => (body += chunk));
  req.on('end', () => {
    let payload = {};
    try {
      payload = JSON.parse(body || '{}');
    } catch {
      payload = {};
    }

    const messages = Array.isArray(payload.messages) ? payload.messages : [];
    const prompt = (messages.map((m) => m.content || '').join('\n')).toLowerCase();
    const lastUser = [...messages].reverse().find((m) => m.role === 'user')?.content || '';

    // Retry test: return broken JSON on the first pass when asked for garbage.
    if (/garbage/.test(prompt) && !/was rejected/.test(prompt)) {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({
        id: 'mock-1',
        object: 'chat.completion',
        model: payload.model || 'mock-model',
        choices: [{ index: 0, message: { role: 'assistant', content: 'this is not json at all' }, finish_reason: 'stop' }],
        usage: { prompt_tokens: 12, completion_tokens: 5, total_tokens: 17 },
      }));
      return;
    }

    const content = buildFormSchema(lastUser);

    const promptTokens = Math.round(prompt.length / 4) + 100;
    const completionTokens = Math.round(content.length / 4) + 20;

    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
      id: 'mock-2',
      object: 'chat.completion',
      model: payload.model || 'mock-model',
      choices: [{ index: 0, message: { role: 'assistant', content }, finish_reason: 'stop' }],
      usage: {
        prompt_tokens: promptTokens,
        completion_tokens: completionTokens,
        total_tokens: promptTokens + completionTokens,
      },
    }));
  });
});

function field(key, label, type, extra = {}) {
  return {
    field_key: key,
    label,
    type,
    placeholder: extra.placeholder || '',
    help_text: extra.help_text || '',
    is_required: extra.is_required ?? false,
    is_visible: true,
    default_value: '',
    validation: extra.validation || {},
    options: extra.options || [],
  };
}

function internshipSchema({ requiredPhone = false, translated = false } = {}) {
  const t = (en, hi) => (translated ? hi : en);

  return {
    title: t('Internship Application', 'इंटर्नशिप आवेदन'),
    description: t('Apply for an internship at our company.', 'हमारी कंपनी में इंटर्नशिप के लिए आवेदन करें।'),
    settings: {
      theme: 'default',
      layout: 'vertical',
      show_progress: true,
      recaptcha_enabled: false,
      submit_button_text: t('Submit Application', 'आवेदन जमा करें'),
      success_message: t('Your application has been submitted!', 'आपका आवेदन जमा हो गया है!'),
      redirect_url: null,
    },
    fields: [
      field('full_name', t('Full Name', 'पूरा नाम'), 'text', {
        placeholder: t('e.g. Rahul Sharma', 'जैसे राहुल शर्मा'),
        is_required: true,
        validation: { min: 1, max: 255 },
      }),
      field('email', t('Email Address', 'ईमेल पता'), 'email', {
        placeholder: 'you@example.com',
        is_required: true,
        validation: { email: true },
      }),
      field('phone', t('Phone Number', 'फ़ोन नंबर'), 'phone', {
        placeholder: '+91 98765 43210',
        is_required: requiredPhone,
        validation: { regex: '/^[0-9+\\-\\s()]+$/' },
      }),
      field('education', t('Education History', 'शिक्षा इतिहास'), 'section', {}),
      field('degree', t('Highest Degree', 'उच्चतम डिग्री'), 'select', {
        is_required: true,
        options: [
          { label: t('High School', 'हाई स्कूल'), value: 'high_school' },
          { label: t('Bachelor', 'स्नातक'), value: 'bachelor' },
          { label: t('Master', 'स्नातकोत्तर'), value: 'master' },
          { label: t('PhD', 'पीएचडी'), value: 'phd' },
        ],
        validation: { in: 'high_school,bachelor,master,phd' },
      }),
      field('skills', t('Skills', 'कौशल'), 'checkbox', {
        is_required: true,
        options: [
          { label: 'PHP / Laravel', value: 'php_laravel' },
          { label: 'JavaScript', value: 'javascript' },
          { label: 'Python', value: 'python' },
          { label: t('Design', 'डिज़ाइन'), value: 'design' },
        ],
        validation: { array: true, in: 'php_laravel,javascript,python,design' },
      }),
      field('resume', t('Resume Upload', 'रिज़्यूमे अपलोड'), 'file', {
        help_text: t('PDF or DOCX, max 2MB', 'पीडीएफ या डीओसीएक्स, अधिकतम 2एमबी'),
        is_required: true,
        validation: { file: true, mimes: 'pdf,doc,docx', max: 2048 },
      }),
    ],
  };
}

function buildFormSchema(lastUser) {
  const prompt = lastUser.toLowerCase();

  if (/bogus types/.test(prompt)) {
    return JSON.stringify({
      title: 'Hallucination Test Form',
      description: '',
      settings: { theme: 'default', layout: 'vertical', show_progress: true, recaptcha_enabled: false, submit_button_text: 'Submit', success_message: 'Done!', redirect_url: null },
      fields: [
        field('surname', 'Surname', 'fullname', { placeholder: 'test' }),
        field('country', 'Country', 'dropdown', {
          options: [
            { label: 'India', value: 'india' },
            { label: 'USA', value: 'usa' },
          ],
        }),
        field('thoughts', 'Notes', 'multiline_text'),
        field('monster', 'Something', 'starship', { placeholder: 'unknown' }),
      ],
    });
  }

  let schema;
  if (/hindi/.test(prompt)) {
    schema = internshipSchema({ requiredPhone: false, translated: true });
  } else if (/emergency/.test(prompt)) {
    schema = internshipSchema({ requiredPhone: false });
    schema.fields.push(field('emergency', 'Emergency Contact', 'section', {}));
    schema.fields.push(field('emergency_name', 'Emergency Contact Name', 'text', { is_required: true, validation: { min: 1 } }));
    schema.fields.push(field('emergency_phone', 'Emergency Contact Phone', 'phone', { is_required: true, validation: { regex: '/^[0-9+\\-\\s()]+$/' } }));
  } else if (/phone required/.test(prompt)) {
    schema = internshipSchema({ requiredPhone: true });
  } else if (/feedback|survey/.test(prompt)) {
    schema = {
      title: 'Customer Feedback Survey',
      description: 'Tell us how we did.',
      settings: { theme: 'modern', layout: 'vertical', show_progress: false, recaptcha_enabled: false, submit_button_text: 'Send Feedback', success_message: 'Thank you!', redirect_url: null },
      fields: [
        field('rating', 'Overall Rating', 'rating', { is_required: true, validation: { min: 1, max: 5 } }),
        field('comments', 'Additional Comments', 'textarea', { placeholder: 'Anything else?', validation: { min: 1, max: 1000 } }),
        field('contact_email', 'Contact Email (optional)', 'email', { placeholder: 'you@example.com', validation: { email: true } }),
      ],
    };
  } else if (/registration|event/.test(prompt)) {
    schema = {
      title: 'Event Registration',
      description: 'Register for the upcoming event.',
      settings: { theme: 'default', layout: 'vertical', show_progress: true, recaptcha_enabled: false, submit_button_text: 'Register', success_message: 'You are registered!', redirect_url: null },
      fields: [
        field('full_name', 'Full Name', 'text', { is_required: true }),
        field('email', 'Email Address', 'email', { is_required: true, validation: { email: true } }),
        field('ticket_type', 'Ticket Type', 'radio', {
          is_required: true,
          options: [
            { label: 'General', value: 'general' },
            { label: 'VIP', value: 'vip' },
            { label: 'Student', value: 'student' },
          ],
          validation: { in: 'general,vip,student' },
        }),
        field('dietary', 'Dietary Preferences', 'checkbox', {
          options: [
            { label: 'Vegetarian', value: 'vegetarian' },
            { label: 'Vegan', value: 'vegan' },
            { label: 'Gluten Free', value: 'gluten_free' },
          ],
          validation: { array: true, in: 'vegetarian,vegan,gluten_free' },
        }),
      ],
    };
  } else {
    schema = internshipSchema({ requiredPhone: false });
  }

  return JSON.stringify(schema);
}

server.listen(PORT, () => {
  console.log(`Mock LLM server listening on http://localhost:${PORT}/chat/completions`);
});
