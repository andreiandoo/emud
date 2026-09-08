<?php

namespace App\Commerce;

use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Return requests and the transitions an operator may make on them.
 *
 * Quantities are checked against what is left, not against what was ordered: a customer who
 * already returned two of three items must not be able to open a second request for three more.
 */
class ReturnService
{
    /** @param array<int, int> $quantitiesByOrderItemId */
    public function request(Order $order, ReturnReason $reason, array $quantitiesByOrderItemId, ?string $note = null): OrderReturn
    {
        $lines = $this->validatedLines($order, $quantitiesByOrderItemId);

        if ($lines === []) {
            throw new RuntimeException('Selectează cel puțin un produs de returnat.');
        }

        return DB::transaction(function () use ($order, $reason, $note, $lines): OrderReturn {
            $return = OrderReturn::create([
                'public_id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'status' => ReturnStatus::Requested,
                'reason' => $reason,
                'customer_note' => $note,
                'requested_at' => now(),
            ]);

            foreach ($lines as $orderItemId => $quantity) {
                $return->items()->create(['order_item_id' => $orderItemId, 'quantity' => $quantity]);
            }

            return $return->load('items');
        });
    }

    /**
     * Only the transitions the current status allows. Without this a return could be marked
     * refunded without ever having been received, and the money would leave before the goods
     * arrived.
     */
    public function transition(OrderReturn $return, ReturnStatus $to, ?string $internalNote = null): OrderReturn
    {
        if (! in_array($to, $return->status->allowedNext(), true)) {
            throw new RuntimeException("Tranziția din „{$return->status->label()}” în „{$to->label()}” nu este permisă.");
        }

        $return->update([
            'status' => $to,
            'internal_note' => $internalNote ?? $return->internal_note,
            'resolved_at' => $to->isClosed() ? now() : null,
        ]);

        return $return->refresh();
    }

    /**
     * How many of each order line can still be returned.
     *
     * @return array<int, int>
     */
    public function returnableQuantities(Order $order): array
    {
        $ordered = $order->items()->pluck('quantity', 'id')->map(fn ($quantity) => (int) $quantity);

        $alreadyRequested = DB::table('order_return_items')
            ->join('order_returns', 'order_returns.id', '=', 'order_return_items.order_return_id')
            ->where('order_returns.order_id', $order->id)
            // A rejected or cancelled request frees its quantity again; anything else still
            // holds it, including one merely requested.
            ->whereNotIn('order_returns.status', [ReturnStatus::Rejected->value, ReturnStatus::Cancelled->value])
            ->groupBy('order_return_items.order_item_id')
            ->pluck(DB::raw('sum(order_return_items.quantity)'), 'order_return_items.order_item_id');

        return $ordered
            ->map(fn (int $quantity, int $itemId) => max(0, $quantity - (int) ($alreadyRequested[$itemId] ?? 0)))
            ->all();
    }

    /**
     * @param  array<int, int>  $requested
     * @return array<int, int>
     */
    private function validatedLines(Order $order, array $requested): array
    {
        $returnable = $this->returnableQuantities($order);
        $lines = [];

        foreach ($requested as $orderItemId => $quantity) {
            $quantity = (int) $quantity;
            $available = $returnable[(int) $orderItemId] ?? 0;

            if ($quantity < 1) {
                continue;
            }

            if ($quantity > $available) {
                throw new RuntimeException('Cantitatea cerută depășește ce mai poate fi returnat.');
            }

            $lines[(int) $orderItemId] = $quantity;
        }

        return $lines;
    }
}
