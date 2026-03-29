<?php
// ① .envの値を環境変数から取得
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'farmplan';
$user = getenv('DB_USER') ?: 'farmplan_user';
$pass = getenv('DB_PASS') ?: '';

// ② PDOでDBに接続
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // エラーを例外として投げる
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // 取得結果を連想配列で返す
        PDO::ATTR_EMULATE_PREPARES   => false,                   // プリペアドステートメントを本物にする
    ]);

} catch (PDOException $e) {
    // ③ 接続失敗時：エラー詳細を外部に漏らさない
    error_log('DB接続エラー: ' . $e->getMessage());
    http_response_code(500);
    exit('データベースに接続できませんでした。');
}
