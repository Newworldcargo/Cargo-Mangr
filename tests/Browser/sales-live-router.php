<?php

// Loopback-only UAT runner. Never register this router in the production web server.
$database = getenv('NWC_SALES_TEST_DB');
if (getenv('APP_ENV') !== 'testing' || !preg_match('~^/tmp/nwc-sales-uat-[a-f0-9]+/database.sqlite$~', $database ?: '') || !is_file($database)) {
    http_response_code(403);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/')) {
    $file = realpath(__DIR__.'/../../public'.$path);
    $root = realpath(__DIR__.'/../../public/assets').DIRECTORY_SEPARATOR;
    if ($file && str_starts_with($file, $root) && is_file($file)) {
        $types = ['js'=>'application/javascript','css'=>'text/css','png'=>'image/png','jpg'=>'image/jpeg','svg'=>'image/svg+xml','woff'=>'font/woff','woff2'=>'font/woff2'];
        header('Content-Type: '.($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        readfile($file);
    } else {
        http_response_code(404);
    }
    exit;
}
if (!str_contains($path, '/admin/sales') && $path !== '/livewire/livewire.js') {
    http_response_code(404);
    exit;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
config([
    'database.default'=>'sqlite', 'database.connections.sqlite.database'=>$database,
    'session.driver'=>'file', 'session.files'=>dirname($database).'/sessions',
    'session.secure'=>false, 'session.domain'=>null, 'session.cookie'=>'sales_uat',
    'cache.default'=>'array', 'mail.default'=>'array',
    'app.url'=>'http://'.$_SERVER['HTTP_HOST'],
]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Queue::fake();
Illuminate\Support\Facades\Mail::fake();
Illuminate\Support\Facades\Auth::guard()->setUser(App\Models\User::where('email', 'sales@test.example')->firstOrFail());
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
