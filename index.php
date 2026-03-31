<?php
// =============================================
// index.php - ホーム（ダッシュボード）
// =============================================

session_start();

// 未ログインならログインページへ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ---- 栽培中の区画数を取得 ----
$stmt = $pdo->prepare('
    SELECT COUNT(*) as cnt
    FROM plot_seasons ps
    JOIN plots p ON p.id = ps.plot_id
    JOIN fields f ON f.id = p.field_id
    WHERE f.user_id = ?
      AND ps.status = "growing"
      AND ps.mode = "actual"
');
$stmt->execute([$user_id]);
$growing_count = $stmt->fetch()['cnt'];

// ---- 計画済みの区画数を取得 ----
$stmt = $pdo->prepare('
    SELECT COUNT(*) as cnt
    FROM plot_seasons ps
    JOIN plots p ON p.id = ps.plot_id
    JOIN fields f ON f.id = p.field_id
    WHERE f.user_id = ?
      AND ps.status = "planned"
      AND ps.mode = "plan"
');
$stmt->execute([$user_id]);
$planned_count = $stmt->fetch()['cnt'];

// ---- 今季の収穫回数を取得 ----
$stmt = $pdo->prepare('
    SELECT COUNT(*) as cnt
    FROM harvests h
    JOIN plot_seasons ps ON ps.id = h.plot_season_id
    JOIN plots p ON p.id = ps.plot_id
    JOIN fields f ON f.id = p.field_id
    WHERE f.user_id = ?
      AND YEAR(h.harvested_at) = YEAR(NOW())
');
$stmt->execute([$user_id]);
$harvest_count = $stmt->fetch()['cnt'];

// ---- 直近の栽培記録を取得（5件）----
$stmt = $pdo->prepare('
    SELECT v.name AS veg_name, v.family, ps.status, ps.planted_at, f.name AS field_name
    FROM plot_seasons ps
    JOIN plots p ON p.id = ps.plot_id
    JOIN fields f ON f.id = p.field_id
    JOIN vegetables v ON v.id = ps.vegetable_id
    WHERE f.user_id = ?
      AND ps.mode = "actual"
    ORDER BY ps.created_at DESC
    LIMIT 5
');
$stmt->execute([$user_id]);
$recent_records = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ホーム - FarmPlan</title>
  <link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&family=Shippori+Mincho:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
  <a href="index.php" class="logo">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
      <path d="M12 22V12" stroke="#b8d89a" stroke-width="2" stroke-linecap="round"/>
      <path d="M12 12C12 12 7 10 5 5C9 4 13 6 14 10" fill="#5a8a45"/>
      <path d="M12 12C12 12 17 10 19 5C15 4 11 6 10 10" fill="#8ab870"/>
      <path d="M12 16C12 16 9 14 8 11C10 10.5 12.5 12 12 16Z" fill="#3a5a2e"/>
    </svg>
    Farm<span>Plan</span>
  </a>
  <nav>
    <a href="index.php" class="active">ホーム</a>
    <a href="field.php">畑マップ</a>
    <a href="plan.php">シミュレーション</a>
    <a href="history.php">栽培記録</a>
    <a href="settings.php">設定</a>
  </nav>
</header>

<main class="main-content">

  <div class="page-header">
    <h1 class="page-title">こんにちは、<?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?>さん</h1>
    <p class="page-subtitle">今日の畑の状況を確認しましょう</p>
  </div>

  <!-- 統計カード -->
  <div class="stats-row">
    <div class="stat-card growing">
      <div class="stat-label">栽培中</div>
      <div class="stat-number">
        <?= htmlspecialchars($growing_count, ENT_QUOTES, 'UTF-8') ?>
        <span class="stat-unit">区画</span>
      </div>
    </div>
    <div class="stat-card planned">
      <div class="stat-label">計画済み</div>
      <div class="stat-number">
        <?= htmlspecialchars($planned_count, ENT_QUOTES, 'UTF-8') ?>
        <span class="stat-unit">区画</span>
      </div>
    </div>
    <div class="stat-card harvested">
      <div class="stat-label">今季の収穫</div>
      <div class="stat-number">
        <?= htmlspecialchars($harvest_count, ENT_QUOTES, 'UTF-8') ?>
        <span class="stat-unit">回</span>
      </div>
    </div>
  </div>

  <!-- 直近の栽培記録 -->
  <div class="section">
    <h2 class="section-title">直近の栽培記録</h2>

    <?php if (empty($recent_records)): ?>
      <div class="empty-state">
        <p>まだ栽培記録がありません。</p>
        <a href="field.php" class="btn-link">畑マップから始める</a>
      </div>
    <?php else: ?>
      <div class="record-list">
        <?php foreach ($recent_records as $rec): ?>
          <div class="record-item">
            <div class="record-veg"><?= htmlspecialchars($rec['veg_name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="record-meta">
              <span class="record-family"><?= htmlspecialchars($rec['family'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="record-field"><?= htmlspecialchars($rec['field_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="status-badge status-<?= htmlspecialchars($rec['status'], ENT_QUOTES, 'UTF-8') ?>">
              <?php
              $status_labels = [
                  'planned'   => '計画済み',
                  'growing'   => '栽培中',
                  'harvested' => '収穫済み',
                  'failed'    => '失敗',
              ];
              echo htmlspecialchars($status_labels[$rec['status']] ?? $rec['status'], ENT_QUOTES, 'UTF-8');
              ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</main>

</body>
</html>
