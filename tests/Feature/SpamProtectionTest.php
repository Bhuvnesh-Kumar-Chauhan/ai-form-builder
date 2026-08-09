<?php

namespace Tests\Feature;

use App\Livewire\Forms\FormSubmissions;
use App\Livewire\Forms\FormView;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\SpamProtectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpamProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Spam protection is off by default in the test environment; each test
        // opts in explicitly.
        config(['spam.enabled' => true]);

        $this->user = User::factory()->create();

        $this->form = Form::create([
            'user_id' => $this->user->id,
            'title' => 'Spam Test Form',
            'slug' => 'spam-test-'.uniqid(),
            'is_published' => true,
            'published_at' => now(),
            'settings' => ['theme' => 'default', 'layout' => 'vertical'],
        ]);

        FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'message',
            'label' => 'Message',
            'type' => 'textarea',
            'order' => 1,
            'is_required' => true,
        ]);
    }

    protected function fillComponent(array $overrides = [])
    {
        $component = Livewire::test(FormView::class, ['form' => $this->form])
            ->set('submissionData.message', 'A real human message');

        foreach ($overrides as $key => $value) {
            $component->set($key, $value);
        }

        return $component;
    }

    #[Test]
    public function a_legit_submission_passes_when_the_time_trap_is_satisfied()
    {
        $this->fillComponent(['loadedAt' => time() - 10])
            ->call('submit');

        $this->assertDatabaseHas('form_submissions', [
            'form_id' => $this->form->id,
            'is_spam' => false,
        ]);
    }

    #[Test]
    public function a_filled_honeypot_is_rejected()
    {
        $this->fillComponent(['honeypot' => 'http://spam.example', 'loadedAt' => time() - 10])
            ->call('submit');

        $this->assertSame(0, FormSubmission::where('form_id', $this->form->id)->count());
    }

    #[Test]
    public function an_instant_submission_is_rejected()
    {
        $this->fillComponent(['loadedAt' => time()])
            ->call('submit');

        $this->assertSame(0, FormSubmission::where('form_id', $this->form->id)->count());
    }

    #[Test]
    public function repeat_attempts_from_one_ip_are_flagged_as_spam()
    {
        $service = app(SpamProtectionService::class);
        $ip = request()->ip();

        // Pre-fill the velocity counter below the threshold.
        $service->registerAttempt($ip);
        $service->registerAttempt($ip);
        $service->registerAttempt($ip);
        $service->registerAttempt($ip);
        $service->registerAttempt($ip);

        $this->fillComponent(['loadedAt' => time() - 10])
            ->call('submit');

        $this->assertDatabaseHas('form_submissions', [
            'form_id' => $this->form->id,
            'is_spam' => true,
        ]);
    }

    #[Test]
    public function the_controller_endpoint_honours_the_honeypot()
    {
        config(['spam.enabled' => true]);

        $this->post(route('forms.submit', $this->form->slug), [
            'message' => 'hello',
            config('spam.honeypot_field') => 'filled by bot',
        ])->assertStatus(422);

        $this->assertSame(0, FormSubmission::where('form_id', $this->form->id)->count());
    }

    #[Test]
    public function the_controller_endpoint_accepts_legit_submissions()
    {
        config(['spam.enabled' => true]);

        $this->post(route('forms.submit', $this->form->slug), [
            'message' => 'hello',
            'started_at' => time() - 10,
        ])->assertOk();

        $this->assertDatabaseHas('form_submissions', [
            'form_id' => $this->form->id,
            'is_spam' => false,
        ]);
    }

    #[Test]
    public function flagged_submissions_appear_in_the_spam_filter()
    {
        $spam = FormSubmission::create([
            'form_id' => $this->form->id,
            'data' => ['message' => 'buy cheap pills'],
            'ip_address' => '203.0.113.9',
            'is_spam' => true,
            'submitted_at' => now(),
        ]);

        $legit = FormSubmission::create([
            'form_id' => $this->form->id,
            'data' => ['message' => 'a real message'],
            'ip_address' => '203.0.113.10',
            'is_spam' => false,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->user);

        Livewire::test(FormSubmissions::class, ['form' => $this->form])
            ->set('spamFilter', 'spam')
            ->assertOk()
            ->assertSee('SPAM');

        $spam->forceDelete();
        $legit->forceDelete();
    }
}
