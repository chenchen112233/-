<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) $data = $_POST;

$type = isset($data['type']) ? $data['type'] : '';
$path = isset($data['path']) ? trim($data['path']) : '';

function resp($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
if (!$type || $path === '') resp(['ok'=>false,'error'=>'invalid_parameters']);

// 禁止路徑穿越、null bytes
$clean = str_replace(["\0", ".."], '', $path);
$clean = trim($clean, " \t\n\r\0\x0B/\\"); // 去除開頭結尾的斜線

// base note 目錄（只允許刪除 note 下的子項）
$baseNote = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'note');
if ($baseNote === false) resp(['ok'=>false,'error'=>'note_dir_missing']);

// 目標絕對路徑
$target = $baseNote . DIRECTORY_SEPARATOR . $clean;
// 若 target 不存在 realpath 會回 false，但我們還要允許刪除已存在的檔/資料夾 => 先嘗試 realpath
$realTarget = realpath($target) ?: $target;

// 檢查目標在 note 目錄內
$basePrefix = rtrim($baseNote, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$targetPrefix = rtrim($realTarget, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (stripos($targetPrefix, $basePrefix) !== 0 && strcasecmp($realTarget, $baseNote) !== 0) {
    resp(['ok'=>false,'error'=>'invalid_path','message'=>'目標不在 note 目錄內或路徑不安全']);
}

try {
    if ($type === 'file') {
        if (!file_exists($realTarget)) return resp(['ok'=>false,'error'=>'file_not_found']);
        if (!is_file($realTarget)) return resp(['ok'=>false,'error'=>'not_a_file']);
        if (@unlink($realTarget)) return resp(['ok'=>true]);
        return resp(['ok'=>false,'error'=>'unlink_failed']);
    }

    if ($type === 'folder') {
        // 資料夾必須存在
        if (!file_exists($realTarget)) return resp(['ok'=>false,'error'=>'folder_not_found']);
        if (!is_dir($realTarget)) return resp(['ok'=>false,'error'=>'not_a_folder']);

        // 使用 SPL 迭代器安全遞迴刪除
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realTarget, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $item) {
            try {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            } catch (Throwable $e) {
                // 忽略個別檔案錯誤，最終檢查整體狀態
            }
        }

        // 嘗試刪除資料夾本身
        if (@rmdir($realTarget)) return resp(['ok'=>true]);
        return resp(['ok'=>false,'error'=>'rmdir_failed','message'=>'無法刪除資料夾（可能為權限問題）']);
    }

    resp(['ok'=>false,'error'=>'invalid_type']);
} catch (Exception $e) {
    resp(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()]);
}
?> 