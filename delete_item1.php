<?php
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$type = isset($data['type']) ? $data['type'] : '';
$path = isset($data['path']) ? $data['path'] : '';

if (!$type || !$path) {
    echo json_encode(['ok'=>false,'error'=>'missing_params']);
    exit;
}

$baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
if (!$baseDir || !is_dir($baseDir)) {
    echo json_encode(['ok'=>false,'error'=>'uploads_not_found']);
    exit;
}

// Normalize path: remove null bytes and collapse dangerous tokens
$path = str_replace("\0", '', $path);
$path = str_replace(['../','..\\'], '', $path);
$path = ltrim($path, '/\\');

// Resolve target and ensure it's under uploads
$target = $baseDir . DIRECTORY_SEPARATOR . $path;
$realTarget = realpath($target);

if ($type === 'file') {
    if (!$realTarget || !is_file($realTarget)) {
        echo json_encode(['ok'=>false,'error'=>'file_not_found','path'=>$path]);
        exit;
    }
    if (strpos($realTarget, $baseDir) !== 0) {
        echo json_encode(['ok'=>false,'error'=>'invalid_path']);
        exit;
    }
    if (@unlink($realTarget)) {
        echo json_encode(['ok'=>true]);
    } else {
        echo json_encode(['ok'=>false,'error'=>'unlink_failed','path'=>$realTarget]);
    }
    exit;
}

if ($type === 'folder') {
    // For folder we allow deleting any subdir under uploads
    if (!$realTarget || !is_dir($realTarget)) {
        echo json_encode(['ok'=>false,'error'=>'folder_not_found','path'=>$path]);
        exit;
    }
    if (strpos($realTarget, $baseDir) !== 0) {
        echo json_encode(['ok'=>false,'error'=>'invalid_path']);
        exit;
    }
    // prevent deleting the base uploads directory itself
    if ($realTarget === $baseDir) {
        echo json_encode(['ok'=>false,'error'=>'cannot_delete_base']);
        exit;
    }
    // recursive remove
    $err = [];
    $deleteDir = function($dir) use (&$deleteDir, &$err) {
        $items = array_diff(scandir($dir), ['.','..']);
        foreach ($items as $it) {
            $p = $dir . DIRECTORY_SEPARATOR . $it;
            if (is_dir($p)) {
                $deleteDir($p);
            } else {
                if (!@unlink($p)) $err[] = $p;
            }
        }
        if (!@rmdir($dir)) $err[] = $dir;
    };
    $deleteDir($realTarget);
    if (empty($err)) {
        echo json_encode(['ok'=>true]);
    } else {
        echo json_encode(['ok'=>false,'error'=>'partial_fail','details'=>$err]);
    }
    exit;
}

echo json_encode(['ok'=>false,'error'=>'unknown_type']);
?>