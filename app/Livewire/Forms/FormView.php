<?php

namespace App\Livewire\Forms;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\WithFileUploads;

class FormView extends Component
{
    use WithFileUploads;

    public Form $form;

    public $submissionData = [];

    public $currentStep = 0;

    public $totalSteps = 1;

    public $submitted = false;

    public $submissionId = null;

    public $validationErrors = [];

    protected $rules = [];

    public function mount(Form $form)
    {
        $this->form = $form;

        // Allow owners and users with edit permission to preview drafts.
        $canView = $form->is_published
            || (auth()->check()
                && ($form->user_id === auth()->id() || auth()->user()->can('edit forms')));

        if (! $canView) {
            abort(404, 'This form is not published.');
        }

        if ($form->expires_at && $form->expires_at->isPast()) {
            abort(404, 'This form has expired.');
        }

        $this->totalSteps = $this->calculateSteps();
        $this->initializeSubmissionData();
    }

    public function initializeSubmissionData()
    {
        foreach ($this->form->fields as $field) {
            if (! in_array($field->type, ['section', 'divider', 'heading', 'paragraph'])) {
                $this->submissionData[$field->field_key] = $field->default_value ?? '';
            }
        }
    }

    public function calculateSteps()
    {
        if (! $this->form->is_multi_step) {
            return 1;
        }

        $maxStep = $this->form->fields->max('step') ?? 1;

        return max(1, $maxStep);
    }

    public function getCurrentFields()
    {
        if (! $this->form->is_multi_step) {
            return $this->form->fields->whereNotIn('type', ['section', 'divider', 'heading', 'paragraph']);
        }

        return $this->form->fields->where('step', $this->currentStep + 1);
    }

    public function getStepFields()
    {
        if (! $this->form->is_multi_step) {
            return $this->form->fields->whereNotIn('type', ['section', 'divider', 'heading', 'paragraph']);
        }

        return $this->form->fields->where('step', $this->currentStep + 1)
            ->whereNotIn('type', ['section', 'divider', 'heading', 'paragraph']);
    }

    public function getStepSections()
    {
        if (! $this->form->is_multi_step) {
            return $this->form->fields->where('type', 'section');
        }

        return $this->form->fields->where('step', $this->currentStep + 1)
            ->where('type', 'section');
    }

    public function nextStep()
    {
        if ($this->validateStep()) {
            if ($this->currentStep < $this->totalSteps - 1) {
                $this->currentStep++;
                $this->validationErrors = [];
            }
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 0) {
            $this->currentStep--;
            $this->validationErrors = [];
        }
    }

    public function validateStep()
    {
        $currentFields = $this->getStepFields();
        $rules = [];
        $messages = [];

        foreach ($currentFields as $field) {
            $fieldRules = [];

            if ($field->is_required) {
                $fieldRules[] = 'required';
                $messages[$field->field_key.'.required'] = "The {$field->label} field is required.";
            }

            if ($field->type === 'email' && ! $field->is_required) {
                $fieldRules[] = 'email';
                $messages[$field->field_key.'.email'] = 'Please enter a valid email address.';
            }

            if ($field->type === 'file') {
                $fieldRules[] = 'file';
                $fieldRules[] = 'max:2048';
                if (! empty($field->validation['mimes'] ?? '')) {
                    $fieldRules[] = 'mimes:'.$field->validation['mimes'];
                }
            }

            if (! empty($field->validation)) {
                foreach ($field->validation as $rule => $value) {
                    if ($value !== null && $value !== '') {
                        if (in_array($rule, ['min', 'max', 'minlength', 'maxlength'])) {
                            $fieldRules[] = $rule.':'.$value;
                        } elseif ($rule === 'email') {
                            $fieldRules[] = 'email';
                        } elseif ($rule === 'numeric') {
                            $fieldRules[] = 'numeric';
                        } elseif ($rule === 'url') {
                            $fieldRules[] = 'url';
                        } elseif ($rule === 'regex') {
                            $fieldRules[] = 'regex:'.$value;
                        }
                    }
                }
            }

            if (! empty($fieldRules)) {
                $rules[$field->field_key] = $fieldRules;
            }
        }

        if (empty($rules)) {
            return true;
        }

        $validator = Validator::make($this->submissionData, $rules, $messages);

        if ($validator->fails()) {
            $this->validationErrors = $validator->errors()->toArray();
            $this->dispatch('validationFailed', errors: $this->validationErrors);

            return false;
        }

        $this->validationErrors = [];

        return true;
    }

    public function submit()
    {
        // Validate all steps
        if (! $this->validateStep()) {
            return;
        }

        // Additional server-side validation
        $validationRules = $this->form->getValidationRulesArray();
        $validator = Validator::make($this->submissionData, $validationRules);

        if ($validator->fails()) {
            $this->validationErrors = $validator->errors()->toArray();
            $this->dispatch('validationFailed', errors: $this->validationErrors);

            return;
        }

        // Handle file uploads
        foreach ($this->submissionData as $key => $value) {
            if ($value instanceof UploadedFile) {
                $path = $value->store('form_uploads', 'public');
                $this->submissionData[$key] = $path;
            }
        }

        // Save submission
        $submission = FormSubmission::create([
            'form_id' => $this->form->id,
            'data' => $this->submissionData,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta_data' => [
                'referrer' => request()->header('referer'),
                'user_agent' => request()->userAgent(),
                'session_id' => session()->getId(),
            ],
            'submitted_at' => now(),
        ]);

        $this->form->increment('submission_count');

        $this->submitted = true;
        $this->submissionId = $submission->id;

        $this->dispatch('formSubmitted', submission: $submission);
    }

    public function render()
    {
        $stepFields = $this->getStepFields();
        $stepSections = $this->getStepSections();

        return view('livewire.forms.form-view', [
            'stepFields' => $stepFields,
            'stepSections' => $stepSections,
        ])->layout('layouts.public');
    }
}
