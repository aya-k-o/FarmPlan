<?php
// =============================================
// plot_clear.php - 栽培記録のリセット
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$user_id  = $_SESSION['user_id'];
$field_id = (int)($_POST['field_id'] ?? 0);
$target   = $_POST['target'] ?? 'all'; // all or season

// 自分の畑か確認
$stmt = $pdo->prepare('SELECT id FROM fields WHERE id = ? AND user_id = ?');
$stmt->execute([$field_id, $user_id]);
if (!$stmt->fetch()) {
    header('Location: field.php');
    exit;
}

if ($target === 'all') {
    // 畑の全栽培記録を削除
    $stmt = $pdo->prepare('
        DELETE ps FROM plot_seasons ps
        JOIN plots p ON p.id = ps.plot_id
        WHERE p.field_id = ?
    ');
    $stmt->execute([$field_id]);
} elseif ($target === 'season') {
    // 今年の栽培記録のみ削除
    $stmt = $pdo->prepare('
        DELETE ps FROM plot_seasons ps
        JOIN plots p ON p.id = ps.plot_id
        WHERE p.field_id = ?
          AND ps.year = YEAR(NOW())
    ');
    $stmt->execute([$field_id]);
}

header('Location: field.php?id=' . $field_id);
exit;
