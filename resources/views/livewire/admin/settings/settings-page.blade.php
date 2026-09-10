<div>
    <x-admin.page-header title="Setări" subtitle="Identitatea magazinului, datele firmei și configurarea comercială." />

    <x-admin.tabs :tabs="$tabs" :current="$tab" field="tab" class="mb-6" />

    @switch($tab)
        @case('header')
            <livewire:admin.settings.header-settings />
            @break
        @case('footer')
            <livewire:admin.settings.footer-settings />
            @break
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
