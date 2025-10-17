<?php
/**
 * Get favorite articles by IDs
 * Returns JSON array of article IDs that exist in DB
 */

require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_GET['ids']) || empty($_GET['ids'])) {
    echo json_encode([]);
    exit;
}

$ids = explode(',', $_GET['ids']);
$ids = array_map('intval', $ids);
$ids = array_filter($ids, function($id) { return $id > 0; });

if (empty($ids)) {
    echo json_encode([]);
    exit;
}

$conn = getDB();

// Build placeholders
$placeholders = str_repeat('?,', count($ids) - 1) . '?';
$sql = "SELECT id, title, description, link, pub_date, source, image_url, tags 
        FROM articles 
        WHERE id IN ($placeholders)
        ORDER BY pub_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$result = $stmt->get_result();

$articles = [];
while ($row = $result->fetch_assoc()) {
    $articles[] = $row;
}

echo json_encode($articles);

$conn->close();
?>