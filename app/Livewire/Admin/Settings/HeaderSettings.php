<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\StoreSettings;
use Livewire\Component;

/**
 * The two bars that frame the storefront header.
 *
 * Both are editable rather than hard-coded because they carry the things that change most
 * often — a shipping threshold, a campaign, a holiday closure — and none of those should need
 * a deploy.
 */
class HeaderSettings extends Component
{
    public string $topbar_message = '';

    public string $topbar_link_label = '';

    public string $topbar_link_url = '';

    public bool $promo_enabled = false;

    public string $promo_message = '';

    public string $promo_link_label = '';

    public string $promo_link_url = '';

    public string $saved = '';

    public function mount(StoreSettings $settings): void
    {
        $this->topbar_message = $settings->string('topbar_message');
        $this->topbar_link_label = $settings->string('topbar_link_label');
        $this->topbar_link_url = $settings->string('topbar_link_url');
        $this->promo_enabled = $settings->bool('promo_enabled');
        $this->promo_message = $settings->string('promo_message');
        $this->promo_link_label = $settings->string('promo_link_label');
        $this->promo_link_url = $settings->string('promo_link_url');
    }

    public function save(StoreSettings $settings): void
    {
        $data = $this->validate([
            'topbar_message' => ['nullable', 'string', 'max:160'],
            'topbar_link_label' => ['nullable', 'string', 'max:40'],
            'topbar_link_url' => ['nullable', 'string', 'max:2048'],
            'promo_message' => ['nullable', 'string', 'max:200'],
            'promo_link_label' => ['nullable', 'string', 'max:40'],
            'promo_link_url' => ['nullable', 'string', 'max:2048'],
        ], [], [
            'topbar_message' => 'mesajul din bara de sus',
            'promo_message' => 'mesajul din banda de sub header',
        ]);

        $settings->put('header', [
            'topbar_message' => $data['topbar_message'] ?: null,
            'topbar_link_label' => $data['topbar_link_label'] ?: null,
            'topbar_link_url' => $data['topbar_link_url'] ?: null,
            'promo_enabled' => $this->promo_enabled,
            'promo_message' => $data['promo_message'] ?: null,
            'promo_link_label' => $data['promo_link_label'] ?: null,
            'promo_link_url' => $data['promo_link_url'] ?: null,
            // Dismissal is remembered per visitor against this stamp, so editing the message
            // shows it again to everyone who had already closed the previous one. Without it a
            // new campaign would stay invisible to exactly the returning customers it is for.
            'promo_version' => substr(sha1((string) $data['promo_message']), 0, 8),
        ]);

        $this->saved = 'Setările de header au fost salvate.';
    }

    public function render()
    {
        return view('livewire.admin.settings.header-settings');
    }
}
