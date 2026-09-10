<?php

namespace App\Workshops\Sources\Rar;

use App\Workshops\Ingestion\SourceHttpClient;
use App\Workshops\Ingestion\SourceThrottle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * The public endpoints behind https://portal.rarom.ro/rar-public/registry-search.
 *
 * Found by reading the portal's own JavaScript (see docs/workshops/sources.md): the Angular app
 * calls GET /rarApi/public/RarPublicAuthorizations/{SERVICE|ITP|GPL|TLV|B4} with county, from and
 * to, and GET /rarApi/public/address/regions/RO for the county list. No key, no CAPTCHA, JSON back.
 * `from` is inclusive and `to` exclusive. Every call waits its turn behind the RAR throttle.
 */
class RarClient
{
    public function __construct(private SourceHttpClient $http, private SourceThrottle $throttle) {}

    /**
     * The counties exactly as the registry spells them ("Bucuresti", "Caras-Severin"), which is
     * also the spelling its search expects back.
     *
     * @return list<string>
     */
    public function counties(): array
    {
        $json = $this->get('/public/address/regions/RO')->json();

        if (! is_array($json)) {
            throw new RarResponseException('The registry returned a county list that is not a list.');
        }

        return array_values(array_filter(array_map(
            fn (mixed $county): string => is_string($county) ? trim($county) : '',
            $json,
        )));
    }

    /** @return list<array<string, mixed>> */
    public function authorizations(string $system, string $county, int $from, int $to): array
    {
        $json = $this->get($this->authorizationsPath($system), ['county' => $county, 'from' => $from, 'to' => $to])->json();

        if (! is_array($json)) {
            throw new RarResponseException("The registry answered {$system} / {$county} with something that is not a list.");
        }

        // The portal itself reads the answer with Object.keys(), so an object keyed "0", "1", …
        // is as valid a reply as a list.
        return array_values(array_filter($json, 'is_array'));
    }

    /**
     * The portal's Romanian translation file, where the wording of every activity code lives.
     *
     * @return array<string, mixed>
     */
    public function nomenclature(): array
    {
        $this->throttle->wait('rar', (int) config('workshops.rar.request_delay_ms'));

        $json = $this->pending()->acceptJson()->get((string) config('workshops.rar.nomenclature_url'))->throw()->json();

        if (! is_array($json) || ! isset($json['RarAuthorizationActivitiesEnumBySection'])) {
            throw new RarResponseException('The portal translation file no longer carries the activity nomenclature.');
        }

        return $json;
    }

    public function authorizationsUrl(string $system, string $county, int $from, int $to): string
    {
        return $this->baseUrl().$this->authorizationsPath($system).'?'.http_build_query(['county' => $county, 'from' => $from, 'to' => $to]);
    }

    private function authorizationsPath(string $system): string
    {
        return '/public/RarPublicAuthorizations/'.rawurlencode(strtoupper($system));
    }

    private function get(string $path, array $query = []): Response
    {
        $this->throttle->wait('rar', (int) config('workshops.rar.request_delay_ms'));

        return $this->pending()->acceptJson()->get($this->baseUrl().$path, $query)->throw();
    }

    private function pending(): PendingRequest
    {
        return $this->http->request(
            (int) config('workshops.rar.request_timeout'),
            (int) config('workshops.rar.max_retries'),
            (int) config('workshops.rar.retry_base_delay_ms'),
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('workshops.rar.base_url'), '/');
    }
}
