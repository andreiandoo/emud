<div>
    <x-admin.page-header title="Setări" subtitle="Identitatea magazinului, datele firmei și configurarea comercială." />

    <nav class="mb-6 flex flex-wrap gap-1 border-b border-stone-200">
        @foreach($tabs as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" @class([
                '-mb-px border-b-2 px-3 py-2 text-sm transition',
                'border-stone-900 font-semibold text-stone-900' => $tab === $key,
                'border-transparent text-stone-500 hover:text-stone-900' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </nav>

    @switch($tab)
        @case('company')
            <livewire:admin.settings.company-settings />
            @break
        @case('contact')
            <livewire:admin.settings.contact-settings />
            @break
        @case('documents')
            <livewire:admin.settings.document-settings />
            @break
        @case('social')
            <livewire:admin.settings.social-settings />
            @break
        @case('commerce')
            <livewire:admin.commerce-settings />
            @break
        @default
            <livewire:admin.settings.general-settings />
    @endswitch
</div>
