<?php
// Debug temporarily: show detailed errors and DB/MYSQL errors
ini_set('display_errors',0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

function respond($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];
$content = trim($data['content'] ?? $data['note'] ?? '');
$title = trim($data['title'] ?? '');
$file = trim($data['file'] ?? '');
$page = isset($data['page']) ? intval($data['page']) : 0;

if ($content === '') {
    respond(['ok'=>false,'error'=>'empty_content']);
}

// DB settings (如需調整請修改)
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db = 'notebook';
$table = 'notes';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) respond(['ok'=>false,'error'=>'db_connect','detail'=>$mysqli->connect_error]);
$mysqli->set_charset('utf8mb4');

// 取得 notes 表欄位
$cols = [];
$q = $mysqli->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '". $mysqli->real_escape_string($db) ."' AND TABLE_NAME = '". $mysqli->real_escape_string($table) ."'");
if ($q) {
    while ($r = $q->fetch_assoc()) $cols[] = $r['COLUMN_NAME'];
    $q->free();
} else {
    $mysqli->close();
    respond(['ok'=>false,'error'=>'schema_query_failed','detail'=>$mysqli->error]);
}

// 欲寫入的欄位與值
$payload = [
    'file' => $file,
    'page' => $page,
    'title' => $title ?: mb_substr($content,0,60),
    'content' => $content
];

// 取交集（排除自增 id / created_at 由 DB 自行處理）
$insertCols = [];
$insertVals = [];
$types = '';
foreach ($payload as $k => $v) {
    if (in_array($k, $cols)) {
        $insertCols[] = $k;
        $insertVals[] = $v;
        // page 整數，其餘當字串
        $types .= ($k === 'page') ? 'i' : 's';
    }
}

if (count($insertCols) === 0) {
    $mysqli->close();
    respond(['ok'=>false,'error'=>'no_matching_columns','available_columns'=>$cols]);
}

// 建立 prepared statement
$placeholders = implode(',', array_fill(0, count($insertCols), '?'));
$colList = implode(',', array_map(function($c){ return "`$c`"; }, $insertCols));
$sql = "INSERT INTO `$table` ($colList) VALUES ($placeholders)";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    $err = $mysqli->error;
    $mysqli->close();
    respond(['ok'=>false,'error'=>'prepare_failed','detail'=>$err,'sql'=>$sql]);
}

// bind_param 需傳參考
$bind_names[] = $types;
for ($i=0;$i<count($insertVals);$i++){
    $bind_names[] = &$insertVals[$i];
}
call_user_func_array([$stmt,'bind_param'], $bind_names);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    $mysqli->close();
    respond(['ok'=>false,'error'=>'execute_failed','detail'=>$err]);
}

$insertId = $stmt->insert_id;
$stmt->close();
$mysqli->close();

respond(['ok'=>true,'id'=>$insertId,'inserted_columns'=>$insertCols,'note'=>array_combine($insertCols,$insertVals)]);
?>