<x-layouts::app :title="__('Bulk Upload Results')">
    <div class="space-y-6">
        <x-admin-header :title="__('Bulk Upload Results')">
            <flux:button variant="subtle" size="sm" href="{{ route('admin.results.index') }}" wire:navigate icon="arrow-left">
                {{ __('Back to Result Sheets') }}
            </flux:button>
        </x-admin-header>

        <div class="max-w-2xl space-y-6">
            {{-- Instructions --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="sm" class="mb-3">{{ __('How It Works') }}</flux:heading>
                <ul class="text-sm text-zinc-600 dark:text-zinc-400 space-y-1 list-disc list-inside">
                    <li>{{ __('Select a class and upload multiple PDF result files.') }}</li>
                    <li>{{ __('Each file must be named with the student\'s username (e.g. john.doe.pdf).') }}</li>
                    <li>{{ __('Files are automatically matched to students by filename.') }}</li>
                    <li>{{ __('Unmatched files will be shown so you can rename and re-upload.') }}</li>
                    <li>{{ __('If a result already exists for a student in this term, it will be replaced.') }}</li>
                </ul>
            </div>

            {{-- Upload Form --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <form method="POST" action="{{ route('admin.results.bulk.preview') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <flux:select name="class_id" :label="__('Class')" required>
                        <option value="">{{ __('Select class...') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(old('class_id') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </flux:select>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <input type="hidden" name="session_id" value="{{ $currentSession?->id }}">
                        <flux:input :label="__('Session')" :value="$currentSession?->name ?? __('No active session')" disabled />
                        <input type="hidden" name="term_id" value="{{ $currentTerm?->id }}">
                        <flux:input :label="__('Term')" :value="$currentTerm?->name ?? __('No active term')" disabled />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Result PDFs') }}</label>
                        <input type="file" name="result_files[]" accept=".pdf" multiple required class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-300" />
                        @error('result_files')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('result_files.*')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Upload & Match') }}</flux:button>
                </form>
            </div>
        </div>
    </div>
</x-layouts::app>
