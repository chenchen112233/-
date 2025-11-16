<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$html = $input['html'] ?? '';
$text = $input['text'] ?? '';
$filename = $input['filename'] ?? '';

if (trim($text) === '' && trim($html) === '') {
    echo json_encode(['success'=>false,'error'=>'內容為空'], JSON_UNESCAPED_UNICODE);
    exit;
}

$promptBase = "你是中文文件排版助手。請把下列文字整理為乾淨的 HTML 段落與標題，保留原有內容意思，不新增說明文字。回傳僅包含 HTML 片段（不要包 <html> <body>）。\n\n內容：\n" . ($html ?: $text) . "\n\n請回傳結果。";

/**
 * 簡單排版（本地 fallback）
 */
function simple_format_html($raw) {
    if (preg_match('/<[^>]+>/', $raw)) {
        $s = preg_replace('/\r\n|\r/', "\n", $raw);
        $s = preg_replace("/\n{2,}/", "\n\n", $s);
        $paras = preg_split("/\n{2}/", trim($s));
        $out = '';
        foreach ($paras as $p) {
            $p = trim(preg_replace('/\n+/', ' ', $p));
            $out .= '<p>' . htmlspecialchars($p, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        return $out;
    }
    $s = str_replace(["\r\n","\r"], "\n", $raw);
    $paras = preg_split("/\n{2,}/", trim($s));
    $out = '';
    foreach ($paras as $p) {
        $p = trim($p);
        $p = htmlspecialchars($p, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $p = nl2br($p);
        $out .= '<p style="white-space:pre-wrap; margin-bottom:1em;">' . $p . '</p>';
    }
    return $out;
}

/**
 * 清理與合併斷行 / 日期 / 實體等
 */
function cleanup_formatted_html(string $s): string {
    // decode HTML entities (把 &lt;mark&gt; 恢復成 <mark> 等)
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // normalize newlines
    $s = str_replace(["\r\n", "\r"], "\n", $s);

    // 將連續多個中文字因換行而斷行的情況合併（中\n華 -> 中華）
    $s = preg_replace('/(?<=\p{Han})\s*\n\s*(?=\p{Han})/u', '', $s);

    // 合併年份/月/日被斷行或空白分隔的情況
    $s = preg_replace_callback('/(\d{2,4})\s*\n*\s*年\s*\n*\s*(\d{1,2})\s*\n*\s*月\s*\n*\s*(\d{1,2})\s*\n*\s*日/u',
        function($m){ return $m[1].'年'.intval($m[2]).'月'.intval($m[3]).'日'; }, $s);

    // 數字換行數字合併（避免 113 \n 7 -> 1137 的極端情況先保守處理只在日月年上下文處理上面）
    // 但保留一般分行
    // 移除中文字之間的多餘空格（避免 "台 北"）
    $s = preg_replace('/(?<=\p{Han})\s+(?=\p{Han})/u', '', $s);

    // 移除重複空行（保留最多一個空行作為段落分隔）
    $s = preg_replace("/\n{3,}/", "\n\n", $s);

    // 允許的標籤（保留 mark, p, br, strong, em, ul, ol, li, h1-h3, a）
    $allowed = '<p><br><strong><em><mark><ul><ol><li><h1><h2><h3><a>';
    $s = strip_tags($s, $allowed);

    // 若最終只有文字（無 <p> 與 <br>），把多段落包成 <p>
    if (strpos($s, '<p') === false && strpos($s, '<br') === false) {
        $parts = preg_split("/\n{2,}/", trim($s));
        $out = '';
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            // 保留行內換行為 <br>
            $p = nl2br(htmlspecialchars_decode($p, ENT_QUOTES | ENT_HTML5));
            $out .= '<p style="white-space:pre-wrap; margin-bottom:1em;">' . $p . '</p>';
        }
        if ($out !== '') $s = $out;
    }

    return $s;
}

/**
 * 取得金鑰（請於 Apache 環境變數或系統 env 設定 GOOGLE_API_KEY 或 OPENAI_API_KEY）
 */
$googleKey = getenv('GOOGLE_API_KEY') ?: null;
$openaiKey  = getenv('OPENAI_API_KEY') ?: null;

/**
 * 若有 Google Key，優先呼叫 Google Generative API
 */
if ($googleKey) {
    $url = 'https://generativelanguage.googleapis.com/v1beta2/models/text-bison-001:generate?key=' . urlencode($googleKey);
    $payload = [
        'prompt' => ['text' => $promptBase],
        'temperature' => 0.2,
        'maxOutputTokens' => 1200
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $err) {
        echo json_encode(['success'=>false,'error'=>'呼叫 Google Generative API 失敗: ' . $err], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $json = json_decode($resp, true);
    $formatted = null;
    if (!empty($json['candidates']) && is_array($json['candidates'])) {
        $cand = $json['candidates'][0];
        $formatted = $cand['output'] ?? $cand['content'] ?? $cand['outputText'] ?? $cand['text'] ?? null;
    }
    if (!$formatted && !empty($json['results'][0]['content'])) {
        $parts = $json['results'][0]['content'];
        if (is_array($parts)) {
            $out = '';
            foreach ($parts as $p) {
                if (isset($p['text'])) $out .= $p['text'];
                elseif (is_string($p)) $out .= $p;
            }
            $formatted = $out;
        }
    }
    if (!$formatted) {
        // 若 Google 回傳格式不符合，回傳部分 resp 供診斷（截短）
        echo json_encode(['success'=>false,'error'=>'Google 回傳格式未含預期欄位，請檢查 API 回應: '.substr($resp,0,500)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $clean = cleanup_formatted_html($formatted);
    echo json_encode(['success'=>true,'formatted_html'=>$clean], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 若有 OpenAI key 則呼叫 OpenAI
 */
if ($openaiKey) {
    $payload = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role'=>'system','content'=>'你是一個專注於中文排版與段落整理的工具。'],
            ['role'=>'user','content'=>$promptBase]
        ],
        'temperature' => 0.2,
        'max_tokens' => 1500
    ];
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $err) {
        echo json_encode(['success'=>false,'error'=>'呼叫 OpenAI 失敗: ' . $err], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $json = json_decode($resp, true);
    if (!isset($json['choices'][0]['message']['content'])) {
        echo json_encode(['success'=>false,'error'=>'OpenAI 回傳格式異常'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $formatted = $json['choices'][0]['message']['content'];
    $clean = cleanup_formatted_html($formatted);
    echo json_encode(['success'=>true,'formatted_html'=>$clean], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 無金鑰使用本地 fallback
 */
$formatted = simple_format_html($html ?: $text);
$clean = cleanup_formatted_html($formatted);
echo json_encode(['success'=>true,'formatted_html'=>$clean], JSON_UNESCAPED_UNICODE);