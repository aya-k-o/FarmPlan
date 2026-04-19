<?php
// =============================================
// login.php - ログイン
// =============================================

session_start();

// すでにログイン済みならホームへ
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require 'db_connect.php';

$error = '';

// ---- POSTされたとき処理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // 空欄チェック
    if ($email === '' || $password === '') {
        $error = 'ログインIDとパスワードを入力してください。';
    } else {
        // ログインIDでユーザーを検索
        $stmt = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // ユーザーが存在し、パスワードが一致するか確認
        if ($user && password_verify($password, $user['password_hash'])) {
            // セッション固定攻撃対策：ログイン後にセッションIDを再生成
            session_regenerate_id(true);

            // セッションにユーザー情報を保存
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            header('Location: index.php');
            exit;
        } else {
            // 存在しないメールとパスワード不一致を同じメッセージにする（ユーザー列挙攻撃対策）
            $error = 'ログインIDまたはパスワードが正しくありません。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ログイン - FarmPlan</title>
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
    <h1 class="auth-title">ログイン</h1>
    <p class="auth-subtitle">FarmPlanへようこそ</p>

    <?php if (isset($_GET['registered'])): ?>
      <div class="alert alert-success">
        登録が完了しました。ログインしてください。
      </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php">

      <div class="form-group">
        <label class="form-label" for="email">ログインID</label>
        <input
          class="form-input"
          type="text"
          id="email"
          name="email"
          value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          placeholder="ログインIDを入力"
          autocomplete="username"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">パスワード</label>
        <input
          class="form-input"
          type="password"
          id="password"
          name="password"
          placeholder="パスワードを入力"
          autocomplete="current-password"
        >
      </div>

      <button class="btn-primary" type="submit">ログイン</button>
    </form>

    <div class="auth-link">
      アカウントをお持ちでない方は <a href="register.php">新規登録</a>
    </div>
  </div>
</div>

</body>
</html>
