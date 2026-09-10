<?php

namespace App\Workshops\Web;

/**
 * A robots.txt file, read the way RFC 9309 describes: the group for our user agent if there is
 * one, otherwise the "*" group; within it the longest matching rule wins, and Allow wins a tie.
 * "*" matches any run of characters and a trailing "$" anchors the end.
 */
class RobotsTxt
{
    /** @param array<string, list<array{allow: bool, pattern: string}>> $groups user agent => rules */
    private function __construct(private array $groups, private bool $disallowAll = false) {}

    public static function parse(string $content): self
    {
        $groups = [];
        $agents = [];
        $inRules = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if ($inRules) {
                    $agents = [];
                    $inRules = false;
                }

                $agents[] = strtolower($value);
                $groups[strtolower($value)] ??= [];

                continue;
            }

            if (in_array($field, ['allow', 'disallow'], true)) {
                $inRules = true;

                if ($value === '' && $field === 'disallow') {
                    continue;
                }

                foreach ($agents as $agent) {
                    $groups[$agent][] = ['allow' => $field === 'allow', 'pattern' => $value];
                }
            }
        }

        return new self($groups);
    }

    public static function allowAll(): self
    {
        return new self([]);
    }

    public static function disallowAll(): self
    {
        return new self([], true);
    }

    public function allows(string $path, string $userAgent): bool
    {
        if ($this->disallowAll) {
            return false;
        }

        $rules = $this->rulesFor(strtolower($userAgent));
        $best = null;

        foreach ($rules as $rule) {
            if (! $this->matches($rule['pattern'], $path)) {
                continue;
            }

            $length = strlen($rule['pattern']);

            if ($best === null || $length > $best['length'] || ($length === $best['length'] && $rule['allow'])) {
                $best = ['length' => $length, 'allow' => $rule['allow']];
            }
        }

        return $best === null || $best['allow'];
    }

    /** @return list<array{allow: bool, pattern: string}> */
    private function rulesFor(string $userAgent): array
    {
        foreach ($this->groups as $agent => $rules) {
            if ($agent !== '*' && $agent !== '' && str_contains($userAgent, $agent)) {
                return $rules;
            }
        }

        return $this->groups['*'] ?? [];
    }

    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return preg_match($regex, $path) === 1;
    }
}
