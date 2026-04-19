<?php
// =============================================
// task_action.php - タスクの追加・完了処理
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

// ---- タスクを追加 ----
if ($action === 'add') {
    $title    = trim($_POST['title']    ?? '');
    $due_date = trim($_POST['due_date'] ?? '');

    if ($title !== '') {
        $stmt = $pdo->prepare('INSERT INTO tasks (user_id, title, due_date) VALUES (?, ?, ?)');
        $stmt->execute([$user_id, $title, $due_date ?: null]);
    }
}

// ---- タスクを完了（削除） ----
if ($action === 'complete') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    if ($task_id) {
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$task_id, $user_id]);
    }
}

header('Location: index.php');
exit;
