<?php
ini_set('display_errors',1);
ini_set('log_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
function resp($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || empty($data['folder'])) resp(['ok'=>false,'error'=>'invalid_request']);
$folder = trim($data['folder']);
$id = isset($data['id']) ? intval($data['id']) : null;
$src = trim($data['src'] ?? '');
$mode = strtolower(trim($data['mode'] ?? 'move')); // 預設 move

$mysqli = @new mysqli('127.0.0.1','root','','notebook');
if ($mysqli->connect_errno) resp(['ok'=>false,'error'=>'db_connect_failed','db_error'=>$mysqli->connect_error]);
$mysqli->set_charset('utf8mb4');

$row = null;
if ($id) {
    $st = $mysqli->prepare("SELECT id,path,pdf_file,page FROM screenshots WHERE id=? LIMIT 1");
    $st->bind_param('i',$id);
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc();
    $st->close();
} elseif ($src) {
    $st = $mysqli->prepare("SELECT id,path,pdf_file,page FROM screenshots WHERE path=? LIMIT 1");
    $st->bind_param('s',$src);
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc();
    $st->close();
}
if (!$row) { $mysqli->close(); resp(['ok'=>false,'error'=>'screenshot_not_found']); }

$curRel = $row['path'];

// 嘗試多種可能的實體路徑
$candidates = [];
// 1. 如果 path 看起來已是絕對路徑
if (preg_match('#^[A-Za-z]:[\\\\/]#', $curRel) || strpos($curRel, '/') === 0) {
    $candidates[] = $curRel;
}
// 2. __DIR__ + path
$candidates[] = __DIR__ . DIRECTORY_SEPARATOR . ltrim($curRel, '/\\');
// 3. 若 path 前面有 "uploads/" 或 "note/"，也嘗試在專案根目錄下解析
$parts = ['uploads','note'];
foreach ($parts as $p) {
    $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . $p . DIRECTORY_SEPARATOR . ltrim(str_replace(['uploads/','note/'],'',$curRel), '/\\');
}
// 4. 直接 realpath 嘗試（如果能resolve）
foreach ($candidates as $c) {
    $rp = realpath($c);
    if ($rp) $candidates[] = $rp;
}
// 去除重複
$candidates = array_values(array_unique($candidates));

$found = false;
$foundAbs = null;
$tried = [];
foreach ($candidates as $c) {
    $tried[] = $c;
    if (file_exists($c) && is_file($c)) { $found = true; $foundAbs = $c; break; }
}

// 若沒找到，回傳嘗試清單（方便你檢查 DB path）
if (!$found) {
    error_log("move_screenshot: source not found for db path={$curRel}; tried=" . implode(';', $tried));
    $mysqli->close();
    resp(['ok'=>false,'error'=>'source_file_not_found','db_path'=>$curRel,'tried_paths'=>$tried]);
}

// 以下與原本邏輯相同：建立 note 目錄並 move/copy
$baseNotePath = __DIR__ . DIRECTORY_SEPARATOR . 'note';
if (!is_dir($baseNotePath)) { if (!@mkdir($baseNotePath,0777,true)){ $mysqli->close(); resp(['ok'=>false,'error'=>'cannot_create_note_dir']); } }
$san = preg_replace('/[^\p{L}\p{N}_\-\s]/u','_',$folder);
$targetDir = $baseNotePath . DIRECTORY_SEPARATOR . $san;
if (!is_dir($targetDir)) { if (!@mkdir($targetDir,0777,true)){ $mysqli->close(); resp(['ok'=>false,'error'=>'mkdir_failed']); } }

$fname = basename($curRel);
$targetRel = 'note/' . $san . '/' . $fname;
$targetAbs = $targetDir . DIRECTORY_SEPARATOR . $fname;

if ($mode === 'move') {
    $mv = @rename($foundAbs, $targetAbs);
    if (!$mv) {
        if (!@copy($foundAbs, $targetAbs)) { $mysqli->close(); resp(['ok'=>false,'error'=>'move_failed','detail'=>'rename_copy_failed']); }
        @unlink($foundAbs);
    }
    $st2 = $mysqli->prepare("UPDATE screenshots SET path=? WHERE id=?");
    $st2->bind_param('si', $targetRel, $row['id']);
    $ok = $st2->execute();
    $st2->close();
    $mysqli->close();
    if ($ok) resp(['ok'=>true,'action'=>'moved','path'=>$targetRel]); else resp(['ok'=>false,'error'=>'db_update_failed']);
} else {
    if (!@copy($foundAbs, $targetAbs)) { $mysqli->close(); resp(['ok'=>false,'error'=>'copy_failed']); }
    $pdf_file = $row['pdf_file'] ?? null;
    $page = isset($row['page']) ? intval($row['page']) : 0;
    $stmt = $mysqli->prepare("INSERT INTO screenshots (pdf_file, page, path, created_at) VALUES (?, ?, ?, NOW())");
    if (!$stmt) { $err = $mysqli->error; $mysqli->close(); resp(['ok'=>false,'error'=>'db_prepare_failed','detail'=>$err]); }
    $stmt->bind_param('sis', $pdf_file, $page, $targetRel);
    $exec = $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();
    $mysqli->close();
    if ($exec) resp(['ok'=>true,'action'=>'copied','new_id'=>$newId,'path'=>$targetRel]); else resp(['ok'=>false,'error'=>'db_insert_failed']);
}
?>