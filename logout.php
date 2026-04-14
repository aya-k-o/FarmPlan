<?php
// =============================================
// logout.php - ログアウト
// =============================================

session_start();

// セッションを完全に破棄する
$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
