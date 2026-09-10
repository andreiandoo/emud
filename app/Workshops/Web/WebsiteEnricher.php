<?php

namespace App\Workshops\Web;

use App\Enums\WorkshopEvidenceType;
use App\Models\Workshop;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopSourceRecord;
use App\Models\WorkshopWebsiteCandidate;
use App\Workshops\Contracts\ServiceClassifier;
use App\Workshops\Domain\ContactWriter;
use App\Workshops\Domain\WorkshopServiceWriter;
use App\Workshops\Domain\WorkshopStateRefresher;
use App\Workshops\Ingestion\DataSourceCatalog;

/**
 * Reads an accepted website and records what it says: contacts (each with the page it came from)
 * and the services it describes, as "declared on the website" and never as authorised.
 */
class WebsiteEnricher
{
    public function __construct(
        private WebsiteCrawler $crawler,
        private WebsiteExtractor $extractor,
        private ServiceClassifier $classifier,
        private ContactWriter $contacts,
        private WorkshopServiceWriter $services,
        private WorkshopStateRefresher $state,
    ) {}

    /** @return array{pages: int, emails: int, phones: int, services: int} */
    public function enrich(Workshop $workshop, WorkshopWebsiteCandidate $site): array
    {
        $pages = $this->crawler->crawl($site->url, $workshop->id);
        $site->update(['crawled_at' => now()]);

        if ($pages === []) {
            return ['pages' => 0, 'emails' => 0, 'phones' => 0, 'services' => 0];
        }

        $counts = ['pages' => count($pages), 'emails' => 0, 'phones' => 0, 'services' => 0];
        $emails = $phones = [];
        $home = $pages[0]->record;
        $this->contacts->link($workshop, 'website', $site->url, $home, max(70, (int) $site->confidence), 'Site oficial', $site->url);

        foreach ($pages as $page) {
            $facts = $this->extractor->extract($page->html, $page->url, $site->domain);

            foreach ($facts['emails'] as $email => $how) {
                if ($how['confidence'] > 0) {
                    $this->contacts->email($workshop, $email, $page->record, $how['confidence'], 'Site: '.$how['how'], $page->url);
                    $emails[$email] = true;
                }
            }

            foreach ($facts['phones'] as $phone) {
                $this->contacts->phone($workshop, $phone, $page->record, 75, 'Site oficial', $page->url);
                $phones[$phone->e164] = true;
            }

            foreach ($facts['whatsapp'] as $phone) {
                $this->contacts->record($workshop, 'whatsapp', $phone->display(), $phone->e164, $page->record, 75, 'WhatsApp (site)', $page->url);
            }

            foreach ($facts['facebook'] as $url) {
                $this->contacts->link($workshop, 'facebook', $url, $page->record, 70, 'Link de pe site', $page->url);
            }

            foreach ($facts['instagram'] as $url) {
                $this->contacts->link($workshop, 'instagram', $url, $page->record, 70, 'Link de pe site', $page->url);
            }
        }

        // A footer repeats the same address on every page: count addresses, not sightings.
        $counts['emails'] = count($emails);
        $counts['phones'] = count($phones);
        $counts['services'] = $this->classify($workshop);
        $workshop->forceFill(['website_status' => 'found', 'website_checked_at' => now()])->save();
        $this->state->refresh($workshop);

        return $counts;
    }

    /**
     * Re-reads the stored pages of a workshop's website into services, without fetching anything.
     * Returns the number of services found.
     */
    public function classify(Workshop $workshop): int
    {
        $source = WorkshopDataSource::query()->where('key', DataSourceCatalog::WEBSITE)->first();

        if ($source === null) {
            return 0;
        }

        // Pages are found by the site they belong to, not by workshop: two workshops of a chain
        // can share one website, and the page was fetched once for whichever came first.
        $domains = $workshop->websiteCandidates()->where('validation_status', WorkshopWebsiteCandidate::ACCEPTED)->pluck('domain')->all();
        $pages = collect($domains)->flatMap(fn (string $domain) => WorkshopSourceRecord::query()
            ->where('data_source_id', $source->id)
            ->where('record_type', 'page')
            ->where('is_current', true)
            ->where('source_reference', 'like', '%'.$domain.'%')
            ->latest('id')
            ->limit(20)
            ->get())
            ->filter(fn (WorkshopSourceRecord $page): bool => in_array(DirectoryDomains::host((string) $page->source_reference), $domains, true))
            ->unique('id');

        $services = [];

        foreach ($pages as $page) {
            $text = $this->extractor->extract((string) $page->raw_content, (string) $page->source_reference, (string) DirectoryDomains::host((string) $page->source_reference))['text'];

            foreach ($this->classifier->classify($text) as $key => $found) {
                $service = $services[$key] ?? ['confidence' => 0, 'evidence' => [], 'source_record_id' => $page->id];
                $service['confidence'] = max($service['confidence'], $found['confidence']);

                foreach ($found['snippets'] as $snippet) {
                    if (count($service['evidence']) < 5) {
                        $service['evidence'][] = ['url' => $page->source_reference, 'snippet' => $snippet, 'source_record_id' => $page->id];
                    }
                }

                $services[$key] = $service;
            }
        }

        $this->services->sync($workshop, WorkshopEvidenceType::Website, $services);

        return count($services);
    }
}
