<?php

namespace App\Jobs;

use App\Models\FormImportJob;
use App\Services\FormImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ParseImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(public int $importJobId) {}

    public function handle(FormImportService $service): void
    {
        $job = FormImportJob::findOrFail($this->importJobId);

        $job->update([
            'status' => FormImportJob::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $startedAt = hrtime(true);

        try {
            $path = Storage::disk('local')->path($job->file_path);

            $result = $service->parseFile($path, $job->extension);

            $job->update([
                'status' => FormImportJob::STATUS_SUCCEEDED,
                'parsed_data' => $result,
                'warnings' => $result['warnings'],
                'error' => null,
                'finished_at' => now(),
            ]);

            Log::channel('ai')->info('ai.import.succeeded', [
                'import_job_id' => $job->id,
                'user_id' => $job->user_id,
                'extension' => $job->extension,
                'layout' => $result['layout'],
                'fields' => count($result['fields']),
                'warnings' => count($result['warnings']),
                'total_elapsed_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status' => FormImportJob::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Log::channel('ai')->error('ai.import.failed', [
                'import_job_id' => $job->id,
                'user_id' => $job->user_id,
                'extension' => $job->extension,
                'error' => $e->getMessage(),
                'total_elapsed_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        }
    }
}
