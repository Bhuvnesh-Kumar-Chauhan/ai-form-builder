<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isAdmin = $user->hasAnyRole(['super-admin', 'admin']);

        $formQuery = $isAdmin
            ? Form::query()
            : Form::where('user_id', $user->id);

        $submissionQuery = FormSubmission::whereHas('form', function ($query) use ($user, $isAdmin) {
            if (!$isAdmin) {
                $query->where('user_id', $user->id);
            }
        });

        $totalForms = (clone $formQuery)->count();
        $publishedForms = (clone $formQuery)->where('is_published', true)->count();
        $draftForms = (clone $formQuery)->where('is_published', false)->count();
        $totalSubmissions = (clone $submissionQuery)->count();

        $forms = (clone $formQuery)
            ->withCount('submissions')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentSubmissions = (clone $submissionQuery)
            ->with('form')
            ->orderBy('submitted_at', 'desc')
            ->limit(8)
            ->get();

        $userRoles = $user->getRoleNames();
        $userPermissions = collect($user->getAllPermissions()->pluck('name'));

        $modules = [
            'Form Management' => [
                'view forms' => 'View forms',
                'create forms' => 'Create forms',
                'edit forms' => 'Edit forms',
                'delete forms' => 'Delete forms',
                'publish forms' => 'Publish forms',
                'duplicate forms' => 'Duplicate forms',
            ],
            'Submission Management' => [
                'view submissions' => 'View submissions',
                'export submissions' => 'Export submissions',
                'delete submissions' => 'Delete submissions',
            ],
            'User Administration' => [
                'manage users' => 'Manage users',
                'manage roles' => 'Manage roles',
                'manage permissions' => 'Manage permissions',
            ],
            'System Settings' => [
                'manage settings' => 'Manage settings',
            ],
        ];

        return view('dashboard', compact(
            'totalForms',
            'publishedForms',
            'draftForms',
            'totalSubmissions',
            'forms',
            'recentSubmissions',
            'userRoles',
            'userPermissions',
            'modules'
        ));
    }
}
