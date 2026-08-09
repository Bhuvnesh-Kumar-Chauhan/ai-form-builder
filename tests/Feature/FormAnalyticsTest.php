<?php

namespace Tests\Feature;

use App\Livewire\Forms\FormAnalytics;
use App\Livewire\Forms\FormView;
use App\Models\Form;
use App\Models\FormAnalytic;
use App\Models\FormField;
use App\Models\User;
use App\Services\FormAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->form = Form::create([
            'user_id' => $this->user->id,
            'title' => 'Analytics Test Form',
            'slug' => 'analytics-test-'.uniqid(),
            'is_published' => true,
            'published_at' => now(),
            'settings' => ['theme' => 'default', 'layout' => 'vertical'],
        ]);

        FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'name',
            'label' => 'Full Name',
            'type' => 'text',
            'order' => 1,
            'is_required' => true,
        ]);
    }

    protected function viewForm(): void
    {
        Livewire::test(FormView::class, ['form' => $this->form])
            ->assertOk();
    }

    #[Test]
    public function a_public_visit_records_a_view_event()
    {
        $this->viewForm();

        $this->assertDatabaseHas('form_analytics', [
            'form_id' => $this->form->id,
            'event_type' => 'view',
        ]);
    }

    #[Test]
    public function owner_preview_does_not_pollute_analytics()
    {
        $this->actingAs($this->user);

        $this->viewForm();

        $this->assertDatabaseMissing('form_analytics', [
            'form_id' => $this->form->id,
            'event_type' => 'view',
        ]);
    }

    #[Test]
    public function advancing_and_submitting_records_a_full_funnel()
    {
        $this->viewForm();

        Livewire::test(FormView::class, ['form' => $this->form])
            ->set('submissionData.name', 'Jane Doe')
            ->call('submit');
        $this->assertDatabaseHas('form_analytics', ['form_id' => $this->form->id, 'event_type' => 'start']);
        $this->assertDatabaseHas('form_analytics', ['form_id' => $this->form->id, 'event_type' => 'complete']);
        $this->assertDatabaseHas('form_analytics', ['form_id' => $this->form->id, 'event_type' => 'field_interaction']);

        $service = app(FormAnalyticsService::class);
        $summary = $service->summary($this->form);

        $this->assertSame(1, $summary['totals']['views']);
        $this->assertSame(1, $summary['totals']['started']);
        $this->assertSame(1, $summary['totals']['completed']);
        $this->assertSame(100.0, $summary['rates']['completion_rate']);
    }

    #[Test]
    public function reloading_the_page_does_not_double_count_views()
    {
        $this->viewForm();
        $this->viewForm();
        $this->viewForm();

        $this->assertSame(
            1,
            FormAnalytic::where('form_id', $this->form->id)
                ->where('event_type', 'view')
                ->count()
        );
    }

    #[Test]
    public function abandon_is_only_recorded_for_started_incomplete_sessions()
    {
        $service = app(FormAnalyticsService::class);
        $session = 'session-abandon-test';

        // Not started yet -> no abandon recorded.
        $service->recordAbandon($this->form, $session);
        $this->assertSame(0, FormAnalytic::where('event_type', 'abandon')->count());

        // Started but not completed -> abandon recorded.
        $service->recordStart($this->form, $session);
        $service->recordAbandon($this->form, $session);
        $this->assertSame(1, FormAnalytic::where('event_type', 'abandon')->count());

        // Completed -> abandon no longer meaningful.
        $service->recordComplete($this->form, $session);
        $service->recordAbandon($this->form, $session);
        $this->assertSame(1, FormAnalytic::where('event_type', 'abandon')->count());
    }

    #[Test]
    public function the_abandon_beacon_endpoint_accepts_a_beacon_request()
    {
        app(FormAnalyticsService::class)->recordStart($this->form, 'beacon-session');

        $this->post(
            route('forms.analytics.beacon', $this->form->slug),
            ['event' => 'abandon', 'session_id' => 'beacon-session']
        )->assertNoContent();

        $this->assertDatabaseHas('form_analytics', [
            'form_id' => $this->form->id,
            'event_type' => 'abandon',
        ]);
    }

    #[Test]
    public function the_analytics_dashboard_renders_funnel_metrics()
    {
        $service = app(FormAnalyticsService::class);
        $service->recordView($this->form, 'dash-session-1');
        $service->recordStart($this->form, 'dash-session-1');
        $service->recordComplete($this->form, 'dash-session-1', ['submission_id' => 1]);
        $service->recordView($this->form, 'dash-session-2');

        $this->actingAs($this->user);

        Livewire::test(FormAnalytics::class, ['form' => $this->form])
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee('Started')
            ->assertSee('Completed');
    }

    #[Test]
    public function non_owners_cannot_view_analytics()
    {
        $other = User::factory()->create();

        $this->actingAs($other);

        Livewire::test(FormAnalytics::class, ['form' => $this->form])
            ->assertForbidden();
    }
}
