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

// ---- 未完了タスクを取得（期限あり優先・期限順）----
$stmt = $pdo->prepare('
    SELECT id, title, due_date
    FROM tasks
    WHERE user_id = ? AND done = 0
    ORDER BY ISNULL(due_date), due_date ASC, created_at ASC
');
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();

// ---- 昨年の今頃（±14日）の完了タスクを取得 ----
$stmt = $pdo->prepare('
    SELECT title, DATE_FORMAT(done_at, "%m/%d") AS done_date
    FROM tasks
    WHERE user_id = ?
      AND done = 1
      AND done_at BETWEEN
        DATE_SUB(DATE_SUB(NOW(), INTERVAL 1 YEAR), INTERVAL 14 DAY) AND
        DATE_ADD(DATE_SUB(NOW(), INTERVAL 1 YEAR), INTERVAL 14 DAY)
    ORDER BY done_at ASC
');
$stmt->execute([$user_id]);
$last_year_tasks = $stmt->fetchAll();

$page_title = 'ホーム';
$active_nav = 'home';
require 'header.php';
?>

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

  <!-- タスク -->
  <div class="section">
    <h2 class="section-title">農作業タスク</h2>

    <!-- タスク追加フォーム -->
    <form method="post" action="task_action.php" class="task-add-form">
      <input type="hidden" name="action" value="add">
      <input
        class="form-input task-add-input"
        type="text"
        name="title"
        placeholder="タスクを入力（例：防虫網設置、土寄せ）"
        required
      >
      <input
        class="form-input task-add-date"
        type="date"
        name="due_date"
      >
      <button class="btn-primary task-add-btn" type="submit">追加</button>
    </form>

    <?php if (empty($tasks)): ?>
      <div class="empty-state">
        <p>タスクはありません。</p>
      </div>
    <?php else: ?>
      <ul class="task-list">
        <?php foreach ($tasks as $task):
          $overdue = $task['due_date'] && $task['due_date'] < date('Y-m-d');
        ?>
          <li class="task-item <?= $overdue ? 'task-overdue' : '' ?>">
            <div class="task-info">
              <span class="task-title"><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if ($task['due_date']): ?>
                <span class="task-due"><?= htmlspecialchars($task['due_date'], ENT_QUOTES, 'UTF-8') ?>まで</span>
              <?php endif; ?>
            </div>
            <form method="post" action="task_action.php">
              <input type="hidden" name="action"  value="complete">
              <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
              <button class="btn-task-complete" type="submit">完了</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- 昨年の今頃のタスク -->
  <?php if (!empty($last_year_tasks)): ?>
  <div class="section">
    <h2 class="section-title">昨年の今頃の作業</h2>
    <p class="page-subtitle" style="margin-bottom:12px;">昨年の同じ時期（±2週間）に完了したタスクです</p>
    <ul class="task-list">
      <?php foreach ($last_year_tasks as $task): ?>
        <li class="task-item task-last-year">
          <div class="task-info">
            <span class="task-title"><?= htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="task-due">昨年 <?= htmlspecialchars($task['done_date'], ENT_QUOTES, 'UTF-8') ?> に完了</span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

</main>

<?php require 'footer.php'; ?>
