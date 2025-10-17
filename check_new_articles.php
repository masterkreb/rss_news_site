<?php
/**
 * Check for new articles (for auto-refresh notification)
 * Returns JSON with current article count
 */

require_once 'config.php';
header('Content-Type: application/json');

$conn = getDB();
$result = $conn->query("SELECT COUNT(*) as total FROM articles");
$row = $result->fetch_assoc();

echo json_encode(['count' => (int)$row['total']]);

$conn->close();
?>
