<?php
// =============================================
// plan_action.php - シミュレーション操作の処理
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$user_id  = $_SESSION['user_id'];
$action   = $_POST['action']   ?? '';
$field_id = (int)($_POST['field_id'] ?? 0);
$year     = (int)($_POST['year']     ?? date('Y'));

// 自分の畑か確認
$stmt = $pdo->prepare('SELECT id FROM fields WHERE id = ? AND user_id = ?');
$stmt->execute([$field_id, $user_id]);
if (!$stmt->fetch()) {
    header('Location: plan.php');
    exit;
}

// ---- 計画を追加 ----
if ($action === 'plan') {
    $plot_id      = (int)($_POST['plot_id']      ?? 0);
    $vegetable_id = (int)($_POST['vegetable_id'] ?? 0);
    $quantity     = max(1, min(99, (int)($_POST['quantity'] ?? 1)));

    if ($plot_id && $vegetable_id) {
        $stmt = $pdo->prepare('
            INSERT INTO plot_seasons (plot_id, vegetable_id, quantity, year, mode, status)
            VALUES (?, ?, ?, ?, "plan", "planned")
        ');
        $stmt->execute([$plot_id, $vegetable_id, $quantity, $year]);
    }
}

// ---- 計画を削除 ----
if ($action === 'remove') {
    $season_id = (int)($_POST['season_id'] ?? 0);
    if ($season_id) {
        $stmt = $pdo->prepare('DELETE FROM plot_seasons WHERE id = ? AND mode = "plan"');
        $stmt->execute([$season_id]);
    }
}

header('Location: plan.php?id=' . $field_id . '&year=' . $year);
exit;
