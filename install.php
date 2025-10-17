<?php
/**
 * Installations-Script
 * Erstellt die Datenbank-Tabelle
 * WICHTIG: Nach einmaliger Ausführung diese Datei löschen!
 */

require_once 'config.php';

echo "<h1>🔧 Installation</h1>";

$conn = getDB();

// Tabelle für Artikel erstellen
$sql = "CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    link VARCHAR(1000) NOT NULL,
    description TEXT,
    pub_date DATETIME NOT NULL,
    source VARCHAR(100) NOT NULL,
    guid VARCHAR(500) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_pub_date (pub_date),
    INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✅ Tabelle 'articles' erfolgreich erstellt!</p>";
    echo "<p>Die Datenbank ist bereit.</p>";
} else {
    echo "<p style='color: red;'>❌ Fehler beim Erstellen der Tabelle: " . $conn->error . "</p>";
}

$conn->close();

echo "<hr>";
echo "<h3>Nächste Schritte:</h3>";
echo "<ol>";
echo "<li><strong>Diese Datei (install.php) löschen</strong> - aus Sicherheitsgründen</li>";
echo "<li><a href='fetch_feeds.php'>Feeds abrufen (fetch_feeds.php)</a></li>";
echo "<li><a href='index.php'>Zur Startseite (index.php)</a></li>";
echo "</ol>";
?>