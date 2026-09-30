<?php
require '/home/newworldcargo/web/admin.newworldcargo.com/public_html/vendor/autoload.php';
$app = require '/home/newworldcargo/web/admin.newworldcargo.com/public_html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$source = file_get_contents(base_path('Modules/Cargo/Resources/views/adminLte/pages/shipments/show.blade.php'));
$start = strpos($source, '<div class="modal fade" id="markPaidModal"');
$end = strpos($source, '<!-- Add Refund Modal -->', $start);
$source = substr($source, $start, $end - $start);
$shipment = (object) ['id' => 41533];
$paymentCurrency = 'ZMW'; $viewerCurrencyLocation = 'Lusaka'; $baseUsdAmount = 40;
$paymentSymbol = 'K'; $totalAmount = 1000; $selectedPaymentMethod = '';
$paymentMethodOptions = ['cash_payment' => 'Cash Payment', 'bank_transfer' => 'Bank Transfer', 'airtel' => 'Airtel Mobile Money', 'mtn' => 'MTN Mobile Money'];
$__env = app('view');
ob_start(); eval('?>' . app('blade.compiler')->compileString($source)); $html = ob_get_clean();
file_put_contents('/tmp/nwc-full-payment-modal.html', $html);
echo 'Rendered full modal fixture.';
