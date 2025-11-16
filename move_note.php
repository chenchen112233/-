<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors',1);
error_reporting(E_ALL);

function resp($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) $data = $_POST;

$id = isset($data['id']) ? intval($data['id']) : 0;
$folder = isset($data['folder']) ? trim($data['folder']) : '';
if (!$id || $folder === '') resp(['ok'=>false,'error'=>'invalid_parameters']);

$folder = str_replace(array("\0","..","/","\\"), '', $folder);
$baseNoteDir = __DIR__ . DIRECTORY_SEPARATOR . 'note';
if (!is_dir($baseNoteDir) && !mkdir($baseNoteDir, 0777, true)) resp(['ok'=>false,'error'=>'mkdir_base_failed']);
$targetDir = $baseNoteDir . DIRECTORY_SEPARATOR . $folder;
if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) resp(['ok'=>false,'error'=>'mkdir_failed','path'=>$targetDir]);

$mysqli = new mysqli('127.0.0.1','root','','notebook');
if (!$mysqli || $mysqli->connect_errno) resp(['ok'=>false,'error'=>'db_connect_failed']);
$mysqli->set_charset('utf8mb4');

// fetch note
$stmt = $mysqli->prepare("SELECT id, title, content FROM notes WHERE id = ?");
$stmt->bind_param('i',$id);
$stmt->execute();
$res = $stmt->get_result();
$note = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$note) { $mysqli->close(); resp(['ok'=>false,'error'=>'note_not_found']); }

$title = $note['title'] ?? ('note_'.$id);
$content = $note['content'] ?? '';

// save base64 images (if any) and replace src to relative path under note/<folder>/
$savedImages = [];
if (preg_match_all('/<img[^>]+src=[\'"](?P<src>data:[^\'"]+)[\'"][^>]*>/i', $content, $m)) {
    $n = 0;
    foreach ($m['src'] as $dataUri) {
        $n++;
        $parts = explode(',', $dataUri, 2);
        if (count($parts)!==2) continue;
        $meta = $parts[0]; $b64 = $parts[1];
        if (strpos($meta,'base64')===false) continue;
        $decoded = base64_decode($b64);
        if ($decoded===false) continue;
        if (preg_match('/data:image\/(png|jpeg|jpg|gif|webp)/i',$meta,$mm)) {
            $ext = strtolower($mm[1]) === 'jpeg' ? 'jpg' : strtolower($mm[1]);
        } else $ext = 'png';
        $imgName = 'img_note_'.$id.'_'.$n.'.'.$ext;
        $imgPath = $targetDir . DIRECTORY_SEPARATOR . $imgName;
        if (file_put_contents($imgPath, $decoded) !== false) {
            $rel = 'note/' . rawurlencode($folder) . '/' . rawurlencode($imgName);
            // replace only the exact data uri occurrences
            $content = str_replace($dataUri, $rel, $content);
            $savedImages[] = $rel;
        }
    }
}

// safe filename
function slugify($s){
    $s = preg_replace('/[^\p{L}\p{N}\-_]+/u','-', trim($s));
    $s = preg_replace('/-+/','-',$s);
    $s = trim($s,'-');
    return substr($s,0,60);
}
$baseName = slugify($title) ?: 'note';
$txtName  = $baseName . '-' . $id . '.txt';
$txtPath  = $targetDir . DIRECTORY_SEPARATOR . $txtName;

// write TXT file：去掉 HTML 標籤並 decode entities
$plain = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
// normalize whitespace
$plain = preg_replace("/\r\n|\r|\n/", "\n", $plain);
$plain = preg_replace("/\n{2,}/", "\n\n", $plain);
$plain = trim($plain);

// 如果想在 txt 前面加上標題與分隔，可啟用下列程式碼（目前僅寫入純文字內容）
// $plain = $title . "\n\n" . $plain;

if (file_put_contents($txtPath, $plain) === false) {
    $mysqli->close();
    resp(['ok'=>false,'error'=>'write_txt_failed','txt_path'=>$txtPath]);
}

// update DB folder column if exists
$updated = false;
$q = $mysqli->query("SHOW COLUMNS FROM notes LIKE 'folder'");
if ($q && $q->num_rows>0) {
    $stmt2 = $mysqli->prepare("UPDATE notes SET folder = ? WHERE id = ?");
    if ($stmt2) {
        $stmt2->bind_param('si',$folder,$id);
        $updated = $stmt2->execute();
        $stmt2->close();
    }
}
$mysqli->close();

resp([
    'ok'=>true,
    'id'=>$id,
    'txt'  => 'note/'.$folder.'/'.$txtName,
    'images' => $savedImages,
    'db_folder_updated' => $updated ? true : false
]);
?>