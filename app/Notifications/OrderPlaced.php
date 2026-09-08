<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order)
    {
        // Queued onto the supervised notifications queue rather than the connection default,
        // which no worker in this deployment listens to.
        $this->onQueue('notifications');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Comanda {$this->order->number} a fost înregistrată")
            ->greeting('Salut!')
            ->line("Am înregistrat comanda ta cu numărul {$this->order->number}.");

        foreach ($this->order->items as $item) {
            $message->line("· {$item->name} × {$item->quantity} — ".$this->money($item->line_total));
        }

        $message
            ->line('Transport: '.$this->money($this->order->shipping_total))
            ->line('Total: '.$this->money($this->order->grand_total))
            // The order exists, but only the payment provider can confirm the money, so the
            // email never tells a customer they have paid when we do not know that yet.
            ->line('Confirmarea plății vine de la procesator. Îți scriem imediat ce o primim.')
            ->action('Vezi comanda', route('storefront.order', $this->order->checkout_token))
            ->line('Dacă nu tu ai plasat această comandă, răspunde la acest email.');

        return $message;
    }

    private function money(mixed $amount): string
    {
        return Money::of($amount, (string) $this->order->currency)->format();
    }
}
