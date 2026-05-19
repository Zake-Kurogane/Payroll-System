<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$db = $app->make('db');

foreach (['0016','0037'] as $empNo) {
  $e = $db->table('employees as e')
    ->leftJoin('employment_statuses as es','es.id','=','e.employment_status_id')
    ->where('e.emp_no',$empNo)
    ->first(['e.emp_no','e.first_name','e.last_name','e.status as status_text','e.employment_status_id','es.label as employment_label']);
  if ($e) {
    echo $e->emp_no.' | '.$e->last_name.', '.$e->first_name.' | status_text='.$e->status_text.' | employment_label='.$e->employment_label.PHP_EOL;
  }
}
