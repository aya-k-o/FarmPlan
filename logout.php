<?php
// =============================================
// logout.php - ログアウト
// =============================================

session_start();

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
