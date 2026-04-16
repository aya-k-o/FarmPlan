<?php
// =============================================
// history_delete.php - 栽培記録の一括削除
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$user_id    = $_SESSION['user_id'];
$season_ids = $_POST['season_ids'] ?? [];

// 絞り込み条件を引き継ぐ（削除後に同じ画面に戻る）
$year   = (int)($_POST['year']   ?? date('Y'));
$status = $_POST['status'] ?? '';
$family = $_POST['family'] ?? '';

if (!empty($season_ids)) {
    foreach ($season_ids as $season_id) {
        $season_id = (int)$season_id;

        // 自分の畑の記録か確認してから削除
        $stmt = $pdo->prepare('
            SELECT ps.id
            FROM plot_seasons ps
            JOIN plots p  ON p.id  = ps.plot_id
            JOIN fields f ON f.id  = p.field_id
            WHERE ps.id = ? AND f.user_id = ?
        ');
        $stmt->execute([$season_id, $user_id]);

        if ($stmt->fetch()) {
            $stmt = $pdo->prepare('DELETE FROM plot_seasons WHERE id = ?');
            $stmt->execute([$season_id]);
        }
    }
}

// 絞り込み条件を維持したまま戻る
$query = http_build_query([
    'year'   => $year,
    'status' => $status,
    'family' => $family,
]);
header('Location: history.php?' . $query);
exit;
