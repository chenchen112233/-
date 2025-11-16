<?php
// filepath: c:\xampp\htdocs\save_word.php
$data = json_decode(file_get_contents('php://input'), true);
$filename = $data['file'] ?? '';
$content = $data['content'] ?? '';
$filepath = __DIR__ . '/uploads/' . $filename;

// 允許中英文、數字、底線、減號、點、空白
if (
    preg_match('/^[\w.\x{4e00}-\x{9fa5} \-]+$/u', $filename)
    && is_file($filepath)
    && !str_ends_with(strtolower($filename), '.pdf')
) {
    // 儲存內容（HTML 格式，含 <mark> 標記）
    file_put_contents($filepath, $content);
    echo '儲存成功！';
} else {
    echo '檔案不存在或非法檔名，或不支援此格式';
}