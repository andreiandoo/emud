<?php

namespace App\Notifications;

use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierFeedUnhealthy extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<array{code: string, message: string}> $issues */
    public function __construct(public readonly Supplier $supplier, public readonly array $issues)
    {
        // The default connection queue has no worker in this deployment, so without
        // this the alert would be queued and never delivered.
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Furnizor cu probleme: {$this->supplier->name}")
            ->greeting('Sincronizarea unui furnizor necesită atenție')
            ->line("Furnizorul **{$this->supplier->name}** ({$this->supplier->code}) are următoarele probleme:");

        foreach ($this->issues as $issue) {
            $message->line("• {$issue['message']}");
        }

        return $message
            ->action('Deschide jurnalul sincronizărilor', route('admin.suppliers.sync-runs'))
            ->line('Magazinul public continuă să funcționeze; un feed căzut nu blochează vânzarea, dar datele afișate îmbătrânesc.');
    }
}
