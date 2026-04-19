<?php
// =============================================
// settings.php - 設定（ユーザー情報・パスワード変更）
// =============================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';
require 'functions.php';

$user_id = $_SESSION['user_id'];
$errors  = [];
$success = '';

// 現在のユーザー情報を取得
$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// ---- ユーザー情報の更新 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ---- 名前・メール変更 ----
    if ($_POST['action'] === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '')  $errors[] = 'ユーザー名を入力してください。';
        if ($email === '') $errors[] = 'ログインIDを入力してください。';
        if ($email !== '' && mb_strlen($email) < 3) {
            $errors[] = 'ログインIDは3文字以上で入力してください。';
        }

        // 他のユーザーが同じログインIDを使っていないか確認
        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'このログインIDはすでに使用されています。';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $stmt->execute([$name, $email, $user_id]);

            // セッションの名前も更新
            $_SESSION['user_name'] = $name;
            $user['name']  = $name;
            $user['email'] = $email;
            $success = 'プロフィールを更新しました。';
        }
    }

    // ---- パスワード変更 ----
    if ($_POST['action'] === 'update_password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if ($current === '') $errors[] = '現在のパスワードを入力してください。';
        if ($new === '')     $errors[] = '新しいパスワードを入力してください。';
        if ($new !== '' && mb_strlen($new) < 8) {
            $errors[] = '新しいパスワードは8文字以上で入力してください。';
        }
        if ($new !== $confirm) $errors[] = '新しいパスワードが一致しません。';

        if (empty($errors)) {
            // 現在のパスワードを確認
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $row = $stmt->fetch();

            if (!password_verify($current, $row['password_hash'])) {
                $errors[] = '現在のパスワードが正しくありません。';
            }
        }

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $user_id]);
            $success = 'パスワードを変更しました。';
        }
    }

    // ---- 野菜を追加 ----
    if ($_POST['action'] === 'add_vegetable') {
        $veg_name = trim($_POST['veg_name'] ?? '');
        $family   = trim($_POST['family']   ?? '');
        $variety  = trim($_POST['variety']  ?? '');

        if ($veg_name === '') $errors[] = '野菜名を入力してください。';
        if ($family === '')   $errors[] = '科を選択してください。';

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO vegetables (name, family, variety) VALUES (?, ?, ?)');
            $stmt->execute([$veg_name, $family, $variety ?: null]);
            $success = '「' . htmlspecialchars($veg_name, ENT_QUOTES, 'UTF-8') . '」を登録しました。';
        }
    }

    // ---- 野菜を削除 ----
    if ($_POST['action'] === 'delete_vegetable') {
        $veg_id = (int)($_POST['veg_id'] ?? 0);
        if ($veg_id) {
            // 栽培記録で使われていないか確認
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM plot_seasons WHERE vegetable_id = ?');
            $stmt->execute([$veg_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'この野菜は栽培記録で使用中のため削除できません。';
            } else {
                $stmt = $pdo->prepare('DELETE FROM vegetables WHERE id = ?');
                $stmt->execute([$veg_id]);
                $success = '野菜を削除しました。';
            }
        }
    }
}

// 野菜一覧を取得
$stmt = $pdo->prepare('SELECT id, name, family, variety FROM vegetables ORDER BY family, name');
$stmt->execute();
$vegetables = $stmt->fetchAll();

