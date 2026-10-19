<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $document->number ?: 'Draft' }} - New World Cargo</title>@include('billing::styles')</head><body>
<div class="no-print" style="max-width:1000px;margin:20px auto"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
@include('billing::document', ['originalDocument' => true])
</body></html>
