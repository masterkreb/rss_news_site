<?php
require_once __DIR__ . '/vendor/autoload.php';

use FeedIo\Factory;

echo "<h2>🔍 Feed-IO Diagnose</h2>";

// Test 1: Autoloader
echo "✅ Autoloader geladen!<br><br>";

// Test 2: Prüfe ob Klassen existieren
echo "<h3>Test 2: Klassen-Check</h3>";
echo "FeedIo\\Factory existiert? " . (class_exists('FeedIo\\Factory') ? '✅ JA' : '❌ NEIN') . "<br>";
echo "FeedIo\\FeedIo existiert? " . (class_exists('FeedIo\\FeedIo') ? '✅ JA' : '❌ NEIN') . "<br><br>";

// Test 3: Versuche Factory zu laden
echo "<h3>Test 3: Factory laden</h3>";
try {
    $feedIo = Factory::create()->getFeedIo();
    echo "✅ Feed-IO erfolgreich initialisiert!<br>";
} catch (Error $e) {
    echo "❌ Fehler: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

// Test 4: Alle FeedIo Klassen
echo "<h3>Test 4: Alle geladenen FeedIo-Klassen</h3>";
$classes = get_declared_classes();
$feedIoClasses = array_filter($classes, function($class) {
    return stripos($class, 'FeedIo') !== false;
});

if (empty($feedIoClasses)) {
    echo "❌ KEINE FeedIo Klassen geladen!<br>";
} else {
    echo "✅ " . count($feedIoClasses) . " Klassen gefunden:<br>";
    foreach (array_slice($feedIoClasses, 0, 10) as $class) {
        echo "- $class<br>";
    }
    if (count($feedIoClasses) > 10) {
        echo "... und " . (count($feedIoClasses) - 10) . " weitere<br>";
    }
}

// Test 5: Composer.json
echo "<h3>Test 5: Composer-Konfiguration</h3>";
$composerJson = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);
echo "<strong>Installierte Packages:</strong><br>";
echo "<pre>";
print_r($composerJson['require'] ?? 'Keine requires gefunden');
echo "</pre>";

// Test 6: Autoload-Dateien prüfen
echo "<h3>Test 6: Autoload-Dateien</h3>";
$autoloadFile = __DIR__ . '/vendor/composer/autoload_classmap.php';
if (file_exists($autoloadFile)) {
    $classmap = include $autoloadFile;
    $feedIoEntries = array_filter(array_keys($classmap), function($class) {
        return stripos($class, 'FeedIo') !== false;
    });
    echo "Einträge in Classmap mit 'FeedIo': " . count($feedIoEntries) . "<br>";
    if (count($feedIoEntries) > 0) {
        echo "Beispiele (erste 5):<br>";
        foreach (array_slice($feedIoEntries, 0, 5) as $entry) {
            echo "- $entry<br>";
        }
    } else {
        echo "❌ Keine FeedIo-Einträge in der Classmap!<br>";
    }
} else {
    echo "❌ Classmap nicht gefunden!<br>";
}

// Test 7: PSR-4 Autoload prüfen
echo "<h3>Test 7: PSR-4 Autoloading</h3>";
$psr4File = __DIR__ . '/vendor/composer/autoload_psr4.php';
if (file_exists($psr4File)) {
    $psr4 = include $psr4File;
    echo "Registrierte Namespaces:<br>";
    foreach ($psr4 as $namespace => $paths) {
        if (stripos($namespace, 'FeedIo') !== false) {
            echo "✅ <strong>$namespace</strong> → " . implode(', ', $paths) . "<br>";
        }
    }
} else {
    echo "❌ PSR-4 Datei nicht gefunden!<br>";
}

echo "<hr>";
echo "<h3>📊 Zusammenfassung</h3>";
if (class_exists('FeedIo\\Factory')) {
    echo "✅ <strong>Feed-IO ist korrekt installiert!</strong><br>";
} else {
    echo "❌ <strong>Feed-IO ist NICHT korrekt installiert!</strong><br>";
    echo "Bitte führe aus: <code>composer require debril/feed-io</code><br>";
}
?>