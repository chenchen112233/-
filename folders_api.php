<?php
ini_set('display_errors',0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$mysqli = new mysqli('127.0.0.1', 'root', '', 'notebook');
if ($mysqli->connect_errno) {
  echo json_encode(['ok'=>false,'error'=>'db_connect']);
  exit;
}
$mysqli->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $res = $mysqli->query("SELECT id,name,created_at FROM note_folders ORDER BY id DESC");
  $rows = [];
  if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $res->free();
  }
  echo json_encode(['ok'=>true,'folders'=>$rows], JSON_UNESCAPED_UNICODE);
  $mysqli->close();
  exit;
}

// POST: create or delete
$action = $data['action'] ?? 'create';
if ($action === 'create') {
  $name = trim($data['name'] ?? '');
  if ($name === '') { echo json_encode(['ok'=>false,'error'=>'empty_name']); exit; }
  $stmt = $mysqli->prepare("INSERT INTO note_folders (name, created_at) VALUES (?, NOW())");
  if (!$stmt) { echo json_encode(['ok'=>false,'error'=>'prepare_failed']); exit; }
  $stmt->bind_param('s', $name);
  $stmt->execute();
  $id = $stmt->insert_id;
  $stmt->close();
  echo json_encode(['ok'=>true,'id'=>$id,'name'=>$name], JSON_UNESCAPED_UNICODE);
  $mysqli->close();
  exit;
} elseif ($action === 'delete') {
  $id = intval($data['id'] ?? 0);
  if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
  $stmt = $mysqli->prepare("DELETE FROM note_folders WHERE id = ?");
  if (!$stmt) { echo json_encode(['ok'=>false,'error'=>'prepare_failed']); exit; }
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $ok = $stmt->affected_rows >= 0;
  $stmt->close();
  echo json_encode(['ok'=>$ok], JSON_UNESCAPED_UNICODE);
  $mysqli->close();
  exit;
}

echo json_encode(['ok'=>false,'error'=>'unsupported_method']);
$mysqli->close();
?>