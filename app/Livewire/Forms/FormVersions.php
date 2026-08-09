<?php

namespace App\Livewire\Forms;

use App\Models\Form;
use App\Models\FormVersion;
use App\Services\FormVersioningService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FormVersions extends Component
{
    public Form $form;

    public ?int $previewVersionId = null;

    public ?int $confirmRollbackId = null;

    public function mount(Form $form)
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin() && $form->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $this->form = $form;
    }

    public function previewVersion(int $versionId)
    {
        $this->previewVersionId = $versionId;
    }

    public function closePreview()
    {
        $this->previewVersionId = null;
    }

    public function askRollback(int $versionId)
    {
        $this->confirmRollbackId = $versionId;
        $this->previewVersionId = null;
    }

    public function cancelRollback()
    {
        $this->confirmRollbackId = null;
    }

    public function rollback(int $versionId)
    {
        $version = FormVersion::where('form_id', $this->form->id)
            ->where('id', $versionId)
            ->firstOrFail();

        app(FormVersioningService::class)->restore($version, auth()->id());

        session()->flash('message', "Form rolled back to version {$version->version}.");

        return redirect()->route('forms.edit', $this->form);
    }

    public function render()
    {
        $service = app(FormVersioningService::class);

        $versions = $this->form->versions()
            ->with('author')
            ->orderByDesc('version')
            ->get();

        $preview = null;
        $previewDiff = null;

        if ($this->previewVersionId) {
            $preview = $versions->firstWhere('id', $this->previewVersionId);

            if ($preview) {
                $previous = FormVersion::where('form_id', $this->form->id)
                    ->where('version', '<', $preview->version)
                    ->orderByDesc('version')
                    ->first();

                $baseline = $previous?->schema ?? $service->snapshot($this->form);

                $previewDiff = $service->diff($baseline, $preview->schema);
            }
        }

        return view('livewire.forms.form-versions', [
            'versions' => $versions,
            'preview' => $preview,
            'previewDiff' => $previewDiff,
            'confirmRollbackId' => $this->confirmRollbackId,
        ])->layout('layouts.app');
    }
}
