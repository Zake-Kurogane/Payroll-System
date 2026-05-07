<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=payroll_system', 'root', '', [
    PDO::ATTR_TIMEOUT => 3,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "=== PROCESSLIST ===\n";
foreach ($pdo->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ID:{$r['Id']} User:{$r['User']} State:{$r['State']} Command:{$r['Command']} Time:{$r['Time']}s Info:" . substr($r['Info'] ?? '', 0, 80) . "\n";
}

echo "\n=== METADATA LOCKS ===\n";
$sql = "SELECT * FROM performance_schema.metadata_locks WHERE OBJECT_TYPE='TABLE' AND OBJECT_SCHEMA='payroll_system'";
foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Table:{$r['OBJECT_NAME']} Lock:{$r['LOCK_TYPE']} Status:{$r['LOCK_STATUS']} Owner:{$r['OWNER_THREAD_ID']}\n";
}

echo "\n=== INNODB LOCKS ===\n";
$sql2 = "SELECT * FROM information_schema.INNODB_LOCKS";
try {
    foreach ($pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo print_r($r, true) . "\n";
    }
} catch (Exception $e) {
    echo "No InnoDB locks (or table unavailable): " . $e->getMessage() . "\n";
}

echo "done\n";
