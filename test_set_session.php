<?php
header('Content-Type: text/plain; charset=utf-8');

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
$_SESSION['username'] = 'demo_user';
echo "已設定 session username = demo_user\nsession_id: " . session_id() . "\n";
echo "請回到首頁（用相同 host/port）檢查右上角是否變更。";