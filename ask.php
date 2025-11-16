$php_start = microtime(true);
<?php
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents("php://input"), true);
$question = trim($data['question'] ?? '');

// basic validation
if ($question === '') {
    echo json_encode(['error' => '請輸入問題'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. 連接資料庫並搜尋相關內容
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=notebook;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo json_encode(['error' => '無法連線資料庫: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
$parts = preg_split('/\s+/', $question);
$wheres = [];
$params = [];
foreach ($parts as $word) {
    if (mb_strlen($word) > 1) {
        $wheres[] = '(title LIKE ? OR content LIKE ?)';
        $params[] = '%' . $word . '%';
        $params[] = '%' . $word . '%';
    }
}
$whereSql = implode(' OR ', $wheres);
$sql = "SELECT title, content FROM notes" . ($whereSql ? " WHERE $whereSql" : "") . " LIMIT 3";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$context = '';
if ($rows) {
    foreach ($rows as $row) {
        $context .= "標題：{$row['title']}\n內容：" . mb_substr(strip_tags($row['content']), 0, 200) . "\n\n";
    }
} else {
    $context = "（資料庫沒有找到相關內容）";
}

// 2. 呼叫 OpenAI GPT API
$openai_api_key = getenv('OPENAI_API_KEY') ?: '';
if (empty($openai_api_key)) {
    echo json_encode(['error' => 'OpenAI API key 未設定，請設定環境變數 OPENAI_API_KEY'], JSON_UNESCAPED_UNICODE);
    exit;
}
$prompt = "根據以下資料庫內容回答使用者的問題。\n\n資料庫內容：\n$context\n\n使用者問題：$question\n\n請根據資料庫內容回答，如果找不到請說明找不到。";

// call OpenAI
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $openai_api_key
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        ['role' => 'system', 'content' => '你是一個根據資料庫內容回答問題的小助手。'],
        ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => 800,
]));

$response = curl_exec($ch);
if ($response === false) {
    $err = curl_error($ch);
    curl_close($ch);
    echo json_encode(['error' => '呼叫 OpenAI 發生錯誤: ' . $err], JSON_UNESCAPED_UNICODE);
    exit;
}
curl_close($ch);

$res = json_decode($response, true);
if (!$res || !isset($res['choices'][0]['message']['content'])) {
    echo json_encode(['error' => 'AI 回傳格式不正確或無回應', 'raw' => $res], JSON_UNESCAPED_UNICODE);
    exit;
}

$gpt_answer = $res['choices'][0]['message']['content'];
echo json_encode(['answer' => nl2br($gpt_answer)], JSON_UNESCAPED_UNICODE);