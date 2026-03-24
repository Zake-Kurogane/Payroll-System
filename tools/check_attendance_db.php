<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_DATABASE') ?: 'payroll_system';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "DB connect failed: {$e->getMessage()}\n");
    exit(1);
}

function scalar(PDO $pdo, string $sql): string
{
    $v = $pdo->query($sql)->fetchColumn();
    return $v === null ? 'NULL' : (string) $v;
}

echo "attendance_records count=" . scalar($pdo, 'select count(*) from attendance_records') . PHP_EOL;
echo "attendance_records latest_date=" . scalar($pdo, 'select max(date) from attendance_records') . PHP_EOL;
echo "attendance_records sample_date_counts (top 10):" . PHP_EOL;

$rows = $pdo->query('select date, count(*) as c from attendance_records group by date order by date desc limit 10')->fetchAll();
foreach ($rows as $r) {
    echo "  {$r['date']}  {$r['c']}" . PHP_EOL;
}

