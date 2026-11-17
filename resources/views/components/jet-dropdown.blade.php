@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white dark:bg-gray-700', 'dropdownClasses' => ''])

@php
$width = match ($width) {
    'w-full', 'full' => 'full',
    default => $width,
};
@endphp

<x-dropdown :align="$align" :width="$width" :contentClasses="$contentClasses" :dropdownClasses="$dropdownClasses">
    <x-slot name="trigger">
        {{ $trigger }}
    </x-slot>
    <x-slot name="content">
        {{ $content }}
    </x-slot>
</x-dropdown>
