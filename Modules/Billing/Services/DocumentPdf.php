<?php

namespace Modules\Billing\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;

final class DocumentPdf
{
    public function receipt(object $document, object $receipt): string
    {
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $options->setChroot(public_path('assets/lte'));
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('billing::receipt', compact('document', 'receipt'))->render());
        $pdf->setPaper('A4');
        $pdf->render();
        return $pdf->output();
    }

    public function get(string $type, int $id): string
    {
        if (!in_array($type, ['invoices', 'quotations'], true)) throw new \DomainException('Invalid document.');
        return DB::transaction(function () use ($type, $id) {
            $document = DB::table('billing_' . $type)->where('id', $id)->lockForUpdate()->first();
            if (!$document) throw new \DomainException('Document not found.');
            $cached = DB::table('billing_documents')->where('type', $type)->where('document_id', $id)->first();
            if ($cached) return base64_decode($cached->pdf_base64, true);
            $items = $type === 'invoices' ? DB::table('billing_invoice_items')->where('invoice_id', $id)->orderBy('position')->get()->all()
                : json_decode($document->items, false, 512, JSON_THROW_ON_ERROR);
            // The original issued document is independent of subsequent settlement status.
            if ($document->number) $document->status = 'issued';
            $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
            $options->setChroot(public_path('assets/lte'));
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('billing::pdf', compact('type', 'document', 'items'))->render());
            $pdf->setPaper('A4');
            $pdf->render();
            $bytes = $pdf->output();
            if ($document->number) DB::table('billing_documents')->insert([
                'type' => $type, 'document_id' => $id, 'pdf_base64' => base64_encode($bytes),
                'sha256' => hash('sha256', $bytes), 'created_at' => now(),
            ]);
            return $bytes;
        }, 3);
    }
}
