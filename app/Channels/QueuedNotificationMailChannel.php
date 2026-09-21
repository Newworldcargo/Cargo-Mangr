<?php
namespace App\Channels;

use App\Services\Messaging\Outbox;
use Illuminate\Support\Str;

class QueuedNotificationMailChannel
{
    public function send($notifiable, $notification): void
    {
        $data = $notification->data['message'] ?? [];
        $purpose = (int) $notifiable->role === 4 ? 'customer_notifications' : 'staff_notifications';
        app(Outbox::class)->enqueue('email', $purpose, (string) $notifiable->email, [
            'subject' => $data['subject'] ?? 'New World Cargo',
            'body' => ($data['content'] ?? '') . "\n\n" . ($data['url'] ?? ''),
        ], 'notification:' . ($notification->id ?: Str::uuid()) . ':' . $notifiable->id);
    }
}
