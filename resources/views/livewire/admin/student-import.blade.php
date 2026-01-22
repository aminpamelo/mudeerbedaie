<?php

use App\Services\StudentImportService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $csvFile;

    public $step = 1; // 1: Upload, 2: Preview, 3: Results

    public $previewData = [];

    public $importResults = [];

    public $isProcessing = false;

    protected $rules = [
        'csvFile' => 'required|file|mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel|max:2048',
    ];

    protected $messages = [
        'csvFile.required' => 'Please select a CSV file to upload.',
        'csvFile.mimetypes' => 'Please upload a valid CSV file.',
        'csvFile.max' => 'File size must not exceed 2MB.',
    ];

    public function uploadCsv(): void
    {
        try {
            // Clear any previous session errors
            session()->forget('error');

            $this->validate();

            $this->isProcessing = true;

            if (! $this->csvFile) {
                throw new \Exception('No file selected. Please choose a CSV file.');
            }

            if (! $this->csvFile->isValid()) {
                throw new \Exception('Invalid file upload.');
            }

            // Get the temporary file path directly from Livewire
            $tempPath = $this->csvFile->getRealPath();

            if (! $tempPath || ! file_exists($tempPath)) {
                throw new \Exception('Failed to access uploaded file');
            }

            // Parse and validate CSV directly from temporary location
            $service = new StudentImportService;
            $service->parseCsv($tempPath);
            $this->previewData = $service->validateData();

            $this->step = 2;
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Let validation errors be handled by Livewire
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Error processing CSV: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function importData(): void
    {
        try {
            $this->isProcessing = true;

            // Get the temporary file path directly from Livewire
            $tempPath = $this->csvFile->getRealPath();

            if (! $tempPath || ! file_exists($tempPath)) {
                throw new \Exception('Failed to access uploaded file');
            }

            $service = new StudentImportService;
            $service->parseCsv($tempPath);
            $service->validateData();
            $this->importResults = $service->importValidData();

            $this->step = 3;
        } catch (\Exception $e) {
            session()->flash('error', 'Error importing data: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function resetImport(): void
    {
        $this->reset(['csvFile', 'step', 'previewData', 'importResults', 'isProcessing']);
    }

    public function downloadSample(): void
    {
        // Redirect to the sample download route
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
                <span class="ml-2 text-sm font-medium">Import Results</span>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
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
                    <!-- Guidelines -->
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
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Sample Download -->
                    <div class="text-center">
                        <flux:button wire:click="downloadSample" variant="outline" icon="document-text">
                            Download Sample CSV
                        </flux:button>
                    </div>

                    <!-- File Upload -->
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
                    <!-- Summary Stats -->
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

                    <!-- Preview Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Row</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IC Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issues</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($previewData as $row)
                                    <tr class="hover:bg-gray-50">
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

                    <!-- Action Buttons -->
                    <div class="flex justify-between">
                        <flux:button wire:click="resetImport" variant="ghost">
                            Start Over
                        </flux:button>
                        <flux:button wire:click="importData" variant="primary" :disabled="$isProcessing || ($this->getValidRowsCount() + $this->getWarningRowsCount()) === 0">
                            <span wire:loading.remove wire:target="importData">Import {{ $this->getValidRowsCount() + $this->getWarningRowsCount() }} Records</span>
                            <span wire:loading wire:target="importData">Importing...</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:card>

    @elseif($step === 3)
        <!-- Step 3: Import Results -->
        <flux:card>
            <div class="p-6">
                <div class="space-y-6">
                    <div class="text-center">
                        <flux:icon name="check-circle" class="w-16 h-16 text-green-600 mx-auto mb-4" />
                        <flux:heading size="lg">Import Complete</flux:heading>
                    </div>

                    <!-- Results Summary -->
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
                            <div class="space-y-3">
                                @foreach($importResults['errors'] as $error)
                                    <div class="bg-white border border-red-300 rounded p-3">
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
