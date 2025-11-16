<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servername = "127.0.0.1";
    $username = "root";
    $password = "";
    $dbname = "user";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("連接資料庫失敗 " . $conn->connect_error);
    }

    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);

    $salt = bin2hex(random_bytes(16));
    $Password = hash('sha256', $password . $salt);

    try {
        $sql = "INSERT INTO users (username, password, salt) VALUES ('$username', '$Password', '$salt')";
        if ($conn->query($sql) === TRUE) {
            echo "註冊成功<br>";
            echo '<button onclick="window.location.href=\'index0.html\'">前往登入頁面</button>';
        } else {
            showFailButton();
        }
    } catch (mysqli_sql_exception $e) {
        // 如果是帳號重複（MySQL錯誤碼1062），只顯示註冊失敗
        showFailButton();
    }

    $conn->close();
}

function showFailButton() {
    echo "註冊失敗<br>";
    echo '<button onclick="window.location.href=\'user.html\'">返回註冊頁面</button>';
}
?>