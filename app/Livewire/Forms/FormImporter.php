<?php

namespace App\Livewire\Forms;

use App\Exceptions\LlmException;
use App\Jobs\ParseImportJob;
use App\Models\FieldOption;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormImportJob;
use App\Services\FormImportService;
use App\Services\FormSchemaService;
use App\Services\LlmClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class FormImporter extends Component
{
    use WithFileUploads;

    public $file;

    public $importJobId = null;

    public $importJob = null;

    public $hydratedJobId = null;

    public $title = '';

    public $description = '';

    public $fields = [];

    public $warnings = [];

    public $layout = null;

    public $refining = false;

    public $refinedCount = 0;

    public $refinedModel = null;

    public $error = null;

    protected $rules = [
        'title' => 'required|min:3|max:255',
        'fields' => 'required|array|min:1',
        'fields.*.label' => 'required|string',
        'fields.*.field_key' => 'required|alpha_dash',
        'fields.*.type' => 'required|string',
    ];

    public function updatedFile()
    {
        $this->validate([
            'file' => [
                'required',
                'file',
                'mimes:docx,xlsx,xls',
                'max:' . (int) (config('ai.import_max_size') / 1024),
            ],
        ]);

        $this->resetImport();

        try {
            $uploaded = $this->file;
            $path = $uploaded->store('imports', 'local');

            $job = FormImportJob::create([
                'user_id' => auth()->id(),
                'original_name' => $uploaded->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $uploaded->getSize(),
                'extension' => strtolower($uploaded->getClientOriginalExtension()),
                'status' => FormImportJob::STATUS_QUEUED,
            ]);

            $this->importJobId = $job->id;
            $this->importJob = $job;

            if ($job->file_size > config('ai.import_queue_threshold')) {
                ParseImportJob::dispatch($job->id);
                return;
            }

            try {
                $service = app(FormImportService::class);
                $result = $service->parseFile(Storage::disk('local')->path($path), $job->extension);

                $job->update([
                    'status' => FormImportJob::STATUS_SUCCEEDED,
                    'parsed_data' => $result,
                    'warnings' => $result['warnings'],
                    'finished_at' => now(),
                ]);

                $this->applyParsedJob($job);
            } catch (\Throwable $e) {
                $job->update([
                    'status' => FormImportJob::STATUS_FAILED,
                    'error' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
                $this->error = $e->getMessage();
            }
        } catch (\Throwable $e) {
            $this->error = 'Could not read the uploaded file: ' . $e->getMessage();
        }
    }

    public function refreshStatus()
    {
        if ($this->importJobId) {
            $this->importJob = FormImportJob::find($this->importJobId);
        }
    }

    protected function applyParsedJob(FormImportJob $job): void
    {
        $data = $job->parsed_data ?? [];

        $this->title = ! empty($data['title'])
            ? $data['title']
            : $this->titleFromOriginalName($job->original_name);

        $this->description = $data['description'] ?? '';
        $this->fields = $data['fields'] ?? [];
        $this->warnings = $data['warnings'] ?? [];
        $this->layout = $data['layout'] ?? null;
        $this->hydratedJobId = $job->id;
        $this->error = null;
    }

    protected function resetImport(): void
    {
        $this->importJobId = null;
        $this->importJob = null;
        $this->hydratedJobId = null;
        $this->title = '';
        $this->description = '';
        $this->fields = [];
        $this->warnings = [];
        $this->layout = null;
        $this->refinedCount = 0;
        $this->refinedModel = null;
        $this->error = null;
    }

    public function removeField($index)
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);

        foreach ($this->fields as $i => $field) {
            $this->fields[$i]['order'] = $i;
        }
    }

    public function refineWithAi()
    {
        $this->refining = true;
        $this->error = null;

        try {
            $result = app(FormImportService::class)->refineWithAi(
                fields: $this->fields,
                client: app(LlmClient::class),
                maxAttempts: config('ai.max_attempts'),
            );

            $this->fields = $result['fields'];
            $this->refinedCount = $result['changed'];
            $this->refinedModel = $result['model'];
        } catch (LlmException $e) {
            $this->error = 'AI refinement failed: ' . $e->getMessage();
        } catch (\Throwable $e) {
            $this->error = 'AI refinement failed: ' . $e->getMessage();
        } finally {
            $this->refining = false;
        }
    }

    public function saveForm()
    {
        $this->validate();

        $model = new Form();
        $model->user_id = auth()->id();
        $model->title = $this->title;
        $model->description = $this->description;
        $model->settings = FormSchemaService::SETTINGS_DEFAULTS;
        $model->is_published = false;
        $model->is_multi_step = false;
        $model->slug = Str::slug($this->title) . '-' . Str::random(6);
        $model->save();

        foreach ($this->fields as $index => $field) {
            if (empty($field['label']) || empty($field['field_key'])) {
                continue;
            }

            $data = $field;
            unset($data['id'], $data['confidence'], $data['origin'], $data['refined_by_ai']);

            $data['order'] = $index;
            $data['form_id'] = $model->id;

            $formField = FormField::create($data);

            foreach ($data['options'] ?? [] as $option) {
                $option['form_field_id'] = $formField->id;
                FieldOption::create($option);
            }
        }

        session()->flash('message', 'Form created from the imported document. Review it, then publish when ready.');

        return redirect()->route('forms.edit', $model->slug);
    }

    public function discardImport()
    {
        $this->resetImport();
    }

    protected function titleFromOriginalName(string $name): string
    {
        return Str::title(str_replace(['_', '-'], ' ', pathinfo($name, PATHINFO_FILENAME)));
    }

    public function getFieldTypes()
    {
        return [
            ['value' => 'text', 'label' => 'Text Input'],
            ['value' => 'email', 'label' => 'Email Input'],
            ['value' => 'number', 'label' => 'Number Input'],
            ['value' => 'phone', 'label' => 'Phone Input'],
            ['value' => 'textarea', 'label' => 'Text Area'],
            ['value' => 'date', 'label' => 'Date Picker'],
            ['value' => 'time', 'label' => 'Time Picker'],
            ['value' => 'datetime', 'label' => 'DateTime Picker'],
            ['value' => 'select', 'label' => 'Dropdown'],
            ['value' => 'radio', 'label' => 'Radio Buttons'],
            ['value' => 'checkbox', 'label' => 'Checkboxes'],
            ['value' => 'file', 'label' => 'File Upload'],
            ['value' => 'rating', 'label' => 'Rating'],
            ['value' => 'section', 'label' => 'Section Heading'],
            ['value' => 'divider', 'label' => 'Divider'],
            ['value' => 'heading', 'label' => 'Heading'],
            ['value' => 'paragraph', 'label' => 'Paragraph Text'],
            ['value' => 'url', 'label' => 'URL Input'],
            ['value' => 'password', 'label' => 'Password Input'],
            ['value' => 'color', 'label' => 'Color Picker'],
            ['value' => 'range', 'label' => 'Range Slider'],
            ['value' => 'hidden', 'label' => 'Hidden Field'],
        ];
    }

    public function render()
    {
        if ($this->importJobId) {
            $this->importJob = FormImportJob::find($this->importJobId);

            if ($this->importJob && $this->importJob->status === FormImportJob::STATUS_SUCCEEDED && $this->hydratedJobId !== $this->importJob->id) {
                $this->applyParsedJob($this->importJob);
            }

            if ($this->importJob && $this->importJob->status === FormImportJob::STATUS_FAILED && empty($this->error)) {
                $this->error = $this->importJob->error;
            }
        }

        return view('livewire.forms.form-importer', [
            'fieldTypes' => $this->getFieldTypes(),
        ])->layout('layouts.app');
    }
}
