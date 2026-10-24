<x-layouts::app :title="__('My Subjects')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('My Subjects')"
            :description="__('Subjects assigned to your classes.')"
            :action="route('teacher.subjects.create')"
            :actionLabel="__('Manage Subjects')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" icon="x-circle">{{ $errors->first() }}</flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Short Name') }}</flux:table.column>
                <flux:table.column>{{ __('Assigned Classes') }}</flux:table.column>
                <flux:table.column class="w-16" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($subjects as $subject)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $subject->name }}</flux:table.cell>
                        <flux:table.cell>{{ $subject->short_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $subject->classes->pluck('name')->join(', ') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ((int) $subject->created_by === (int) auth()->id() && $subject->classes_count === $subject->classes->count())
                                <x-confirm-delete
                                    :action="route('teacher.subjects.destroy', $subject)"
                                    :title="__('Delete :name?', ['name' => $subject->name])"
                                    :message="__('It will be removed from your classes. Subjects with CBT or score history are protected and will not be deleted.')"
                                    :buttonLabel="__('Delete')"
                                    :ariaLabel="__('Delete :name', ['name' => $subject->name])"
                                />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">
                            {{ __('No subjects are assigned to your classes yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $subjects->links() }}
    </div>
</x-layouts::app>
