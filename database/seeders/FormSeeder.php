<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FieldOption;
use Illuminate\Database\Seeder;

class FormSeeder extends Seeder
{
    public function run()
    {
        if (Form::count() > 0) {
            return;
        }

        $user = User::where('email', 'admin@example.com')->first();

        if (!$user) {
            $user = User::first();
        }

        // 1. Contact Form
        $this->createContactForm($user);

        // 2. Registration Form (Multi-step)
        $this->createRegistrationForm($user);

        // 3. Survey Form
        $this->createSurveyForm($user);

        // 4. Job Application Form
        $this->createJobApplicationForm($user);

        // 5. Feedback Form
        $this->createFeedbackForm($user);
    }

    private function createContactForm($user)
    {
        $form = Form::create([
            'user_id' => $user->id,
            'title' => 'Contact Us',
            'slug' => 'contact-us',
            'description' => 'Please fill out this form to get in touch with us.',
            'is_published' => true,
            'published_at' => now(),
            'settings' => [
                'theme' => 'default',
                'layout' => 'vertical',
                'show_progress' => true,
                'recaptcha_enabled' => false,
                'submit_button_text' => 'Send Message',
                'success_message' => 'Thank you for your message! We\'ll get back to you soon.',
                'redirect_url' => null,
            ],
            'validation_rules' => [
                'full_name' => 'required|min:2|max:255',
                'email' => 'required|email',
                'subject' => 'required',
                'message' => 'required|min:10|max:2000',
            ],
        ]);

        $fields = [
            [
                'field_key' => 'full_name',
                'label' => 'Full Name',
                'type' => 'text',
                'placeholder' => 'Enter your full name',
                'help_text' => 'We\'ll use this to address you properly.',
                'is_required' => true,
                'validation' => ['min' => 2, 'max' => 255],
                'order' => 1,
            ],
            [
                'field_key' => 'email',
                'label' => 'Email Address',
                'type' => 'email',
                'placeholder' => 'Enter your email address',
                'help_text' => 'We\'ll send a confirmation to this email.',
                'is_required' => true,
                'validation' => ['email' => true],
                'order' => 2,
            ],
            [
                'field_key' => 'phone',
                'label' => 'Phone Number',
                'type' => 'phone',
                'placeholder' => 'Enter your phone number',
                'help_text' => 'Optional, but helpful if we need to reach you quickly.',
                'is_required' => false,
                'validation' => ['regex' => '/^[0-9+\-\s()]+$/'],
                'order' => 3,
            ],
            [
                'field_key' => 'subject',
                'label' => 'Subject',
                'type' => 'select',
                'placeholder' => 'Select a subject',
                'help_text' => 'Choose the topic of your inquiry.',
                'is_required' => true,
                'validation' => ['in' => 'general,support,sales,other'],
                'order' => 4,
            ],
            [
                'field_key' => 'message',
                'label' => 'Message',
                'type' => 'textarea',
                'placeholder' => 'Write your message here',
                'help_text' => 'Please provide as much detail as possible.',
                'is_required' => true,
                'validation' => ['min' => 10, 'max' => 2000],
                'order' => 5,
            ],
            [
                'field_key' => 'rating',
                'label' => 'How would you rate our service?',
                'type' => 'rating',
                'help_text' => 'Rate your experience from 1 (poor) to 5 (excellent).',
                'is_required' => false,
                'validation' => ['numeric' => true, 'min' => 1, 'max' => 5],
                'order' => 6,
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = FormField::create(array_merge($fieldData, ['form_id' => $form->id]));
            
            if ($field->type === 'select') {
                $options = [
                    ['label' => 'General Inquiry', 'value' => 'general', 'order' => 1],
                    ['label' => 'Technical Support', 'value' => 'support', 'order' => 2],
                    ['label' => 'Sales Question', 'value' => 'sales', 'order' => 3],
                    ['label' => 'Other', 'value' => 'other', 'order' => 4],
                ];
                foreach ($options as $optionData) {
                    FieldOption::create(array_merge($optionData, ['form_field_id' => $field->id]));
                }
            }
        }
    }

    private function createRegistrationForm($user)
    {
        $form = Form::create([
            'user_id' => $user->id,
            'title' => 'User Registration',
            'slug' => 'user-registration',
            'description' => 'Register for an account to get started with our platform.',
            'is_published' => true,
            'published_at' => now(),
            'is_multi_step' => true,
            'settings' => [
                'theme' => 'modern',
                'layout' => 'vertical',
                'show_progress' => true,
                'recaptcha_enabled' => true,
                'submit_button_text' => 'Complete Registration',
                'success_message' => 'Registration successful! Please check your email to verify your account.',
                'redirect_url' => '/login',
            ],
            'validation_rules' => [
                'first_name' => 'required|min:2|max:50',
                'last_name' => 'required|min:2|max:50',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])/',
                'terms' => 'required|accepted',
            ],
        ]);

