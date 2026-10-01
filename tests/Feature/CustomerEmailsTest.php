<?php

namespace Tests\Feature;

use App\Jobs\SendOutboundMessage;
use App\Mail\CustomerLifecycleMail;
use App\Models\MessagingSetting;
use App\Models\OutboundMessage;
use App\Models\ShipmentPaymentReceipt;
use App\Models\User;
use App\Services\Messaging\CustomerEmails;
use App\Services\Messaging\MtnClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\OnlineBookingRequest;
use Modules\Cargo\Entities\Shipment;
use Tests\TestCase;

class CustomerEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); Mail::fake();
        config(['messaging.activation_ready' => true]);
        MessagingSetting::create(['id' => 1, 'email_enabled' => true, 'email_per_minute' => 600]);
    }

    private function customer(): User
    {
        return User::create(['name' => 'Customer <script>', 'email' => 'customer@example.test', 'password' => bcrypt('test'), 'role' => 4, 'verified' => false]);
    }

    private function shipment(): Shipment
    {
        $user = $this->customer();
        $client = Client::create(['user_id' => $user->id, 'code' => 1, 'name' => 'Customer', 'email' => $user->email, 'responsible_mobile' => '000']);
        return Shipment::create(['client_id' => $client->id, 'code' => 'EMAIL-TEST', 'status_id' => Shipment::PENDING_STATUS, 'type' => Shipment::DROPOFF, 'shipping_date' => now()->toDateString()]);
    }

    private function receipt(Shipment $shipment, array $attributes = []): ShipmentPaymentReceipt
    {
        return ShipmentPaymentReceipt::create(array_merge(['shipment_id' => $shipment->id, 'receipt_number' => 'R-EMAIL', 'currency' => 'USD', 'amount' => 30, 'method_of_payment' => 'cash_payment'], $attributes));
    }

    public function test_verification_sends_one_welcome_not_each_profile_update(): void
    {
        $user = $this->customer();
        $this->assertSame(0, OutboundMessage::count());
        $user->update(['verified' => true]);
        $user->update(['name' => 'Updated']);
        app(CustomerEmails::class)->welcome($user);
        $this->assertSame(1, OutboundMessage::count());
        Queue::assertPushed(SendOutboundMessage::class, 1);
        Mail::assertNothingSent();
    }

    public function test_payment_is_owner_addressed_currency_exact_and_idempotent(): void
    {
        $shipment = $this->shipment();
        $receipt = $this->receipt($shipment);
        app(CustomerEmails::class)->payment($receipt);
        $message = OutboundMessage::sole();
        $this->assertSame('customer@example.test', $message->recipient);
        $this->assertSame('USD 30.00', $message->content['details']['Amount received']);
        $this->assertStringNotContainsString('fully paid', $message->content['body']);
        $job = new SendOutboundMessage($message->id);
        $job->handle(app(MtnClient::class));
        $job->handle(app(MtnClient::class));
        Mail::assertSent(CustomerLifecycleMail::class, 1);
        $mail = (new CustomerLifecycleMail($message->content))->build();
        $html = view($mail->view, ['content' => $message->content])->render();
        $this->assertStringContainsString('USD 30.00', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('/shipments/' . $shipment->id, $html);
    }

    public function test_each_installment_gets_its_own_receipt(): void
    {
        $shipment = $this->shipment();
        $this->receipt($shipment);
        $this->receipt($shipment, ['receipt_number' => 'R-EMAIL-2', 'currency' => 'ZMW', 'amount' => 100]);
        $this->assertSame(2, OutboundMessage::count());
        $this->assertSame('ZMW 100.00', OutboundMessage::latest('id')->first()->content['details']['Amount received']);
    }

    public function test_refunded_voided_and_zero_receipts_do_not_send(): void
    {
        $shipment = $this->shipment();
        $this->receipt($shipment, ['refunded' => true]);
        $this->receipt($shipment, ['status' => 'voided_duplicate']);
        $this->receipt($shipment, ['amount' => 0]);
        $this->assertSame(0, OutboundMessage::count());
    }

    public function test_rollback_removes_receipt_and_email_intent(): void
    {
        $shipment = $this->shipment();
        DB::beginTransaction();
        $this->receipt($shipment);
        $this->assertSame(1, OutboundMessage::count());
        DB::rollBack();
        $this->assertSame(0, OutboundMessage::count());
        $this->assertSame(0, ShipmentPaymentReceipt::count());
    }

    public function test_booking_waits_for_final_reference_then_approval_has_separate_message(): void
    {
        $shipment = $this->shipment();
        $booking = OnlineBookingRequest::create([
            'client_id' => $shipment->client_id, 'branch_id' => 1, 'reference' => 'PENDING-test',
            'service' => 'local', 'status' => 'pending', 'pickup_address' => 'Lusaka',
            'destination_address' => 'Woodlands', 'recipient_name' => 'Recipient',
            'recipient_phone' => '0970000000', 'submitted_at' => now(), 'payload' => [],
        ]);
        $this->assertSame(0, OutboundMessage::count());
        $booking->update(['reference' => 'OBR0000001']);
        $booking->update(['instructions' => 'Updated']);
        $this->assertSame(1, OutboundMessage::count());
        $booking->update(['shipment_id' => $shipment->id]);
        $this->assertSame(2, OutboundMessage::count());
    }

    public function test_disabled_email_does_not_backfill_when_reenabled(): void
    {
        MessagingSetting::current()->update(['email_enabled' => false]);
        $shipment = $this->shipment();
        $receipt = $this->receipt($shipment);
        MessagingSetting::current()->update(['email_enabled' => true]);
        $receipt->update(['cashier_name' => 'Updated']);
        $this->assertSame(0, OutboundMessage::count());
    }
}
