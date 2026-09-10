<?php

namespace App\Workshops\Domain;

use App\Models\Workshop;
use App\Models\WorkshopContact;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Data\PhoneNumber;

/**
 * Contacts are evidence, one row per source per value: the same number from RAR and from the
 * workshop's own website is two rows, and neither source's attribution is lost. What a source no
 * longer mentions is kept with its last_seen_at, never deleted.
 */
class ContactWriter
{
    public function phone(Workshop $workshop, PhoneNumber $phone, ?WorkshopSourceRecord $record, int $confidence, ?string $label = null, ?string $url = null): WorkshopContact
    {
        return $this->record($workshop, $phone->contactType(), $phone->display(), $phone->e164, $record, $confidence, $label, $url);
    }

    public function email(Workshop $workshop, string $email, ?WorkshopSourceRecord $record, int $confidence, ?string $label = null, ?string $url = null): WorkshopContact
    {
        $email = mb_strtolower(trim($email));

        return $this->record($workshop, 'email', $email, $email, $record, $confidence, $label, $url);
    }

    public function link(Workshop $workshop, string $type, string $url, ?WorkshopSourceRecord $record, int $confidence, ?string $label = null, ?string $sourceUrl = null): WorkshopContact
    {
        return $this->record($workshop, $type, $url, self::normalizeUrl($url), $record, $confidence, $label, $sourceUrl);
    }

    public function record(Workshop $workshop, string $type, string $value, string $normalized, ?WorkshopSourceRecord $record, int $confidence, ?string $label = null, ?string $sourceUrl = null, bool $verified = false): WorkshopContact
    {
        $contact = WorkshopContact::query()->firstOrNew([
            'workshop_id' => $workshop->id,
            'type' => $type,
            'normalized_value' => mb_substr($normalized, 0, 255),
            'data_source_id' => $record?->data_source_id,
        ]);

        $now = now();
        $contact->fill([
            'value' => mb_substr($value, 0, 255),
            'label' => $label ?? $contact->label,
            'source_record_id' => $record?->id ?? $contact->source_record_id,
            'source_url' => $sourceUrl ?? $contact->source_url,
            'confidence_score' => max($confidence, (int) $contact->confidence_score),
            'is_verified' => (bool) $contact->is_verified || $verified,
            'last_seen_at' => $now,
        ]);
        $contact->first_seen_at ??= $now;
        $contact->save();

        return $contact;
    }

    /** One primary per kind: the most trusted, then the most recently seen. */
    public function electPrimaries(Workshop $workshop): void
    {
        $contacts = $workshop->contacts()->get();

        foreach ([['phone', 'mobile'], ['email'], ['website'], ['facebook'], ['instagram'], ['whatsapp']] as $group) {
            $members = $contacts->whereIn('type', $group);
            $best = $members->sortByDesc(fn (WorkshopContact $contact): array => [$contact->confidence_score, $contact->last_seen_at?->getTimestamp() ?? 0])->first();

            foreach ($members as $contact) {
                $primary = $best !== null && $contact->id === $best->id;

                if ($contact->is_primary !== $primary) {
                    $contact->forceFill(['is_primary' => $primary])->save();
                }
            }
        }
    }

    /** Lowercase host without "www.", no fragment, no trailing slash: how two links are compared. */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return mb_strtolower($url);
        }

        $host = preg_replace('/^www\./', '', mb_strtolower($parts['host'])) ?? $parts['host'];
        $path = rtrim($parts['path'] ?? '', '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $host.$path.$query;
    }
}
