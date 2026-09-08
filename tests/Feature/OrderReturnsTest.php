<?php

namespace Tests\Feature;

use App\Commerce\ReturnService;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Livewire\Admin\ReturnsIndex;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class OrderReturnsTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private int $lineId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->order = Order::create([
            'number' => 'EM-RET-1',
            'user_id' => $user->id,
            'checkout_token' => (string) Str::uuid(),
            'customer_email' => $user->email,
            'currency' => 'RON',
            'grand_total' => 300,
            'placed_at' => now(),
        ]);

        $this->lineId = $this->order->items()->create([
            'name' => 'Filtru ulei',
            'quantity' => 3,
            'unit_price' => 100,
            'line_total' => 300,
        ])->id;
    }

    public function test_a_return_can_be_requested(): void
    {
        $return = app(ReturnService::class)->request($this->order, ReturnReason::DoesNotFit, [$this->lineId => 2]);

        $this->assertSame(ReturnStatus::Requested, $return->status);
        $this->assertSame(2, $return->items->sole()->quantity);
    }

    public function test_a_request_with_no_lines_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        app(ReturnService::class)->request($this->order, ReturnReason::Withdrawal, [$this->lineId => 0]);
    }

    public function test_more_than_was_ordered_cannot_be_returned(): void
    {
        $this->expectException(RuntimeException::class);

        app(ReturnService::class)->request($this->order, ReturnReason::Damaged, [$this->lineId => 4]);
    }

    /**
     * Checked against what is left, not against what was ordered: a customer who already
     * returned two of three must not be able to open a second request for three more.
     */
    public function test_a_second_request_can_only_cover_what_is_left(): void
    {
        $service = app(ReturnService::class);
        $service->request($this->order, ReturnReason::Damaged, [$this->lineId => 2]);

        $this->assertSame([$this->lineId => 1], $service->returnableQuantities($this->order->fresh()));

        $this->expectException(RuntimeException::class);
        $service->request($this->order->fresh(), ReturnReason::Damaged, [$this->lineId => 2]);
    }

    /**
     * A rejected request frees its quantity again; one merely requested still holds it.
     */
    public function test_a_rejected_request_frees_its_quantity(): void
    {
        $service = app(ReturnService::class);
        $return = $service->request($this->order, ReturnReason::Damaged, [$this->lineId => 3]);

        $this->assertSame([$this->lineId => 0], $service->returnableQuantities($this->order->fresh()));

        $service->transition($return, ReturnStatus::Rejected);

        $this->assertSame([$this->lineId => 3], $service->returnableQuantities($this->order->fresh()));
    }

    public function test_the_normal_path_runs_to_refunded(): void
    {
        $service = app(ReturnService::class);
        $return = $service->request($this->order, ReturnReason::Defective, [$this->lineId => 1]);

        foreach ([ReturnStatus::Approved, ReturnStatus::Received, ReturnStatus::Refunded] as $step) {
            $return = $service->transition($return, $step);
        }

        $this->assertSame(ReturnStatus::Refunded, $return->status);
        $this->assertNotNull($return->resolved_at);
    }

    /**
     * Without a state machine the money could leave before the goods arrived.
     */
    public function test_a_return_cannot_be_refunded_before_it_is_received(): void
    {
        $return = app(ReturnService::class)->request($this->order, ReturnReason::Defective, [$this->lineId => 1]);

        $this->expectException(RuntimeException::class);

        app(ReturnService::class)->transition($return, ReturnStatus::Refunded);
    }

    public function test_a_closed_return_accepts_no_further_transitions(): void
    {
        $service = app(ReturnService::class);
        $return = $service->request($this->order, ReturnReason::Other, [$this->lineId => 1]);
        $return = $service->transition($return, ReturnStatus::Cancelled);

        $this->assertSame([], $return->status->allowedNext());
    }

    public function test_an_operator_can_advance_a_return(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $return = app(ReturnService::class)->request($this->order, ReturnReason::WrongPart, [$this->lineId => 1]);

        Livewire::test(ReturnsIndex::class)
            ->call('advance', $return->id, ReturnStatus::Approved->value);

        $this->assertSame(ReturnStatus::Approved, $return->refresh()->status);
    }

    /**
     * A refused transition is an ordinary operational answer, not a crash, so it is shown to
     * the operator rather than thrown at them.
     */
    public function test_a_refused_transition_is_reported_not_thrown(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $return = app(ReturnService::class)->request($this->order, ReturnReason::WrongPart, [$this->lineId => 1]);

        Livewire::test(ReturnsIndex::class)
            ->call('advance', $return->id, ReturnStatus::Refunded->value)
            ->assertSet('error', fn (string $error): bool => $error !== '');

        $this->assertSame(ReturnStatus::Requested, $return->refresh()->status);
    }

    public function test_returns_can_be_filtered_by_status(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        app(ReturnService::class)->request($this->order, ReturnReason::WrongPart, [$this->lineId => 1]);

        Livewire::test(ReturnsIndex::class)
            ->assertSee('EM-RET-1')
            ->set('status', ReturnStatus::Refunded->value)
            ->assertDontSee('EM-RET-1');
    }

    public function test_the_returns_screen_is_closed_to_customers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        $this->get(route('admin.returns.index'))->assertForbidden();
    }

    public function test_a_return_is_addressed_by_its_public_id(): void
    {
        $return = app(ReturnService::class)->request($this->order, ReturnReason::Other, [$this->lineId => 1]);

        $this->assertNotSame((string) $return->id, $return->getRouteKey());
        $this->assertSame($return->public_id, $return->getRouteKey());
        $this->assertSame(1, OrderReturn::query()->count());
    }
}
