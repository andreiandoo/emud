<?php

namespace App\Directory;

use App\Models\ServiceShopHour;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A workshop's week, and the one question a visitor actually asks of it: is it open now.
 *
 * Evaluated against the shop's own timezone rather than the visitor's. A workshop in Cluj is
 * open when it is 09:00 in Cluj, whatever time it is on the phone looking at the page.
 *
 * A closing time earlier than the opening time means the shift runs past midnight, which is
 * how a workshop with an evening shift has to be stored in two time columns.
 */
final class OpeningSchedule
{
    /** @param Collection<int, ServiceShopHour> $hours */
    private function __construct(private Collection $hours) {}

    /**
     * The same rule as isOpenAt(), expressed for the database.
     *
     * It lives here, next to the PHP, because the rule is subtle enough to drift: the third
     * clause is the shift that started yesterday and has not closed yet, and a copy of this SQL
     * kept in a query builder somewhere else would lose it the first time either was edited.
     * OpeningScheduleTest asserts the two agree.
     *
     * @return array{0: string, 1: array<int, int|string>}
     */
    public static function openNowConstraint(?CarbonInterface $moment = null): array
    {
        $moment = $moment ? $moment->copy() : Carbon::now(config('app.timezone'));

        $today = (int) $moment->isoWeekday();
        $yesterday = $today === 1 ? 7 : $today - 1;
        $now = $moment->format('H:i:s');

        $sql = <<<'SQL'
            exists (
                select 1 from service_shop_hours h
                where h.service_shop_id = service_shops.id
                  and h.is_closed = false
                  and h.opens_at is not null
                  and h.closes_at is not null
                  and (
                        (h.weekday = ? and h.closes_at > h.opens_at and ? >= h.opens_at and ? < h.closes_at)
                     or (h.weekday = ? and h.closes_at <= h.opens_at and ? >= h.opens_at)
                     or (h.weekday = ? and h.closes_at <= h.opens_at and ? < h.closes_at)
                  )
            )
        SQL;

        return [$sql, [$today, $now, $now, $today, $now, $yesterday, $now]];
    }

    /** @param iterable<ServiceShopHour> $hours */
    public static function make(iterable $hours): self
    {
        return new self(collect($hours)->keyBy(fn (ServiceShopHour $hour) => (int) $hour->weekday));
    }

    public function isEmpty(): bool
    {
        return $this->hours->isEmpty();
    }

    public function isOpenAt(?CarbonInterface $moment = null): bool
    {
        $moment = $moment ? $moment->copy() : Carbon::now(config('app.timezone'));

        if ($this->openOn((int) $moment->isoWeekday(), $moment)) {
            return true;
        }

        // A shift that started yesterday and runs past midnight is still open right now.
        $yesterday = $moment->copy()->subDay();

        return $this->spansMidnightOn((int) $yesterday->isoWeekday())
            && $this->openOn((int) $yesterday->isoWeekday(), $moment, previousDay: true);
    }

    /** @return array<int, array{weekday: int, label: string, closed: bool, opens: ?string, closes: ?string}> */
    public function week(): array
    {
        $names = [1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi', 5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică'];
        $rows = [];

        foreach ($names as $weekday => $label) {
            $hour = $this->hours->get($weekday);

            $rows[] = [
                'weekday' => $weekday,
                'label' => $label,
                // No row at all means the same thing to a reader as an explicit closed row.
                'closed' => $hour === null || $hour->is_closed || $hour->opens_at === null || $hour->closes_at === null,
                'opens' => $this->format($hour?->opens_at),
                'closes' => $this->format($hour?->closes_at),
            ];
        }

        return $rows;
    }

    /**
     * The openingHoursSpecification schema.org expects, using the two-letter day codes it
     * defines rather than our own numbering.
     *
     * @return list<array<string, string>>
     */
    public function structuredData(): array
    {
        $codes = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $specification = [];

        foreach ($this->week() as $row) {
            if ($row['closed']) {
                continue;
            }

            $specification[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $codes[$row['weekday']],
                'opens' => $row['opens'],
                'closes' => $row['closes'],
            ];
        }

        return $specification;
    }

    private function openOn(int $weekday, CarbonInterface $moment, bool $previousDay = false): bool
    {
        $hour = $this->hours->get($weekday);

        if ($hour === null || $hour->is_closed || $hour->opens_at === null || $hour->closes_at === null) {
            return false;
        }

        $opens = $this->minutes($hour->opens_at);
        $closes = $this->minutes($hour->closes_at);
        $now = $moment->hour * 60 + $moment->minute;

        if ($closes <= $opens) {
            return $previousDay ? $now < $closes : $now >= $opens;
        }

        return ! $previousDay && $now >= $opens && $now < $closes;
    }

    private function spansMidnightOn(int $weekday): bool
    {
        $hour = $this->hours->get($weekday);

        if ($hour === null || $hour->is_closed || $hour->opens_at === null || $hour->closes_at === null) {
            return false;
        }

        return $this->minutes($hour->closes_at) <= $this->minutes($hour->opens_at);
    }

    private function minutes(mixed $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $this->format($time) ?? '00:00'), 2, '0');

        return ((int) $hours) * 60 + (int) $minutes;
    }

    private function format(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        // Postgres hands back "09:00:00" while a form posts "09:00"; both must render the same.
        return substr((string) $time, 0, 5);
    }
}
