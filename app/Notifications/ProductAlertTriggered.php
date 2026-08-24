<?php

namespace App\Notifications;

use App\Models\ProductAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductAlertTriggered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ProductAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->alert->product;
        $message = $this->alert->type === 'price'
            ? "Produsul a ajuns la prețul urmărit de tine: {$this->alert->target_price} {$this->alert->currency}."
            : 'Produsul urmărit de tine este din nou disponibil la unul dintre furnizorii noștri.';

        return (new MailMessage)
            ->subject("Alertă eMUD: {$product->name}")
            ->greeting("Salut, {$notifiable->name}!")
            ->line($message)
            ->action('Vezi produsul', url('/produse/'.$product->slug))
            ->line('Disponibilitatea se poate modifica rapid la furnizor.');
    }
}
