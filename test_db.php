<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db = 'notebook';

$mysqli = @new mysqli($host,$user,$pass,$db);
if ($mysqli->connect_errno) {
  echo json_encode(['ok'=>false,'db_connect_error'=>$mysqli->connect_error], JSON_UNESCAPED_UNICODE);
  exit;
}
$mysqli->set_charset('utf8mb4');
$res = $mysqli->query("SELECT 1 AS ok");
$ok = ($res && $res->fetch_assoc()['ok']==1);
$mysqli->close();
echo json_encode(['ok'=>$ok,'host'=>$host,'user'=>$user,'db'=>$db], JSON_UNESCAPED_UNICODE);
?>