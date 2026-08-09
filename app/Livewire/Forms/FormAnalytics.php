<?php

namespace App\Livewire\Forms;

use App\Models\Form;
use App\Services\FormAnalyticsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FormAnalytics extends Component
{
    public Form $form;

    public int $days = 30;

    protected $queryString = [
        'days' => ['except' => 30],
    ];

    public function mount(Form $form)
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin() && $form->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $this->form = $form;
    }

    public function render()
    {
        $service = app(FormAnalyticsService::class);

        return view('livewire.forms.form-analytics', [
            'summary' => $service->summary($this->form, $this->days),
            'stepFunnel' => $service->stepFunnel($this->form, $this->days),
            'avgSeconds' => $service->avgCompletionSeconds($this->form, $this->days),
            'series' => $service->activitySeries($this->form, 14),
            'recentEvents' => $service->recent($this->form, 15),
        ])->layout('layouts.app');
    }
}
