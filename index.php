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

// Suchfunktion
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Datum-Filter
$dateFilter = isset($_GET['date']) ? $_GET['date'] : 'all';

// SQL Query bauen
$where = [];
$params = [];
$types = '';

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
        case 'week':
            $where[] = "pub_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where[] = "pub_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
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

$countSql = "SELECT COUNT(*) as total FROM articles $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalArticles = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalArticles / $perPage);
?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🎮 Gaming News Aggregator</title>
        <style>
            :root {
                --bg-primary: #ffffff;
                --bg-secondary: #f8f9fa;
                --bg-card: #ffffff;
                --text-primary: #1a1a1a;
                --text-secondary: #6c757d;
                --accent: #667eea;
                --accent-hover: #5568d3;
                --border: #e9ecef;
                --shadow: rgba(0, 0, 0, 0.1);
                --shadow-hover: rgba(0, 0, 0, 0.15);
            }

            [data-theme="dark"] {
                --bg-primary: #1a1a1a;
                --bg-secondary: #2d2d2d;
                --bg-card: #242424;
                --text-primary: #ffffff;
                --text-secondary: #a0a0a0;
                --accent: #7c8adb;
                --accent-hover: #8b99e3;
                --border: #3a3a3a;
                --shadow: rgba(0, 0, 0, 0.3);
                --shadow-hover: rgba(0, 0, 0, 0.5);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif;
                background: var(--bg-secondary);
                color: var(--text-primary);
                line-height: 1.6;
                transition: background-color 0.3s ease, color 0.3s ease;
            }

            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }

            /* Header */
            header {
                background: var(--bg-card);
                padding: 40px 20px;
                border-radius: 20px;
                margin-bottom: 30px;
                box-shadow: 0 4px 20px var(--shadow);
                position: relative;
            }

            header h1 {
                font-size: 2.5em;
                text-align: center;
                margin-bottom: 10px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            header p {
                text-align: center;
                color: var(--text-secondary);
                font-size: 1.1em;
            }

            header small {
                display: block;
                text-align: center;
                margin-top: 10px;
                color: var(--text-secondary);
            }

            /* Dark Mode Toggle */
            .theme-toggle {
                position: absolute;
                top: 20px;
                right: 20px;
                background: var(--bg-secondary);
                border: 2px solid var(--border);
                border-radius: 50px;
                padding: 8px 16px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
            }

            .theme-toggle:hover {
                transform: scale(1.05);
                border-color: var(--accent);
            }

            /* Layout Toggle - Hybrid */
            .layout-toggle-hybrid {
                position: absolute;
                top: 20px;
                left: 20px;
                background: var(--bg-secondary);
                border: 2px solid var(--border);
                border-radius: 50px;
                padding: 8px 16px;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
            }

            .layout-toggle-hybrid > .layout-dropdown-menu {
                position: absolute;
                top: calc(100% + 10px);
                left: 0;
            }

            .layout-current {
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                padding-right: 10px;
                border-right: 1px solid var(--border);
            }

            .layout-current:hover {
                color: var(--accent);
            }

            .layout-dropdown-arrow {
                cursor: pointer;
                padding-left: 10px;
                display: flex;
                align-items: center;
            }

            .layout-dropdown-arrow:hover {
                color: var(--accent);
            }

            .layout-dropdown-menu {
                position: absolute;
                top: 100%;
                left: 0;
                margin-top: 10px;
                background: var(--bg-card);
                border: 2px solid var(--border);
                border-radius: 12px;
                box-shadow: 0 4px 20px var(--shadow);
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px);
                transition: all 0.3s ease;
                z-index: 1000;
                width: 180px;
                white-space: nowrap;
            }

            .layout-dropdown-menu.show {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            .layout-option-hybrid {
                padding: 12px 20px;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                gap: 10px;
                color: var(--text-primary);
            }

            .layout-option-hybrid:first-child {
                border-radius: 10px 10px 0 0;
            }

            .layout-option-hybrid:last-child {
                border-radius: 0 0 10px 10px;
            }

            .layout-option-hybrid:hover {
                background: var(--bg-secondary);
            }

            .layout-option-hybrid.active {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            /* Filter */
            .filter-container {
                margin-bottom: 30px;
            }

            .articles.compact-view + .pagination {
                margin-top: 0;
            }

            body:has(.articles.compact-view) .filter-container {
                margin-bottom: 0;
            }

            body:has(.articles.compact-view) .filter {
                border-radius: 16px 16px 0 0;
                margin-bottom: 0;
            }

            .search-box {
                background: var(--bg-card);
                padding: 20px;
                border-radius: 16px;
                box-shadow: 0 2px 10px var(--shadow);
                margin-bottom: 20px;
            }

            .search-form {
                display: flex;
                gap: 10px;
                max-width: 600px;
                margin: 0 auto;
            }

            .search-input {
                flex: 1;
                padding: 12px 20px;
                border: 2px solid var(--border);
                border-radius: 25px;
                background: var(--bg-secondary);
                color: var(--text-primary);
                font-size: 1em;
                transition: all 0.3s ease;
            }

            .search-input:focus {
                outline: none;
                border-color: var(--accent);
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .search-btn {
                padding: 12px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 25px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .search-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }

            .clear-search {
                padding: 12px 20px;
                background: var(--bg-secondary);
                color: var(--text-primary);
                border: 2px solid var(--border);
                border-radius: 25px;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.3s ease;
                display: inline-block;
            }

            .clear-search:hover {
                border-color: var(--accent);
                transform: translateY(-2px);
            }

            .filter {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                justify-content: center;
                padding: 20px;
                background: var(--bg-card);
                border-radius: 16px;
                box-shadow: 0 2px 10px var(--shadow);
            }

            .filter-btn {
                padding: 10px 20px;
                background: var(--bg-secondary);
                border-radius: 25px;
                text-decoration: none;
                color: var(--text-primary);
                font-size: 0.95em;
                font-weight: 500;
                transition: all 0.3s ease;
                border: 2px solid var(--border);
            }

            .filter-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px var(--shadow-hover);
                border-color: var(--accent);
            }

            .filter-btn.active {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-color: #667eea;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }

            /* Articles Grid (default) */
            .articles {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 24px;
                margin-bottom: 30px;
            }

            /* Articles List View */
            .articles.list-view {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .articles.list-view .article-card {
                flex-direction: row;
                height: auto;
            }

            .articles.list-view .article-image {
                width: 280px;
                min-width: 280px;
                height: auto;
                min-height: 180px;
                object-fit: cover;
                align-self: stretch;
            }

            .articles.list-view .article-image.no-image {
                width: 280px;
                min-width: 280px;
                height: auto;
                min-height: 180px;
                align-self: stretch;
            }

            .articles.list-view .article-content {
                flex: 1;
                position: relative;
            }

            /* Favoriten-Stern in Listen-Ansicht - auf gleicher Höhe wie Meta */
            .articles.list-view .article-card .favorite-star {
                position: absolute !important;
                top: 20px !important;  /* padding (20px) */
                right: 20px !important;
                left: auto !important;
                transform: none !important;
                z-index: 10;
                margin: 0 !important;
                line-height: 1 !important;
            }

            /* Stelle sicher dass Meta nicht unter den Stern rutscht */
            .articles.list-view .article-meta {
                padding-right: 40px;
            }

            .article-card {
                background: var(--bg-card);
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 4px 15px var(--shadow);
                transition: all 0.3s ease;
                border: 1px solid var(--border);
                display: flex;
                flex-direction: column;
                position: relative; /* Für absolute Positionierung des Sterns */
            }

            .article-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 8px 30px var(--shadow-hover);
            }

            .article-image {
                width: 100%;
                height: 200px;
                object-fit: cover;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .article-image.no-image {
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 4em;
                color: rgba(255,255,255,0.8);
            }

            .article-content {
                padding: 20px;
                flex: 1;
                display: flex;
                flex-direction: column;
            }

            .article-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
                flex-wrap: wrap;
                gap: 8px;
            }

            .source {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 4px 12px;
                border-radius: 12px;
                font-weight: 600;
                font-size: 0.8em;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .date {
                color: var(--text-secondary);
                font-size: 0.85em;
            }

            .article-card h2 {
                font-size: 1.2em;
                margin-bottom: 12px;
                line-height: 1.4;
                flex: 1;
            }

            .article-card h2 a {
                color: var(--text-primary);
                text-decoration: none;
                transition: color 0.3s ease;
            }

            .article-card h2 a:hover {
                color: var(--accent);
            }

            .description {
                color: var(--text-secondary);
                font-size: 0.95em;
                line-height: 1.5;
                margin-bottom: 15px;
            }

            /* Tags */
            .tags {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-top: 10px;
            }

            .tag {
                background: var(--bg-secondary);
                color: var(--text-secondary);
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 0.75em;
                border: 1px solid var(--border);
                text-decoration: none;
                display: inline-block;
                transition: all 0.3s ease;
                cursor: pointer;
            }

            .tag:hover {
                background: var(--accent);
                color: white;
                border-color: var(--accent);
                transform: translateY(-2px);
            }

            /* No articles */
            .no-articles {
                text-align: center;
                padding: 80px 20px;
                background: var(--bg-card);
                border-radius: 16px;
                box-shadow: 0 4px 15px var(--shadow);
            }

            .no-articles p {
                font-size: 1.2em;
                margin-bottom: 20px;
                color: var(--text-secondary);
            }

            /* Pagination */
            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 15px;
                margin-top: 40px;
                padding: 25px;
                background: var(--bg-card);
                border-radius: 16px;
                box-shadow: 0 4px 15px var(--shadow);
            }

            .page-link {
                padding: 12px 24px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 10px;
                font-weight: 600;
                transition: all 0.3s ease;
            }

            .page-link:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            }

            .page-info {
                font-weight: 600;
                color: var(--text-primary);
            }

            /* Footer */
            footer {
                text-align: center;
                padding: 30px;
                margin-top: 40px;
                background: var(--bg-card);
                border-radius: 16px;
                box-shadow: 0 4px 15px var(--shadow);
            }

            footer p {
                color: var(--text-secondary);
                margin-bottom: 15px;
            }

            .update-btn {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 10px;
                font-weight: 600;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
                font-size: 1em;
            }

            .update-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            }

            .update-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            #update-status {
                margin-top: 15px;
                font-weight: 500;
            }

            /* Responsive */
            @media (max-width: 768px) {
                header h1 {
                    font-size: 2em;
                }

                .articles {
                    grid-template-columns: 1fr;
                }

                .articles.list-view .article-card {
                    flex-direction: column;
                }

                .articles.list-view .article-image {
                    width: 100%;
                    height: 200px;
                }

                .articles.list-view .article-image.no-image {
                    width: 100%;
                }

                .theme-toggle, .layout-toggle {
                    position: static;
                    margin: 10px auto 0;
                    font-size: 0.9em;
                    padding: 6px 12px;
                }

                header {
                    padding: 30px 15px;
                }
            }

            /* Loading Animation */
            .loading {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(255,255,255,.3);
                border-radius: 50%;
                border-top-color: #fff;
                animation: spin 1s ease-in-out infinite;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            /* Scroll to Top Button */
            .scroll-top {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 50px;
                height: 50px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5em;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                z-index: 1000;
            }

            .scroll-top.visible {
                opacity: 1;
                visibility: visible;
            }

            .scroll-top:hover {
                transform: translateY(-5px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            }

            /* Compact View */
            .articles.compact-view ~ .filter-container .filter {
                margin-bottom: 0;
                border-radius: 16px 16px 0 0;
            }

            .articles.compact-view {
                display: flex;
                flex-direction: column;
                gap: 0;
                margin-top: 0;
                border-radius: 0 0 16px 16px;
                overflow: hidden;
                box-shadow: 0 4px 15px var(--shadow);
            }

            .articles.compact-view .article-card {
                flex-direction: column;  /* Zurück zu column */
                border-radius: 0;
                border: none;
                border-bottom: 1px solid var(--border);
                padding: 8px 20px;
                box-shadow: none;
                align-items: flex-start;
                gap: 4px;
            }

            .articles.compact-view .article-card:hover {
                background: var(--bg-secondary);
                transform: none;
            }

            .articles.compact-view .article-image,
            .articles.compact-view .article-image.no-image {
                display: none;
            }

            .articles.compact-view .article-content {
                padding: 0;
                display: flex;
                flex-direction: column;
                gap: 4px;
                align-items: flex-start;
                width: 100%;
            }

            .articles.compact-view h2 {
                font-size: 1em;
                margin: 0;
                width: 100%;
                order: 1;  /* Titel nach oben */
            }

            .articles.compact-view .description,
            .articles.compact-view .tags {
                display: none;
            }

            .articles.compact-view .article-meta {
                margin: 0;
                gap: 0;
                order: 2;
                display: flex;
                flex-direction: row;
                align-items: center;
                font-size: 0.95em;
                color: var(--text-secondary);
                position: relative;
                padding-right: 0;
            }

            .articles.compact-view .date {
                order: 1;
            }

            .articles.compact-view .date::after {
                content: ' | ';
                margin: 0 8px;
            }

            .articles.compact-view .source {
                order: 2;
                padding: 0;
                background: transparent;
                border: none;
                color: var(--text-secondary);
                font-weight: 500;
            }

            .articles.compact-view .source::after {
                content: ' | ';
                margin: 0 8px;
            }

            /* Date Filter */
            .date-filter {
                display: flex;
                gap: 10px;
                justify-content: center;
                margin-bottom: 15px;
                flex-wrap: wrap;
            }

            .date-filter-btn {
                padding: 8px 18px;
                background: var(--bg-secondary);
                border: 2px solid var(--border);
                border-radius: 20px;
                color: var(--text-primary);
                font-size: 0.9em;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
            }

            .date-filter-btn:hover {
                border-color: var(--accent);
                transform: translateY(-2px);
            }

            .date-filter-btn.active {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-color: #667eea;
            }

            /* Auto-refresh notification */
            .refresh-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--bg-card);
                border: 2px solid var(--accent);
                border-radius: 12px;
                padding: 15px 20px;
                box-shadow: 0 4px 20px var(--shadow);
                z-index: 1000;
                opacity: 0;
                transform: translateY(-20px);
                transition: all 0.3s ease;
                pointer-events: none;
            }

            .refresh-notification.show {
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
            }

            .refresh-notification button {
                margin-left: 10px;
                padding: 5px 15px;
                background: var(--accent);
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-weight: 600;
            }

            /* Favorite Star */
            .favorite-star {
                position: absolute;
                top: 15px;
                right: 15px;
                font-size: 1.5em;
                cursor: pointer;
                color: var(--text-secondary);
                transition: all 0.3s ease;
                z-index: 10;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }

            .favorite-star:hover {
                transform: scale(1.2);
            }

            .favorite-star.favorited {
                color: #ffd700;
            }

            /* In Compact: Stern wird inline nach dem Datum */
            .articles.compact-view .favorite-star {
                position: static;
                display: inline;
                font-size: 1em;
                margin-left: 8px;
                line-height: 1;
                vertical-align: middle;
                order: 3;
            }
        </style>
    </head>
    <body>
    <div class="container">
        <header>
            <div class="layout-toggle-hybrid">
                <div class="layout-current" onclick="cycleLayout()">
                    <span id="layout-icon">▦</span>
                    <span id="layout-text">Grid</span>
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

            <button class="theme-toggle" onclick="toggleTheme()" title="Theme wechseln">
                <span id="theme-icon">🌙</span>
                <span id="theme-text">Dark</span>
            </button>

            <h1>🎮 Gaming News Aggregator</h1>
            <p>Die neuesten Gaming-News auf einen Blick</p>
            <small>Gesamt: <?php echo number_format($totalArticles, 0, ',', '.'); ?> Artikel<?php if($searchQuery) echo ' für "'.htmlspecialchars($searchQuery).'"'; ?></small>
        </header>

        <!-- Suchfunktion -->
        <div class="filter-container">
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

            <!-- Datum Filter -->
            <div class="date-filter">
                <a href="?<?php echo $sourceFilter ? 'source='.urlencode($sourceFilter).'&' : ''; ?><?php echo $searchQuery ? 'search='.urlencode($searchQuery).'&' : ''; ?>date=all"
                   class="date-filter-btn <?php echo (!isset($_GET['date']) || $_GET['date'] == 'all') ? 'active' : ''; ?>">
                    Alle Zeiten
                </a>
                <a href="?<?php echo $sourceFilter ? 'source='.urlencode($sourceFilter).'&' : ''; ?><?php echo $searchQuery ? 'search='.urlencode($searchQuery).'&' : ''; ?>date=today"
                   class="date-filter-btn <?php echo (isset($_GET['date']) && $_GET['date'] == 'today') ? 'active' : ''; ?>">
                    Heute
                </a>
                <a href="?<?php echo $sourceFilter ? 'source='.urlencode($sourceFilter).'&' : ''; ?><?php echo $searchQuery ? 'search='.urlencode($searchQuery).'&' : ''; ?>date=week"
                   class="date-filter-btn <?php echo (isset($_GET['date']) && $_GET['date'] == 'week') ? 'active' : ''; ?>">
                    Diese Woche
                </a>
                <a href="?<?php echo $sourceFilter ? 'source='.urlencode($sourceFilter).'&' : ''; ?><?php echo $searchQuery ? 'search='.urlencode($searchQuery).'&' : ''; ?>date=month"
                   class="date-filter-btn <?php echo (isset($_GET['date']) && $_GET['date'] == 'month') ? 'active' : ''; ?>">
                    Dieser Monat
                </a>
            </div>

            <div class="filter">
                <a href="index.php" class="filter-btn <?php echo !$sourceFilter && !isset($_GET['favorites']) ? 'active' : ''; ?>">
                    Alle Quellen
                </a>
                <a href="?favorites=1" class="filter-btn <?php echo isset($_GET['favorites']) ? 'active' : ''; ?>" id="favoritesBtn">
                    ⭐ Favoriten (<span id="favCount">0</span>)
                </a>
                <?php while ($source = $sources->fetch_assoc()): ?>
                    <a href="?source=<?php echo urlencode($source['source']); ?>"
                       class="filter-btn <?php echo $sourceFilter == $source['source'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($source['source']); ?>
                    </a>
                <?php endwhile; ?>
            </div>

            <!-- Articles -->
            <div class="articles">
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
                        <a href="?page=<?php echo $page - 1; ?><?php echo $sourceFilter ? '&source=' . urlencode($sourceFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>"
                           class="page-link">
                            ← Vorherige
                        </a>
                    <?php endif; ?>

                    <span class="page-info">
                    Seite <?php echo $page; ?> von <?php echo $totalPages; ?>
                </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $sourceFilter ? '&source=' . urlencode($sourceFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>"
                           class="page-link">
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

        <script>
            console.log('=== Script started ===');

            // Dark/Light Mode Toggle
            function toggleTheme() {
                const html = document.documentElement;
                const currentTheme = html.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);

                // Update button
                const icon = document.getElementById('theme-icon');
                const text = document.getElementById('theme-text');
                if (newTheme === 'dark') {
                    icon.textContent = '🌙';
                    text.textContent = 'Dark';
                } else {
                    icon.textContent = '☀️';
                    text.textContent = 'Light';
                }
            }

            // Toggle Dropdown
            function toggleDropdown() {
                const dropdown = document.getElementById('layoutDropdown');
                dropdown.classList.toggle('show');
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const dropdown = document.getElementById('layoutDropdown');
                const toggle = document.querySelector('.layout-toggle-hybrid');
                if (!toggle.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            });

            // Cycle through layouts (for left click)
            function cycleLayout() {
                const articles = document.querySelector('.articles');
                let currentLayout = 'grid';

                if (articles.classList.contains('list-view')) {
                    currentLayout = 'list';
                } else if (articles.classList.contains('compact-view')) {
                    currentLayout = 'compact';
                }

                let newLayout;
                if (currentLayout === 'grid') {
                    newLayout = 'list';
                } else if (currentLayout === 'list') {
                    newLayout = 'compact';
                } else {
                    newLayout = 'grid';
                }

                changeLayout(newLayout);
            }

            // Change Layout
            function changeLayout(layout) {
                const articles = document.querySelector('.articles');
                const icon = document.getElementById('layout-icon');
                const text = document.getElementById('layout-text');
                const options = document.querySelectorAll('.layout-option-hybrid');

                // Remove all classes
                articles.classList.remove('list-view', 'compact-view');

                // Add appropriate class and update UI
                if (layout === 'list') {
                    articles.classList.add('list-view');
                    icon.textContent = '☰';
                    text.textContent = 'Liste';
                } else if (layout === 'compact') {
                    articles.classList.add('compact-view');
                    icon.textContent = '≡';
                    text.textContent = 'Kompakt';
                } else {
                    icon.textContent = '▦';
                    text.textContent = 'Grid';
                }

                // Update active state
                options.forEach(opt => opt.classList.remove('active'));
                const activeOption = Array.from(options).find(opt => {
                    if (layout === 'grid' && opt.textContent.includes('Grid')) return true;
                    if (layout === 'list' && opt.textContent.includes('Liste')) return true;
                    if (layout === 'compact' && opt.textContent.includes('Kompakt')) return true;
                });
                if (activeOption) activeOption.classList.add('active');

                // Save to localStorage
                localStorage.setItem('layout', layout);

                // Close dropdown
                document.getElementById('layoutDropdown').classList.remove('show');
            }

            // Layout Toggle (Grid ↔ List ↔ Compact)
            function toggleLayout() {
                const articles = document.querySelector('.articles');
                let currentLayout = 'grid';

                if (articles.classList.contains('list-view')) {
                    currentLayout = 'list';
                } else if (articles.classList.contains('compact-view')) {
                    currentLayout = 'compact';
                }

                // Cycle through layouts: grid → list → compact → grid
                let newLayout;
                if (currentLayout === 'grid') {
                    newLayout = 'list';
                    articles.classList.add('list-view');
                    articles.classList.remove('compact-view');
                } else if (currentLayout === 'list') {
                    newLayout = 'compact';
                    articles.classList.remove('list-view');
                    articles.classList.add('compact-view');
                } else {
                    newLayout = 'grid';
                    articles.classList.remove('list-view');
                    articles.classList.remove('compact-view');
                }

                localStorage.setItem('layout', newLayout);

                // Update button
                const icon = document.getElementById('layout-icon');
                const text = document.getElementById('layout-text');
                if (newLayout === 'list') {
                    icon.textContent = '☰';
                    text.textContent = 'Liste';
                } else if (newLayout === 'compact') {
                    icon.textContent = '≡';
                    text.textContent = 'Kompakt';
                } else {
                    icon.textContent = '▦';
                    text.textContent = 'Grid';
                }
            }

            // Scroll to Top
            function scrollToTop() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            // Show/Hide Scroll to Top Button
            window.addEventListener('scroll', function() {
                const scrollTop = document.getElementById('scrollTop');
                if (window.pageYOffset > 300) {
                    scrollTop.classList.add('visible');
                } else {
                    scrollTop.classList.remove('visible');
                }
            });

            // Favoriten-System
            function toggleFavorite(articleId) {
                const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
                const star = document.querySelector(`[data-article-id="${articleId}"]`);

                const index = favorites.indexOf(articleId);
                if (index > -1) {
                    // Remove from favorites
                    favorites.splice(index, 1);
                    star.textContent = '☆';
                    star.classList.remove('favorited');
                } else {
                    // Add to favorites
                    favorites.push(articleId);
                    star.textContent = '★';
                    star.classList.add('favorited');
                }

                localStorage.setItem('favorites', JSON.stringify(favorites));
                updateFavoriteCount();
            }

            // Update Favoriten-Counter
            function updateFavoriteCount() {
                const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
                document.getElementById('favCount').textContent = favorites.length;
            }

            // Load Favoriten beim Laden
            function loadFavorites() {
                const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
                console.log('Loading favorites:', favorites);

                favorites.forEach(id => {
                    const star = document.querySelector(`[data-article-id="${id}"]`);
                    if (star) {
                        star.textContent = '★';
                        star.classList.add('favorited');
                    }
                });

                // Check if we're in favorites view
                const urlParams = new URLSearchParams(window.location.search);
                const isFavoritesView = urlParams.get('favorites') === '1';
                console.log('Is favorites view?', isFavoritesView, 'URL params:', window.location.search);

                if (isFavoritesView) {
                    console.log('Loading favorites view with IDs:', favorites);
                    loadFavoritesView(favorites);
                }
            }

            // Load Favorites View - zeige nur gespeicherte Favoriten
            async function loadFavoritesView(favoriteIds) {
                console.log('loadFavoritesView called with:', favoriteIds);

                if (favoriteIds.length === 0) {
                    const articles = document.querySelector('.articles');
                    articles.innerHTML = '<p style="text-align:center; padding:40px; color:var(--text-secondary);">Keine Favoriten gespeichert. Klicke auf ⭐ bei Artikeln um sie zu speichern!</p>';
                    return;
                }

                try {
                    const url = `get_favorites.php?ids=${favoriteIds.join(',')}`;
                    console.log('Fetching:', url);

                    const response = await fetch(url);
                    const articles = await response.json();

                    console.log('Got articles:', articles);

                    // ⭐ NEU: Bereinige verwaiste Favoriten (IDs die nicht mehr in DB existieren)
                    const validIds = articles.map(a => a.id);
                    const orphanedIds = favoriteIds.filter(id => !validIds.includes(id));

                    if (orphanedIds.length > 0) {
                        console.log('Removing orphaned favorites:', orphanedIds);
                        const cleanedFavorites = favoriteIds.filter(id => validIds.includes(id));
                        localStorage.setItem('favorites', JSON.stringify(cleanedFavorites));
                        document.getElementById('favCount').textContent = cleanedFavorites.length;
                    }

                    if (articles.length === 0) {
                        const articlesDiv = document.querySelector('.articles');
                        articlesDiv.innerHTML = '<p style="text-align:center; padding:40px; color:var(--text-secondary);">Keine Favoriten gefunden. Möglicherweise wurden die Artikel gelöscht.</p>';
                        return;
                    }

                    displayFavorites(articles, favoriteIds);
                } catch (error) {
                    console.error('Error loading favorites:', error);
                }
            }

            // Display Favorites
            function displayFavorites(articles, favoriteIds) {
                const articlesDiv = document.querySelector('.articles');
                articlesDiv.innerHTML = '';

                articles.forEach(article => {
                    const card = createArticleCard(article, favoriteIds.includes(article.id));
                    articlesDiv.appendChild(card);
                });
            }

            // Create Article Card
            function createArticleCard(article, isFavorite) {
                const card = document.createElement('article');
                card.className = 'article-card';
                card.style.position = 'relative';

                const date = new Date(article.pub_date);
                const formattedDate = date.toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit', year: 'numeric'}) + ' ' +
                    date.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});

                card.innerHTML = `
                    ${article.image_url ? `
                        <img src="${article.image_url}"
                             alt="${article.title}"
                             class="article-image"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="article-image no-image" style="display:none;">🎮</div>
                    ` : `
                        <div class="article-image no-image">🎮</div>
                    `}

                    <div class="article-content">
                        <div class="article-meta">
                            <span class="date">${formattedDate} Uhr</span>
                            <span class="source">${article.source}</span>
                            <span class="favorite-star ${isFavorite ? 'favorited' : ''}"
                                  onclick="toggleFavorite(${article.id})"
                                  data-article-id="${article.id}"
                                  title="Zu Favoriten hinzufügen">
                                ${isFavorite ? '★' : '☆'}
                            </span>
                        </div>

                        <h2>
                            <a href="${article.link}" target="_blank" rel="noopener noreferrer">
                                ${article.title}
                            </a>
                        </h2>

                        ${article.description ? `
                            <p class="description">
                                ${article.description.substring(0, 150)}${article.description.length > 150 ? '...' : ''}
                            </p>
                        ` : ''}

                        ${article.tags ? `
                            <div class="tags">
                                ${article.tags.split(',').slice(0, 5).map(tag =>
                    `<a href="?search=${encodeURIComponent(tag.trim())}" class="tag">${tag.trim()}</a>`
                ).join('')}
                            </div>
                        ` : ''}
                    </div>
                `;

                return card;
            }

            // Load Favoriten beim Laden
            // Auto-Refresh (alle 5 Minuten checken)
            let lastArticleCount = <?php echo $totalArticles; ?>;

            function checkForNewArticles() {
                fetch('check_new_articles.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.count > lastArticleCount) {
                            const diff = data.count - lastArticleCount;
                            showRefreshNotification(`🎮 ${diff} neue Artikel verfügbar!`);
                        }
                    })
                    .catch(error => console.log('Auto-refresh check failed:', error));
            }

            function showRefreshNotification(message) {
                const notification = document.getElementById('refreshNotification');
                const messageEl = document.getElementById('refreshMessage');
                messageEl.textContent = message;
                notification.classList.add('show');

                // Auto-hide after 10 seconds
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 10000);
            }

            // Start auto-refresh check every 5 minutes
            setInterval(checkForNewArticles, 5 * 60 * 1000);

            // Load saved theme and layout
            (function() {
                console.log('=== IIFE started ===');

                // Theme
                const savedTheme = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-theme', savedTheme);

                const themeIcon = document.getElementById('theme-icon');
                const themeText = document.getElementById('theme-text');
                if (savedTheme === 'dark') {
                    themeIcon.textContent = '🌙';
                    themeText.textContent = 'Dark';
                } else {
                    themeIcon.textContent = '☀️';
                    themeText.textContent = 'Light';
                }

                // Layout
                const savedLayout = localStorage.getItem('layout') || 'grid';
                const articles = document.querySelector('.articles');
                const layoutIcon = document.getElementById('layout-icon');
                const layoutText = document.getElementById('layout-text');

                if (savedLayout === 'list') {
                    articles.classList.add('list-view');
                    layoutIcon.textContent = '☰';
                    layoutText.textContent = 'Liste';
                } else if (savedLayout === 'compact') {
                    articles.classList.add('compact-view');
                    layoutIcon.textContent = '≡';
                    layoutText.textContent = 'Kompakt';
                } else {
                    layoutIcon.textContent = '▦';
                    layoutText.textContent = 'Grid';
                }

                // Load Favoriten
                loadFavorites();
                updateFavoriteCount();
            })();

            // AJAX Feed Update (kein Redirect!)
            function updateFeeds() {
                const btn = document.getElementById('update-btn');
                const status = document.getElementById('update-status');

                btn.disabled = true;
                btn.innerHTML = '<span class="loading"></span> Lade Feeds...';
                status.innerHTML = '<p style="color: var(--text-secondary);">Bitte warten, Feeds werden aktualisiert...</p>';

                // Erstelle ein unsichtbares iframe für fetch_feeds.php
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = 'fetch_feeds.php';
                document.body.appendChild(iframe);

                // Nach 5 Sekunden: Seite neu laden
                setTimeout(() => {
                    status.innerHTML = '<p style="color: green;">✅ Feeds aktualisiert! Seite wird neu geladen...</p>';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }, 5000);
            }
        </script>
    </body>
    </html>
<?php $conn->close(); ?>