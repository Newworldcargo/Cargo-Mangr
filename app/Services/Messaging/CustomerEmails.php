<?php

namespace App\Services\Messaging;

use App\Models\MessagingSetting;
use App\Models\ShipmentPaymentReceipt;
use App\Models\User;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\OnlineBookingRequest;

class CustomerEmails
{
    public function welcome(User $user): void
    {
        if ((int) $user->role !== 4 || !$user->verified) return;
        $this->send($user, 'welcome:' . $user->id, [
            'subject' => 'Welcome to New World Cargo',
            'body' => 'Your account is ready. You can book a shipment, follow its progress, and manage your payments.',
            'path' => '/shipments', 'action' => 'View my shipments',
        ]);
    }

    public function booking(OnlineBookingRequest $booking): void
    {
        if (!$booking->submitted_at || !str_starts_with((string) $booking->reference, 'OBR')) return;
        $approved = (bool) $booking->shipment_id;
        $this->send($this->owner($booking->client_id), 'booking:' . $booking->id . ':' . ($approved ? 'approved' : 'received'), [
            'subject' => $approved ? 'Your booking has been approved' : 'We have received your booking',
            'body' => $approved ? 'Your booking has been approved. Follow your shipment and check its payment details in your account.' : 'Thank you for booking with New World Cargo. Our team will review your request and confirm the details and price.',
            'details' => ['Booking' => $booking->reference, 'From' => $booking->pickup_address, 'To' => $booking->destination_address],
            'path' => '/shipments/' . ($approved ? $booking->shipment_id : 'booking-' . $booking->id),
            'action' => 'View booking',
        ]);
    }

    public function payment(ShipmentPaymentReceipt $receipt): void
    {
        if (!in_array($receipt->status ?? 'active', ['active', 'completed'], true) || $receipt->refunded || (float) $receipt->amount <= 0) return;
        $shipment = $receipt->shipment;
        if (!$shipment) return;
        $this->send($this->owner($shipment->client_id), 'payment-receipt:' . $receipt->id, [
            'subject' => 'Payment received - New World Cargo',
            'body' => 'Thank you for your payment. The amount below has been received. You can download your receipt and check any remaining balance in your shipment payment details.',
            'details' => [
                'Receipt' => $receipt->receipt_number,
                'Shipment' => $shipment->code ?: (string) $shipment->id,
                'Amount received' => strtoupper((string) $receipt->currency) . ' ' . number_format((float) $receipt->amount, 2),
                'Payment method' => $receipt->method_of_payment,
                'Date' => $receipt->created_at?->format('d M Y H:i T'),
            ],
            'path' => '/shipments/' . $shipment->id, 'action' => 'View receipt and balance',
        ]);
    }

    private function owner($clientId): ?User
    {
        if (!MessagingSetting::current()->allows('email', 'customer_notifications')) return null;
        $userId = Client::whereKey($clientId)->value('user_id');
        return $userId ? User::find($userId) : null;
    }

    private function send(?User $user, string $key, array $content): void
    {
        if (!$user || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) return;
        app(Outbox::class)->enqueue('email', 'customer_notifications', $user->email,
            array_merge($content, ['template' => 'customer-lifecycle', 'name' => $user->name]),
            $key, now()->addDays(7));
    }
}
