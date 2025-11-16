<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function resp($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$folder = isset($_GET['folder']) ? $_GET['folder'] : '';
$folder = trim($folder);
if ($folder === '') resp(['ok'=>false,'error'=>'empty_folder']);

// 防止路徑穿越
$folder = str_replace(array("\0","..","/","\\"), '', $folder);
// 若瀏覽器以 URL encoded 傳入，嘗試 decode（安全）
$folder = rawurldecode($folder);

// note 目錄（確保與 index2.php 相同）
$base = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'note');
if ($base === false) resp(['ok'=>false,'error'=>'note_dir_missing']);

$target = $base . DIRECTORY_SEPARATOR . $folder;
if (!is_dir($target)) resp(['ok'=>false,'error'=>'folder_not_exists','path'=>$target]);

$files = [];
$dh = opendir($target);
if ($dh) {
    while (($f = readdir($dh)) !== false) {
        if ($f === '.' || $f === '..') continue;
        $abs = $target . DIRECTORY_SEPARATOR . $f;
        if (!is_file($abs)) continue;
        $mime = @mime_content_type($abs) ?: '';
        $isImage = strpos($mime, 'image/') === 0;
        // 回傳給前端的相對路徑（web 可存取）
        $rel = 'note/' . $folder . '/' . $f;
        $files[] = [
            'name' => $f,
            'rel' => $rel,
            'is_image' => $isImage,
            'mime' => $mime,
            'size' => filesize($abs),
            'mtime' => filemtime($abs)
        ];
    }
    closedir($dh);
} else {
    resp(['ok'=>false,'error'=>'opendir_failed','path'=>$target]);
}

// 依修改時間排序（新到舊）
usort($files, function($a,$b){ return $b['mtime'] <=> $a['mtime']; });

resp(['ok'=>true,'files'=>$files]);
?>