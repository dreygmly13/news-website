<?php
// index.php - RESPONSIVE PUBLIC NEWS SITE WITH READ MORE
require_once 'config.php';

$db = getDB();

// Fetch news by category (limit to 3 initially, expand with "See More")
$weatherNews = $db->query("SELECT * FROM news WHERE category = 'weather' ORDER BY created_at DESC LIMIT 6");
$healthNews = $db->query("SELECT * FROM news WHERE category = 'health' ORDER BY created_at DESC LIMIT 6");
$disasterNews = $db->query("SELECT * FROM news WHERE category = 'disaster' ORDER BY created_at DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Portal - Stay Informed</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Read More Button */
        .read-more-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .read-more-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        /* News Card Truncation */
        .news-content {
            max-height: 120px;
            overflow: hidden;
            position: relative;
        }
        
        .news-content.expanded {
            max-height: none;
        }
        
        .news-content::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40px;
            background: linear-gradient(transparent, white);
        }
        
        .news-content.expanded::after {
            display: none;
        }
        
        .see-more-section {
            text-align: center;
            margin: 30px 0;
        }
        
        .see-more-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }
        
        .see-more-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .news-card.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>📰 News Portal</h1>
            <p>Stay informed with the latest updates on Weather, Health, and Disasters</p>
        </div>
    </header>

    <nav>
        <div class="container">
        
            
            <ul id="nav-menu">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="#weather">Weather</a></li>
                <li><a href="#health">Health</a></li>
                <li><a href="#disaster">Disaster</a></li>
                <li><a href="login.php">Admin Login</a></li>
            </ul>
        </div>
    </nav>

    <main>
        <div class="container">
            <!-- Weather Section -->
            <section id="weather" class="category-section">
                <div class="category-header weather-header">
                    <span class="category-icon weather-icon">⛅</span>
                    <h2>Weather Updates</h2>
                </div>
                <div class="news-grid">
                    <?php if ($weatherNews->num_rows > 0): ?>
                        <?php 
                        $count = 0;
                        while ($news = $weatherNews->fetch_assoc()): 
                            $count++;
                            $hiddenClass = $count > 3 ? 'hidden' : '';
                        ?>
                            <article class="news-card weather <?= $hiddenClass ?>" data-category="weather">
                                <div class="news-card-header">
                                    <h3><?= htmlspecialchars($news['title']) ?></h3>
                                    <p class="news-date"><?= formatDate($news['created_at']) ?></p>
                                </div>
                                <div class="news-card-body">
                                    <div class="news-content" id="content-<?= $news['id'] ?>">
                                        <p><?= nl2br(htmlspecialchars($news['content'])) ?></p>
                                    </div>
                                    <?php if (strlen($news['content']) > 200): ?>
                                    <button class="read-more-btn" onclick="toggleContent(<?= $news['id'] ?>)" id="btn-<?= $news['id'] ?>">
                                        Read More
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                        
                        <?php if ($weatherNews->num_rows > 3): ?>
                        <div class="see-more-section" style="grid-column: 1 / -1;">
                            <button class="see-more-btn" onclick="toggleCategory('weather')">
                                <span id="weather-btn-text">See More Weather News</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <p>No weather updates available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Health Section -->
            <section id="health" class="category-section">
                <div class="category-header health-header">
                    <span class="category-icon health-icon">🏥</span>
                    <h2>Health News</h2>
                </div>
                <div class="news-grid">
                    <?php if ($healthNews->num_rows > 0): ?>
                        <?php 
                        $count = 0;
                        while ($news = $healthNews->fetch_assoc()): 
                            $count++;
                            $hiddenClass = $count > 3 ? 'hidden' : '';
                        ?>
                            <article class="news-card health <?= $hiddenClass ?>" data-category="health">
                                <div class="news-card-header">
                                    <h3><?= htmlspecialchars($news['title']) ?></h3>
                                    <p class="news-date"><?= formatDate($news['created_at']) ?></p>
                                </div>
                                <div class="news-card-body">
                                    <div class="news-content" id="content-<?= $news['id'] ?>">
                                        <p><?= nl2br(htmlspecialchars($news['content'])) ?></p>
                                    </div>
                                    <?php if (strlen($news['content']) > 200): ?>
                                    <button class="read-more-btn" onclick="toggleContent(<?= $news['id'] ?>)" id="btn-<?= $news['id'] ?>">
                                        Read More
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                        
                        <?php if ($healthNews->num_rows > 3): ?>
                        <div class="see-more-section" style="grid-column: 1 / -1;">
                            <button class="see-more-btn" onclick="toggleCategory('health')">
                                <span id="health-btn-text">See More Health News</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <p>No health news available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Disaster Section -->
            <section id="disaster" class="category-section">
                <div class="category-header disaster-header">
                    <span class="category-icon disaster-icon">🚨</span>
                    <h2>Disaster Alerts</h2>
                </div>
                <div class="news-grid">
                    <?php if ($disasterNews->num_rows > 0): ?>
                        <?php 
                        $count = 0;
                        while ($news = $disasterNews->fetch_assoc()): 
                            $count++;
                            $hiddenClass = $count > 3 ? 'hidden' : '';
                        ?>
                            <article class="news-card disaster <?= $hiddenClass ?>" data-category="disaster">
                                <div class="news-card-header">
                                    <h3><?= htmlspecialchars($news['title']) ?></h3>
                                    <p class="news-date"><?= formatDate($news['created_at']) ?></p>
                                </div>
                                <div class="news-card-body">
                                    <div class="news-content" id="content-<?= $news['id'] ?>">
                                        <p><?= nl2br(htmlspecialchars($news['content'])) ?></p>
                                    </div>
                                    <?php if (strlen($news['content']) > 200): ?>
                                    <button class="read-more-btn" onclick="toggleContent(<?= $news['id'] ?>)" id="btn-<?= $news['id'] ?>">
                                        Read More
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                        
                        <?php if ($disasterNews->num_rows > 3): ?>
                        <div class="see-more-section" style="grid-column: 1 / -1;">
                            <button class="see-more-btn" onclick="toggleCategory('disaster')">
                                <span id="disaster-btn-text">See More Disaster Alerts</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <p>No disaster alerts available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> News Portal. All rights reserved.</p>
        </div>
    </footer>

    <script>
    // Toggle navigation menu (mobile)
    function toggleNav() {
        const menu = document.getElementById('nav-menu');
        const icon = document.getElementById('nav-icon');
        
        menu.classList.toggle('active');
        icon.textContent = menu.classList.contains('active') ? '✕ Close' : '☰ Menu';
    }

    // Toggle read more/less for individual article
    function toggleContent(id) {
        const content = document.getElementById('content-' + id);
        const btn = document.getElementById('btn-' + id);
        
        content.classList.toggle('expanded');
        btn.textContent = content.classList.contains('expanded') ? 'Read Less' : 'Read More';
    }

    // Toggle see more/less for entire category
    function toggleCategory(category) {
        const cards = document.querySelectorAll('.news-card.' + category);
        const btnText = document.getElementById(category + '-btn-text');
        const hiddenCards = document.querySelectorAll('.news-card.' + category + '.hidden');
        
        if (hiddenCards.length > 0) {
            // Show all hidden cards
            hiddenCards.forEach(card => card.classList.remove('hidden'));
            btnText.textContent = 'Show Less';
        } else {
            // Hide cards after the first 3
            cards.forEach((card, index) => {
                if (index >= 3) {
                    card.classList.add('hidden');
                }
            });
            btnText.textContent = 'See More ' + category.charAt(0).toUpperCase() + category.slice(1) + ' News';
        }
    }

    // Smooth scroll to sections
    document.querySelectorAll('nav a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Close mobile menu if open
                const menu = document.getElementById('nav-menu');
                if (menu.classList.contains('active')) {
                    menu.classList.remove('active');
                    document.getElementById('nav-icon').textContent = '☰ Menu';
                }
            }
        });
    });

    // Close nav when clicking outside
    document.addEventListener('click', function(e) {
        const nav = document.querySelector('nav');
        const menu = document.getElementById('nav-menu');
        
        if (menu && !nav.contains(e.target) && menu.classList.contains('active')) {
            menu.classList.remove('active');
            document.getElementById('nav-icon').textContent = '☰ Menu';
        }
    });
    </script>
</body>
</html>