<?php
// =============================================
// field_create.php - 畑の新規作成
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$errors = [];
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name      = trim($_POST['name']      ?? '');
    $grid_rows = (int)($_POST['grid_rows'] ?? 6);
    $grid_cols = (int)($_POST['grid_cols'] ?? 8);

    // バリデーション
    if ($name === '') $errors[] = '畑の名前を入力してください。';
    if ($grid_rows < 1 || $grid_rows > 20) $errors[] = '行数は1〜20で入力してください。';
    if ($grid_cols < 1 || $grid_cols > 20) $errors[] = '列数は1〜20で入力してください。';

    // 同名の畑が存在しないか確認
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM fields WHERE user_id = ? AND name = ?');
        $stmt->execute([$user_id, $name]);
        if ($stmt->fetch()) {
            $errors[] = '「' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '」という名前の畑はすでに登録されています。';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // fieldsテーブルに畑を登録
            $stmt = $pdo->prepare('INSERT INTO fields (user_id, name, grid_rows, grid_cols) VALUES (?, ?, ?, ?)');
            $stmt->execute([$user_id, $name, $grid_rows, $grid_cols]);
            $field_id = $pdo->lastInsertId();

            // plotsテーブルに全区画を生成（grid_rows × grid_cols 個）
            $stmt = $pdo->prepare('INSERT INTO plots (field_id, row_num, col_num) VALUES (?, ?, ?)');
            for ($r = 1; $r <= $grid_rows; $r++) {
                for ($c = 1; $c <= $grid_cols; $c++) {
                    $stmt->execute([$field_id, $r, $c]);
                }
            }

            $pdo->commit();
            header('Location: field.php?id=' . $field_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = '畑の作成に失敗しました。もう一度お試しください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>畑を作成 - FarmPlan</title>
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
    <a href="index.php">ホーム</a>
    <a href="field.php" class="active">畑マップ</a>
    <a href="plan.php">シミュレーション</a>
    <a href="history.php">栽培記録</a>
    <a href="settings.php">設定</a>
  </nav>
  <a href="logout.php" class="nav-logout">ログアウト</a>
</header>

<main class="main-content">
  <div class="page-header">
    <h1 class="page-title">畑を作成する</h1>
    <p class="page-subtitle">畑の名前とサイズを設定してください</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="form-card">
    <form method="post" action="field_create.php">

      <div class="form-group">
        <label class="form-label" for="name">畑の名前</label>
        <input
          class="form-input"
          type="text"
          id="name"
          name="name"
          value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          placeholder="例：自宅の畑、市民農園A区画"
        >
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="grid_rows">行数（縦・m）</label>
          <input
            class="form-input"
            type="number"
            id="grid_rows"
            name="grid_rows"
            value="<?= htmlspecialchars($_POST['grid_rows'] ?? '6', ENT_QUOTES, 'UTF-8') ?>"
            min="1" max="20"
          >
        </div>
        <div class="form-group">
          <label class="form-label" for="grid_cols">列数（横・m）</label>
          <input
            class="form-input"
            type="number"
            id="grid_cols"
            name="grid_cols"
            value="<?= htmlspecialchars($_POST['grid_cols'] ?? '8', ENT_QUOTES, 'UTF-8') ?>"
            min="1" max="20"
          >
        </div>
      </div>

      <p class="form-hint">※ 1マス＝1m² として計算します</p>

      <button class="btn-primary" type="submit">畑を作成する</button>
    </form>
  </div>
</main>

</body>
</html>
