<x-layouts::app :title="__('Teachers')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Teachers')"
            :action="route('admin.teachers.create')"
            :actionLabel="__('Add Teacher')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        {{-- Search --}}
        <form method="GET" action="{{ route('admin.teachers.index') }}" class="flex items-end gap-3">
            <div class="flex-1 max-w-sm">
                <flux:input name="search" :value="request('search')" placeholder="{{ __('Search name or username...') }}" icon="magnifying-glass" />
            </div>
            <flux:button type="submit" variant="filled" size="sm">{{ __('Search') }}</flux:button>
            @if (request('search'))
                <flux:button variant="subtle" size="sm" href="{{ route('admin.teachers.index') }}" wire:navigate>{{ __('Clear') }}</flux:button>
            @endif
        </form>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Username') }}</flux:table.column>
                <flux:table.column>{{ __('Assigned Classes') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-32" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($teachers as $teacher)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:avatar size="xs" :src="$teacher->avatarTableUrl()" :name="$teacher->name" />
                                <span class="font-medium">{{ $teacher->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $teacher->username }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($teacher->assignedClasses->count())
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($teacher->assignedClasses as $class)
                                        <flux:badge size="sm">{{ $class->name }}</flux:badge>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-zinc-400">{{ __('None') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $teacher->phone ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($teacher->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-end">
                            <x-row-actions :label="__('Actions for :name', ['name' => $teacher->name])">
                                <flux:menu.item icon="pencil-square" href="{{ route('admin.teachers.edit', $teacher) }}" wire:navigate>{{ __('Edit details & classes') }}</flux:menu.item>
                                <flux:menu.separator />
                                @if ($teacher->is_active)
                                    <flux:menu.item icon="pause-circle" variant="danger" x-on:click="$flux.modal('deactivate-teacher-{{ $teacher->id }}').show()">{{ __('Deactivate (block login)') }}</flux:menu.item>
                                @else
                                    <x-row-actions.form :action="route('admin.teachers.activate', $teacher)" icon="play-circle">{{ __('Activate (allow login)') }}</x-row-actions.form>
                                @endif
                            </x-row-actions>

                            {{-- Deactivate modal --}}
                            @if ($teacher->is_active)
                                <flux:modal :name="'deactivate-teacher-' . $teacher->id" class="max-w-md">
                                    <form method="POST" action="{{ route('admin.teachers.deactivate', $teacher) }}" class="space-y-4">
                                        @csrf
                                        <div>
                                            <flux:heading size="lg">{{ __('Deactivate :name', ['name' => $teacher->name]) }}</flux:heading>
                                            <flux:text class="mt-1">{{ __('This teacher will not be able to log in until you reactivate their account.') }}</flux:text>
                                        </div>
                                        <flux:textarea
                                            name="deactivation_reason"
                                            :label="__('Reason for deactivation')"
                                            :placeholder="__('e.g. Teacher no longer with the school...')"
                                            required
                                            rows="3"
                                        />
                                        <div class="flex justify-end gap-2">
                                            <flux:modal.close>
                                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                            </flux:modal.close>
                                            <flux:button type="submit" variant="danger">{{ __('Deactivate') }}</flux:button>
                                        </div>
                                    </form>
                                </flux:modal>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-8">
                            {{ __('No teachers found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $teachers->links() }}
    </div>
</x-layouts::app>
