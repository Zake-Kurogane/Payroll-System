<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$db = $app->make('db');

$invalid = $db->table('attendance_records as ar')
  ->join('employees as e','e.id','=','ar.employee_id')
  ->whereNotNull('e.date_hired')
  ->whereColumn('ar.date','<','e.date_hired')
  ->count();

echo "invalid_before_hire=".$invalid.PHP_EOL;

$ana = $db->table('employees')->where('emp_no','0001')->first(['id','emp_no','first_name','last_name','assignment_type','area_place','based_location']);
if ($ana) {
  echo "anastasia_before=".$ana->assignment_type."|".$ana->area_place."|".$ana->based_location.PHP_EOL;
}
