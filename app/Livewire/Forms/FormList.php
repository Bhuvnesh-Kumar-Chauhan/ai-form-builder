<?php

namespace App\Livewire\Forms;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Form;
use Illuminate\Support\Facades\Auth;

class FormList extends Component
{
    use WithPagination;

    public $search = '';
    public $filter = 'all'; // all, published, draft
    public $perPage = 10;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'filter' => ['except' => 'all'],
        'perPage' => ['except' => 10],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilter()
    {
        $this->resetPage();
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

    public function getForms()
    {
        $query = Auth::user()->hasAnyRole(['super-admin', 'admin'])
            ? Form::query()
            : Form::where('user_id', Auth::id());

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'LIKE', '%' . $this->search . '%')
                  ->orWhere('description', 'LIKE', '%' . $this->search . '%')
                  ->orWhere('slug', 'LIKE', '%' . $this->search . '%');
            });
        }

        // Filter
        if ($this->filter === 'published') {
            $query->where('is_published', true);
        } elseif ($this->filter === 'draft') {
            $query->where('is_published', false);
        }

        // Sort
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    public function deleteForm($formId)
    {
        $form = Form::find($formId);
        if ($form && $form->user_id === Auth::id()) {
            $form->delete();
            $this->dispatch('formDeleted');
            session()->flash('message', 'Form deleted successfully.');
        }
    }

    public function render()
    {
        $forms = $this->getForms();
        
        return view('livewire.forms.form-list', [
            'forms' => $forms,
        ])->layout('layouts.app');
    }
}