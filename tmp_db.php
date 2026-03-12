<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

$updates = [
    'Aura fortune g5 traders corporation' => 'Aura Fortune G5 Traders Corporation',
    'Buena gold trading' => 'Buena Gold Trading',
    'Davao gold trading' => 'Davao Gold Trading',
    'Grnscor gold trading' => 'Grnscor Gold Trading',
    'Nab gold trading' => 'Nab Gold Trading',
    'Ruby gold buying and general merchandise' => 'Ruby Gold Buying And General Merchandise',
    'South c gold trading' => 'South C Gold Trading',
    'Twelve hours gold trading' => 'Twelve Hours Gold Trading',
];

foreach ($updates as $from => $to) {
    DB::table('area_places')
        ->whereRaw('LOWER(label) = ?', [strtolower($from)])
        ->update(['label' => $to]);
}

\Illuminate\Support\Facades\Cache::forget('employees.area_places');
\Illuminate\Support\Facades\Cache::forget('employees.area_places_grouped');

echo "Updated labels:\n";
$rows = DB::table('area_places')->orderBy('parent_assignment')->orderBy('sort_order')->get(['parent_assignment','label']);
foreach ($rows as $r) {
    echo ($r->parent_assignment ?? 'NULL'), "\t", $r->label, PHP_EOL;
}
