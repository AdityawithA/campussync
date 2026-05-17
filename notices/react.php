<?php
// AJAX endpoint for toggling reactions on notices
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$uid       = (int)$_SESSION['user_id'];
$notice_id = isset($_POST['notice_id']) ? (int)$_POST['notice_id'] : 0;

if (!$notice_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid notice']);
    exit;
}

// Check existing reaction
$existing = $conn->query("SELECT id FROM reactions WHERE notice_id = $notice_id AND user_id = $uid")->fetch_assoc();

if ($existing) {
    $conn->query("DELETE FROM reactions WHERE notice_id = $notice_id AND user_id = $uid");
    $reacted = false;
} else {
    $stmt = $conn->prepare("INSERT INTO reactions (notice_id, user_id, type) VALUES (?, ?, 'like')");
    $stmt->bind_param('ii', $notice_id, $uid);
    $stmt->execute();
    $stmt->close();
    $reacted = true;
}

$count = $conn->query("SELECT COUNT(*) as cnt FROM reactions WHERE notice_id = $notice_id")->fetch_assoc()['cnt'];

echo json_encode(['success' => true, 'reacted' => $reacted, 'count' => $count]);
