<?php
// filepath: c:\xampp\htdocs\word_content.php
$filename = $_GET['file'] ?? '';
$filepath = __DIR__ . '/uploads/' . $filename;

// 允許中英文、數字、底線、減號、點、空白
if (preg_match('/^[\w.\x{4e00}-\x{9fa5} \-]+$/u', $filename) && is_file($filepath)) {
    // 讀取完整內容（不做 htmlspecialchars）
    echo file_get_contents($filepath);
} else {
    echo '檔案不存在或非法檔名';
}