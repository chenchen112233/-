<?php
header('Content-Type: application/json; charset=utf-8');

// 儲存路徑（annotations 目錄下的 view_positions.json）
$dir = __DIR__ . DIRECTORY_SEPARATOR . 'annotations';
if (!is_dir($dir)) @mkdir($dir, 0777, true);
$posFile = $dir . DIRECTORY_SEPARATOR . 'view_positions.json';

// 讀入現有資料
function load_positions($f) {
    if (!file_exists($f)) return [];
    $s = @file_get_contents($f);
    $j = $s ? json_decode($s, true) : null;
    return is_array($j) ? $j : [];
}
function save_positions($f, $arr) {
    return file_put_contents($f, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    // 支援 form-data 與 raw JSON
    $file = $_POST['file'] ?? null;
    $page = $_POST['page'] ?? null;
    // 從 raw body 嘗試解析（若沒用 form）
    if (!$file || !$page) {
        $raw = file_get_contents('php://input');
        $j = json_decode($raw, true);
        if (is_array($j)) {
            $file = $file ?: ($j['file'] ?? null);
            $page = $page ?: ($j['page'] ?? ($j['pageNum'] ?? null));
        }
    }
    if (!$file || !$page) {
        echo json_encode(['ok'=>false, 'error'=>'missing_params'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // sanitize filename (只保留基本合法字元)
    $fileSafe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file);
    $pageNum = intval($page) ?: 1;

    $all = load_positions($posFile);
    $all[$fileSafe] = ['page' => $pageNum, 'ts' => time()];
    if (save_positions($posFile, $all)) {
        echo json_encode(['ok'=>true, 'file'=>$fileSafe, 'page'=>$pageNum], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok'=>false, 'error'=>'write_failed'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// GET: 回傳指定檔案的最後頁（方便未來自動還原）
$file = $_GET['file'] ?? null;
if (!$file) { echo json_encode(['ok'=>false,'error'=>'missing_file'], JSON_UNESCAPED_UNICODE); exit; }
$fileSafe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file);
$all = load_positions($posFile);
if (isset($all[$fileSafe])) {
    echo json_encode(['ok'=>true, 'file'=>$fileSafe, 'page'=>intval($all[$fileSafe]['page'])], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['ok'=>true, 'file'=>$fileSafe, 'page'=>null], JSON_UNESCAPED_UNICODE);
}
?>