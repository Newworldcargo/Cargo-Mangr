<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandedEmailTemplatesTest extends TestCase
{
    public function test_active_templates_share_branding_and_escape_customer_content(): void
    {
        $name = 'Customer <script>alert(1)</script>';
        $samples = [
            'otp' => ['emails.otp', ['customerName' => $name, 'otp' => '123456']],
            'welcome' => ['emails.welcome', ['user' => ['name' => $name, 'email' => 'customer@example.test']]],
            'payment' => ['emails.customer-lifecycle', ['content' => [
                'subject' => 'Payment received', 'name' => $name,
                'body' => 'Thank you for your payment. Check your shipment for any remaining balance.',
                'details' => ['Receipt' => 'REC-1234567890123456789012345678901234567890', 'Amount received' => 'ZMW 4,622.31', 'Payment method' => 'Mobile money'],
                'path' => '/shipments/123', 'action' => 'View receipt and balance',
            ]]],
            'contact' => ['emails.contact_notification', ['contactData' => ['name' => $name, 'email' => 'customer@example.test', 'message' => 'Please check my shipment.']]],
            'report' => ['emails.nwc-report', ['summary' => ['total_bill_usd' => 12.34, 'total_bill_kwacha' => 200]]],
            'support' => ['cargo::emails.ticket_support', ['ticketData' => ['id' => 123, 'subject' => 'Delivery question', 'category' => 'Shipment', 'priority' => 'normal', 'message' => $name]]],
        ];
        foreach ($samples as $sample => [$view, $data]) {
            $html = view($view, $data)->render();
            $this->assertStringContainsString('cargo-logo-email.png', $html, $sample);
            $this->assertStringContainsString('The New World Cargo team', $html, $sample);
            $this->assertStringContainsString('max-width:600px', $html, $sample);
            $this->assertStringNotContainsString('<svg', $html, $sample);
            $this->assertStringNotContainsString('<script>', $html, $sample);
            $this->assertStringNotContainsString('href="#"', $html, $sample);
            $this->assertStringNotContainsString('@import', $html, $sample);
            if (getenv('NWC_EMAIL_PREVIEWS') === '1') file_put_contents('/tmp/nwc-email-' . $sample . '.html', $html);
        }
        $this->assertSame('Welcome to New World Cargo', (new \App\Mail\WelcomeMail(['name' => 'Customer', 'email' => 'customer@example.test']))->build()->subject);
    }
}
