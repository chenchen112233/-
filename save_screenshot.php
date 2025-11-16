<?php
ini_set('display_errors',1);
ini_set('log_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function resp($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) resp(['ok'=>false,'error'=>'invalid_json','raw_len'=>strlen($raw)]);

$file = trim($data['file'] ?? '');
$page = isset($data['page']) ? intval($data['page']) : 0;
$image = $data['image'] ?? '';

if ($file === '' || $image === '') resp(['ok'=>false,'error'=>'missing_params','file_len'=>strlen($file),'image_len'=>strlen($image)]);

// 檢查 data URL
if (strpos($image, 'data:image/') !== 0 || strpos($image, 'base64,') === false) {
    resp(['ok'=>false,'error'=>'invalid_image_format','starts_with'=>substr($image,0,60)]);
}

// 準備目錄
$screensDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'screenshots';
if (!is_dir($screensDir)) {
    $mk = @mkdir($screensDir, 0777, true);
    if (!$mk) {
        error_log("save_screenshot: mkdir failed for $screensDir");
        resp(['ok'=>false,'error'=>'mkdir_failed','target'=>$screensDir]);
    }
}

// 檔名/資料夾處理
$sanFolder = preg_replace('/[^\p{L}\p{N}_\- ]/u', '_', pathinfo($file, PATHINFO_DIRNAME) === '.' ? pathinfo($file, PATHINFO_FILENAME) : pathinfo($file, PATHINFO_DIRNAME) . '_' . pathinfo($file, PATHINFO_FILENAME));
$sanFolder = $sanFolder ?: date('Ymd');
$targetDir = $screensDir . DIRECTORY_SEPARATOR . $sanFolder;
if (!is_dir($targetDir)) {
    $mk2 = @mkdir($targetDir, 0777, true);
    if (!$mk2) {
        error_log("save_screenshot: mkdir failed for $targetDir");
        resp(['ok'=>false,'error'=>'mkdir_target_failed','target'=>$targetDir]);
    }
}

$baseName = preg_replace('/[^\p{L}\p{N}_\- ]/u', '_', pathinfo($file, PATHINFO_FILENAME));
$ts = time();
$fname = $baseName . '_p' . $page . '_' . $ts . '.png';
$targetPath = $targetDir . DIRECTORY_SEPARATOR . $fname;

// 解碼並寫檔（先檢查 base64 長度）
$parts = explode(',', $image, 2);
if (count($parts) < 2) resp(['ok'=>false,'error'=>'no_base64_part']);
$base64len = strlen($parts[1]);
if ($base64len < 100) resp(['ok'=>false,'error'=>'base64_too_short','len'=>$base64len]);

$decoded = base64_decode($parts[1], true);
if ($decoded === false) {
    error_log("save_screenshot: base64_decode failed, len=$base64len");
    resp(['ok'=>false,'error'=>'base64_decode_failed','len'=>$base64len]);
}

// 嘗試寫檔並回傳更詳細錯誤
$w = @file_put_contents($targetPath, $decoded, LOCK_EX);
if ($w === false) {
    $err = error_get_last();
    error_log("save_screenshot: file_put_contents failed to $targetPath; last_error: " . json_encode($err));
    resp(['ok'=>false,'error'=>'file_put_failed','target'=>$targetPath,'last_error'=>$err]);
}

// 檔案寫入成功，準備寫入 DB
$relPath = 'uploads/screenshots/' . $sanFolder . '/' . $fname;

// DB 設定（如有不同請修改）
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'notebook';

$mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    error_log("save_screenshot: db connect failed: " . $mysqli->connect_error);
    // 回傳成功但提示 DB 連線失敗
    resp(['ok'=>true,'path'=>$relPath,'size'=>$w,'warning'=>'db_connect_failed']);
}
$mysqli->set_charset('utf8mb4');

// 嘗試建立表（若不存在）
$createSql = "CREATE TABLE IF NOT EXISTS `screenshots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pdf_file` VARCHAR(512),
  `page` INT,
  `path` VARCHAR(1024),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4";
$mysqli->query($createSql);

// 插入紀錄
$stmt = $mysqli->prepare("INSERT INTO screenshots (pdf_file, page, path, created_at) VALUES (?, ?, ?, NOW())");
if (!$stmt) {
    $err = $mysqli->error;
    error_log("save_screenshot: prepare failed: $err");
    // 回傳成功但提示 DB 寫入失敗
    $mysqli->close();
    resp(['ok'=>true,'path'=>$relPath,'size'=>$w,'warning'=>'db_prepare_failed','db_error'=>$err]);
}
$stmt->bind_param('sis', $file, $page, $relPath);
$execOk = $stmt->execute();
if (!$execOk) {
    $err = $stmt->error;
    error_log("save_screenshot: execute failed: $err");
    $stmt->close();
    $mysqli->close();
    resp(['ok'=>true,'path'=>$relPath,'size'=>$w,'warning'=>'db_execute_failed','db_error'=>$err]);
}
$insertId = $stmt->insert_id;
$stmt->close();
$mysqli->close();

// 成功回傳包含 DB id 與相對路徑
resp(['ok'=>true,'id'=>$insertId,'path'=>$relPath,'size'=>$w,'base64_len'=>$base64len]);
?>