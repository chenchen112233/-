<?php
header('Content-Type: application/json; charset=utf-8');

// 設定資料庫連線參數
$host = '127.0.0.1';
$db   = 'notebook';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// 設定 PDO 參數
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    // 建立 PDO 連線
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 取得前端傳來的 JSON
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode(['error' => '無效的資料']);
        exit;
    }

    // 取得筆記內容
    $id = isset($data['id']) ? $data['id'] : null;
    $title = isset($data['title']) ? $data['title'] : '';
    $content = isset($data['content']) ? $data['content'] : '';
    $titleColor = isset($data['titleColor']) ? $data['titleColor'] : null;

    // 嘗試將接收到的字串轉為 UTF-8（若原始編碼不是 UTF-8）
    if (!function_exists('ensure_utf8')) {
        function ensure_utf8($s) {
            if ($s === null) return '';
            // 若已經是 UTF-8 就直接回傳
            if (mb_check_encoding($s, 'UTF-8')) return $s;
            // 嘗試常見編碼轉換（GBK/CP936、ISO-8859-1、Windows-1252）
            $enc = mb_detect_encoding($s, ['UTF-8','GBK','CP936','ISO-8859-1','Windows-1252'], true);
            if ($enc && $enc !== 'UTF-8') {
                return mb_convert_encoding($s, 'UTF-8', $enc);
            }
            // fallback: try converting from common single-byte encodings
            return mb_convert_encoding($s, 'UTF-8', 'CP936,GBK,ISO-8859-1,Windows-1252');
        }
    }

    $title = ensure_utf8($title);
    $content = ensure_utf8($content);

    if (empty($id)) {
        // 新增：INSERT
        $sql = "INSERT INTO notes (title, content) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content]);
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        // 更新：UPDATE
        $sql = "UPDATE notes SET title = ?, content = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $id]);
        echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
