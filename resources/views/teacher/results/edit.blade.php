<x-layouts::app :title="__('Edit Result')">
    <div class="space-y-6">
        <x-admin-header :title="__('Edit Result')">
            <flux:button variant="subtle" size="sm" href="{{ route('teacher.results.index') }}" wire:navigate icon="arrow-left">
                {{ __('Back to Result Sheets') }}
            </flux:button>
        </x-admin-header>

        @if ($result->status === 'rejected')
            <flux:callout variant="danger" icon="exclamation-circle">
                {{ __('This result was rejected.') }}
                @if ($rejectionReason)
                    {{ __('Reason:') }} {{ $rejectionReason }}
                @endif
                {{ __('You can edit and resubmit it.') }}
            </flux:callout>
        @elseif ($result->status === 'pending')
            <flux:callout variant="warning" icon="clock">
                {{ __('This result is pending approval. You can still edit and resubmit it.') }}
            </flux:callout>
        @endif

        <div class="max-w-2xl">
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <form method="POST" action="{{ route('teacher.results.update', $result) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @method('PUT')

                    {{-- Cascading Level → Class → Student selector (scoped to assigned classes) --}}
                    @livewire('student-selector', [
                        'restrictClassIds' => $assignedClassIds,
                        'studentId' => old('student_id', $result->student_id) ? (int) old('student_id', $result->student_id) : null,
                        'classId' => old('class_id', $result->class_id) ? (int) old('class_id', $result->class_id) : null,
                    ])

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <flux:input :label="__('Session')" :value="$currentSession?->name ?? __('No active session')" disabled />
                        </div>
                        <div>
                            <flux:input :label="__('Term')" :value="$currentTerm?->name ?? __('No active term')" disabled />
                        </div>
                    </div>

                    @if ($result->file_url)
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Current file:') }}
                            <a href="{{ $result->file_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 underline">
                                {{ __('View current PDF') }}
                            </a>
                        </div>
                    @endif

                    <div>
                        <label for="result_file" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Replace Result PDF (optional)') }}</label>
                        <input type="file" name="result_file" id="result_file" accept=".pdf"
                            class="block w-full text-sm text-zinc-500 dark:text-zinc-400
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-lg file:border-0
                                file:text-sm file:font-medium
                                file:bg-zinc-100 file:text-zinc-700
                                dark:file:bg-zinc-700 dark:file:text-zinc-200
                                hover:file:bg-zinc-200 dark:hover:file:bg-zinc-600
                                file:cursor-pointer" />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Leave empty to keep the current file. PDF files only, max 10MB.') }}</p>
                        @error('result_file')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <flux:textarea name="notes" :label="__('Notes (optional)')" rows="3" :value="old('notes', $result->notes)" placeholder="{{ __('Any additional notes about this result...') }}" />

                    <div class="flex gap-3 pt-2">
                        <flux:button variant="primary" type="submit">{{ __('Resubmit for Approval') }}</flux:button>
                        <flux:button variant="ghost" href="{{ route('teacher.results.index') }}" wire:navigate>{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts::app>
