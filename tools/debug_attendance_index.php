<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/attendance/records', 'GET', [
    'date' => '2026-01-12',
    'per_page' => 20,
    'page' => 1,
    'sort' => 'name',
    'dir' => 'asc',
]);

$controller = $app->make(App\Http\Controllers\AttendanceController::class);
$response = $controller->index($request);

echo 'status=' . $response->getStatusCode() . PHP_EOL;
$content = (string) $response->getContent();
echo 'body_prefix=' . substr($content, 0, 300) . PHP_EOL;

