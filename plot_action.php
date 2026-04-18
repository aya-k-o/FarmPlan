<?php
// =============================================
// plot_action.php - 区画操作の処理
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$user_id = $_SESSION['user_id'];

// ---- GETリクエスト：連作チェック（AJAX） ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action  = $_GET['action']  ?? '';
    $plot_id = (int)($_GET['plot_id'] ?? 0);
    $family  = $_GET['family']  ?? '';

    if ($action === 'check_rotation' && $plot_id && $family) {
        // 過去3年間に同じ科を栽培していたか確認
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as cnt
            FROM plot_seasons ps
            JOIN vegetables v ON v.id = ps.vegetable_id
            WHERE ps.plot_id = ?
              AND v.family   = ?
              AND ps.year    >= YEAR(NOW()) - 3
              AND ps.year    <  YEAR(NOW())
              AND ps.mode    = "actual"
        ');
        $stmt->execute([$plot_id, $family]);
        $result = $stmt->fetch();

        header('Content-Type: application/json');
        echo json_encode(['warning' => $result['cnt'] > 0]);
        exit;
    }

    exit;
}

// ---- POSTリクエスト：植え付け / 収穫 / 失敗 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $field_id = (int)($_POST['field_id'] ?? 0);

    // field_idが自分のものか確認
    $stmt = $pdo->prepare('SELECT id FROM fields WHERE id = ? AND user_id = ?');
    $stmt->execute([$field_id, $user_id]);
    if (!$stmt->fetch()) {
        header('Location: field.php');
        exit;
    }

    // ---- 植え付け ----
    if ($action === 'plant') {
        $plot_id      = (int)($_POST['plot_id']      ?? 0);
        $vegetable_id = (int)($_POST['vegetable_id'] ?? 0);
        $planted_at   = $_POST['planted_at'] ?? date('Y-m-d');
        $quantity     = max(1, min(99, (int)($_POST['quantity'] ?? 1)));

        if ($plot_id && $vegetable_id) {
            $stmt = $pdo->prepare('
                INSERT INTO plot_seasons (plot_id, vegetable_id, quantity, year, mode, status, planted_at)
                VALUES (?, ?, ?, YEAR(NOW()), "actual", "growing", ?)
            ');
            $stmt->execute([$plot_id, $vegetable_id, $quantity, $planted_at]);
        }
    }

    // ---- 収穫済み ----
    if ($action === 'harvest') {
        $season_id = (int)($_POST['season_id'] ?? 0);
        if ($season_id) {
            $stmt = $pdo->prepare('
                UPDATE plot_seasons
                SET status = "harvested", harvested_at = CURDATE()
                WHERE id = ?
            ');
            $stmt->execute([$season_id]);

            // 収穫記録にも追加
            $stmt = $pdo->prepare('
                INSERT INTO harvests (plot_season_id, harvested_at)
                VALUES (?, CURDATE())
            ');
            $stmt->execute([$season_id]);
        }
    }

    // ---- 失敗 ----
    if ($action === 'fail') {
        $season_id = (int)($_POST['season_id'] ?? 0);
        if ($season_id) {
            $stmt = $pdo->prepare('
                UPDATE plot_seasons SET status = "failed" WHERE id = ?
            ');
            $stmt->execute([$season_id]);
        }
    }

    header('Location: field.php?id=' . $field_id);
    exit;
}
