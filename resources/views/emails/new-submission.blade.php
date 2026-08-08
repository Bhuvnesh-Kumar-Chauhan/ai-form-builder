<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New form submission</title>
    <style>
        body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 24px; color: #1f2933; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; border: 1px solid #e4e7eb; }
        .header { background: #3b82f6; color: #fff; padding: 16px 24px; }
        .header h1 { margin: 0; font-size: 18px; }
        .body { padding: 24px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 8px 10px; border-bottom: 1px solid #eef1f4; font-size: 14px; text-align: left; vertical-align: top; }
        th { color: #6b7280; font-weight: 600; width: 40%; }
        .footer { padding: 16px 24px; background: #f9fafb; font-size: 12px; color: #9ca3af; }
        a { color: #3b82f6; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>&#128276; New submission: {{ $form->title }}</h1>
        </div>
        <div class="body">
            <p>A new response was submitted on <strong>{{ $submission->submitted_at->format('M j, Y g:i A') }}</strong>.</p>
            <table>
                @foreach($submission->formatted_data as $key => $value)
                    <tr>
                        <th>{{ $key }}</th>
                        <td>{{ is_array($value) ? implode(', ', $value) : $value }}</td>
                    </tr>
                @endforeach
            </table>
            <p style="margin-top: 20px;">
                <a href="{{ route('forms.submissions', $form->slug) }}">View all submissions</a>
            </p>
        </div>
        <div class="footer">
            Sent from {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
