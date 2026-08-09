<?php

namespace App\Livewire\Forms;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Livewire\Component;
use Livewire\WithPagination;

class FormSubmissions extends Component
{
    use WithPagination;

    public Form $form;

    public $search = '';

    public $perPage = 10;

    public $sortField = 'submitted_at';

    public $sortDirection = 'desc';

    public $selectedSubmissions = [];

    public $selectAll = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => 10],
        'sortField' => ['except' => 'submitted_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(Form $form)
    {
        $user = Auth::user();

        // Super admins can view any form's submissions.
        // Other users can only view submissions of their own forms.
        if (! $user->isSuperAdmin() && $form->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $this->form = $form;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedSubmissions = $this->getSubmissions()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedSubmissions = [];
        }
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function getSubmissions()
    {
        return FormSubmission::where('form_id', $this->form->id)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('data', 'LIKE', '%'.$this->search.'%')
                        ->orWhere('ip_address', 'LIKE', '%'.$this->search.'%')
                        ->orWhere('submission_uuid', 'LIKE', '%'.$this->search.'%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    public function deleteSubmission($id)
    {
        $submission = FormSubmission::find($id);
        if ($submission && $submission->form_id === $this->form->id) {
            $submission->delete();
            $this->dispatch('submissionDeleted');
            session()->flash('message', 'Submission deleted successfully.');
        }
    }

    public function deleteSelected()
    {
        if (! empty($this->selectedSubmissions)) {
            FormSubmission::whereIn('id', $this->selectedSubmissions)
                ->where('form_id', $this->form->id)
                ->delete();
            $this->selectedSubmissions = [];
            $this->selectAll = false;
            $this->dispatch('submissionsDeleted');
            session()->flash('message', 'Selected submissions deleted successfully.');
        }
    }

    public function exportCSV()
    {
        $submissions = $this->getSubmissions()->get();

        if ($submissions->isEmpty()) {
            session()->flash('error', 'No submissions to export.');

            return;
        }

        // Get all field keys from the form
        $fieldKeys = $this->form->fields->pluck('field_key')->toArray();
        $headers = array_merge(['Submission ID', 'Submitted At', 'IP Address'], $fieldKeys);

        $rows = $submissions->map(function ($submission) use ($fieldKeys) {
            $row = [
                $submission->submission_uuid,
                $submission->submitted_at->format('Y-m-d H:i:s'),
                $submission->ip_address,
            ];

            foreach ($fieldKeys as $key) {
                $value = $submission->data[$key] ?? '';
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $row[] = $value;
            }

            return $row;
        });

        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        $filename = 'submissions_'.$this->form->slug.'_'.now()->format('Y-m-d').'.csv';

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function render()
    {
        $submissions = $this->getSubmissions()->paginate($this->perPage);

        return view('livewire.forms.form-submissions', [
            'submissions' => $submissions,
        ])->layout('layouts.app');
    }
}
