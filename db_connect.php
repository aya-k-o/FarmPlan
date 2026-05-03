<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'farmplan';
$user = getenv('DB_USER') ?: 'farmplan_user';
$pass = getenv('DB_PASS') ?: '';

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {
    // エラー詳細を外部に漏らさずログのみ記録
    error_log('DB接続エラー: ' . $e->getMessage());
    http_response_code(500);
    exit('データベースに接続できませんでした。');
}
