<?php
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

$sql = "SELECT password, salt FROM users WHERE username='$username'";
$result = $conn->query($sql);
if ($result->num_rows ==1) {
    $row = $result->fetch_assoc();
    $storedHashedPassword = $row['password'];
    $salt = $row['salt'];

    $hashedPassword = hash('sha256', $password . $salt);
    if ($hashedPassword === $storedHashedPassword) {
        // --- 新增：設定 session cookie 並啟用 session，寫入 username ---
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $host,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
        $_SESSION['username'] = $username;
        session_regenerate_id(true);
        // --------------------------------------------------------------

        // 回傳小段 HTML 同步 localStorage（保留原本行為）並導回 index.html
        echo "<!doctype html><html><head><meta charset='utf-8'><title>登入成功</title></head><body>";
        echo "<script>";
        echo "localStorage.setItem('username', " . json_encode($username) . ");";
        echo "window.location.href='index.html';";
        echo "</script>";
        echo "</body></html>";
        exit;
    } else {
        echo "登入失敗 密碼錯誤";
        echo '<button onclick="window.location.href=\'index0.html\'">返回登入頁面</button>';
    }
} else {
    echo "登入失敗 使用者不存在";
    echo '<button onclick="window.location.href=\'user.html\'">返回註冊頁面</button>';
}
$conn->close();
}
?>