$families = ['ナス科', 'ウリ科', '根菜', '葉野菜', 'イモ類', 'マメ科'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>設定 - FarmPlan</title>
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
    <a href="field.php">畑マップ</a>
    <a href="plan.php">シミュレーション</a>
    <a href="history.php">栽培記録</a>
    <a href="settings.php" class="active">設定</a>
  </nav>
</header>

<main class="main-content">

  <div class="page-header">
    <h1 class="page-title">設定</h1>
    <p class="page-subtitle">ユーザー情報の確認・変更ができます</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="max-width:480px; margin-bottom:20px;">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success !== ''): ?>
    <div class="alert alert-success" style="max-width:480px; margin-bottom:20px;">
      <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <!-- プロフィール変更 -->
  <div class="section">
    <h2 class="section-title">プロフィール</h2>
    <div class="form-card">
      <form method="post" action="settings.php">
        <input type="hidden" name="action" value="update_profile">

        <div class="form-group">
          <label class="form-label" for="name">ユーザー名</label>
          <input
            class="form-input"
            type="text"
            id="name"
            name="name"
            value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="email">ログインID</label>
          <input
            class="form-input"
            type="text"
            id="email"
            name="email"
            value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>

        <button class="btn-primary" type="submit">変更を保存する</button>
      </form>
    </div>
  </div>

  <!-- パスワード変更 -->
  <div class="section">
    <h2 class="section-title">パスワード変更</h2>
    <div class="form-card">
      <form method="post" action="settings.php">
        <input type="hidden" name="action" value="update_password">

        <div class="form-group">
          <label class="form-label" for="current_password">現在のパスワード</label>
          <input
            class="form-input"
            type="password"
            id="current_password"
            name="current_password"
            placeholder="現在のパスワードを入力"
            autocomplete="current-password"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="new_password">新しいパスワード（8文字以上）</label>
          <input
            class="form-input"
            type="password"
            id="new_password"
            name="new_password"
            placeholder="新しいパスワードを入力"
            autocomplete="new-password"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">新しいパスワード（確認）</label>
          <input
            class="form-input"
            type="password"
            id="confirm_password"
            name="confirm_password"
            placeholder="もう一度入力"
            autocomplete="new-password"
          >
        </div>

        <button class="btn-primary" type="submit">パスワードを変更する</button>
      </form>
    </div>
  </div>

  <!-- 野菜マスタ管理 -->
  <div class="section">
    <h2 class="section-title">野菜の追加・管理</h2>

    <!-- 野菜追加フォーム -->
    <div class="form-card" style="margin-bottom:20px;">
      <form method="post" action="settings.php">
        <input type="hidden" name="action" value="add_vegetable">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="veg_name">野菜名</label>
            <input class="form-input" type="text" id="veg_name" name="veg_name"
                   placeholder="例：スイカ">
          </div>
          <div class="form-group">
            <label class="form-label" for="family">科</label>
            <select class="form-input" id="family" name="family">
              <option value="">-- 選択 --</option>
              <?php foreach ($families as $f): ?>
                <option value="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="variety">品種（任意）</label>
            <input class="form-input" type="text" id="variety" name="variety"
                   placeholder="例：大玉・小玉">
          </div>
        </div>
        <button class="btn-primary" type="submit" style="margin-top:4px;">追加する</button>
      </form>
    </div>

    <!-- 野菜一覧 -->
    <div class="history-table-wrap">
      <table class="history-table">
        <thead>
          <tr>
            <th>野菜名</th>
            <th>科</th>
            <th>品種</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vegetables as $v): ?>
            <tr>
              <td class="td-veg"><?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <span class="family-tag family-<?= familyClass($v['family']) ?>">
                  <?= htmlspecialchars($v['family'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td><?= htmlspecialchars($v['variety'] ?? '―', ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <form method="post" action="settings.php"
                      onsubmit="return confirm('「<?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?>」を削除しますか？')">
                  <input type="hidden" name="action" value="delete_vegetable">
                  <input type="hidden" name="veg_id" value="<?= $v['id'] ?>">
                  <button class="btn-delete-field" type="submit">削除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ログアウト -->
  <div class="section">
    <h2 class="section-title">ログアウト</h2>
    <div class="form-card">
      <p style="font-size:13px; color:var(--text-light); margin-bottom:16px;">
        ログアウトするとログイン画面に戻ります。
      </p>
      <a href="logout.php" class="btn-logout">ログアウト</a>
    </div>
  </div>

</main>

</body>
</html>
