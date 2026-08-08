<?php

namespace App\Http\Controllers;

use App\Mail\NewSubmissionNotification;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function destroy(Form $form)
    {
        // Check permission
        if (!Auth::user()->canDeleteForms()) {
            abort(403, 'You do not have permission to delete forms.');
        }

        if ($form->user_id !== Auth::id() && !Auth::user()->isSuperAdmin()) {
            abort(403, 'You do not own this form.');
        }

        $form->delete();
        
        return redirect()->route('forms.index')
            ->with('message', 'Form deleted successfully.');
    }

    public function duplicate(Form $form)
    {
        // Check permission
        if (!Auth::user()->canCreateForms()) {
            abort(403, 'You do not have permission to duplicate forms.');
        }

        if ($form->user_id !== Auth::id() && !Auth::user()->isSuperAdmin()) {
            abort(403, 'You do not own this form.');
        }

        $newForm = $form->replicate();
        $newForm->title = $form->title . ' (Copy)';
        $newForm->slug = $form->slug . '-copy-' . time();
        $newForm->is_published = false;
        $newForm->submission_count = 0;
        $newForm->user_id = Auth::id();
        $newForm->save();
        
        // Duplicate fields
        foreach ($form->fields as $field) {
            $newField = $field->replicate();
            $newField->form_id = $newForm->id;
            $newField->save();
            
            // Duplicate options
            foreach ($field->options as $option) {
                $newOption = $option->replicate();
                $newOption->form_field_id = $newField->id;
                $newOption->save();
            }
        }
        
        return redirect()->route('forms.edit', $newForm)
            ->with('message', 'Form duplicated successfully.');
    }

    public function togglePublish(Form $form)
    {
        // Check permission
        if (!Auth::user()->canPublishForms()) {
            abort(403, 'You do not have permission to publish forms.');
        }

        if ($form->user_id !== Auth::id() && !Auth::user()->isSuperAdmin()) {
            abort(403, 'You do not own this form.');
        }

        $form->is_published = !$form->is_published;
        if ($form->is_published && !$form->published_at) {
            $form->published_at = now();
        }
        $form->save();
        
        return back()->with('message', 
            $form->is_published ? 'Form published successfully.' : 'Form unpublished.'
        );
    }

    public function submit(Request $request, Form $form)
    {
        // Get validation rules from form schema
        $rules = $form->getValidationRulesArray();
        
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Save submission
        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'data' => $validator->validated(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta_data' => [
                'referrer' => $request->header('referer'),
                'user_agent' => $request->userAgent(),
                'session_id' => session()->getId(),
            ],
            'submitted_at' => now(),
        ]);
        
        $form->increment('submission_count');

        $this->notifyOwner($form, $submission);

        return response()->json([
            'success' => true,
            'message' => $form->settings['success_message'] ?? 'Form submitted successfully!',
            'redirect_url' => $form->settings['redirect_url'] ?? null,
            'submission_id' => $submission->id,
        ]);
    }

    protected function notifyOwner(Form $form, FormSubmission $submission): void
    {
        $settings = $form->settings ?? [];

        if (empty($settings['email_notifications_enabled'])) {
            return;
        }

        $to = trim((string) ($settings['notification_email'] ?? ''));

        if ($to === '') {
            $to = $form->user?->email;
        }

        if ($to === null || $to === '') {
            return;
        }

        Mail::to($to)->queue(new NewSubmissionNotification($form, $submission));
    }

    public function exportSubmissions(Form $form)
    {
        // Check permission
        if (!Auth::user()->canExportSubmissions()) {
            abort(403, 'You do not have permission to export submissions.');
        }

        if ($form->user_id !== Auth::id() && !Auth::user()->isSuperAdmin()) {
            abort(403, 'You do not own this form.');
        }

        // Export logic here
        return redirect()->back()->with('message', 'Export started.');
    }

    public function deleteSubmission(Form $form, FormSubmission $submission)
    {
        // Check permission
        if (!Auth::user()->canDeleteSubmissions()) {
            abort(403, 'You do not have permission to delete submissions.');
        }

        if ($form->user_id !== Auth::id() && !Auth::user()->isSuperAdmin()) {
            abort(403, 'You do not own this form.');
        }

        $submission->delete();
        
        return redirect()->back()->with('message', 'Submission deleted successfully.');
    }
}