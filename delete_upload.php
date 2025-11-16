<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['error' => '無效的請求內容'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 支援 "file" (單一) 或 "files" (陣列)
$files = [];
if (!empty($input['files']) && is_array($input['files'])) {
    $files = $input['files'];
} elseif (!empty($input['file'])) {
    $files = [$input['file']];
}

if (empty($files)) {
    echo json_encode(['error' => '缺少要刪除的檔案名稱'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$realDir = realpath($dir);
if ($realDir === false) {
    echo json_encode(['error' => 'uploads 目錄不存在'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed = ['pdf','txt','doc','docx','md','html','htm','jpg','jpeg','png','gif','csv'];

$deleted = [];
$failed = [];

foreach ($files as $f) {
    $filename = basename($f);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $failed[$filename] = '不允許的檔案類型';
        continue;
    }
    $filepath = realpath($realDir . DIRECTORY_SEPARATOR . $filename);
    if ($filepath === false || strpos($filepath, $realDir) !== 0) {
        $failed[$filename] = '檔案不存在或不在 uploads 目錄';
        continue;
    }
    if (!file_exists($filepath)) {
        $failed[$filename] = '檔案不存在';
        continue;
    }
    if (!is_writable($filepath) && !is_writable($realDir)) {
        $failed[$filename] = '伺服器無權限刪除檔案';
        continue;
    }
    if (!@unlink($filepath)) {
        $failed[$filename] = '無法刪除（伺服器錯誤）';
        continue;
    }
    $deleted[] = $filename;
}

// 回傳結果
echo json_encode([
    'success' => count($failed) === 0,
    'deleted' => $deleted,
    'failed' => $failed
], JSON_UNESCAPED_UNICODE);