<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;

/**
 * Snapshot, history, and rollback for form schemas.
 *
 * A "version" is a full JSON snapshot of the form (title, description,
 * settings, multi-step flag and every field + its options). Saving from the
 * builder captures a version only when the schema actually changed, so
 * cosmetic saves never spam the history.
 */
class FormVersioningService
{
    /**
     * Build a canonical snapshot of the form's current state.
     */
    public function snapshot(Form $form): array
    {
        // Query directly (not via the cached relation) so consecutive snapshots
        // always reflect the latest persisted state.
        $fields = FormField::with('options')
            ->where('form_id', $form->id)
            ->orderBy('order')
            ->get();

        return [
            'title' => $form->title,
            'description' => $form->description,
            'settings' => $form->settings,
            'is_multi_step' => (bool) $form->is_multi_step,
            'fields' => $fields->map(function (FormField $field) {
                return [
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'placeholder' => $field->placeholder,
                    'help_text' => $field->help_text,
                    'default_value' => $field->default_value,
                    'validation' => $field->validation ?? [],
                    'settings' => $field->settings ?? [],
                    'step' => (int) $field->step,
                    'order' => (int) $field->order,
                    'is_required' => (bool) $field->is_required,
                    'is_visible' => (bool) $field->is_visible,
                    'options' => $field->options->map(function ($option) {
                        return [
                            'label' => $option->label,
                            'value' => $option->value,
                            'order' => (int) $option->order,
                            'is_default' => (bool) $option->is_default,
                        ];
                    })->all(),
                ];
            })->all(),
        ];
    }

    public function latest(Form $form): ?FormVersion
    {
        return FormVersion::where('form_id', $form->id)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Capture a new version of the form's schema. Returns null when the
     * schema is identical to the latest recorded version.
     */
    public function capture(Form $form, ?int $userId = null, ?string $note = null): ?FormVersion
    {
        $schema = $this->snapshot($form);

        $latest = $this->latest($form);

        if ($latest && $this->sameSchema($latest->schema, $schema)) {
            return null;
        }

        $version = new FormVersion([
            'form_id' => $form->id,
            'created_by' => $userId,
            'version' => ($latest?->version ?? 0) + 1,
            'note' => $note,
            'schema' => $schema,
        ]);
        $version->save();

        return $version;
    }

    /**
     * Roll a form back to a previous version. The pre-rollback state is
     * captured first so the rollback is itself reversible.
     */
    public function restore(FormVersion $version, ?int $userId = null): FormVersion
    {
        $form = $version->form;

        $this->capture($form, $userId, 'Snapshot before rollback to v'.$version->version);

        $schema = $version->schema;

        $form->title = $schema['title'] ?? $form->title;
        $form->description = $schema['description'] ?? null;
        $form->settings = $schema['settings'] ?? $form->settings;
        $form->is_multi_step = (bool) ($schema['is_multi_step'] ?? $form->is_multi_step);
        $form->save();

        $this->rebuildFields($form, $schema['fields'] ?? []);

        return $version;
    }

    /**
     * Field-level summary of what changed between two schemas, for the
     * history UI.
     */
    public function diff(array $from, array $to): array
    {
        $fromFields = collect($from['fields'] ?? [])->keyBy('field_key');
        $toFields = collect($to['fields'] ?? [])->keyBy('field_key');

        $added = $toFields->diffKeys($fromFields)->values()
            ->map(fn ($f) => $f['label'] ?? $f['field_key'])
            ->all();

        $removed = $fromFields->diffKeys($toFields)->values()
            ->map(fn ($f) => $f['label'] ?? $f['field_key'])
            ->all();

        $changed = [];
        foreach ($toFields as $key => $to) {
            if (! $fromFields->has($key)) {
                continue;
            }

            $from = $fromFields[$key];

            foreach (['label', 'type', 'is_required'] as $attribute) {
                $fromValue = $from[$attribute] ?? null;
                $toValue = $to[$attribute] ?? null;

                if ($fromValue != $toValue) {
                    $changed[] = [
                        'field_key' => $key,
                        'label' => $to['label'] ?? $key,
                        'attribute' => $attribute,
                        'from' => $fromValue,
                        'to' => $toValue,
                    ];
                }
            }
        }

        return [
            'added' => array_values($added),
            'removed' => array_values($removed),
            'changed' => $changed,
        ];
    }

    public function sameSchema(array $a, array $b): bool
    {
        return $this->normalize($a) === $this->normalize($b);
    }

    protected function normalize(array $schema): string
    {
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function rebuildFields(Form $form, array $fields): void
    {
        $form->fields()->delete();

        foreach ($fields as $index => $fieldData) {
            $field = FormField::create([
                'form_id' => $form->id,
                'field_key' => $fieldData['field_key'],
                'label' => $fieldData['label'],
                'type' => $fieldData['type'],
                'placeholder' => $fieldData['placeholder'] ?? null,
                'help_text' => $fieldData['help_text'] ?? null,
                'default_value' => $fieldData['default_value'] ?? null,
                'validation' => $fieldData['validation'] ?? [],
                'settings' => $fieldData['settings'] ?? [],
                'step' => $fieldData['step'] ?? 1,
                'order' => $fieldData['order'] ?? $index,
                'is_required' => (bool) ($fieldData['is_required'] ?? false),
                'is_visible' => (bool) ($fieldData['is_visible'] ?? true),
            ]);

            foreach ($fieldData['options'] ?? [] as $optionData) {
                $field->options()->create([
                    'label' => $optionData['label'],
                    'value' => $optionData['value'],
                    'order' => $optionData['order'] ?? 0,
                    'is_default' => (bool) ($optionData['is_default'] ?? false),
                ]);
            }
        }
    }
}
