@props([
    'action',
    'title' => __('Delete this item?'),
    'message' => __('This cannot be undone.'),
    'confirmLabel' => __('Delete'),
    'label' => __('Delete'),
])

{{-- The delete item of an <x-row-actions> menu, confirmed with the app's dialog. --}}
<x-row-actions.form
    :action="$action"
    method="DELETE"
    icon="trash"
    variant="danger"
    class="text-red-600! dark:text-red-400!"
    :confirm="['title' => $title, 'message' => $message, 'confirmLabel' => $confirmLabel, 'variant' => 'danger']"
>
    {{ $label }}
</x-row-actions.form>
