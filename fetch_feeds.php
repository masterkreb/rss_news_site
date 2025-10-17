<?php
/**
 * RSS Feed Fetcher - Feed-IO v6.1+ (ohne Factory)
 * Kompatibel mit PHP 8.x
 *
 * Rechtlicher Hinweis:
 * - Dieser Aggregator verwendet öffentlich verfügbare RSS-Feeds
 * - Open Graph Images werden nur für Vorschaubilder verwendet
 * - Alle Inhalte verlinken zur Originalquelle
 * - Bei Beanstandungen kontaktieren Sie bitte den Betreiber
 */

require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use FeedIo\FeedIo;
use FeedIo\Adapter\Http\Client;
use Symfony\Component\HttpClient\Psr18Client;
use Psr\Log\NullLogger;

/**
 * Extrahiert Open Graph Image von einer URL
 * Wird verwendet für Feeds ohne Bilder (z.B. play3.de, xboxdynasty)
 */
function getOgImageFromUrl($url) {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'GamingNewsAggregator/1.0 (RSS Feed Reader; +https://github.com/yourusername/gaming-news)'
            ]
        ]);

        $html = @file_get_contents($url, false, $context);
        if (!$html) return null;

        // Suche nach og:image Meta-Tag
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return $matches[1];
        }

        // Alternative Reihenfolge: content vor property
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'][^>]*>/i', $html, $matches)) {
            return $matches[1];
        }

        // Fallback: twitter:image
        if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\'][^>]*>/i', $html, $matches)) {
            return $matches[1];
        }

    } catch (Exception $e) {
        // Fehler ignorieren
    }

    return null;
}

