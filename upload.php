<?php
// Upload handler：將顯示用的 UTF-8 名稱轉為檔案系統（CP950/Big5）再寫入，回到 index7.php 顯示 UTF-8 名稱
ini_set('display_errors',1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

function sendHtml($msg){ 
  echo '<!doctype html><meta charset="utf-8"><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p><p><a href="index7.php">回檔案列表</a></p>';
  exit;
}

if (!isset($_FILES['pdf_file'])) sendHtml('沒有收到檔案上傳。');

$file = $_FILES['pdf_file'];
if ($file['error'] !== UPLOAD_ERR_OK) sendHtml('上傳失敗，錯誤代碼：' . $file['error']);

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($mime !== 'application/pdf' && $ext !== 'pdf') {
  sendHtml('僅接受 PDF 檔案。');
}

// 取得並 sanitize target folder（輸入為 UTF-8）
$rawFolder = trim($_POST['target_folder'] ?? '');
if ($rawFolder === '') {
  $folderUtf8 = date('Ymd');
} else {
  // 允許中文/英數/底線/破折號與空格，其他轉 _
  $folderUtf8 = preg_replace('/[^\p{L}\p{N}_\- ]/u', '_', $rawFolder);
  if ($folderUtf8 === '') $folderUtf8 = date('Ymd');
}

// base uploads dir
$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';

// 轉換 UTF-8 顯示名稱為檔案系統編碼（Windows 常用 CP950 / Big5）
// 若 iconv 失敗則 fallback 使用 UTF-8 名稱（某些環境可直接使用 Unicode）
function utf8_to_fs($s){
    // 優先用 CP950，若失敗再用 BIG5，再 fallback 原字串
    $try = @iconv('UTF-8','CP950//TRANSLIT',$s);
    if ($try !== false) return $try;
    $try2 = @iconv('UTF-8','BIG5//TRANSLIT',$s);
    if ($try2 !== false) return $try2;
    return $s;
}

// 建資料夾（檔案系統路徑）
$folderFs = utf8_to_fs($folderUtf8);
$targetDirFs = $baseDir . DIRECTORY_SEPARATOR . $folderFs;
if (!is_dir($targetDirFs)) {
  if (!mkdir($targetDirFs, 0777, true)) {
    sendHtml('無法建立目錄：' . $targetDirFs . '，請確認 Apache 有寫入權限。');
  }
}

// 產生安全檔名（UTF-8 顯示名稱）
$origBase = pathinfo($file['name'], PATHINFO_FILENAME);
$baseUtf8 = preg_replace('/[^\p{L}\p{N}_\- ]/u', '_', $origBase);
$timestamp = time();
$targetNameUtf8 = $baseUtf8 . '_' . $timestamp . '.pdf';

// 轉為檔案系統編碼路徑
$targetNameFs = utf8_to_fs($targetNameUtf8);
$targetPathFs = $targetDirFs . DIRECTORY_SEPARATOR . $targetNameFs;

// 移動上傳檔案
if (!move_uploaded_file($file['tmp_name'], $targetPathFs)) {
  sendHtml('無法移動上傳檔案到：' . $targetPathFs);
}

// 成功後導回 index7.php，使用 UTF-8 名稱作為 folder query
header('Location: index7.php?folder=' . rawurlencode($folderUtf8));
exit;
?>
