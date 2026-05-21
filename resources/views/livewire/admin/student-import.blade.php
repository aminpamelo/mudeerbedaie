<?php

use App\Jobs\ProcessStudentImport;
use App\Models\StudentImportProgress;
use App\Services\StudentImportService;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $csvFile;

    public $step = 1; // 1: Upload, 2: Preview, 3: Importing (progress), 4: Results

    public $previewData = [];

    public $importResults = [];

    public $isProcessing = false;

    public ?int $importProgressId = null;

    public ?array $progressSnapshot = null;

    protected $rules = [
        'csvFile' => 'required|file|mimes:csv,txt|max:2048',
    ];

    protected $messages = [
        'csvFile.required' => 'Please select a CSV file to upload.',
        'csvFile.mimes' => 'Please upload a valid CSV file (.csv or .txt).',
        'csvFile.max' => 'File size must not exceed 2MB.',
    ];

    public function uploadCsv(): void
    {
        try {
            session()->forget('error');

            $this->validate();

            $this->isProcessing = true;

            if (! $this->csvFile) {
                throw new \Exception('No file selected. Please choose a CSV file.');
            }

            if (! $this->csvFile->isValid()) {
                throw new \Exception('Invalid file upload.');
            }

            $tempPath = $this->csvFile->getRealPath();

            if (! $tempPath || ! file_exists($tempPath)) {
                throw new \Exception('Failed to access uploaded file');
            }

            $service = new StudentImportService;
            $service->parseCsv($tempPath);
            $this->previewData = $service->validateData();

            $this->step = 2;
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Error processing CSV: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function startImport(): void
    {
        try {
            $this->isProcessing = true;

            if (! $this->csvFile || ! $this->csvFile->isValid()) {
                throw new \Exception('Uploaded file is no longer available. Please re-upload the CSV.');
            }

            $storedPath = $this->csvFile->storeAs(
                'student-imports',
                'student-import-'.now()->format('Ymd_His').'-'.\Illuminate\Support\Str::random(8).'.csv',
                'local'
            );

            if (! $storedPath) {
                throw new \Exception('Failed to persist uploaded file for background processing.');
            }

            $progress = StudentImportProgress::create([
                'user_id' => auth()->id(),
                'class_id' => null,
                'type' => 'general',
                'file_path' => $storedPath,
                'status' => 'pending',
                'total_rows' => count($this->previewData),
            ]);

            ProcessStudentImport::dispatch($progress->id);

            $this->importProgressId = $progress->id;
            $this->progressSnapshot = $this->loadProgressSnapshot();
            $this->step = 3;
        } catch (\Exception $e) {
            session()->flash('error', 'Error starting import: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function refreshProgress(): void
    {
        if (! $this->importProgressId) {
            return;
        }

        $this->progressSnapshot = $this->loadProgressSnapshot();

        if ($this->progressSnapshot === null) {
            return;
        }

        if (in_array($this->progressSnapshot['status'], ['completed', 'failed', 'cancelled'], true)) {
            $this->importResults = $this->progressSnapshot['result'] ?? [
                'imported' => $this->progressSnapshot['created_count'],
                'updated' => $this->progressSnapshot['matched_count'],
                'skipped' => $this->progressSnapshot['skipped_count'],
                'errors' => [],
                'total' => $this->progressSnapshot['processed_rows'],
            ];
            $this->step = 4;
        }
    }

    public function cancelImport(): void
    {
        if (! $this->importProgressId) {
            return;
        }

        $progress = StudentImportProgress::find($this->importProgressId);
        if ($progress && in_array($progress->status, ['pending', 'processing'], true)) {
            $progress->update(['status' => 'cancelled']);
        }

        $this->refreshProgress();
    }

    public function resetImport(): void
    {
        $this->reset(['csvFile', 'step', 'previewData', 'importResults', 'isProcessing', 'importProgressId', 'progressSnapshot']);
    }

    public function downloadSample(): void
    {
        $this->redirect(route('students.sample-csv'));
    }

    public function getValidRowsCount(): int
    {
        return collect($this->previewData)->where('status', 'valid')->count();
    }

    public function getWarningRowsCount(): int
    {
        return collect($this->previewData)->where('status', 'warning')->count();
    }

    public function getInvalidRowsCount(): int
    {
        return collect($this->previewData)->where('status', 'invalid')->count();
    }

    public function getProgressPercentage(): int
    {
        if (! $this->progressSnapshot || ($this->progressSnapshot['total_rows'] ?? 0) === 0) {
            return 0;
        }

        return (int) round(($this->progressSnapshot['processed_rows'] / $this->progressSnapshot['total_rows']) * 100);
    }

    protected function loadProgressSnapshot(): ?array
    {
        $progress = StudentImportProgress::find($this->importProgressId);

        if (! $progress) {
            return null;
        }

        return [
            'id' => $progress->id,
            'status' => $progress->status,
            'total_rows' => $progress->total_rows,
            'processed_rows' => $progress->processed_rows,
            'created_count' => $progress->created_count,
            'matched_count' => $progress->matched_count,
            'skipped_count' => $progress->skipped_count,
            'error_count' => $progress->error_count,
            'error_message' => $progress->error_message,
            'result' => $progress->result,
            'started_at' => optional($progress->started_at)->toDateTimeString(),
            'completed_at' => optional($progress->completed_at)->toDateTimeString(),
        ];
    }
}; ?>

<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <flux:heading size="xl">Import Students</flux:heading>
        <flux:text class="mt-2">Upload a CSV file to import multiple students at once</flux:text>
    </div>

    <!-- Progress Steps -->
    <div class="mb-8">
        <div class="flex items-center">
            <div class="flex items-center">
                <span class="flex items-center justify-center w-8 h-8 rounded-full {{ $step >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600' }}">1</span>
                <span class="ml-2 text-sm font-medium">Upload CSV</span>
            </div>
            <div class="flex-1 mx-4 h-1 {{ $step >= 2 ? 'bg-blue-600' : 'bg-gray-300' }} rounded"></div>
            <div class="flex items-center">
                <span class="flex items-center justify-center w-8 h-8 rounded-full {{ $step >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600' }}">2</span>
                <span class="ml-2 text-sm font-medium">Preview & Validate</span>
            </div>
            <div class="flex-1 mx-4 h-1 {{ $step >= 3 ? 'bg-blue-600' : 'bg-gray-300' }} rounded"></div>
            <div class="flex items-center">
                <span class="flex items-center justify-center w-8 h-8 rounded-full {{ $step >= 3 ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600' }}">3</span>
                <span class="ml-2 text-sm font-medium">Importing</span>
            </div>
            <div class="flex-1 mx-4 h-1 {{ $step >= 4 ? 'bg-blue-600' : 'bg-gray-300' }} rounded"></div>
            <div class="flex items-center">
                <span class="flex items-center justify-center w-8 h-8 rounded-full {{ $step >= 4 ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600' }}">4</span>
                <span class="ml-2 text-sm font-medium">Results</span>
            </div>
        </div>
    </div>

    @if(session()->has('error'))
        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex">
                <flux:icon name="x-circle" class="w-5 h-5 text-red-400 mr-3 mt-0.5" />
                <div class="text-sm text-red-800">
                    {{ session('error') }}
                </div>
            </div>
        </div>
    @endif

    @if($step === 1)
        <!-- Step 1: File Upload -->
        <flux:card>
            <div class="p-6">
                <div class="space-y-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <flux:icon name="information-circle" class="w-5 h-5 text-blue-400 mr-3 mt-0.5" />
                            <div class="text-sm">
                                <p class="font-medium text-blue-800">Import Guidelines</p>
                                <ul class="mt-2 text-blue-700 list-disc list-inside space-y-1">
                                    <li>Upload a CSV file with student information</li>
                                    <li><strong>Required columns:</strong> name, phone</li>
                                    <li><strong>Optional columns:</strong> email, ic_number, address, date_of_birth, gender, nationality, status</li>
                                    <li>If phone/email/IC number exists, existing student will be updated</li>
                                    <li>Student ID will be auto-generated if not provided</li>
                                    <li>Default password will be set to 'password123' for new accounts</li>
                                    <li>Auto-generated email if not provided (student{phone}@example.com)</li>
                                    <li>Maximum file size: 2MB</li>
                                    <li>Large imports run in the background &mdash; progress is shown live.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <flux:button wire:click="downloadSample" variant="outline" icon="document-text">
                            Download Sample CSV
                        </flux:button>
                    </div>

                    <form wire:submit.prevent="uploadCsv">
                        <div class="space-y-4">
                            <div>
                                <flux:field>
                                    <flux:label>Select CSV File</flux:label>
                                    <flux:input type="file" wire:model="csvFile" accept=".csv,.txt" />
                                    <flux:error name="csvFile" />
                                </flux:field>
                            </div>

                            <div class="flex justify-between">
                                <flux:button href="{{ route('students.index') }}" variant="ghost">
                                    Cancel
                                </flux:button>
                                <flux:button type="submit" variant="primary" :disabled="$isProcessing || !$csvFile">
                                    <span wire:loading.remove wire:target="uploadCsv,csvFile">Upload & Preview</span>
                                    <span wire:loading wire:target="csvFile">Uploading file...</span>
                                    <span wire:loading wire:target="uploadCsv">Processing...</span>
                                </flux:button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </flux:card>

    @elseif($step === 2)
        <!-- Step 2: Preview & Validation -->
        <flux:card>
            <div class="p-6">
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <flux:icon name="check-circle" class="w-5 h-5 text-green-600 mr-2" />
                                <div>
                                    <p class="text-2xl font-semibold text-green-900">{{ $this->getValidRowsCount() }}</p>
                                    <p class="text-sm text-green-700">Valid Records</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-yellow-600 mr-2" />
                                <div>
                                    <p class="text-2xl font-semibold text-yellow-900">{{ $this->getWarningRowsCount() }}</p>
                                    <p class="text-sm text-yellow-700">With Warnings</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <flux:icon name="x-circle" class="w-5 h-5 text-red-600 mr-2" />
                                <div>
                                    <p class="text-2xl font-semibold text-red-900">{{ $this->getInvalidRowsCount() }}</p>
                                    <p class="text-sm text-red-700">Invalid Records</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                            <thead class="bg-gray-50 dark:bg-zinc-700/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Row</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IC Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Phone</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Issues</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                                @foreach($previewData as $row)
                                    <tr wire:key="preview-row-{{ $row['index'] }}" class="hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                                        <td class="px-4 py-4 text-sm text-gray-900">{{ $row['data']['_row_number'] }}</td>
                                        <td class="px-4 py-4">
                                            <flux:badge :class="match($row['status']) {
                                                'valid' => 'badge-green',
                                                'warning' => 'badge-yellow',
                                                'invalid' => 'badge-red',
                                                default => 'badge-gray'
                                            }">
                                                {{ ucfirst($row['status']) }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900">{{ $row['data']['name'] ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-900">{{ $row['data']['email'] ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-900">{{ $row['data']['ic_number'] ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-900">{{ $row['data']['phone'] ?? 'N/A' }}</td>
                                        <td class="px-4 py-4 text-sm">
                                            @if(count($row['errors']) > 0)
                                                @foreach($row['errors'] as $error)
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-red-100 text-red-800 mr-1 mb-1">
                                                        {{ $error }}
                                                    </span>
                                                @endforeach
                                            @endif
                                            @if(count($row['warnings']) > 0)
                                                @foreach($row['warnings'] as $warning)
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800 mr-1 mb-1">
                                                        {{ $warning }}
                                                    </span>
                                                @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-between">
                        <flux:button wire:click="resetImport" variant="ghost">
                            Start Over
                        </flux:button>
                        <flux:button wire:click="startImport" variant="primary" :disabled="$isProcessing || ($this->getValidRowsCount() + $this->getWarningRowsCount()) === 0">
                            <span wire:loading.remove wire:target="startImport">Queue Import ({{ $this->getValidRowsCount() + $this->getWarningRowsCount() }} Records)</span>
                            <span wire:loading wire:target="startImport">Queuing...</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:card>

    @elseif($step === 3)
        <!-- Step 3: Live Progress (polls every 2s while running) -->
        <flux:card wire:poll.2s="refreshProgress">
            <div class="p-6">
                <div class="space-y-6">
                    <div class="text-center">
                        @if($progressSnapshot && $progressSnapshot['status'] === 'pending')
                            <flux:icon name="clock" class="w-16 h-16 text-gray-500 mx-auto mb-4" />
                            <flux:heading size="lg">Waiting for queue worker&hellip;</flux:heading>
                            <flux:text class="mt-2 text-sm text-gray-500">If this stays here, make sure the queue worker is running (`php artisan queue:work` or `composer run dev`).</flux:text>
                        @elseif($progressSnapshot && $progressSnapshot['status'] === 'processing')
                            <flux:icon name="arrow-path" class="w-16 h-16 text-blue-600 mx-auto mb-4 animate-spin" />
                            <flux:heading size="lg">Importing students&hellip;</flux:heading>
                            <flux:text class="mt-2">{{ $progressSnapshot['processed_rows'] }} of {{ $progressSnapshot['total_rows'] }} rows processed</flux:text>
                        @else
                            <flux:icon name="arrow-path" class="w-16 h-16 text-gray-500 mx-auto mb-4 animate-spin" />
                            <flux:heading size="lg">Starting&hellip;</flux:heading>
                        @endif
                    </div>

                    <div>
                        <div class="flex justify-between text-sm font-medium text-gray-700 mb-2">
                            <span>Progress</span>
                            <span>{{ $this->getProgressPercentage() }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="bg-blue-600 h-3 rounded-full transition-all duration-300"
                                 style="width: {{ $this->getProgressPercentage() }}%"></div>
                        </div>
                    </div>

                    @if($progressSnapshot)
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
                            <div class="bg-green-50 border border-green-200 rounded p-3">
                                <p class="text-2xl font-bold text-green-900">{{ $progressSnapshot['created_count'] }}</p>
                                <p class="text-xs text-green-700">New</p>
                            </div>
                            <div class="bg-blue-50 border border-blue-200 rounded p-3">
                                <p class="text-2xl font-bold text-blue-900">{{ $progressSnapshot['matched_count'] }}</p>
                                <p class="text-xs text-blue-700">Updated</p>
                            </div>
                            <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                                <p class="text-2xl font-bold text-yellow-900">{{ $progressSnapshot['skipped_count'] }}</p>
                                <p class="text-xs text-yellow-700">Skipped</p>
                            </div>
                            <div class="bg-red-50 border border-red-200 rounded p-3">
                                <p class="text-2xl font-bold text-red-900">{{ $progressSnapshot['error_count'] }}</p>
                                <p class="text-xs text-red-700">Errors</p>
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-center">
                        <flux:button wire:click="cancelImport" variant="danger" size="sm">
                            Cancel Import
                        </flux:button>
                    </div>

                    <flux:text class="text-center text-xs text-gray-500">
                        You can safely close this page &mdash; the import will continue in the background.
                    </flux:text>
                </div>
            </div>
        </flux:card>

    @elseif($step === 4)
        <!-- Step 4: Final Results -->
        <flux:card>
            <div class="p-6">
                <div class="space-y-6">
                    <div class="text-center">
                        @if(($progressSnapshot['status'] ?? null) === 'failed')
                            <flux:icon name="x-circle" class="w-16 h-16 text-red-600 mx-auto mb-4" />
                            <flux:heading size="lg">Import Failed</flux:heading>
                            @if(! empty($progressSnapshot['error_message']))
                                <flux:text class="mt-2 text-red-700">{{ $progressSnapshot['error_message'] }}</flux:text>
                            @endif
                        @elseif(($progressSnapshot['status'] ?? null) === 'cancelled')
                            <flux:icon name="no-symbol" class="w-16 h-16 text-yellow-600 mx-auto mb-4" />
                            <flux:heading size="lg">Import Cancelled</flux:heading>
                            <flux:text class="mt-2">Stopped after {{ $progressSnapshot['processed_rows'] }} of {{ $progressSnapshot['total_rows'] }} rows.</flux:text>
                        @else
                            <flux:icon name="check-circle" class="w-16 h-16 text-green-600 mx-auto mb-4" />
                            <flux:heading size="lg">Import Complete</flux:heading>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-green-900">{{ $importResults['imported'] ?? 0 }}</p>
                            <p class="text-sm text-green-700">New Students</p>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-blue-900">{{ $importResults['updated'] ?? 0 }}</p>
                            <p class="text-sm text-blue-700">Updated Students</p>
                        </div>

                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-red-900">{{ $importResults['skipped'] ?? 0 }}</p>
                            <p class="text-sm text-red-700">Skipped</p>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-gray-900">{{ $importResults['total'] ?? 0 }}</p>
                            <p class="text-sm text-gray-700">Total Processed</p>
                        </div>
                    </div>

                    @if(isset($importResults['errors']) && count($importResults['errors']) > 0)
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h4 class="font-medium text-red-800 mb-3 flex items-center">
                                <flux:icon name="exclamation-triangle" class="w-5 h-5 mr-2" />
                                Import Errors:
                            </h4>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                @foreach($importResults['errors'] as $error)
                                    <div wire:key="result-error-{{ $error['row'] }}" class="bg-white border border-red-300 rounded p-3">
                                        <p class="text-sm font-medium text-red-900 mb-1">Row {{ $error['row'] }}:</p>
                                        <p class="text-sm text-red-700 break-words">{{ $error['error'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-center space-x-4">
                        <flux:button href="{{ route('students.index') }}" variant="primary">
                            View Students
                        </flux:button>
                        <flux:button wire:click="resetImport" variant="outline">
                            Import More Students
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:card>
    @endif
</div>
