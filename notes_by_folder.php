<?php
header('Content-Type: application/json; charset=utf-8');
$folder = isset($_GET['folder']) ? intval($_GET['folder']) : null;

$host='127.0.0.1'; $db='notebook'; $user='root'; $pass=''; $charset='utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  if ($folder) {
    $stmt = $pdo->prepare("SELECT id, title, content, folder_id FROM notes WHERE folder_id = ? ORDER BY id DESC");
    $stmt->execute([$folder]);
  } else {
    $stmt = $pdo->query("SELECT id, title, content, folder_id FROM notes ORDER BY id DESC");
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'notes'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>