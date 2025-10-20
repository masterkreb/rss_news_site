<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
$conn = getDB();

// Pagination
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Filter nach Quelle
$sourceFilter = isset($_GET['source']) ? $_GET['source'] : '';

// Favoriten-Filter (NEU)
$favoritesFilter = isset($_GET['favorites']) && $_GET['favorites'] == '1';

// Variablen initialisieren (WICHTIG!)
$where = [];
$params = [];
$types = '';

// Favoriten aus Cookie laden (HIERHIN!)
$isFavoritesView = isset($_GET['favorites']) && $_GET['favorites'] == '1';
$favoriteIds = [];
if (isset($_COOKIE['favorites'])) {
    $favoriteIds = json_decode($_COOKIE['favorites'], true);
    if (!is_array($favoriteIds)) {
        $favoriteIds = [];
    }
}

$favoriteCount = count($favoriteIds);

// Suchfunktion
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Datum-Filter
$dateFilter = isset($_GET['date']) ? $_GET['date'] : 'all';

if ($sourceFilter) {
    $where[] = "source = ?";
    $params[] = $sourceFilter;
    $types .= 's';
}

if ($searchQuery) {
    $where[] = "(title LIKE ? OR description LIKE ? OR tags LIKE ?)";
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

// Datum-Filter anwenden
if ($dateFilter != 'all') {
    switch ($dateFilter) {
        case 'today':
            $where[] = "DATE(pub_date) = CURDATE()";
            break;
        case 'yesterday':
            $where[] = "DATE(pub_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case '3days':
            $where[] = "pub_date >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
            break;
    }
}

// Monats-Archiv laden
$monthFilter = isset($_GET['month']) ? $_GET['month'] : '';
$archiveMonths = $conn->query("SELECT DATE_FORMAT(pub_date, '%Y-%m') as month, COUNT(*) as count FROM articles GROUP BY month HAVING count > 0 ORDER BY month DESC LIMIT 12");
// Monats-Filter anwenden
if ($monthFilter && preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
    $where[] = "DATE_FORMAT(pub_date, '%Y-%m') = ?";
    $params[] = $monthFilter;
    $types .= 's';
}

// Favoriten-Filter hinzufügen (AN DIE RICHTIGE STELLE!)
if ($favoritesFilter) {
    if (!empty($favoriteIds)) {
        // Normale Favoriten-Filter
        $placeholders = implode(',', array_fill(0, count($favoriteIds), '?'));
        $where[] = "id IN ($placeholders)";
        foreach ($favoriteIds as $favId) {
            $params[] = $favId;
            $types .= 'i';
        }
    } else {
        // Keine Favoriten = zeige nichts
        $where[] = "1 = 0";  // Impossible condition
    }
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT * FROM articles $whereClause ORDER BY pub_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

// Bind parameters
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Quellen für Filter
$sources = $conn->query("SELECT DISTINCT source FROM articles ORDER BY source");

// Gesamtanzahl
$countParams = [];
$countTypes = '';

// Favoriten-IDs auch für COUNT hinzufügen (NEU!)
if ($favoritesFilter && !empty($favoriteIds)) {
    foreach ($favoriteIds as $favId) {
        $countParams[] = $favId;
        $countTypes .= 'i';
    }
}

if ($sourceFilter) {
    $countParams[] = $sourceFilter;
    $countTypes .= 's';
}
if ($searchQuery) {
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= 'sss';
}

// Monats-Filter auch für COUNT hinzufügen
if ($monthFilter && preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
    $countParams[] = $monthFilter;
    $countTypes .= 's';
}

$countSql = "SELECT COUNT(*) as total FROM articles $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalArticles = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalArticles / $perPage);

// Theme aus Cookie für initiales Rendering
$currentTheme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
$sakuraPetalsActive = isset($_COOKIE['sakuraPetals']) ? ($_COOKIE['sakuraPetals'] === 'true') : false;
$currentLayout = isset($_COOKIE['layout']) ? $_COOKIE['layout'] : 'grid';

$themeConfig = [ // ← DIESE ZEILEN FEHLEN!
        'light' => ['icon' => '☀️', 'text' => 'Light'],
        'dark' => ['icon' => '🌙', 'text' => 'Dark'],
        'sakura-light' => ['icon' => '🌸', 'text' => 'Sakura Light'],
        'sakura-dark' => ['icon' => '🌸', 'text' => 'Sakura Dark']
];

$layoutConfig = [ // NEU
        'grid' => ['icon' => '▦', 'text' => 'Grid'],
        'list' => ['icon' => '☰', 'text' => 'Liste'],
        'compact' => ['icon' => '≡', 'text' => 'Kompakt']
];
?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🎮 Gaming News Aggregator</title>
        <!-- Prevent Theme Flash: Load theme IMMEDIATELY -->
        <script>
            (function() {
                const savedTheme = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-theme', savedTheme);
            })();
        </script>
        <link rel="stylesheet" href="./css/base.css">
        <link rel="stylesheet" href="./css/themes.css">
    </head>
    <body>
    <div class="container">
        <header>
            <div class="layout-toggle-hybrid">
                <div class="layout-current" onclick="cycleLayout()">
                    <span id="layout-icon"><?php echo $layoutConfig[$currentLayout]['icon']; ?></span>
                    <span id="layout-text"><?php echo $layoutConfig[$currentLayout]['text']; ?></span>
                </div>
                <div class="layout-dropdown-arrow" onclick="toggleDropdown()">
                    <span>▼</span>
                </div>

                <div class="layout-dropdown-menu" id="layoutDropdown">
                    <div class="layout-option-hybrid active" onclick="event.stopPropagation(); changeLayout('grid')">
                        <span>▦</span> Grid
                    </div>
                    <div class="layout-option-hybrid" onclick="event.stopPropagation(); changeLayout('list')">
                        <span>☰</span> Liste
                    </div>
                    <div class="layout-option-hybrid" onclick="event.stopPropagation(); changeLayout('compact')">
                        <span>≡</span> Kompakt
                    </div>
                </div>
            </div>

            <div class="theme-toggle-hybrid">
                <div class="theme-current" onclick="cycleTheme()">
                    <span id="theme-icon"><?php echo $themeConfig[$currentTheme]['icon']; ?></span>
                    <span id="theme-text"><?php echo $themeConfig[$currentTheme]['text']; ?></span>
                </div>
                <div class="theme-dropdown-arrow" onclick="toggleThemeDropdown()">
                    <span>▼</span>
                </div>

                <div class="theme-dropdown-menu" id="themeDropdown">
                    <div class="theme-option active" onclick="event.stopPropagation(); changeTheme('light')">
                        <span>☀️</span> Light Mode
                    </div>
                    <div class="theme-option" onclick="event.stopPropagation(); changeTheme('dark')">
                        <span>🌙</span> Dark Mode
                    </div>
                    <div class="theme-option" onclick="event.stopPropagation(); changeTheme('sakura-light')">
                        <span>🌸</span> Sakura Light
                    </div>
                    <div class="theme-option" onclick="event.stopPropagation(); changeTheme('sakura-dark')">
                        <span>🌸</span> Sakura Dark
                    </div>
                </div>
            </div>

            <button class="sakura-petals-toggle <?php echo $sakuraPetalsActive ? 'active' : ''; ?>" id="sakuraPetalsToggle" onclick="toggleSakuraPetals()" title="Sakura Blütenblätter Animation">
                <span class="petals-label">
                    <span id="petals-icon">🌸</span>
                    <span id="petals-text">Blütenblätter</span>
                </span>
                <span class="toggle-switch">
                    <span class="toggle-slider"></span>
                </span>
            </button>

            <h1>🎮 Gaming News Aggregator</h1>
            <p>Die neuesten Gaming-News auf einen Blick</p>
            <small>Gesamt: <?php echo number_format($totalArticles, 0, ',', '.'); ?> Artikel<?php if($searchQuery) echo ' für "'.htmlspecialchars($searchQuery).'"'; ?></small>
        </header>

        <!-- Suchfunktion -->
        <div class="search-container">
            <div class="search-box">
                <form method="GET" action="index.php" class="search-form">
                    <input type="text"
                           name="search"
                           class="search-input"
                           placeholder="🔍 Suche nach Titel, Beschreibung oder Tags..."
                           value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <?php if ($sourceFilter): ?>
                        <input type="hidden" name="source" value="<?php echo htmlspecialchars($sourceFilter); ?>">
                    <?php endif; ?>
                    <button type="submit" class="search-btn">Suchen</button>
                    <?php if ($searchQuery || $sourceFilter): ?>
                        <a href="index.php" class="clear-search">✕ Zurücksetzen</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Filter Container -->
            <div class="filter-container">
                <!-- Zeit-Filter Toggle -->
                <div class="filter-toggle-group">
                    <div class="filter-toggle" onclick="toggleFilterDropdown('timeDropdown')">
            <span id="time-text"><?php
                if ($dateFilter == 'today') echo 'Heute';
                elseif ($dateFilter == 'yesterday') echo 'Gestern';
                elseif ($dateFilter == '3days') echo 'Letzte 3 Tage';
                else echo 'Alle Zeiten';
                ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="filter-dropdown" id="timeDropdown">
                        <a href="?date=all<?php echo $sourceFilter ? '&source='.urlencode($sourceFilter) : ''; ?><?php echo $monthFilter ? '&month='.$monthFilter : ''; ?>"
                           class="filter-option <?php echo $dateFilter == 'all' && !$monthFilter ? 'active' : ''; ?>">
                            Alle Zeiten
                        </a>
                        <a href="?date=today<?php echo $sourceFilter ? '&source='.urlencode($sourceFilter) : ''; ?><?php echo $monthFilter ? '&month='.$monthFilter : ''; ?>"
                           class="filter-option <?php echo $dateFilter == 'today' ? 'active' : ''; ?>">
                            Heute
                        </a>
                        <a href="?date=yesterday<?php echo $sourceFilter ? '&source='.urlencode($sourceFilter) : ''; ?><?php echo $monthFilter ? '&month='.$monthFilter : ''; ?>"
                           class="filter-option <?php echo $dateFilter == 'yesterday' ? 'active' : ''; ?>">
                            Gestern
                        </a>
                        <a href="?date=3days<?php echo $sourceFilter ? '&source='.urlencode($sourceFilter) : ''; ?><?php echo $monthFilter ? '&month='.$monthFilter : ''; ?>"
                           class="filter-option <?php echo $dateFilter == '3days' ? 'active' : ''; ?>">
                            Letzte 3 Tage
                        </a>
                    </div>
                </div>

                <!-- Archiv-Filter Toggle -->
                <div class="filter-toggle-group">
                    <div class="filter-toggle" onclick="toggleFilterDropdown('archiveDropdown')">
            <span id="archive-text"><?php
                if ($monthFilter) {
                    $year = substr($monthFilter, 0, 4);
                    $mon = substr($monthFilter, 5, 2);
                    $months_de = ['01'=>'Jan','02'=>'Feb','03'=>'Mär','04'=>'Apr','05'=>'Mai','06'=>'Jun',
                            '07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Dez'];
                    echo $months_de[$mon] . ' ' . $year;
                } else {
                    echo 'Archiv';
                }
                ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="filter-dropdown" id="archiveDropdown">
                        <a href="?date=<?php echo $dateFilter; ?><?php echo $sourceFilter ? '&source='.urlencode($sourceFilter) : ''; ?>"
                           class="filter-option <?php echo !$monthFilter ? 'active' : ''; ?>">
                            Kein Archiv
                        </a>
                        <?php
                        $archiveMonths->data_seek(0);
                        $months_de = ['01'=>'Januar','02'=>'Februar','03'=>'März','04'=>'April','05'=>'Mai','06'=>'Juni',
                                '07'=>'Juli','08'=>'August','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Dezember'];
                        while($month = $archiveMonths->fetch_assoc()):
                            $year = substr($month['month'], 0, 4);
                            $mon = substr($month['month'], 5, 2);
                            $label = $months_de[$mon] . ' ' . $year;
                            ?>
                            <a href="?month=<?php echo $month['month']; ?><?php echo $sourceFilter ? '&source='.urlencode($sourceFilter) : ''; ?><?php echo $dateFilter != 'all' ? '&date='.$dateFilter : ''; ?>"
                               class="filter-option <?php echo $monthFilter == $month['month'] ? 'active' : ''; ?>">
                                <?php echo $label; ?>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Quellen-Filter Toggle -->
                <div class="filter-toggle-group">
                    <div class="filter-toggle" onclick="toggleFilterDropdown('sourceDropdown')">
                        <span id="source-text"><?php echo $sourceFilter ? htmlspecialchars($sourceFilter) : 'Alle Quellen'; ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="filter-dropdown" id="sourceDropdown">
                        <a href="?<?php echo $dateFilter != 'all' ? 'date='.$dateFilter : ''; ?><?php echo $monthFilter ? '&month='.$monthFilter : ''; ?>"
                           class="filter-option <?php echo !$sourceFilter ? 'active' : ''; ?>">
                            Alle Quellen
                        </a>
                        <?php
                        $sources->data_seek(0);
                        while($source = $sources->fetch_assoc()):
                            ?>
                            <a href="?source=<?php echo urlencode($source['source']); ?><?php echo $dateFilter != 'all' ? '&date='.$dateFilter : ''; ?><?php echo $monthFilter ? '&month='.$monthFilter : ''; ?>"
                               class="filter-option <?php echo $sourceFilter == $source['source'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($source['source']); ?>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Favoriten Button (separater Button) -->
                <a href="?favorites=1" class="filter-btn-favorites <?php echo isset($_GET['favorites']) ? 'active-filter' : ''; ?>" id="favoritesBtn">
                    ⭐ Favoriten (<span id="favCount"><?php echo $favoriteCount; ?></span>)
                </a>

            </div> <!-- Ende filter-container -->
            </div>

            <!-- Articles -->
            <div class="articles <?php
            if ($currentLayout === 'list') echo 'list-view';
            elseif ($currentLayout === 'compact') echo 'compact-view';
            ?>">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($article = $result->fetch_assoc()): ?>
                        <article class="article-card" style="position: relative;">
                            <?php if (!empty($article['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($article['image_url']); ?>"
                                     alt="<?php echo htmlspecialchars($article['title']); ?>"
                                     class="article-image"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="article-image no-image" style="display:none;">🎮</div>
                            <?php else: ?>
                                <div class="article-image no-image">🎮</div>
                            <?php endif; ?>

                            <div class="article-content">
                                <div class="article-meta">
                                    <span class="date">
                                    <?php
                                    $date = new DateTime($article['pub_date']);
                                    echo $date->format('d.m.Y H:i');
                                    ?> Uhr
                                    </span>
                                    <span class="source"><?php echo htmlspecialchars($article['source']); ?></span>
                                    <span class="favorite-star"
                                          onclick="toggleFavorite(<?php echo $article['id']; ?>)"
                                          data-article-id="<?php echo $article['id']; ?>"
                                          title="Zu Favoriten hinzufügen">
                                        ☆
                                    </span>
                                </div>

                                <h2>
                                    <a href="<?php echo htmlspecialchars($article['link']); ?>"
                                       target="_blank"
                                       rel="noopener noreferrer">
                                        <?php echo htmlspecialchars($article['title']); ?>
                                    </a>
                                </h2>

                                <?php if (!empty($article['description'])): ?>
                                    <p class="description">
                                        <?php
                                        $desc = strip_tags($article['description']);
                                        echo mb_substr($desc, 0, 150) . (mb_strlen($desc) > 150 ? '...' : '');
                                        ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($article['tags'])): ?>
                                    <div class="tags">
                                        <?php
                                        $tags = explode(',', $article['tags']);
                                        foreach (array_slice($tags, 0, 5) as $tag):
                                            $trimmedTag = trim($tag);
                                            ?>
                                            <a href="?search=<?php echo urlencode($trimmedTag); ?>"
                                               class="tag"
                                               title="Nach '<?php echo htmlspecialchars($trimmedTag); ?>' suchen">
                                                <?php echo htmlspecialchars($trimmedTag); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-articles">
                        <p>😢 Noch keine Artikel vorhanden.</p>
                        <button onclick="updateFeeds()" class="update-btn">Feeds jetzt abrufen</button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $sourceFilter ? '&source=' . urlencode($sourceFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $dateFilter != 'all' ? '&date='.$dateFilter : ''; ?>"                           class="page-link">
                            ← Vorherige
                        </a>
                    <?php endif; ?>

                    <span class="page-info">
                    Seite <?php echo $page; ?> von <?php echo $totalPages; ?>
                </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $sourceFilter ? '&source=' . urlencode($sourceFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $dateFilter != 'all' ? '&date='.$dateFilter : ''; ?>"                           class="page-link">
                            Nächste →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <footer>
                <p>Letztes Update: <?php echo date('d.m.Y H:i:s'); ?></p>
                <button onclick="updateFeeds()" class="update-btn" id="update-btn">
                    🔄 Feeds aktualisieren
                </button>
                <div id="update-status"></div>

                <hr style="margin: 20px 0; border: none; border-top: 1px solid var(--border);">
                <p style="font-size: 0.85em; color: var(--text-secondary); line-height: 1.6;">
                    ℹ️ Alle Inhalte, Bilder und Marken sind Eigentum der jeweiligen Quellen und Rechteinhaber.<br>
                    Dieser News-Aggregator dient ausschließlich als Übersicht und verlinkt direkt zu den Originalartikeln.<br>
                    Bei Fragen oder Beanstandungen kontaktieren Sie bitte den Betreiber.
                </p>
            </footer>
        </div>

        <!-- Scroll to Top Button -->
        <button class="scroll-top" id="scrollTop" onclick="scrollToTop()" title="Nach oben">
            ↑
        </button>

        <!-- Auto-Refresh Notification -->
        <div class="refresh-notification" id="refreshNotification">
            <span id="refreshMessage"></span>
            <button onclick="window.location.reload()">Jetzt laden</button>
        </div>

        <!-- PHP Variablen für JavaScript -->
        <script>
            // Diese Variable wird von PHP gesetzt
            window.initialArticleCount = <?php echo $totalArticles; ?>;
        </script>
        <script src="./js/script.js"></script>
    </body>
    </html>
<?php $conn->close(); ?>