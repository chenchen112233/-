<?php
// 測試/除錯階段建議關閉 display_errors，讓腳本只回傳 JSON
ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['ok'=>false,'error'=>'無效的 JSON 請求'], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = $data['items'] ?? null;
$messages = $data['messages'] ?? null;

// 讀取金鑰（優先 OpenAI）
$openai = getenv('OPENAI_API_KEY') ?: null;
$googleKey = getenv('GOOGLE_API_KEY') ?: null;

function respond($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}
 
// 小工具：縮短長字串
function snippet($s, $len = 500) {
    if ($s === null) return '';
    $s = (string)$s;
    return mb_substr($s, 0, $len) . (mb_strlen($s) > $len ? '...' : '');
}

/**
 * 若為 chat 模式（帶 messages），回傳 single reply
 */
if ($messages !== null) {
    $system = "你是一個中文文件標記助理。使用者會就文件的標記內容與你對話，請以清楚、簡潔的中文回答。";
    if (!empty($items) && is_array($items)) {
        $parts = [];
        foreach ($items as $it) {
            $txt = $it['text'] ?? '';
            $parts[] = "索引: {$it['index']} | 頁: {$it['page']} | 文字: " . trim($txt);
        }
        $system .= "\n下列為標記區塊摘要（供你理解上下文）：\n" . implode("\n---\n", $parts);
    }

    if (!$openai && !$googleKey) {
        respond(['ok'=>false,'error'=>'未設定任何 AI 金鑰 (OPENAI_API_KEY 或 GOOGLE_API_KEY)。請在系統環境或 Apache 設定中設定後重啟伺服器。']);
    }

    // OpenAI 路徑
    if ($openai) {
        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => array_merge([ ['role'=>'system','content'=>$system] ], $messages),
            'temperature' => 0.2,
            'max_tokens' => 800
        ];
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openai
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($err) {
            respond(['ok'=>false,'error'=>'cURL 錯誤','detail'=>$err,'curl_info'=>$info]);
        }

        // 若 HTTP code 非 200，回傳狀態與回應片段
        $http_code = $info['http_code'] ?? 0;
        if ($http_code < 200 || $http_code >= 300) {
            respond([
                'ok'=>false,
                'error'=>'OpenAI 回應非 2xx',
                'http_code'=>$http_code,
                'raw_snippet'=>snippet($resp, 1000),
                'note'=>'若回傳 HTML (以 "<" 開頭)，代表伺服器回傳錯誤頁面或代理攔截，請檢查 API key 與網路'
            ]);
        }

        // 嘗試解析 JSON；若不是 JSON（例如 HTML），回傳 snippet 幫助診斷
        $j = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            respond([
                'ok'=>false,
                'error'=>'OpenAI 回傳非 JSON',
                'http_code'=>$http_code,
                'raw_snippet'=>snippet($resp, 1000),
                'curl_info'=>$info
            ]);
        }

        // 處理正常回覆
        $reply = $j['choices'][0]['message']['content'] ?? null;
        if ($reply === null) {
            respond(['ok'=>false,'error'=>'OpenAI 回傳格式異常','raw_parsed'=>$j,'curl_info'=>$info]);
        }
        respond(['ok'=>true,'reply'=>trim($reply),'raw'=>$j]);
    }

    // Google 路徑（簡短處理）
    if ($googleKey) {
        $msgText = $system . "\n\n";
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $content = $m['content'] ?? '';
            $msgText .= strtoupper($role) . ": " . $content . "\n";
        }
        $url = 'https://generativelanguage.googleapis.com/v1/models/text-bison-001:generate?key=' . urlencode($googleKey);
        $payload = [
            'prompt' => ['text' => $msgText],
            'temperature' => 0.2,
            'maxOutputTokens' => 800
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($err) {
            respond(['ok'=>false,'error'=>'Google cURL 錯誤','detail'=>$err,'curl_info'=>$info]);
        }
        $http_code = $info['http_code'] ?? 0;
        if ($http_code < 200 || $http_code >= 300) {
            respond(['ok'=>false,'error'=>'Google API 非 2xx','http_code'=>$http_code,'raw_snippet'=>snippet($resp,1000)]);
        }
        $j = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            respond(['ok'=>false,'error'=>'Google 回傳非 JSON','raw_snippet'=>snippet($resp,1000),'curl_info'=>$info]);
        }
        // 擷取回覆文字（簡化）
        $text = null;
        if (!empty($j['candidates'][0])) {
            $c = $j['candidates'][0];
            $text = $c['output'] ?? $c['content'] ?? null;
        }
        if (!$text && !empty($j['results'][0]['content'])) {
            $parts = $j['results'][0]['content'];
            if (is_array($parts)) {
                $outs = '';
                foreach ($parts as $p) {
                    if (is_array($p) && isset($p['text'])) $outs .= $p['text'];
                    elseif (is_string($p)) $outs .= $p;
                }
                $text = $outs;
            }
        }
        if (!$text) {
            respond(['ok'=>false,'error'=>'Google 回傳無可解析文字','raw'=>$j,'curl_info'=>$info]);
        }
        respond(['ok'=>true,'reply'=>trim($text),'raw'=>$j]);
    }

    respond(['ok'=>false,'error'=>'未設定任何 AI 金鑰 (OPENAI_API_KEY 或 GOOGLE_API_KEY)']);
}

// items-only 模式（維持原功能）
if (!$items || !is_array($items)) {
    respond(['ok'=>false,'error'=>'缺少 items 或格式錯誤']);
}

// 以下維持原本 items-only 摘要流程（略過重複程式碼，保留原邏輯）
// 示範簡短回應以免此示範檔過長
$results = [];
foreach ($items as $it) {
    $results[] = ['index'=>$it['index']??0, 'page'=>$it['page']??0, 'summary'=>mb_substr(($it['text']??''),0,30), 'extracted_text'=>$it['text']??''];
}
respond(['ok'=>true,'results'=>$results]);
?>