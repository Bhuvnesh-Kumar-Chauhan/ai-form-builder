<?php

namespace Tests\Feature;

use App\Jobs\ParseImportJob;
use App\Models\FormImportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParseImportJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
    }

    public function test_docx_import_succeeds_and_stores_parsed_fields(): void
    {
        $this->stageFile('imports/registration-form.docx', 'registration-form.docx');

        $job = FormImportJob::create([
            'user_id' => $this->user->id,
            'original_name' => 'registration-form.docx',
            'file_path' => 'imports/registration-form.docx',
            'file_size' => 1234,
            'extension' => 'docx',
            'status' => FormImportJob::STATUS_QUEUED,
        ]);

        ParseImportJob::dispatchSync($job->id);

        $job->refresh();

        $this->assertSame(FormImportJob::STATUS_SUCCEEDED, $job->status);
        $this->assertNull($job->error);
        $this->assertNotNull($job->finished_at);
        $this->assertSame('docx', $job->parsed_data['layout']);
        $this->assertSame('Registration Form', $job->parsed_data['title']);
        $this->assertNotEmpty($job->parsed_data['fields']);
    }

    public function test_missing_file_marks_job_failed(): void
    {
        $job = FormImportJob::create([
            'user_id' => $this->user->id,
            'original_name' => 'gone.docx',
            'file_path' => 'imports/gone.docx',
            'file_size' => 0,
            'extension' => 'docx',
            'status' => FormImportJob::STATUS_QUEUED,
        ]);

        ParseImportJob::dispatchSync($job->id);

        $job->refresh();

        $this->assertSame(FormImportJob::STATUS_FAILED, $job->status);
        $this->assertNotNull($job->error);
        $this->assertNull($job->parsed_data);
    }

    protected function stageFile(string $diskPath, string $fixtureName): void
    {
        $fixture = realpath(__DIR__.'/../fixtures/'.$fixtureName);
        $this->assertFileExists($fixture);

        Storage::disk('local')->put($diskPath, file_get_contents($fixture));
    }
}