        $fields = [
            // Step 1: Personal Information
            [
                'field_key' => 'section_personal',
                'label' => 'Personal Information',
                'type' => 'section',
                'step' => 1,
                'order' => 1,
            ],
            [
                'field_key' => 'first_name',
                'label' => 'First Name',
                'type' => 'text',
                'placeholder' => 'Enter your first name',
                'help_text' => 'As it appears on your ID.',
                'is_required' => true,
                'validation' => ['min' => 2, 'max' => 50],
                'step' => 1,
                'order' => 2,
            ],
            [
                'field_key' => 'last_name',
                'label' => 'Last Name',
                'type' => 'text',
                'placeholder' => 'Enter your last name',
                'help_text' => 'Your family name or surname.',
                'is_required' => true,
                'validation' => ['min' => 2, 'max' => 50],
                'step' => 1,
                'order' => 3,
            ],
            [
                'field_key' => 'dob',
                'label' => 'Date of Birth',
                'type' => 'date',
                'help_text' => 'You must be at least 18 years old.',
                'is_required' => true,
                'validation' => ['date' => true],
                'step' => 1,
                'order' => 4,
            ],
            [
                'field_key' => 'country',
                'label' => 'Country of Residence',
                'type' => 'select',
                'placeholder' => 'Select your country',
                'is_required' => true,
                'validation' => ['in' => 'us,uk,ca,au,in,other'],
                'step' => 1,
                'order' => 5,
            ],

            // Step 2: Account Information
            [
                'field_key' => 'section_account',
                'label' => 'Account Information',
                'type' => 'section',
                'step' => 2,
                'order' => 6,
            ],
            [
                'field_key' => 'email',
                'label' => 'Email Address',
                'type' => 'email',
                'placeholder' => 'Enter your email address',
                'help_text' => 'We\'ll send verification and notifications to this email.',
                'is_required' => true,
                'validation' => ['email' => true, 'unique' => 'users,email'],
                'step' => 2,
                'order' => 7,
            ],
            [
                'field_key' => 'password',
                'label' => 'Password',
                'type' => 'password',
                'placeholder' => 'Create a strong password',
                'help_text' => 'Must contain at least 8 characters, one uppercase, one lowercase, one number, and one special character.',
                'is_required' => true,
                'validation' => ['min' => 8, 'regex' => '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])/'],
                'step' => 2,
                'order' => 8,
            ],
            [
                'field_key' => 'terms',
                'label' => 'I agree to the Terms and Conditions',
                'type' => 'checkbox',
                'help_text' => 'Please read our terms and conditions before agreeing.',
                'is_required' => true,
                'validation' => ['accepted' => true],
                'step' => 2,
                'order' => 9,
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = FormField::create(array_merge($fieldData, ['form_id' => $form->id]));
            
            if ($field->type === 'select') {
                $options = [
                    ['label' => 'United States', 'value' => 'us', 'order' => 1],
                    ['label' => 'United Kingdom', 'value' => 'uk', 'order' => 2],
                    ['label' => 'Canada', 'value' => 'ca', 'order' => 3],
                    ['label' => 'Australia', 'value' => 'au', 'order' => 4],
                    ['label' => 'India', 'value' => 'in', 'order' => 5],
                    ['label' => 'Other', 'value' => 'other', 'order' => 6],
                ];
                foreach ($options as $optionData) {
                    FieldOption::create(array_merge($optionData, ['form_field_id' => $field->id]));
                }
            }
            
            if ($field->type === 'checkbox') {
                FieldOption::create([
                    'form_field_id' => $field->id,
                    'label' => 'I agree to the Terms and Conditions',
                    'value' => 'agree',
                    'order' => 1,
                ]);
            }
        }
    }

