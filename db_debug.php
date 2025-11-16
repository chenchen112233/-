<?php
header('Content-Type: text/plain; charset=utf-8');
$mysqli = new mysqli('127.0.0.1', 'root', '', 'notebook');
if ($mysqli->connect_errno) {
    echo "Connect failed: " . $mysqli->connect_error . "\n";
    exit;
}
echo "mysqli client charset: " . $mysqli->character_set_name() . "\n\n";

// Show MySQL server character set variables
$res = $mysqli->query("SHOW VARIABLES LIKE 'character\_set\_%'");
while ($row = $res->fetch_assoc()) {
    echo $row['Variable_name'] . " = " . $row['Value'] . "\n";
}
echo "\n";
$res = $mysqli->query("SHOW VARIABLES LIKE 'collation\_server'");
while ($row = $res->fetch_assoc()) {
    echo $row['Variable_name'] . " = " . $row['Value'] . "\n";
}

// Table / column collations
$stmt = $mysqli->prepare("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'notes'");
$db = 'notebook';
$stmt->bind_param('s', $db);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo "\nnotes table collation: " . ($row['TABLE_COLLATION'] ?? 'NULL') . "\n";
} else {
    echo "\nnotes table not found\n";
}

echo "\nnotes columns collations:\n";
$stmt = $mysqli->prepare("SELECT COLUMN_NAME, COLLATION_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'notes'");
$stmt->bind_param('s', $db);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    echo "  " . $row['COLUMN_NAME'] . " -> collation=" . ($row['COLLATION_NAME'] ?? 'NULL') . ", charset=" . ($row['CHARACTER_SET_NAME'] ?? 'NULL') . "\n";
}

// Show first row raw hex and as fetched
echo "\nFirst row (raw hex and as-fetched):\n";
$res = $mysqli->query("SELECT id, title, content, HEX(title) AS title_hex, HEX(content) AS content_hex FROM notes ORDER BY id DESC LIMIT 1");
if ($res && ($r = $res->fetch_assoc())) {
    echo "id=" . $r['id'] . "\n";
    echo "title (as fetched): " . $r['title'] . "\n";
    echo "title HEX: " . ($r['title_hex'] ?? '') . "\n";
    echo "content (as fetched): " . $r['content'] . "\n";
    echo "content HEX: " . ($r['content_hex'] ?? '') . "\n";

    // PHP-level encoding checks
    echo "\nPHP mb_check_encoding(title, 'UTF-8') = " . (mb_check_encoding($r['title'], 'UTF-8') ? 'true' : 'false') . "\n";
    echo "PHP mb_detect_encoding(title) = " . mb_detect_encoding($r['title'], ['UTF-8','GBK','CP936','ISO-8859-1','Windows-1252'], true) . "\n";
    echo "\nPHP mb_check_encoding(content, 'UTF-8') = " . (mb_check_encoding($r['content'], 'UTF-8') ? 'true' : 'false') . "\n";
    echo "PHP mb_detect_encoding(content) = " . mb_detect_encoding($r['content'], ['UTF-8','GBK','CP936','ISO-8859-1','Windows-1252'], true) . "\n";

    // Try converting from common encodings to UTF-8 and show result
    $try = ['UTF-8','GBK','CP936','ISO-8859-1','Windows-1252'];
    echo "\nTry converting title from common encodings:\n";
    foreach ($try as $enc) {
        $converted = @mb_convert_encoding($r['title'], 'UTF-8', $enc);
        echo "  from $enc -> " . $converted . "\n";
    }
    echo "\nTry converting content from common encodings:\n";
    foreach ($try as $enc) {
        $converted = @mb_convert_encoding($r['content'], 'UTF-8', $enc);
        echo "  from $enc -> " . substr($converted,0,200) . "\n";
    }
} else {
    echo "no rows in notes table\n";
}

$mysqli->close();

?>