<?php
file_put_contents('c:/Users/G5-00009/PAYROLL_SYSTEM/storage/logs/mysql_debug.txt', '');

function out($msg) {
    file_put_contents('c:/Users/G5-00009/PAYROLL_SYSTEM/storage/logs/mysql_debug.txt', $msg . "\n", FILE_APPEND);
    echo $msg . "\n";
}

out('Connecting...');
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=payroll_system', 'root', '', [
        PDO::ATTR_TIMEOUT => 3,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    out('Connected OK');
} catch (Exception $e) {
    out('Connect FAILED: ' . $e->getMessage());
    exit;
}

out('Running SHOW PROCESSLIST...');
foreach ($pdo->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if ($r['Command'] !== 'Sleep' || $r['Time'] > 10) {
        out("  ID:{$r['Id']} User:{$r['User']} DB:{$r['db']} Cmd:{$r['Command']} Time:{$r['Time']}s State:{$r['State']} Info:" . substr((string)$r['Info'], 0, 100));
    }
}

out('Running metadata locks check...');
$rows = $pdo->query("SELECT OBJECT_NAME, LOCK_TYPE, LOCK_STATUS, OWNER_THREAD_ID FROM performance_schema.metadata_locks WHERE OBJECT_TYPE='TABLE' AND OBJECT_SCHEMA='payroll_system'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    out('  No metadata locks');
} else {
    foreach ($rows as $r) {
        out("  Table:{$r['OBJECT_NAME']} Lock:{$r['LOCK_TYPE']} Status:{$r['LOCK_STATUS']} Thread:{$r['OWNER_THREAD_ID']}");
    }
}

out('Quick SELECT from users...');
$start = microtime(true);
try {
    $stmt = $pdo->query('SELECT id, name FROM users LIMIT 3');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $elapsed = round((microtime(true) - $start) * 1000);
    out("  OK in {$elapsed}ms: " . json_encode($rows));
} catch (Exception $e) {
    out('  FAILED: ' . $e->getMessage());
}

out('Done.');
