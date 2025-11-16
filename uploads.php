<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['myfile'])) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir);

    $file = $_FILES['myfile'];
    $filename = basename($file['name']);
    $target = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        echo "上傳成功！";
    } else {
        echo "上傳失敗！錯誤碼：" . $file['error'];
        var_dump($file);
    }
} else {
    echo "請選擇檔案";
}