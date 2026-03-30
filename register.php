<?php
// =============================================
// register.php - 新規ユーザー登録
// =============================================

require 'db_connect.php';

$errors  = [];
$success = false;

// ---- POSTされたとき処理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 入力値を取得（trim：前後の空白を除去）
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    // ---- バリデーション ----

    // 空欄チェック
    if ($name === '')     $errors[] = 'ユーザー名を入力してください。';
    if ($email === '')    $errors[] = 'メールアドレスを入力してください。';
    if ($password === '') $errors[] = 'パスワードを入力してください。';

    // メール形式チェック
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'メールアドレスの形式が正しくありません。';
    }

    // パスワード長チェック
    if ($password !== '' && mb_strlen($password) < 8) {
        $errors[] = 'パスワードは8文字以上で入力してください。';
    }

    // パスワード一致チェック
    if ($password !== '' && $password !== $confirm) {
        $errors[] = 'パスワードが一致しません。';
    }

    // 重複メールチェック（バリデーション通過後のみDB確認）
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'このメールアドレスはすでに登録されています。';
        }
    }

    // ---- エラーなしなら登録 ----
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $hash]);

        // 登録成功 → ログインページへリダイレクト
        header('Location: login.php?registered=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>新規登録 - FarmPlan</title>
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
</header>

<div class="auth-wrapper">
  <div class="auth-card">
    <h1 class="auth-title">新規登録</h1>
    <p class="auth-subtitle">アカウントを作成して畑の計画を始めましょう</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="register.php">

      <div class="form-group">
        <label class="form-label" for="name">ユーザー名</label>
        <input
          class="form-input <?= !empty($errors) && $_POST['name'] === '' ? 'is-error' : '' ?>"
          type="text"
          id="name"
          name="name"
          value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          placeholder="例：田中 太郎"
          autocomplete="name"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="email">メールアドレス</label>
        <input
          class="form-input"
          type="email"
          id="email"
          name="email"
          value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          placeholder="例：taro@example.com"
          autocomplete="email"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">パスワード（8文字以上）</label>
        <input
          class="form-input"
          type="password"
          id="password"
          name="password"
          placeholder="8文字以上で入力"
          autocomplete="new-password"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="confirm">パスワード（確認）</label>
        <input
          class="form-input"
          type="password"
          id="confirm"
          name="confirm"
          placeholder="もう一度入力"
          autocomplete="new-password"
        >
      </div>

      <button class="btn-primary" type="submit">登録する</button>
    </form>

    <div class="auth-link">
      すでにアカウントをお持ちの方は <a href="login.php">ログイン</a>
    </div>
  </div>
</div>

</body>
</html>
