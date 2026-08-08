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
 *   - "refine"     -> hybrid import refinement (type/required/validation only)
 *   - "audit"      -> form audit report + corrected schema
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

    let content;
    if (/audit/.test(prompt)) {
      content = buildAudit(payload);
    } else if (/refine|parsed fields/.test(prompt)) {
      content = buildRefinement(payload);
    } else {
      content = buildFormSchema(prompt);
    }

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

function buildFormSchema(prompt) {
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

function extractJsonObject(text) {
  const start = text.indexOf('{');
  const end = text.lastIndexOf('}');
  if (start === -1 || end === -1 || end <= start) return null;
  try {
    return JSON.parse(text.slice(start, end + 1));
  } catch {
    return null;
  }
}

// Hybrid import refinement: reply with type/required/validation for each
// field_key present in the request, guessed from its label and options.
function buildRefinement(payload) {
  const content = (payload.messages.map((m) => m.content || '').join('\n'));
  const outer = extractJsonObject(content);
  const list = outer && Array.isArray(outer.fields) ? outer.fields : [];

  const fields = list.map((f) => {
    const label = (f.label || '').toLowerCase();
    const options = Array.isArray(f.options) ? f.options : [];
    let type = 'text';
    let isRequired = false;
    let validation = { min: 1, max: 255 };

    if (/email/.test(label)) { type = 'email'; isRequired = true; validation = { email: true }; }
    else if (/phone|mobile|contact number|telephone/.test(label)) { type = 'phone'; validation = { regex: '/^[0-9+\\-\\s()]+$/' }; }
    else if (/date|birthday|dob|when/.test(label)) { type = 'date'; validation = { date: true }; }
    else if (/upload|resume|cv|file|photo|image|document/.test(label)) { type = 'file'; validation = { file: true, max: 2048 }; }
    else if (/rating|satisfaction|how satisfied/.test(label)) { type = 'rating'; validation = { numeric: true, min: 1, max: 5 }; }
    else if (/comment|feedback|message|describe|details|notes|opinion|tell us/.test(label)) { type = 'textarea'; validation = { min: 1, max: 1000 }; }
    else if (/how many|age|number of|years of/.test(label)) { type = 'number'; validation = { numeric: true, min: 0 }; }
    else if (/password/.test(label)) { type = 'password'; isRequired = true; validation = { min: 8 }; }
    else if (options.length > 0) {
      const isCheckbox = /which of|select all|check|agree/.test(label);
      type = options.length > 6 && !isCheckbox ? 'select' : (isCheckbox ? 'checkbox' : 'radio');
      isRequired = true;
      const values = options.map((o) => String(o.label ?? o).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, ''));
      validation = { in: values.join(',') };
    }

    return { field_key: f.field_key, type, is_required: isRequired, validation };
  });

  return JSON.stringify({ fields });
}

// Form audit: echo the submitted schema back (for "apply fixes") plus a report.
function buildAudit(payload) {
  const content = (payload.messages.map((m) => m.content || '').join('\n'));
  const outer = extractJsonObject(content);
  const schema = outer && outer.title && Array.isArray(outer.fields) ? outer : null;

  const fieldCount = schema ? schema.fields.length : 0;
  const issues = [];
  if (schema) {
    for (const f of schema.fields) {
      const label = (f.label || '').toLowerCase();
      if (f.type === 'email' && !f.is_required) {
        issues.push({ severity: 'high', title: `"${f.label}" is not required`, detail: 'Contact fields almost always need to be required. Mark this field required.' });
      }
      if (f.type !== 'section' && f.type !== 'heading' && (!f.placeholder || f.placeholder === '')) {
        issues.push({ severity: 'medium', title: `"${f.label}" has no placeholder`, detail: 'Adding an example placeholder reduces confusion and improves fill rates.' });
      }
    }
    if (issues.length === 0) {
      issues.push({ severity: 'low', title: 'Consider a redirect URL', detail: 'Set a redirect_url to send respondents to a thank-you page after submitting.' });
    }
  }

  return JSON.stringify({
    audit: {
      score: schema ? Math.max(55, 100 - issues.length * 8) : 0,
      summary: 'Reviewed the form for completeness, validation and UX. Apply the fixes below to improve it.',
      issues: issues.slice(0, 6),
    },
    schema: schema || { title: 'Untitled', description: '', settings: {}, fields: [] },
  });
}

server.listen(PORT, () => {
  console.log(`Mock LLM server listening on http://localhost:${PORT}/chat/completions`);
});
