<?php
// =============================================
// field_delete.php - 畑の削除
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$user_id  = $_SESSION['user_id'];
$field_id = (int)($_POST['field_id'] ?? 0);

// 自分の畑か確認してから削除
$stmt = $pdo->prepare('SELECT id FROM fields WHERE id = ? AND user_id = ?');
$stmt->execute([$field_id, $user_id]);

if ($stmt->fetch()) {
    // ON DELETE CASCADE により plots・plot_seasons も連動削除される
    $stmt = $pdo->prepare('DELETE FROM fields WHERE id = ?');
    $stmt->execute([$field_id]);
}

header('Location: field.php');
exit;
