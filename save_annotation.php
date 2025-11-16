<?php
// 儲存 PDF 標記的 API：接收 { file: "uploads/xxx/yyy.pdf", annotations: {...} }
ini_set('display_errors',0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

function resp($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) resp(['ok'=>false,'error'=>'invalid_json','raw'=>$raw]);

$file = $data['file'] ?? '';
$anns = $data['annotations'] ?? null;
if (!$file || !is_string($file)) resp(['ok'=>false,'error'=>'empty_file']);
if ($anns === null) resp(['ok'=>false,'error'=>'empty_annotations']);

// 安全清理 file 路徑（移除 ../ 與開頭的斜線與不可見字元）
$file = str_replace("\0", '', $file);
$file = preg_replace('#\.\.#', '', $file);
$file = ltrim($file, "/\\"); 
$file = trim($file);

// 不允許包含冒號等可能造成路徑問題的字元（簡單檢查）
if (preg_match('/[:<>|?"*]/', $file)) resp(['ok'=>false,'error'=>'invalid_filename_chars']);

// 目標資料夾（在專案下的 annotations）
$base = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'annotations';
if (!is_dir($base)) {
    if (!mkdir($base, 0777, true)) resp(['ok'=>false,'error'=>'mkdir_failed','target'=>$base]);
}

// 建立子資料夾（如果 file 內含子目錄）
$targetPath = $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file) . '.json';
$targetDir = dirname($targetPath);
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0777, true)) resp(['ok'=>false,'error'=>'mkdir_subdir_failed','target'=>$targetDir]);
}

// 最後一次安全檢查：確認 targetPath 位於 annotations 目錄下
$realBase = realpath($base);
$realTargetDir = realpath($targetDir) ?: $targetDir;
if (strpos($realTargetDir, $realBase) !== 0) resp(['ok'=>false,'error'=>'invalid_target_path']);

// 將標記寫成 JSON（漂亮輸出）
$json = json_encode($anns, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) resp(['ok'=>false,'error'=>'json_encode_failed']);

// 寫檔並加鎖
$w = @file_put_contents($targetPath, $json, LOCK_EX);
if ($w === false) {
    resp(['ok'=>false,'error'=>'file_put_contents_failed','target'=>$targetPath]);
}

// 成功回傳
echo '儲存成功';
exit;
?>
