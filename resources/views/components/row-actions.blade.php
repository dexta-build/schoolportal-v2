@props(['label' => __('Actions')])

{{--
    The "⋯" menu at the end of a table row: every action has a written label, so nobody has to
    guess what an icon means. Fill it with flux:menu.item links and <x-row-actions.form> buttons.

    Usage:
        <x-row-actions :label="__('Actions for :name', ['name' => $notice->title])">
            <flux:menu.item icon="pencil-square" href="{{ route('admin.notices.edit', $notice) }}" wire:navigate>{{ __('Edit') }}</flux:menu.item>
            <x-row-actions.form :action="route('admin.notices.publish', $notice)" icon="eye">{{ __('Publish') }}</x-row-actions.form>
            <flux:menu.separator />
            <x-row-actions.delete :action="route('admin.notices.destroy', $notice)" :title="__('Delete notice?')" :message="__('…')" />
        </x-row-actions>
--}}
<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" icon="ellipsis-horizontal" :aria-label="$label" :tooltip="__('Actions')" />

    <flux:menu class="min-w-48">
        {{ $slot }}
    </flux:menu>
</flux:dropdown>
