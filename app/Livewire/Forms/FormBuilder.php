<?php

namespace App\Livewire\Forms;

use App\Jobs\GenerateFormSchemaJob;
use App\Models\AiFormGenerationJob;
use App\Models\FieldOption;
use App\Models\Form;
use App\Models\FormField;
use App\Services\FormSchemaService;
use App\Services\FormVersioningService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class FormBuilder extends Component
{
    public $form = [];

    public $fields = [];

    public $editingField = null;

    public $showFieldModal = false;

    public $showSchemaModal = false;

    public $schemaEditor = '';

    public $fieldTypes = [];

    public $formSettings = [];

    public $sections = [];

    public $showSectionModal = false;

    public $editingSection = null;

    public $sectionName = '';

    public $selectedFieldType = null;

    public $fieldTypeConfigs = [];

    public $statusMessage = null;

    public $aiGenerationJobId = null;

    protected $rules = [
        'form.title' => 'required|min:3|max:255',
        'form.description' => 'nullable|max:1000',
        'form.settings.*' => 'nullable',
        'fields.*.label' => 'required|min:1',
        'fields.*.type' => 'required|string',
        'fields.*.is_required' => 'boolean',
        'fields.*.field_key' => 'required|alpha_dash',
    ];

    protected $validationAttributes = [
        'form.title' => 'form title',
        'fields.*.label' => 'field label',
        'fields.*.field_key' => 'field key',
    ];

    public function mount(?Form $form = null)
    {
        $this->form = [
            'id' => $form->id ?? null,
            'user_id' => $form->user_id ?? auth()->id(),
            'slug' => $form->slug ?? null,
            'fill_url' => $form->fill_url ?? null,
            'title' => $form->title ?? '',
            'description' => $form->description ?? '',
            'settings' => $form->settings ?? [
                'theme' => 'default',
                'layout' => 'vertical',
                'show_progress' => true,
                'recaptcha_enabled' => false,
                'submit_button_text' => 'Submit',
                'success_message' => 'Form submitted successfully!',
                'redirect_url' => null,
                'email_notifications_enabled' => false,
                'notification_email' => '',
            ],
            'is_published' => $form->is_published ?? false,
            'is_multi_step' => $form->is_multi_step ?? false,
            'expires_at' => $form?->expires_at?->format('Y-m-d\TH:i') ?: null,
        ];

        $this->fieldTypes = $this->getFieldTypes();
        $this->fieldTypeConfigs = $this->getFieldTypeConfigs();
        $this->loadFields();
        $this->loadSections();
        $this->schemaEditor = $form ? json_encode($form->schema, JSON_PRETTY_PRINT) : '{}';
    }

    public function loadFields()
    {
        if (! empty($this->form['id'])) {
            $this->fields = Form::find($this->form['id'])->fields()
                ->with('options')
                ->orderBy('order')
                ->get()
                ->toArray();
        }
    }

    public function loadSections()
    {
        if (! empty($this->form['id']) && ! empty($this->fields)) {
            $this->sections = collect($this->fields)
                ->filter(fn ($field) => $field['type'] === 'section')
                ->pluck('label')
                ->toArray();
        }
    }

    public function getFieldTypes()
    {
        return [
            ['value' => 'text', 'label' => 'Text Input', 'icon' => 'fa-font', 'category' => 'basic'],
            ['value' => 'email', 'label' => 'Email Input', 'icon' => 'fa-envelope', 'category' => 'basic'],
            ['value' => 'number', 'label' => 'Number Input', 'icon' => 'fa-hashtag', 'category' => 'basic'],
            ['value' => 'phone', 'label' => 'Phone Input', 'icon' => 'fa-phone', 'category' => 'basic'],
            ['value' => 'textarea', 'label' => 'Text Area', 'icon' => 'fa-align-left', 'category' => 'basic'],
            ['value' => 'date', 'label' => 'Date Picker', 'icon' => 'fa-calendar', 'category' => 'basic'],
            ['value' => 'time', 'label' => 'Time Picker', 'icon' => 'fa-clock', 'category' => 'basic'],
            ['value' => 'datetime', 'label' => 'DateTime Picker', 'icon' => 'fa-calendar-alt', 'category' => 'basic'],
            ['value' => 'select', 'label' => 'Dropdown', 'icon' => 'fa-chevron-down', 'category' => 'choice'],
            ['value' => 'radio', 'label' => 'Radio Buttons', 'icon' => 'fa-circle', 'category' => 'choice'],
            ['value' => 'checkbox', 'label' => 'Checkboxes', 'icon' => 'fa-check-square', 'category' => 'choice'],
            ['value' => 'file', 'label' => 'File Upload', 'icon' => 'fa-upload', 'category' => 'advanced'],
            ['value' => 'rating', 'label' => 'Rating', 'icon' => 'fa-star', 'category' => 'advanced'],
            ['value' => 'section', 'label' => 'Section Heading', 'icon' => 'fa-heading', 'category' => 'layout'],
            ['value' => 'divider', 'label' => 'Divider', 'icon' => 'fa-minus', 'category' => 'layout'],
            ['value' => 'heading', 'label' => 'Heading', 'icon' => 'fa-heading', 'category' => 'layout'],
            ['value' => 'paragraph', 'label' => 'Paragraph Text', 'icon' => 'fa-paragraph', 'category' => 'layout'],
            ['value' => 'url', 'label' => 'URL Input', 'icon' => 'fa-link', 'category' => 'advanced'],
            ['value' => 'password', 'label' => 'Password Input', 'icon' => 'fa-lock', 'category' => 'advanced'],
            ['value' => 'color', 'label' => 'Color Picker', 'icon' => 'fa-palette', 'category' => 'advanced'],
            ['value' => 'range', 'label' => 'Range Slider', 'icon' => 'fa-sliders-h', 'category' => 'advanced'],
            ['value' => 'hidden', 'label' => 'Hidden Field', 'icon' => 'fa-eye-slash', 'category' => 'advanced'],
        ];
    }

    public function getFieldTypeConfigs()
    {
        return [
            'text' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['min' => 1, 'max' => 255]],
            'email' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['email' => true]],
            'number' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['numeric' => true, 'min' => 0]],
            'phone' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['regex' => '/^[0-9+\-\s()]+$/']],
            'textarea' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['min' => 1, 'max' => 1000]],
            'date' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['date' => true]],
            'time' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['date_format' => 'H:i']],
            'datetime' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['date' => true]],
            'select' => ['has_options' => true, 'has_validation' => true, 'default_validation' => ['in' => '']],
            'radio' => ['has_options' => true, 'has_validation' => true, 'default_validation' => ['in' => '']],
            'checkbox' => ['has_options' => true, 'has_validation' => true, 'default_validation' => ['array' => true]],
            'file' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['file' => true, 'max' => 2048]],
            'rating' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['numeric' => true, 'min' => 1, 'max' => 5]],
            'section' => ['has_options' => false, 'has_validation' => false],
            'divider' => ['has_options' => false, 'has_validation' => false],
            'heading' => ['has_options' => false, 'has_validation' => false],
            'paragraph' => ['has_options' => false, 'has_validation' => false],
            'url' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['url' => true]],
            'password' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['min' => 8]],
            'color' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['regex' => '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/']],
            'range' => ['has_options' => false, 'has_validation' => true, 'default_validation' => ['numeric' => true, 'min' => 0, 'max' => 100]],
            'hidden' => ['has_options' => false, 'has_validation' => false],
        ];
    }

    public function addField($type)
    {
        $fieldKey = 'field_'.Str::random(8);
        $config = $this->fieldTypeConfigs[$type] ?? [];
        $defaultValidation = $config['default_validation'] ?? [];

        $fieldData = [
            'field_key' => $fieldKey,
            'label' => $this->getDefaultLabel($type),
            'type' => $type,
            'order' => count($this->fields),
            'is_required' => false,
            'is_visible' => true,
            'placeholder' => '',
            'help_text' => '',
            'default_value' => '',
            'validation' => $defaultValidation,
            'settings' => [],
            'options' => [],
        ];

        $this->fields[] = $fieldData;
        $this->dispatch('fieldAdded', field: $fieldData);
    }

    public function getDefaultLabel($type)
    {
        $labels = [
            'text' => 'Text Input',
            'email' => 'Email Address',
            'number' => 'Number',
            'phone' => 'Phone Number',
            'textarea' => 'Long Text',
            'date' => 'Date',
            'time' => 'Time',
            'datetime' => 'Date & Time',
            'select' => 'Select Option',
            'radio' => 'Choose Option',
            'checkbox' => 'Checkbox',
            'file' => 'File Upload',
            'rating' => 'Rating',
            'section' => 'Section Heading',
            'divider' => 'Divider',
            'heading' => 'Heading',
            'paragraph' => 'Paragraph',
            'url' => 'URL',
            'password' => 'Password',
            'color' => 'Color',
            'range' => 'Range',
            'hidden' => 'Hidden Field',
        ];

        return $labels[$type] ?? ucfirst($type);
    }

    public function duplicateField($index)
    {
        $field = $this->fields[$index];
        $newField = $field;
        $newField['field_key'] = 'field_'.Str::random(8);
        $newField['label'] = $field['label'].' (Copy)';
        $newField['order'] = count($this->fields);
        $newField['id'] = null;

        if (isset($newField['options'])) {
            $newField['options'] = collect($newField['options'])->map(function ($option) {
                $option['id'] = null;

                return $option;
            })->toArray();
        }

        $this->fields[] = $newField;
        $this->dispatch('fieldDuplicated');
    }

    public function editField($index)
    {
        $this->editingField = $index;
        $this->showFieldModal = true;
        $this->dispatch('openModal');
    }

    public function updateField()
    {
        $index = $this->editingField;
        $field = $this->fields[$index];

        // Validate field key uniqueness
        $existingKeys = collect($this->fields)
            ->where('id', '!=', $field['id'] ?? null)
            ->pluck('field_key')
            ->toArray();

        if (in_array($field['field_key'], $existingKeys)) {
            $this->addError('fields.'.$index.'.field_key', 'This field key is already used.');

            return;
        }

        $this->showFieldModal = false;
        $this->editingField = null;
        $this->dispatch('fieldUpdated');
    }

    public function addOption($index)
    {
        if (! isset($this->fields[$index]['options'])) {
            $this->fields[$index]['options'] = [];
        }

        $this->fields[$index]['options'][] = [
            'label' => '',
            'value' => 'option_'.Str::random(5),
            'order' => count($this->fields[$index]['options']),
            'is_default' => false,
        ];
    }

    public function removeOption($index, $optionIndex)
    {
        if (isset($this->fields[$index]['options'][$optionIndex])) {
            unset($this->fields[$index]['options'][$optionIndex]);
            $this->fields[$index]['options'] = array_values($this->fields[$index]['options']);
        }
    }

    public function removeField($index)
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);

        // Reorder remaining fields
        foreach ($this->fields as $idx => $field) {
            $this->fields[$idx]['order'] = $idx;
        }

        $this->dispatch('fieldRemoved');
    }

    public function reorderFields($orderedIds)
    {
        $fields = collect($this->fields);

        foreach ($orderedIds as $index => $id) {
            $fieldIndex = $fields->search(function ($field) use ($id) {
                return ($field['id'] ?? null) == $id;
            });

            if ($fieldIndex !== false) {
                $this->fields[$fieldIndex]['order'] = $index;
            }
        }

        // Sort fields by order
        usort($this->fields, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        $this->dispatch('fieldsReordered');
    }

    public function addSection()
    {
        $this->showSectionModal = true;
        $this->editingSection = null;
        $this->sectionName = '';
    }

    public function saveSection()
    {
        if (empty($this->sectionName)) {
            $this->addError('sectionName', 'Section name is required.');

            return;
        }

        $fieldData = [
            'field_key' => 'section_'.Str::slug($this->sectionName).'_'.Str::random(4),
            'label' => $this->sectionName,
            'type' => 'section',
            'order' => count($this->fields),
            'is_required' => false,
            'is_visible' => true,
            'placeholder' => '',
            'help_text' => '',
            'default_value' => '',
            'validation' => [],
            'settings' => ['is_section' => true],
            'options' => [],
        ];

        $this->fields[] = $fieldData;
        $this->sections[] = $this->sectionName;
        $this->showSectionModal = false;
        $this->sectionName = '';
        $this->dispatch('sectionAdded');
    }

    public function openSchemaEditor()
    {
        $this->schemaEditor = json_encode($this->generateSchema(), JSON_PRETTY_PRINT);
        $this->showSchemaModal = true;
    }

    public function saveSchema()
    {
        try {
            $schema = json_decode($this->schemaEditor, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('schemaEditor', 'Invalid JSON format: '.json_last_error_msg());

                return;
            }

            // Validate schema structure
            $validator = Validator::make($schema, [
                'title' => 'required|string',
                'fields' => 'required|array',
                'fields.*.field_key' => 'required|string',
                'fields.*.label' => 'required|string',
                'fields.*.type' => 'required|string',
            ]);

            if ($validator->fails()) {
                $this->addError('schemaEditor', 'Invalid schema structure: '.$validator->errors()->first());

                return;
            }

            // Update form from schema
            $this->form['title'] = $schema['title'];
            $this->form['description'] = $schema['description'] ?? '';
            $this->fields = $schema['fields'];

            $this->showSchemaModal = false;
            $this->dispatch('schemaUpdated');
            session()->flash('message', 'Schema updated successfully!');

        } catch (\Exception $e) {
            $this->addError('schemaEditor', 'Error saving schema: '.$e->getMessage());
        }
    }

    public function generateSchema()
    {
        return [
            'title' => $this->form['title'],
            'description' => $this->form['description'],
            'settings' => $this->form['settings'] ?? [],
            'fields' => array_map(function ($field) {
                unset($field['id'], $field['form_id'], $field['created_at'], $field['updated_at']);

                return $field;
            }, $this->fields),
        ];
    }

    public function saveForm()
    {
        $this->validate();

        // Generate validation rules for fields
        $fieldValidationErrors = [];
        foreach ($this->fields as $index => $field) {
            if (empty($field['label'])) {
                $fieldValidationErrors[] = 'Field at position '.($index + 1).' has no label.';
            }
            if (empty($field['field_key'])) {
                $fieldValidationErrors[] = "Field '{$field['label']}' has no key.";
            }
        }

        if (! empty($fieldValidationErrors)) {
            session()->flash('error', implode('<br>', $fieldValidationErrors));

            return;
        }

        if (! empty($this->form['id'])) {
            $model = Form::findOrFail($this->form['id']);
        } else {
            $model = new Form;
            $model->user_id = auth()->id();
            $model->slug = Str::slug($this->form['title']).'-'.Str::random(6);
        }

        $model->title = $this->form['title'];
        $model->description = $this->form['description'];
        $model->settings = $this->form['settings'];
        $model->is_published = $this->form['is_published'] ?? false;
        $model->is_multi_step = $this->form['is_multi_step'] ?? false;
        $model->expires_at = ! empty($this->form['expires_at'])
            ? Carbon::parse($this->form['expires_at'])
            : null;
        $model->save();

        $this->form['id'] = $model->id;
        $this->form['slug'] = $model->slug;
        $this->form['fill_url'] = $model->fill_url;

        // Save fields and options
        foreach ($this->fields as $fieldData) {
            if (isset($fieldData['id'])) {
                $field = FormField::find($fieldData['id']);
                if ($field) {
                    $field->form_id = $model->id;
                    $field->update($fieldData);
                }
            } else {
                $fieldData['form_id'] = $model->id;
                $field = FormField::create($fieldData);
            }

            // Save options if any
            if (isset($fieldData['options']) && ! empty($fieldData['options'])) {
                // Delete existing options if field exists
                if (isset($fieldData['id'])) {
                    FieldOption::where('form_field_id', $field->id)->delete();
                }

                foreach ($fieldData['options'] as $optionData) {
                    $optionData['form_field_id'] = $field->id;
                    FieldOption::create($optionData);
                }
            }
        }

        // Record a version snapshot (skipped automatically when nothing changed).
        app(FormVersioningService::class)->capture($model, auth()->id(), 'Saved from builder');

        session()->flash('message', 'Form saved successfully!');

        return redirect()->route('forms.index');
    }

    public function previewForm()
    {
        $this->saveForm();

        return redirect()->route('forms.show', $this->form['slug']);
    }

    public function setStatus(bool $published)
    {
        if (! auth()->user()->can('publish forms')) {
            abort(403, 'You do not have permission to publish forms.');
        }

        $this->form['is_published'] = $published;

        if (! empty($this->form['id'])) {
            $model = Form::findOrFail($this->form['id']);
            $model->is_published = $published;
            if ($published && ! $model->published_at) {
                $model->published_at = now();
            }
            $model->save();
            $this->form['fill_url'] = $model->fill_url;
        }

        session()->flash('message', $published ? 'Form published successfully.' : 'Form saved as draft.');
        $this->statusMessage = $published ? 'Form published successfully.' : 'Form saved as draft.';
        $this->dispatch('statusUpdated');
    }

    #[On('aiGenerate')]
    public function startAiGeneration(string $prompt)
    {
        if (! auth()->user()->can('create forms') && ! auth()->user()->can('edit forms')) {
            abort(403, 'You do not have permission to use AI generation.');
        }

        $mode = ! empty($this->form['id']) ? 'edit' : 'create';

        $job = AiFormGenerationJob::create([
            'user_id' => auth()->id(),
            'form_id' => $this->form['id'] ?? null,
            'mode' => $mode,
            'prompt' => $prompt,
            'status' => AiFormGenerationJob::STATUS_QUEUED,
            'input_schema' => $mode === 'edit' ? $this->generateSchema() : null,
        ]);

        $this->aiGenerationJobId = $job->id;

        GenerateFormSchemaJob::dispatch($job->id);

        $this->dispatch('aiGenerationStarted', id: $job->id);
    }

    #[On('aiSchemaReady')]
    public function applyAiSchema($schema)
    {
        if (is_string($schema)) {
            $schema = json_decode($schema, true);
        }

        if (! is_array($schema)) {
            $this->addError('ai', 'The AI schema could not be read. Please try again.');

            return;
        }

        // Never load a broken schema into the builder.
        $schema = app(FormSchemaService::class)->validateAndRepair(
            $schema,
            ! empty($this->form['id']) ? 'edit' : 'create'
        );

        $this->form['title'] = $schema['title'];
        $this->form['description'] = $schema['description'];

        // Apply the AI's standard settings but never clobber extra keys the
        // user configured (e.g. email notification toggles).
        $this->form['settings'] = array_merge(
            $this->form['settings'] ?? [],
            array_intersect_key($schema['settings'], FormSchemaService::SETTINGS_DEFAULTS)
        );

        $existing = collect($this->fields)->keyBy('field_key');
        $newFields = [];
        $order = 0;

        foreach ($schema['fields'] as $field) {
            $key = $field['field_key'];

            if ($existing->has($key)) {
                // Keep the DB row identity so Save updates in place.
                $merged = array_merge($existing[$key], $this->aiFieldToBuilder($field));

                if (empty($merged['options']) && ! empty($existing[$key]['options'] ?? [])) {
                    $merged['options'] = $existing[$key]['options'];
                }
                if (empty($merged['validation']) && ! empty($existing[$key]['validation'] ?? [])) {
                    $merged['validation'] = $existing[$key]['validation'];
                }

                $merged['order'] = $order++;
                $newFields[] = $merged;
            } else {
                $builder = $this->aiFieldToBuilder($field);
                $builder['id'] = null;
                $builder['order'] = $order++;
                $newFields[] = $builder;
            }
        }

        $this->fields = $newFields;
        $this->loadSections();

        $this->statusMessage = 'AI schema applied. Review the fields, then save the form.';
    }

    protected function aiFieldToBuilder(array $field)
    {
        return [
            'field_key' => $field['field_key'],
            'label' => $field['label'],
            'type' => $field['type'],
            'placeholder' => $field['placeholder'] ?? '',
            'help_text' => $field['help_text'] ?? '',
            'default_value' => $field['default_value'] ?? '',
            'validation' => $field['validation'] ?? [],
            'settings' => $field['settings'] ?? [],
            'is_required' => $field['is_required'] ?? false,
            'is_visible' => $field['is_visible'] ?? true,
            'options' => array_values(array_map(function ($option) {
                return [
                    'label' => $option['label'],
                    'value' => $option['value'],
                    'order' => $option['order'] ?? 0,
                    'is_default' => $option['is_default'] ?? false,
                ];
            }, $field['options'] ?? [])),
        ];
    }

    public function render()
    {
        return view('livewire.forms.form-builder')
            ->layout('layouts.app');
    }
}
