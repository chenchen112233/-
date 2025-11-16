<?php
// filepath: c:\xampp\htdocs\preview.php
$filename = $_GET['file'] ?? '';
$filepath = __DIR__ . '/uploads/' . $filename;

// 允許中英文、數字、底線、減號、點、空白
if (preg_match('/^[\w.\x{4e00}-\x{9fa5} \-]+$/u', $filename) && is_file($filepath)) {
    $content = file_get_contents($filepath);
    // 取前200個字元（支援中英文）
    $preview = mb_substr(strip_tags($content), 0, 200, 'UTF-8');
    echo htmlspecialchars($preview);
    if (mb_strlen(strip_tags($content), 'UTF-8') > 200) {
        echo "\n...\n（僅顯示部分內容）";
    }
} else {
    echo '檔案不存在或非法檔名';
}