// Fehler für Entwicklung anzeigen
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Feed Update</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        h1 { color: #667eea; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .source { background: #667eea; color: white; padding: 5px 10px; border-radius: 5px; display: inline-block; margin: 10px 0; }
        hr { margin: 20px 0; }
        a { color: #667eea; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>";

echo "<h1>📡 Feed-Aktualisierung</h1>";
echo "<p><strong>Start:</strong> " . date('d.m.Y H:i:s') . "</p><hr>";

// Feed-IO initialisieren (mit PSR-18 Client!)
try {
    $psr18Client = new Psr18Client();
    $feedIo = new FeedIo(
        new Client($psr18Client),
        new NullLogger()
    );
    echo "<p class='success'>✅ Feed-IO erfolgreich initialisiert!</p><hr>";
} catch (Exception $e) {
    die("<p class='error'>❌ Feed-IO konnte nicht initialisiert werden: " . $e->getMessage() . "</p>");
}

// Liste der RSS-Feeds
$feeds = [
    ['url' => 'https://www.play3.de/feed/rss/', 'name' => 'Play3.de'],
    ['url' => 'https://www.gamestar.de/rss/gaming.rss', 'name' => 'GameStar Gaming News'],
    ['url' => 'https://www.gamestar.de/news/rss/news.rss', 'name' => 'GameStar News'],
    ['url' => 'https://www.buffed.de/feed.cfm', 'name' => 'Buffed'],
    ['url' => 'https://www.gamers.de/rss/', 'name' => 'gamers'],
    ['url' => 'https://www.xboxdynasty.de/cip_xd.rss.xml', 'name' => 'xboxdynasty'],
];

$conn = getDB();
$newArticles = 0;
$skippedArticles = 0;
$errors = [];

foreach ($feeds as $feedData) {
    $feedUrl = $feedData['url'];
    $sourceName = $feedData['name'];

    echo "<div class='source'>🔄 Verarbeite: $sourceName</div><br>";

    try {
        // Feed abrufen
        $result = $feedIo->read($feedUrl);
        $feed = $result->getFeed();

        $itemCount = 0;

        // Maximal 20 Artikel pro Feed verarbeiten
        $items = iterator_to_array($feed);
        $itemsToProcess = array_slice($items, 0, 20);

        foreach ($itemsToProcess as $item) {
            // Artikel-Daten extrahieren
            $title = $item->getTitle();
            $link = $item->getLink();

            // getSummary() statt getDescription()!
            $description = $item->getSummary();
            if (!$description) {
                // Fallback: Content holen
                $description = $item->getContent();
            }

            // Bild extrahieren - Mehrere Methoden versuchen!
            $imageUrl = null;

            // Methode 1: Media/Enclosure Tags
            $medias = $item->getMedias();
            if (!empty($medias)) {
                foreach ($medias as $media) {
                    $url = $media->getUrl();
                    $type = $media->getType();

                    // Prüfe ob es ein Bild ist (nach Typ oder URL-Endung)
                    if ($url && (
                            strpos($type, 'image') !== false ||
                            preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)
                        )) {
                        $imageUrl = $url;
                        break;
                    }
                }
            }

            // Methode 2: Aus Content extrahieren (Content enthält oft mehr als Summary)
            if (!$imageUrl) {
                $content = $item->getContent();
                if ($content) {
                    // Suche nach <img> Tags - nimm das erste Bild
                    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
                        $imageUrl = $matches[1];
                    }

                    // Alternative: Suche nach data-src (Lazy Loading)
                    if (!$imageUrl && preg_match('/<img[^>]+data-src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
                        $imageUrl = $matches[1];
                    }
                }
            }

            // Methode 3: Aus HTML der Beschreibung/Summary extrahieren
            if (!$imageUrl && $description) {
                // Suche nach <img> Tags im HTML
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $description, $matches)) {
                    $imageUrl = $matches[1];
                }

                // Alternative: data-src
                if (!$imageUrl && preg_match('/<img[^>]+data-src=["\']([^"\']+)["\'][^>]*>/i', $description, $matches)) {
                    $imageUrl = $matches[1];
                }
            }

            // Methode 4: URL bereinigen (relative URLs zu absoluten machen)
            if ($imageUrl) {
                // Entferne HTML-Entities
                $imageUrl = html_entity_decode($imageUrl);

                // Wenn URL relativ ist, mache sie absolut
                if (strpos($imageUrl, 'http') !== 0 && strpos($imageUrl, '//') !== 0) {
                    $parsedFeedUrl = parse_url($feedUrl);
                    $baseUrl = $parsedFeedUrl['scheme'] . '://' . $parsedFeedUrl['host'];
                    if (strpos($imageUrl, '/') === 0) {
                        $imageUrl = $baseUrl . $imageUrl;
                    } else {
                        $imageUrl = $baseUrl . '/' . $imageUrl;
                    }
                }

                // Wenn URL mit // beginnt, füge Schema hinzu
                if (strpos($imageUrl, '//') === 0) {
                    $imageUrl = 'https:' . $imageUrl;
                }
                // ⭐ NEU: GameStar Bilder vergrößern
                if (strpos($imageUrl, 'images.cgames.de') !== false || strpos($imageUrl, 'gamestar') !== false) {
                    // Ersetze kleine Bildgröße (z.B. /112/) mit größerer (z.B. /800/)
                    $imageUrl = preg_replace('/\/(\d{2,4})\//', '/800/', $imageUrl);
                }
            }

            // Methode 5: Für Feeds ohne Bilder (play3.de, xboxdynasty) -> Open Graph Image scrapen
            if (!$imageUrl && in_array($sourceName, ['Play3.de', 'xboxdynasty'])) {
                echo "<small style='color: #999;'>⏳ Hole Bild von Webseite...</small><br>";
                $imageUrl = getOgImageFromUrl($link);
                if ($imageUrl) {
                    echo "<small style='color: green;'>✓ Open Graph Bild gefunden!</small><br>";
                }
            }

            // Tags/Kategorien extrahieren
            $tags = '';
            $categories = $item->getCategories();
            if (!empty($categories)) {
                $tagList = [];
                foreach ($categories as $category) {
                    $tagList[] = $category->getLabel();
                }
                $tags = implode(',', array_slice($tagList, 0, 5)); // Max 5 Tags
            }

            // Beschreibung auf 1000 Zeichen begrenzen (HTML-Tags entfernen)
            $cleanDescription = strip_tags($description);
            if ($cleanDescription && strlen($cleanDescription) > 1000) {
                $cleanDescription = substr($cleanDescription, 0, 1000) . '...';
            }

            // Datum formatieren
            $pubDateObj = $item->getLastModified();
            if ($pubDateObj) {
                $pubDate = $pubDateObj->format('Y-m-d H:i:s');
            } else {
                $pubDate = date('Y-m-d H:i:s');
            }

            // GUID generieren (eindeutige ID)
            $guid = $item->getPublicId();
            if (!$guid) {
                $guid = md5($link); // Fallback: Hash des Links
            }

            // Prüfen ob Artikel bereits existiert
            $stmt = $conn->prepare("SELECT id FROM articles WHERE guid = ?");
            $stmt->bind_param("s", $guid);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                // Neuer Artikel - in Datenbank einfügen
                $stmt = $conn->prepare("INSERT INTO articles (title, link, description, pub_date, source, guid, image_url, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $title, $link, $cleanDescription, $pubDate, $sourceName, $guid, $imageUrl, $tags);

                if ($stmt->execute()) {
                    $newArticles++;
                    $itemCount++;

                    // Debug-Ausgabe für Bilder
                    if ($imageUrl) {
                        echo "<small style='color: #666;'>✓ Bild gefunden: " . htmlspecialchars(substr($imageUrl, 0, 50)) . "...</small><br>";
                    }
                } else {
                    $errors[] = "DB-Fehler bei '$title': " . $stmt->error;
                }
            } else {
                $skippedArticles++;
            }
            $stmt->close();
        }

        echo "<p class='success'>✅ $itemCount neue Artikel hinzugefügt</p>";
        echo "<p style='color: #666; font-size: 0.9em;'>Übersprungen: " . (count($itemsToProcess) - $itemCount) . "</p><br>";

    } catch (Exception $e) {
        $errorMsg = "Fehler bei $sourceName: " . $e->getMessage();
        $errors[] = $errorMsg;
        echo "<p class='error'>❌ $errorMsg</p><br>";
    }
}

$conn->close();

// Zusammenfassung
echo "<hr>";
echo "<h2>📊 Zusammenfassung</h2>";
echo "<table style='width: 100%; border-collapse: collapse;'>";
echo "<tr><td style='padding: 10px; border: 1px solid #ddd;'><strong>Neue Artikel:</strong></td><td style='padding: 10px; border: 1px solid #ddd;'>$newArticles</td></tr>";
echo "<tr><td style='padding: 10px; border: 1px solid #ddd;'><strong>Übersprungen (bereits vorhanden):</strong></td><td style='padding: 10px; border: 1px solid #ddd;'>$skippedArticles</td></tr>";
echo "<tr><td style='padding: 10px; border: 1px solid #ddd;'><strong>Verarbeitete Feeds:</strong></td><td style='padding: 10px; border: 1px solid #ddd;'>" . count($feeds) . "</td></tr>";
echo "</table>";

if (!empty($errors)) {
    echo "<h3 class='error'>❌ Fehler während der Verarbeitung:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p class='success'>✅ Alle Feeds erfolgreich verarbeitet!</p>";
}

echo "<p><strong>Ende:</strong> " . date('d.m.Y H:i:s') . "</p>";
echo "<hr>";
echo "<p><a href='index.php'>→ Zur Startseite</a> | <a href='fetch_feeds.php'>🔄 Feeds erneut abrufen</a></p>";

echo "</body></html>";
?>