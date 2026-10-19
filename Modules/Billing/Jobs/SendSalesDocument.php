<?php

namespace Modules\Billing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\MessagingSetting;
use Modules\Billing\Services\DocumentPdf;

class SendSalesDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    public $tries = 1;
    public $timeout = 60;
    public $failOnTimeout = true;
    public int $deliveryId;

    public function __construct(int $id) { $this->deliveryId = $id; }

    public function handle(): void
    {
        $delivery = DB::table('billing_deliveries')->where('id', $this->deliveryId)->first();
        if (!$delivery || $delivery->status !== 'pending') return;
        if (\Carbon\Carbon::parse($delivery->send_at)->isFuture()) { $this->release(now()->diffInSeconds($delivery->send_at) + 1); return; }
        $document = DB::table('billing_' . $delivery->type)->where('id', $delivery->document_id)->first();
        if (!$document || !$document->number || $document->status === 'void'
            || ($delivery->purpose === 'reminder' && ($delivery->type !== 'invoices' || $document->paid_minor >= $document->total_minor))
            || !MessagingSetting::current()->allows('email', 'customer_notifications')) {
            DB::table('billing_deliveries')->where('id', $delivery->id)->where('status', 'pending')->update(['status' => 'suppressed', 'updated_at' => now()]);
            return;
        }
        if (!DB::table('billing_deliveries')->where('id', $delivery->id)->where('status', 'pending')->update(['status' => 'processing', 'updated_at' => now()])) return;
        try {
            $pdf = app(DocumentPdf::class)->get($delivery->type, $document->id);
            Mail::send('billing::email', compact('document', 'delivery'), function ($mail) use ($delivery, $document, $pdf) {
                $mail->to($delivery->recipient)->subject(($delivery->purpose === 'reminder' ? 'Payment reminder: ' : '') . $document->number . ' - New World Cargo')
                    ->attachData($pdf, $document->number . '.pdf', ['mime' => 'application/pdf']);
            });
            DB::table('billing_deliveries')->where('id', $delivery->id)->update(['status' => 'accepted', 'accepted_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $error) {
            $this->failed($error);
            report($error);
        }
    }

    public function failed(\Throwable $error): void
    {
        DB::table('billing_deliveries')->where('id', $this->deliveryId)->where('status', 'processing')->update([
            'status' => 'unknown', 'last_error' => 'Sending could not be confirmed. Check delivery before resending.', 'updated_at' => now(),
        ]);
    }
}
