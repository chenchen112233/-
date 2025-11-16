<?php
header('Content-Type: application/json; charset=utf-8');

// 以請求的 host 動態設定 session cookie，避免 localhost / IP 不一致問題
$host = $_SERVER['HTTP_HOST'] ?? '';
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $host,
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

if (!empty($_SESSION['username'])) {
    echo json_encode(['ok' => true, 'username' => $_SESSION['username']], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
}