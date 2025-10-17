<?php
require_once 'config.php';
$conn = getDB();

echo "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <title>Datenbank Reset</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        a { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
        a:hover { background: #5568d3; }
    </style>
</head>
<body>";

echo "<h1>🗑️ Datenbank Reset</h1>";

// Anzahl Artikel vorher
$count = $conn->query("SELECT COUNT(*) as total FROM articles")->fetch_assoc()['total'];
echo "<p class='warning'>Aktuell in DB: $count Artikel</p>";

// Alle Artikel löschen
$conn->query("DELETE FROM articles");
echo "<p class='success'>✅ Alle Artikel gelöscht!</p>";

// Auto-Increment zurücksetzen
$conn->query("ALTER TABLE articles AUTO_INCREMENT = 1");
echo "<p class='success'>✅ ID-Zähler zurückgesetzt!</p>";

$conn->close();

echo "<hr>";
echo "<p><strong>Nächster Schritt:</strong> Feeds neu laden, damit die Artikel mit Bildern eingefügt werden.</p>";
echo "<a href='fetch_feeds.php'>🔄 Feeds jetzt neu laden</a>";
echo "</body></html>";
?>