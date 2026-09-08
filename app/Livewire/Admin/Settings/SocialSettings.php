<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\StoreSettings;
use Livewire\Component;

class SocialSettings extends Component
{
    /** @var array<string, string> */
    public array $links = [];

    public string $saved = '';

    /** @var array<string, array{label: string, host: string}> */
    public const NETWORKS = [
        'facebook' => ['label' => 'Facebook', 'host' => 'facebook.com'],
        'instagram' => ['label' => 'Instagram', 'host' => 'instagram.com'],
        'linkedin' => ['label' => 'LinkedIn', 'host' => 'linkedin.com'],
        'youtube' => ['label' => 'YouTube', 'host' => 'youtube.com'],
        'twitter' => ['label' => 'X (Twitter)', 'host' => 'x.com'],
        'tiktok' => ['label' => 'TikTok', 'host' => 'tiktok.com'],
    ];

    public function mount(StoreSettings $settings): void
    {
        $stored = $settings->array('social_links');

        foreach (array_keys(self::NETWORKS) as $network) {
            $this->links[$network] = (string) ($stored[$network] ?? '');
        }
    }

    public function save(StoreSettings $settings): void
    {
        $this->validate(
            collect(self::NETWORKS)
                ->mapWithKeys(fn (array $network, string $key): array => [
                    "links.{$key}" => ['nullable', 'url', 'max:255'],
                ])->all()
        );

        // Empty fields are dropped rather than stored as empty strings, so the footer can decide
        // what to show by presence alone instead of filtering blanks at every use.
        $settings->put('social', [
            'social_links' => collect($this->links)
                ->map(fn (string $url): string => trim($url))
                ->filter()
                ->all(),
        ]);

        $this->saved = 'Linkurile au fost salvate.';
    }

    public function render()
    {
        return view('livewire.admin.settings.social-settings', ['networks' => self::NETWORKS]);
    }
}
