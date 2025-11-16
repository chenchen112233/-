<?php
header('Content-Type: application/json; charset=utf-8');

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'annotations';
if (!is_dir($dir)) @mkdir($dir, 0777, true);
$fn = $dir . DIRECTORY_SEPARATOR . 'timer_lock.json';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? 'status');
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

function loadLock($fn){
    if (!file_exists($fn)) return null;
    $s = @file_get_contents($fn);
    $j = $s ? json_decode($s, true) : null;
    return is_array($j) ? $j : null;
}
function saveLock($fn, $data){
    return file_put_contents($fn, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) !== false;
}
function removeLock($fn){ if (file_exists($fn)) @unlink($fn); return true; }

$lock = loadLock($fn);
$now = time();

if ($action === 'lock' && ($method === 'POST' || $method === 'GET')) {
    $duration = intval($_REQUEST['duration'] ?? 0);
    if ($duration <= 0) $duration = 25*60;
    // 若有鎖且未過期且非同 IP，拒絕
    if ($lock && !empty($lock['expires_at']) && $lock['expires_at'] > $now && ($lock['owner_ip'] ?? '') !== $clientIp) {
        echo json_encode(['ok'=>false,'error'=>'locked','owner_ip'=>$lock['owner_ip'],'expires_at'=>$lock['expires_at'],'owner'=>false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // 建立或更新鎖（owner 為 current IP）
    $new = ['owner_ip'=>$clientIp, 'ts'=>$now, 'expires_at'=>$now + $duration];
    if (saveLock($fn, $new)) {
        echo json_encode(['ok'=>true,'action'=>'locked','owner'=>true,'expires_at'=>$new['expires_at']], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok'=>false,'error'=>'write_failed'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'unlock' && ($method === 'POST' || $method === 'GET')) {
    // 只允許擁有者解除（或若鎖過期則移除）
    if (!$lock) { echo json_encode(['ok'=>true,'action'=>'unlocked','note'=>'no_lock'], JSON_UNESCAPED_UNICODE); exit; }
    if (($lock['owner_ip'] ?? '') === $clientIp || ($lock['expires_at'] ?? 0) <= $now) {
        removeLock($fn);
        echo json_encode(['ok'=>true,'action'=>'unlocked'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok'=>false,'error'=>'not_owner','owner_ip'=>$lock['owner_ip']], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// status
if ($lock && ($lock['expires_at'] ?? 0) > $now) {
    $owner = ($lock['owner_ip'] ?? '') === $clientIp;
    echo json_encode(['ok'=>true,'locked'=>true,'owner'=>$owner,'owner_ip'=>$lock['owner_ip'],'expires_at'=>$lock['expires_at']], JSON_UNESCAPED_UNICODE);
} else {
    // 無有效鎖（自動視為 unlocked）
    if (file_exists($fn)) { @unlink($fn); }
    echo json_encode(['ok'=>true,'locked'=>false,'owner'=>false], JSON_UNESCAPED_UNICODE);
}
?>