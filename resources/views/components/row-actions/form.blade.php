@props([
    'action',
    'method' => 'POST',
    'icon' => null,
    'variant' => 'default',
    // Optional app confirm dialog before submitting: ['title' => …, 'message' => …, 'confirmLabel' => …, 'variant' => 'danger']
    'confirm' => null,
])

{{-- A menu item inside <x-row-actions> that submits a form (publish, unpublish, delete …). --}}
@php
    $submit = "\$el.closest('form').requestSubmit()";
    $onClick = $confirm ? 'appConfirm('.Js::from($confirm).").then(ok => ok && {$submit})" : $submit;
@endphp
<form method="POST" action="{{ $action }}">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    <flux:menu.item :icon="$icon" :variant="$variant" x-on:click="{{ $onClick }}" {{ $attributes }}>
        {{ $slot }}
    </flux:menu.item>
</form>
