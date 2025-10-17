<?php
/**
 * Datenbank-Konfiguration
 * XAMPP Standard-Einstellungen
 */

// XAMPP lokale Datenbank
define('DB_HOST', 'localhost');
define('DB_USER', 'root');              // XAMPP Standard
define('DB_PASS', '');                  // XAMPP hat kein Passwort
define('DB_NAME', 'news_aggregator');

/**
 * Datenbank-Verbindung herstellen
 */
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Verbindung prüfen
    if ($conn->connect_error) {
        die("❌ Datenbankverbindung fehlgeschlagen: " . $conn->connect_error);
    }

    // UTF-8 Zeichensatz setzen
    $conn->set_charset("utf8mb4");

    return $conn;
}

/**
 * Basis-URL für Links (optional)
 */
define('BASE_URL', 'http://localhost/news-aggregator/');
?>