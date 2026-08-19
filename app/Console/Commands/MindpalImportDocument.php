<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMindpalDocumentJob;
use App\Models\MindpalDocument;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

class MindpalImportDocument extends Command
{
    protected $signature = 'mindpal:import
        {path : Path to the PDF already on the server (absolute, or relative to the current directory)}
        {--title= : Document title (defaults to the file name without extension)}
        {--description= : Optional description}
        {--user= : Uploader user id or email (defaults to the first admin, else the first user)}
        {--queue : Dispatch processing to the queue instead of running it now}';

    protected $description = 'Import a PDF that was uploaded directly to the server and run the MindPal RAG pipeline on it (bypasses the web upload / Cloudflare size limit).';

    public function handle(): int
    {
        $source = $this->argument('path');
        $realPath = realpath($source);

        if ($realPath === false || ! is_file($realPath)) {
            $this->error("File not found: {$source}");

            return self::FAILURE;
        }

        if (! is_readable($realPath)) {
            $this->error("File is not readable: {$realPath}");

            return self::FAILURE;
        }

        if (strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) !== 'pdf') {
            $this->error('Only PDF files are supported.');

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('No uploader found. Pass --user=<id|email> for an existing user.');

            return self::FAILURE;
        }

        $originalName = basename($realPath);
        $title = $this->option('title') ?: pathinfo($originalName, PATHINFO_FILENAME);

        $this->info("Importing \"{$title}\" ({$this->humanSize(filesize($realPath))}) as {$user->email}...");

        // Stream the file into the same location a web upload would use.
        $storedPath = Storage::disk('public')->putFile('mindpal-documents', new File($realPath));

        $document = MindpalDocument::create([
            'title' => $title,
            'description' => $this->option('description'),
            'file_path' => $storedPath,
            'file_name' => $originalName,
            'file_size' => filesize($realPath),
            'status' => MindpalDocument::STATUS_PROCESSING,
            'uploaded_by' => $user->id,
        ]);

        $this->line("Created document #{$document->id} (stored at {$storedPath}).");

        if ($this->option('queue')) {
            ProcessMindpalDocumentJob::dispatch($document->id);
            $this->info('Queued for processing. Ensure a queue worker is running.');

            return self::SUCCESS;
        }

        $this->info('Processing now (parse → chunk → embed). This can take a few minutes for large PDFs...');
        ProcessMindpalDocumentJob::dispatchSync($document->id);

        $document->refresh();

        if ($document->status === MindpalDocument::STATUS_READY) {
            $this->info("Done — status: ready, {$document->total_pages} pages, {$document->total_chunks} chunks.");

            return self::SUCCESS;
        }

        $this->error("Processing failed — status: {$document->status}");
        if ($document->error_message) {
            $this->error($document->error_message);
        }

        return self::FAILURE;
    }

    private function resolveUser(): ?User
    {
        $option = $this->option('user');

        if ($option !== null && $option !== '') {
            return is_numeric($option)
                ? User::find((int) $option)
                : User::where('email', $option)->first();
        }

        return User::where('role', 'admin')->orderBy('id')->first()
            ?? User::orderBy('id')->first();
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
