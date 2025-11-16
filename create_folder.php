<?php
ini_set('display_errors',0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

function resp($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) resp(['ok'=>false,'error'=>'invalid_json']);

$folder = trim($data['folder'] ?? '');
if ($folder === '') resp(['ok'=>false,'error'=>'empty_folder_name']);

// 基本 sanitize：允許中文/英數/底線/破折號與空格，其餘用底線取代
$folderUtf8 = preg_replace('/[^\p{L}\p{N}_\-\s]/u', '_', $folder);
$folderUtf8 = trim($folderUtf8);
if ($folderUtf8 === '') resp(['ok'=>false,'error'=>'invalid_folder_name']);

// 轉成檔案系統編碼（CP950/BIG5 優先）
function utf8_to_fs($s){
    $try = @iconv('UTF-8','CP950//TRANSLIT',$s);
    if ($try !== false) return $try;
    $try2 = @iconv('UTF-8','BIG5//TRANSLIT',$s);
    if ($try2 !== false) return $try2;
    return $s;
}

// 使用你指定的路徑：C:\xampp\htdocs\note
$basePath = __DIR__ . DIRECTORY_SEPARATOR . 'note';
$base = realpath($basePath);
if ($base === false) {
    if (!@mkdir($basePath, 0777, true)) {
        resp(['ok'=>false,'error'=>'note_not_found_and_could_not_create','target'=>$basePath]);
    }
    $base = realpath($basePath);
    if ($base === false) resp(['ok'=>false,'error'=>'cannot_resolve_base_path','target'=>$basePath]);
}

$fsName = utf8_to_fs($folderUtf8);
$target = $base . DIRECTORY_SEPARATOR . $fsName;
if (file_exists($target)) {
    if (is_dir($target)) resp(['ok'=>false,'error'=>'folder_exists']);
    else resp(['ok'=>false,'error'=>'target_exists_not_dir']);
}

if (!@mkdir($target, 0777, true)) {
    $err = error_get_last();
    resp(['ok'=>false,'error'=>'mkdir_failed','detail'=> $err]);
}

// 成功回傳 UTF-8 名稱與相對路徑（相對於專案根）
$rel = 'note/' . $folderUtf8;
resp(['ok'=>true,'folder'=>$folderUtf8,'path'=>$rel]);
?>