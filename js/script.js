// === FAVORITEN DEBUG ===
console.time('⏱️ Zeit bis Favoriten gefiltert');

const favObserver = new MutationObserver(() => {
    const articles = document.querySelectorAll('.article-card');
    const hiddenCount = Array.from(articles).filter(a => a.style.display === 'none').length;

    if (hiddenCount > 0) {
        console.timeEnd('⏱️ Zeit bis Favoriten gefiltert');
        console.log('📊 Artikel versteckt:', hiddenCount, 'von', articles.length);
        favObserver.disconnect();
    }
});

// Beobachte ob Artikel versteckt werden
document.addEventListener('DOMContentLoaded', () => {
    const articlesContainer = document.querySelector('.articles');
    if (articlesContainer) {
        favObserver.observe(articlesContainer, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style']
        });
    }
});

console.log('=== Script started ===');

// Theme System (4 Themes: Light, Dark, Sakura Light, Sakura Dark)
const themes = ['light', 'dark', 'sakura-light', 'sakura-dark'];
const themeConfig = {
    'light': { icon: '☀️', text: 'Light' },
    'dark': { icon: '🌙', text: 'Dark' },
    'sakura-light': { icon: '🌸', text: 'Sakura Light' },
    'sakura-dark': { icon: '🌸', text: 'Sakura Dark' }
};

function changeTheme(theme) {
    const html = document.documentElement;
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    document.cookie = `theme=${theme}; path=/; max-age=31536000`;

    // Update button
    const icon = document.getElementById('theme-icon');
    const text = document.getElementById('theme-text');
    const config = themeConfig[theme];
    icon.textContent = config.icon;
    text.textContent = config.text;

    // Update active state in dropdown
    document.querySelectorAll('.theme-option').forEach(option => {
        option.classList.remove('active');
    });
    document.querySelector(`.theme-option[onclick*="${theme}"]`).classList.add('active');

    // Close dropdown
    document.getElementById('themeDropdown').classList.remove('show');

    // Update Sakura Petals Toggle visibility
    updateSakuraPetalsToggleVisibility();

    // Handle petals for Sakura themes
    const isSakuraTheme = theme === 'sakura-light' || theme === 'sakura-dark';
    if (isSakuraTheme) {
        const savedPetalsState = localStorage.getItem('sakuraPetals');
        const toggle = document.getElementById('sakuraPetalsToggle');

        // If first time EVER, auto-enable on desktop
        if (savedPetalsState === null || savedPetalsState === undefined) {
            const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
            sakuraPetalsActive = !isMobile;
            localStorage.setItem('sakuraPetals', sakuraPetalsActive);
        } else {
            // Use saved state and activate petals
            sakuraPetalsActive = savedPetalsState === 'true';
        }

        // Apply the state
        if (sakuraPetalsActive) {
            createSakuraPetals();
            toggle.classList.add('active');
        } else {
            removeSakuraPetals();
            toggle.classList.remove('active');
        }
    } else {
        // Not Sakura theme - remove petals
        removeSakuraPetals();
    }
}

function cycleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme') || 'light';
    const currentIndex = themes.indexOf(currentTheme);
    const nextIndex = (currentIndex + 1) % themes.length;
    const nextTheme = themes[nextIndex];

    changeTheme(nextTheme);
}

function toggleThemeDropdown() {
    const dropdown = document.getElementById('themeDropdown');
    const layoutDropdown = document.getElementById('layoutDropdown');

    // Close layout dropdown if open
    layoutDropdown.classList.remove('show');

    dropdown.classList.toggle('show');
}

// Sakura Petals Animation System
let sakuraPetalsActive = false;
let sakuraPetalsContainer = null;

function createSakuraPetals() {
    if (sakuraPetalsContainer) return; // Already exists

    sakuraPetalsContainer = document.createElement('div');
    sakuraPetalsContainer.id = 'sakuraPetalsContainer';
    sakuraPetalsContainer.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999;';

    // Create 6 petals
    for (let i = 0; i < 6; i++) {
        const petal = document.createElement('div');
        petal.className = 'sakura-petal';
        sakuraPetalsContainer.appendChild(petal);
    }

    document.body.appendChild(sakuraPetalsContainer);
}

function removeSakuraPetals() {
    if (sakuraPetalsContainer) {
        sakuraPetalsContainer.remove();
        sakuraPetalsContainer = null;
    }
}

function toggleSakuraPetals() {
    sakuraPetalsActive = !sakuraPetalsActive;
    localStorage.setItem('sakuraPetals', sakuraPetalsActive);
    document.cookie = `sakuraPetals=${sakuraPetalsActive}; path=/; max-age=31536000`; // NEU

    const toggle = document.getElementById('sakuraPetalsToggle');

    if (sakuraPetalsActive) {
        createSakuraPetals();
        toggle.classList.add('active');
    } else {
        removeSakuraPetals();
        toggle.classList.remove('active');
    }
}