    private function createSurveyForm($user)
    {
        $form = Form::create([
            'user_id' => $user->id,
            'title' => 'Customer Satisfaction Survey',
            'slug' => 'customer-survey',
            'description' => 'Help us improve our products and services by providing your feedback.',
            'is_published' => true,
            'published_at' => now(),
            'settings' => [
                'theme' => 'minimal',
                'layout' => 'vertical',
                'show_progress' => true,
                'recaptcha_enabled' => false,
                'submit_button_text' => 'Submit Survey',
                'success_message' => 'Thank you for your valuable feedback!',
                'redirect_url' => null,
            ],
            'validation_rules' => [
                'satisfaction' => 'required|numeric|min:1|max:5',
                'feedback' => 'required|min:10|max:1000',
                'recommend' => 'required',
                'improvements' => 'max:500',
            ],
        ]);

        $fields = [
            [
                'field_key' => 'product_used',
                'label' => 'Which product did you use?',
                'type' => 'select',
                'placeholder' => 'Select a product',
                'is_required' => true,
                'validation' => ['in' => 'product-a,product-b,product-c,product-d'],
                'order' => 1,
            ],
            [
                'field_key' => 'satisfaction',
                'label' => 'How satisfied are you with our product?',
                'type' => 'rating',
                'help_text' => 'Rate your satisfaction from 1 (very unsatisfied) to 5 (very satisfied).',
                'is_required' => true,
                'validation' => ['numeric' => true, 'min' => 1, 'max' => 5],
                'order' => 2,
            ],
            [
                'field_key' => 'feedback',
                'label' => 'What do you like most about our product?',
                'type' => 'textarea',
                'placeholder' => 'Tell us what you like...',
                'is_required' => true,
                'validation' => ['min' => 10, 'max' => 1000],
                'order' => 3,
            ],
            [
                'field_key' => 'improvements',
                'label' => 'What improvements would you suggest?',
                'type' => 'textarea',
                'placeholder' => 'Suggest improvements...',
                'is_required' => false,
                'validation' => ['max' => 500],
                'order' => 4,
            ],
            [
                'field_key' => 'recommend',
                'label' => 'Would you recommend our product to others?',
                'type' => 'radio',
                'is_required' => true,
                'order' => 5,
            ],
            [
                'field_key' => 'newsletter',
                'label' => 'Subscribe to our newsletter for updates?',
                'type' => 'checkbox',
                'is_required' => false,
                'order' => 6,
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = FormField::create(array_merge($fieldData, ['form_id' => $form->id]));
            
            if ($field->type === 'select') {
                $options = [
                    ['label' => 'Product A', 'value' => 'product-a', 'order' => 1],
                    ['label' => 'Product B', 'value' => 'product-b', 'order' => 2],
                    ['label' => 'Product C', 'value' => 'product-c', 'order' => 3],
                    ['label' => 'Product D', 'value' => 'product-d', 'order' => 4],
                ];
                foreach ($options as $optionData) {
                    FieldOption::create(array_merge($optionData, ['form_field_id' => $field->id]));
                }
            }
            
            if ($field->type === 'radio') {
                $options = [
                    ['label' => 'Yes, definitely', 'value' => 'yes', 'order' => 1],
                    ['label' => 'Probably yes', 'value' => 'probably', 'order' => 2],
                    ['label' => 'Not sure', 'value' => 'not-sure', 'order' => 3],
                    ['label' => 'Probably no', 'value' => 'probably-no', 'order' => 4],
                    ['label' => 'No', 'value' => 'no', 'order' => 5],
                ];
                foreach ($options as $optionData) {
                    FieldOption::create(array_merge($optionData, ['form_field_id' => $field->id]));
                }
            }
            
            if ($field->type === 'checkbox') {
                FieldOption::create([
                    'form_field_id' => $field->id,
                    'label' => 'Subscribe to newsletter',
                    'value' => 'subscribe',
                    'order' => 1,
                ]);
            }
        }
    }

    private function createJobApplicationForm($user)
    {
        $form = Form::create([
            'user_id' => $user->id,
            'title' => 'Job Application',
            'slug' => 'job-application',
            'description' => 'Apply for a position at our company.',
            'is_published' => true,
            'published_at' => now(),
            'settings' => [
                'theme' => 'default',
                'layout' => 'vertical',
                'show_progress' => true,
                'recaptcha_enabled' => true,
                'submit_button_text' => 'Submit Application',
                'success_message' => 'Your application has been submitted successfully! We\'ll review it and get back to you.',
                'redirect_url' => null,
            ],
            'validation_rules' => [
                'full_name' => 'required|min:2|max:255',
                'email' => 'required|email',
                'position' => 'required',
                'experience' => 'required|numeric|min:0|max:50',
                'resume' => 'required|file|mimes:pdf,doc,docx|max:2048',
                'cover_letter' => 'required|min:50|max:2000',
                'portfolio_url' => 'nullable|url',
                'available_start' => 'required|date',
            ],
        ]);

        $fields = [
            [
                'field_key' => 'full_name',
                'label' => 'Full Name',
                'type' => 'text',
                'placeholder' => 'Enter your full name',
                'is_required' => true,
                'validation' => ['min' => 2, 'max' => 255],
                'order' => 1,
            ],
            [
                'field_key' => 'email',
                'label' => 'Email Address',
                'type' => 'email',
                'placeholder' => 'Enter your email address',
                'is_required' => true,
                'validation' => ['email' => true],
                'order' => 2,
            ],
            [
                'field_key' => 'phone',
                'label' => 'Phone Number',
                'type' => 'phone',
                'placeholder' => 'Enter your phone number',
                'is_required' => true,
                'order' => 3,
            ],
            [
                'field_key' => 'position',
                'label' => 'Position Applied For',
                'type' => 'select',
                'placeholder' => 'Select a position',
                'is_required' => true,
                'validation' => ['in' => 'developer,designer,manager,marketing,sales'],
                'order' => 4,
            ],
            [
                'field_key' => 'experience',
                'label' => 'Years of Experience',
                'type' => 'number',
                'placeholder' => 'Enter years of experience',
                'is_required' => true,
                'validation' => ['numeric' => true, 'min' => 0, 'max' => 50],
                'order' => 5,
            ],
            [
                'field_key' => 'resume',
                'label' => 'Upload Resume',
                'type' => 'file',
                'help_text' => 'PDF, DOC, or DOCX (max 2MB)',
                'is_required' => true,
                'validation' => ['file' => true, 'mimes' => 'pdf,doc,docx', 'max' => 2048],
                'order' => 6,
            ],
            [
                'field_key' => 'cover_letter',
                'label' => 'Cover Letter',
                'type' => 'textarea',
                'placeholder' => 'Tell us why you\'re the best candidate...',
                'is_required' => true,
                'validation' => ['min' => 50, 'max' => 2000],
                'order' => 7,
            ],
            [
                'field_key' => 'portfolio_url',
                'label' => 'Portfolio URL',
                'type' => 'url',
                'placeholder' => 'https://your-portfolio.com',
                'help_text' => 'Optional - share your portfolio or GitHub profile.',
                'is_required' => false,
                'validation' => ['url' => true],
                'order' => 8,
            ],
            [
                'field_key' => 'available_start',
                'label' => 'Available Start Date',
                'type' => 'date',
                'is_required' => true,
                'validation' => ['date' => true],
                'order' => 9,
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = FormField::create(array_merge($fieldData, ['form_id' => $form->id]));
            
            if ($field->type === 'select') {
                $options = [
                    ['label' => 'Software Developer', 'value' => 'developer', 'order' => 1],
                    ['label' => 'UI/UX Designer', 'value' => 'designer', 'order' => 2],
                    ['label' => 'Project Manager', 'value' => 'manager', 'order' => 3],
                    ['label' => 'Marketing Specialist', 'value' => 'marketing', 'order' => 4],
                    ['label' => 'Sales Representative', 'value' => 'sales', 'order' => 5],
                ];
                foreach ($options as $optionData) {
                    FieldOption::create(array_merge($optionData, ['form_field_id' => $field->id]));
                }
            }
        }
    }

    private function createFeedbackForm($user)
    {
        $form = Form::create([
            'user_id' => $user->id,
            'title' => 'Quick Feedback',
            'slug' => 'quick-feedback',
            'description' => 'Help us improve with just a few clicks.',
            'is_published' => true,
            'published_at' => now(),
            'settings' => [
                'theme' => 'default',
                'layout' => 'inline',
                'show_progress' => false,
                'recaptcha_enabled' => false,
                'submit_button_text' => 'Send Feedback',
                'success_message' => 'Thank you for your feedback!',
                'redirect_url' => null,
            ],
            'validation_rules' => [
                'feedback_type' => 'required',
                'message' => 'required|min:5|max:500',
            ],
        ]);

        $fields = [
            [
                'field_key' => 'heading_feedback',
                'label' => 'Share Your Feedback',
                'type' => 'heading',
                'settings' => ['level' => 3],
                'order' => 1,
            ],
            [
                'field_key' => 'paragraph_intro',
                'label' => 'We value your opinion. Please take a moment to share your thoughts.',
                'type' => 'paragraph',
                'order' => 2,
            ],
            [
                'field_key' => 'divider_1',
                'label' => 'Divider',
                'type' => 'divider',
                'order' => 3,
            ],
            [
                'field_key' => 'feedback_type',
                'label' => 'Type of Feedback',
                'type' => 'radio',
                'is_required' => true,
                'order' => 4,
            ],
            [
                'field_key' => 'message',
                'label' => 'Your Feedback',
                'type' => 'textarea',
                'placeholder' => 'Tell us what you think...',
                'is_required' => true,
                'validation' => ['min' => 5, 'max' => 500],
                'order' => 5,
            ],
            [
                'field_key' => 'rating_section',
                'label' => 'Rate Your Experience',
                'type' => 'section',
                'order' => 6,
            ],
            [
                'field_key' => 'overall_rating',
                'label' => 'Overall Experience',
                'type' => 'rating',
                'is_required' => true,
                'validation' => ['numeric' => true, 'min' => 1, 'max' => 5],
                'order' => 7,
            ],
        ];

        foreach ($fields as $fieldData) {
            $field = FormField::create(array_merge($fieldData, ['form_id' => $form->id]));
            
            if ($field->type === 'radio') {
                $options = [
                    ['label' => 'Suggestion', 'value' => 'suggestion', 'order' => 1],
                    ['label' => 'Compliment', 'value' => 'compliment', 'order' => 2],
                    ['label' => 'Complaint', 'value' => 'complaint', 'order' => 3],
                    ['label' => 'Bug Report', 'value' => 'bug', 'order' => 4],
                    ['label' => 'Other', 'value' => 'other', 'order' => 5],
                ];
                foreach ($options as $optionData) {
                    FieldOption::create(array_merge($optionData, ['form_field_id' => $field->id]));
                }
            }
        }
    }
}