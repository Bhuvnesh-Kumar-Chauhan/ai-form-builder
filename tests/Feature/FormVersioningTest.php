<?php

namespace Tests\Feature;

use App\Livewire\Forms\FormBuilder;
use App\Livewire\Forms\FormVersions;
use App\Models\FieldOption;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\User;
use App\Services\FormVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->form = Form::create([
            'user_id' => $this->user->id,
            'title' => 'Versioned Form',
            'slug' => 'versioned-'.uniqid(),
            'is_published' => false,
            'settings' => ['theme' => 'default', 'layout' => 'vertical'],
        ]);

        $this->field = FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'name',
            'label' => 'Full Name',
            'type' => 'text',
            'order' => 1,
            'is_required' => true,
        ]);
    }

    protected function service(): FormVersioningService
    {
        return app(FormVersioningService::class);
    }

    #[Test]
    public function capturing_a_schema_creates_incrementing_versions()
    {
        $service = $this->service();

        $v1 = $service->capture($this->form, $this->user->id, 'Initial');
        $this->assertSame(1, $v1->version);

        $this->field->update(['label' => 'Full Name Updated']);

        $v2 = $service->capture($this->form, $this->user->id, 'Renamed');
        $this->assertSame(2, $v2->version);

        $this->assertCount(2, $this->form->versions);
    }

    #[Test]
    public function capturing_an_unchanged_schema_is_a_noop()
    {
        $service = $this->service();

        $service->capture($this->form, $this->user->id, 'First');

        $result = $service->capture($this->form, $this->user->id, 'Duplicate');

        $this->assertNull($result);
        $this->assertCount(1, $this->form->versions);
    }

    #[Test]
    public function restoring_a_version_rebuilds_fields_and_options()
    {
        $service = $this->service();

        $v1 = $service->capture($this->form, $this->user->id, 'v1');

        // Change the form substantially.
        FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'extra',
            'label' => 'Extra Field',
            'type' => 'select',
            'order' => 2,
            'is_required' => false,
        ]);
        $this->form->update(['title' => 'Renamed Form']);

        $service->capture($this->form, $this->user->id, 'v2');

        // An unsaved change that exists only in the live form.
        FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'unsaved',
            'label' => 'Unsaved Field',
            'type' => 'text',
            'order' => 3,
        ]);

        $service->restore($v1, $this->user->id);

        $this->form->refresh();

        $this->assertSame('Versioned Form', $this->form->title);
        $this->assertSame(1, $this->form->fields()->count());
        $this->assertSame('name', $this->form->fields->first()->field_key);

        // The rollback captured the unsaved pre-rollback state as a new
        // version, so the rollback itself is reversible.
        $versions = $this->form->versions()->orderBy('version')->get();
        $this->assertSame(3, $versions->count());
        $this->assertSame('Snapshot before rollback to v1', $versions->last()->note);
        $this->assertContains('unsaved', collect($versions->last()->schema['fields'])->pluck('field_key')->all());
    }

    #[Test]
    public function restore_preserves_choice_field_options()
    {
        $service = $this->service();

        $select = FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'position',
            'label' => 'Position',
            'type' => 'select',
            'order' => 2,
        ]);

        FieldOption::create(['form_field_id' => $select->id, 'label' => 'Developer', 'value' => 'dev', 'order' => 1]);
        FieldOption::create(['form_field_id' => $select->id, 'label' => 'Designer', 'value' => 'design', 'order' => 2]);

        $v1 = $service->capture($this->form, $this->user->id, 'with options');

        // Wipe the options, capture, then roll back.
        $select->options()->delete();
        $service->capture($this->form, $this->user->id, 'options removed');

        $service->restore($v1, $this->user->id);

        $restored = $this->form->fields()->where('field_key', 'position')->first();

        $this->assertSame(2, $restored->options()->count());
        $this->assertSame(['dev', 'design'], $restored->options()->pluck('value')->all());
    }

    #[Test]
    public function diff_reports_added_removed_and_changed_fields()
    {
        $service = $this->service();

        $from = $service->snapshot($this->form);

        FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'added_field',
            'label' => 'Added Field',
            'type' => 'text',
            'order' => 2,
        ]);
        $this->field->update(['is_required' => false]);

        $to = $service->snapshot($this->form);

        $diff = $service->diff($from, $to);

        $this->assertContains('Added Field', $diff['added']);
        $this->assertEmpty($diff['removed']);
        $this->assertNotEmpty($diff['changed']);
        $this->assertSame('is_required', $diff['changed'][0]['attribute']);
    }

    #[Test]
    public function saving_from_the_builder_records_a_version()
    {
        $this->actingAs($this->user);

        Livewire::test(FormBuilder::class, ['form' => $this->form])
            ->set('form.title', 'Builder Saved Form')
            ->call('saveForm');

        $this->assertDatabaseHas('form_versions', [
            'form_id' => $this->form->id,
            'version' => 1,
        ]);

        $version = FormVersion::where('form_id', $this->form->id)->first();
        $this->assertSame('Builder Saved Form', $version->schema['title']);
        $this->assertSame('Saved from builder', $version->note);
    }

    #[Test]
    public function the_versions_page_lists_history_and_rejects_non_owners()
    {
        $this->service()->capture($this->form, $this->user->id, 'Initial');

        $this->actingAs($this->user);

        Livewire::test(FormVersions::class, ['form' => $this->form])
            ->assertOk()
            ->assertSee('Version History')
            ->assertSee('v1');

        $other = User::factory()->create();

        $this->actingAs($other);

        Livewire::test(FormVersions::class, ['form' => $this->form])
            ->assertForbidden();
    }
}