function updateSakuraPetalsToggleVisibility() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const toggle = document.getElementById('sakuraPetalsToggle');
    const isSakuraTheme = currentTheme === 'sakura-light' || currentTheme === 'sakura-dark';

    if (isSakuraTheme) {
        toggle.classList.add('show');
    } else {
        toggle.classList.remove('show');
        // Remove petals if switching away from Sakura theme
        // But keep the user's preference in localStorage!
        if (sakuraPetalsActive) {
            removeSakuraPetals();
            sakuraPetalsActive = false;
            toggle.classList.remove('active');
        }
    }
}

// Toggle Dropdown (Layout)
function toggleDropdown() {
    const dropdown = document.getElementById('layoutDropdown');
    const themeDropdown = document.getElementById('themeDropdown');

    // Close theme dropdown if open
    themeDropdown.classList.remove('show');

    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const layoutDropdown = document.getElementById('layoutDropdown');
    const layoutToggle = document.querySelector('.layout-toggle-hybrid');
    const themeDropdown = document.getElementById('themeDropdown');
    const themeToggle = document.querySelector('.theme-toggle-hybrid');

    if (!layoutToggle.contains(event.target)) {
        layoutDropdown.classList.remove('show');
    }

    if (!themeToggle.contains(event.target)) {
        themeDropdown.classList.remove('show');
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
    document.cookie = `layout=${layout}; path=/; max-age=31536000`; // NEU: Cookie setzen

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
    document.cookie = `favorites=${JSON.stringify(favorites)}; path=/; max-age=31536000`; // Cookie setzen
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
let lastArticleCount = window.initialArticleCount || 0;

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

    // Theme (4 Themes: Light, Dark, Sakura Light, Sakura Dark)
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);

    const themeIcon = document.getElementById('theme-icon');
    const themeText = document.getElementById('theme-text');
    const themeConfig = {
        'light': { icon: '☀️', text: 'Light' },
        'dark': { icon: '🌙', text: 'Dark' },
        'sakura-light': { icon: '🌸', text: 'Sakura Light' },
        'sakura-dark': { icon: '🌸', text: 'Sakura Dark' }
    };

    const config = themeConfig[savedTheme] || themeConfig['light'];
    themeIcon.textContent = config.icon;
    themeText.textContent = config.text;

    // Update active state in theme dropdown
    document.querySelectorAll('.theme-option').forEach(option => {
        option.classList.remove('active');
    });
    const activeThemeOption = document.querySelector(`.theme-option[onclick*="${savedTheme}"]`);
    if (activeThemeOption) {
        activeThemeOption.classList.add('active');
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

    // Sakura Petals Initialisierung
    updateSakuraPetalsToggleVisibility();

    // Detect if mobile
    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);

    // Load saved petals state or use default (ON for desktop, OFF for mobile)
    const savedPetalsState = localStorage.getItem('sakuraPetals');
    const isSakuraTheme = savedTheme === 'sakura-light' || savedTheme === 'sakura-dark';

    console.log('🌸 Init Petals - isMobile:', isMobile, 'savedState:', savedPetalsState, 'isSakuraTheme:', isSakuraTheme);

    if (isSakuraTheme) {
        const toggle = document.getElementById('sakuraPetalsToggle');

        // If no saved state exists, use default based on device
        if (savedPetalsState === null || savedPetalsState === undefined) {
            sakuraPetalsActive = !isMobile; // ON for desktop, OFF for mobile
            localStorage.setItem('sakuraPetals', sakuraPetalsActive);
            console.log('🌸 No saved state - setting default:', sakuraPetalsActive);
        } else {
            sakuraPetalsActive = savedPetalsState === 'true';
            console.log('🌸 Using saved state:', sakuraPetalsActive);
        }

        // Apply the state
        if (sakuraPetalsActive) {
            console.log('🌸 Creating petals...');
            createSakuraPetals();
            toggle.classList.add('active');
        } else {
            console.log('🌸 Petals disabled');
        }
    }
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

// Filter Dropdown Toggle
function toggleFilterDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    const group = dropdown.parentElement;

    // Schließe alle anderen Dropdowns
    document.querySelectorAll('.filter-toggle-group').forEach(g => {
        if (g !== group) {
            g.classList.remove('show');
        }
    });

    // Toggle aktuelles Dropdown
    group.classList.toggle('show');
}

// Schließe Dropdowns beim Klick außerhalb
document.addEventListener('click', (e) => {
    if (!e.target.closest('.filter-toggle-group')) {
        document.querySelectorAll('.filter-toggle-group').forEach(g => {
            g.classList.remove('show');
        });
    }
});

// Verhindere dass Dropdown sich beim Klick auf Toggle schließt
document.querySelectorAll('.filter-toggle').forEach(toggle => {
    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
    });
});