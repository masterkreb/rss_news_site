<?php
require_once 'config.php';
$conn = getDB();
$conn->query("DELETE FROM articles");
echo "✅ Alle Artikel gelöscht!<br>";
echo "<a href='fetch_feeds.php'>→ Feeds neu laden</a>";
$conn->close();
?>
