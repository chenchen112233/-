<?php
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'missing id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mysqli = new mysqli('127.0.0.1', 'root', '', 'notebook');
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'error' => 'db connect error'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = $data['id'];
// ensure integer
if (!is_numeric($id)) {
    echo json_encode(['success' => false, 'error' => 'invalid id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $mysqli->prepare("DELETE FROM notes WHERE id = ?");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$mysqli->close();

if ($ok && $affected > 0) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'error' => 'not found or already deleted'], JSON_UNESCAPED_UNICODE);
}