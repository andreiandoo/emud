<?php

namespace Tests\Feature;

use App\Directory\OpeningSchedule;
use App\Models\ServiceShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpeningScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_is_open_inside_its_hours_and_closed_outside_them(): void
    {
        $shop = $this->shop([3 => ['09:00', '18:00']]);

        // Wednesday.
        $this->assertTrue($shop->schedule()->isOpenAt(Carbon::parse('2026-09-09 09:00')));
        $this->assertTrue($shop->schedule()->isOpenAt(Carbon::parse('2026-09-09 17:59')));
        $this->assertFalse($shop->schedule()->isOpenAt(Carbon::parse('2026-09-09 08:59')));
        $this->assertFalse($shop->schedule()->isOpenAt(Carbon::parse('2026-09-09 18:00')));
    }

    public function test_a_day_with_no_row_is_closed(): void
    {
        $shop = $this->shop([3 => ['09:00', '18:00']]);

        // Thursday, which has no row at all.
        $this->assertFalse($shop->schedule()->isOpenAt(Carbon::parse('2026-09-10 12:00')));
    }

    public function test_an_explicitly_closed_day_is_closed(): void
    {
        $shop = $this->shop([3 => ['09:00', '18:00']]);
        $shop->hours()->create(['weekday' => 4, 'is_closed' => true]);

        $this->assertFalse($shop->fresh()->schedule()->isOpenAt(Carbon::parse('2026-09-10 12:00')));
    }

    /**
     * A closing time earlier than the opening time is the only way two time columns can carry an
     * evening shift, so the shop is still open at one in the morning of the following day.
     */
    public function test_a_shift_running_past_midnight_stays_open(): void
    {
        $shop = $this->shop([3 => ['20:00', '02:00']]);

        $this->assertTrue($shop->schedule()->isOpenAt(Carbon::parse('2026-09-09 23:30')));
        $this->assertTrue($shop->schedule()->isOpenAt(Carbon::parse('2026-09-10 01:00')));
        $this->assertFalse($shop->schedule()->isOpenAt(Carbon::parse('2026-09-10 02:00')));
        $this->assertFalse($shop->schedule()->isOpenAt(Carbon::parse('2026-09-09 19:00')));
    }

    /**
     * The filter and the badge have to agree. They are two implementations of one rule, and a
     * directory that lists a workshop as open while its own page says closed is worse than
     * having no filter.
     */
    #[DataProvider('moments')]
    public function test_the_sql_filter_agrees_with_the_php_rule(string $moment): void
    {
        $open = $this->shop([3 => ['09:00', '18:00']], 'deschis');
        $overnight = $this->shop([3 => ['20:00', '02:00']], 'peste-noapte');
        $closed = $this->shop([1 => ['09:00', '18:00']], 'luni-doar');

        $at = Carbon::parse($moment);
        [$sql, $bindings] = OpeningSchedule::openNowConstraint($at);

        $fromSql = ServiceShop::query()->whereRaw($sql, $bindings)->pluck('slug')->sort()->values()->all();

        $fromPhp = collect([$open, $overnight, $closed])
            ->filter(fn (ServiceShop $shop) => $shop->fresh()->schedule()->isOpenAt($at))
            ->pluck('slug')->sort()->values()->all();

        $this->assertSame($fromPhp, $fromSql, "Disagreement at {$moment}");
    }

    /** @return array<string, array{0: string}> */
    public static function moments(): array
    {
        return [
            'wednesday morning' => ['2026-09-09 10:00'],
            'wednesday just before opening' => ['2026-09-09 08:30'],
            'wednesday evening' => ['2026-09-09 21:00'],
            'thursday small hours' => ['2026-09-10 01:00'],
            'thursday after the overnight shift' => ['2026-09-10 03:00'],
            'monday midday' => ['2026-09-07 12:00'],
        ];
    }

    public function test_structured_data_lists_only_open_days(): void
    {
        $shop = $this->shop([3 => ['09:00', '18:00'], 6 => ['10:00', '14:00']]);
        $shop->hours()->create(['weekday' => 7, 'is_closed' => true]);

        $specification = $shop->fresh()->schedule()->structuredData();

        $this->assertCount(2, $specification);
        $this->assertSame('Wednesday', $specification[0]['dayOfWeek']);
        $this->assertSame('09:00', $specification[0]['opens']);
        $this->assertSame('Saturday', $specification[1]['dayOfWeek']);
    }

    /** @param array<int, array{0: string, 1: string}> $hours */
    private function shop(array $hours, string $slug = 'atelier'): ServiceShop
    {
        $shop = ServiceShop::create([
            'name' => 'Atelier '.$slug,
            'slug' => $slug,
            'county' => 'Cluj',
            'city' => 'Cluj-Napoca',
            'status' => 'published',
        ]);

        foreach ($hours as $weekday => [$opens, $closes]) {
            $shop->hours()->create(['weekday' => $weekday, 'opens_at' => $opens, 'closes_at' => $closes]);
        }

        return $shop->refresh();
    }
}
