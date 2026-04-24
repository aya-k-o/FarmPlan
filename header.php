<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? 'FarmPlan', ENT_QUOTES, 'UTF-8') ?> - FarmPlan</title>
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
    <a href="index.php" <?= ($active_nav ?? '') === 'home'     ? 'class="active"' : '' ?>>ホーム</a>
    <a href="field.php" <?= ($active_nav ?? '') === 'field'    ? 'class="active"' : '' ?>>畑マップ</a>
    <a href="plan.php"  <?= ($active_nav ?? '') === 'plan'     ? 'class="active"' : '' ?>>シミュレーション</a>
    <a href="history.php" <?= ($active_nav ?? '') === 'history'  ? 'class="active"' : '' ?>>栽培記録</a>
    <a href="settings.php" <?= ($active_nav ?? '') === 'settings' ? 'class="active"' : '' ?>>設定</a>
  </nav>
  <a href="logout.php" class="nav-logout">ログアウト</a>
</header>
