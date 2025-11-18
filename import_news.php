<?php
// import_news.php - AUTO-IMPORT NEWS FROM NEWSDATA.IO
require_once 'config.php';
checkAuth();

$db = getDB();
$success = '';
$error = '';
$imported = [];

// Category mapping (NewsData.io → Our categories)
$categoryMap = [
    'weather' => 'weather',
    'health' => 'health',
    'disaster' => 'disaster',
    'environment' => 'weather',
    'science' => 'health',
    'climate' => 'weather',
    'medical' => 'health'
];

// Fetch news from NewsData.io API
function fetchNewsFromAPI($category, $limit = 10) {
    $apiKey = NEWSDATA_API_KEY;
    $country = NEWSDATA_COUNTRY;
    $language = NEWSDATA_LANGUAGE;
    
    // Build API URL
    $url = "https://newsdata.io/api/1/news?apikey=$apiKey&country=$country&language=$language&category=$category&size=$limit";
    
    error_log("Fetching from NewsData.io: $url");
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['results']) && is_array($data['results'])) {
            return [
                'success' => true,
                'articles' => $data['results'],
                'count' => count($data['results'])
            ];
        }
    }
    
    error_log("NewsData.io API Error - HTTP: $httpCode");
    return ['success' => false, 'error' => 'Failed to fetch news (HTTP ' . $httpCode . ')'];
}

// Categorize article using AI
function categorizeArticle($title, $content) {
    $data = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a news categorization expert. Analyze the news title and content, then categorize it into ONE of these categories: weather, health, or disaster.

Respond with ONLY the category name (lowercase, single word).

Guidelines:
- weather: Weather forecasts, climate, typhoons, temperature, rain, storms
- health: Medical news, diseases, vaccines, health advisories, hospitals, dengue, COVID
- disaster: Earthquakes, fires, accidents, emergencies, evacuations, calamities

Reply with only: weather OR health OR disaster'
            ],
            [
                'role' => 'user',
                'content' => "Title: $title\n\nContent: " . substr($content, 0, 500)
            ]
        ]
    ];

    $ch = curl_init(AI_API_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'customerId: cus_TEa79DTCHfkEKn',
        'Content-Type: application/json',
        'Authorization: Bearer xxx'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            $category = strtolower(trim($result['choices'][0]['message']['content']));
            
            // Validate category
            if (in_array($category, ['weather', 'health', 'disaster'])) {
                return $category;
            }
        }
    }
    
    // Default to health if categorization fails
    return 'health';
}

// Handle auto-import
if (isset($_POST['import_news'])) {
    $categories = ['environment', 'health', 'disaster'];
    $totalImported = 0;
    
    foreach ($categories as $apiCategory) {
        $result = fetchNewsFromAPI($apiCategory, 5);
        
        if ($result['success']) {
            foreach ($result['articles'] as $article) {
                $title = $article['title'] ?? 'No Title';
                $content = $article['description'] ?? $article['content'] ?? 'No content available';
                
                // Skip if no content
                if (empty($content) || strlen($content) < 50) {
                    continue;
                }
                
                // Check if article already exists
                $checkStmt = $db->prepare("SELECT id FROM news WHERE title = ?");
                $checkStmt->bind_param("s", $title);
                $checkStmt->execute();
                $exists = $checkStmt->get_result()->num_rows > 0;
                $checkStmt->close();
                
                if (!$exists) {
                    // Use AI to categorize
                    $category = categorizeArticle($title, $content);
                    
                    // Insert into database
                    $stmt = $db->prepare("INSERT INTO news (category, title, content) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $category, $title, $content);
                    $stmt->execute();
                    $stmt->close();
                    
                    $imported[] = [
                        'title' => $title,
                        'category' => $category
                    ];
                    $totalImported++;
                    
                    // Small delay to avoid rate limits
                    usleep(500000); // 0.5 seconds
                }
            }
        }
    }
    
    $success = "✅ Successfully imported $totalImported news articles from NewsData.io!";
}

// Get recent imports
$recentNews = $db->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import News - News Portal</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>📥 Auto-Import News</h1>
            <p>Fetch latest news from NewsData.io API</p>
        </div>
    </header>
    
    <nav>
        <div class="container">
            <ul>
                <li><a href="admin.php">Manage News</a></li>
                <li><a href="subscribers.php">Manage Subscribers</a></li>
                <li><a href="send_sms.php">Send SMS</a></li>
                <li><a href="import_news.php" class="active">Import News</a></li>
                <li><a href="index.php">View Site</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <main>
        <div class="container">
            <?php if($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>📡 Fetch Latest News</h2>
                </div>
                <div class="alert alert-info">
                    <strong>🤖 How it works:</strong>
                    <ol style="margin: 0.5rem 0 0 1.5rem;">
                        <li>Fetches latest Philippines news from NewsData.io</li>
                        <li>AI categorizes each article (Weather/Health/Disaster)</li>
                        <li>Imports unique articles into your database</li>
                        <li>Skips duplicates automatically</li>
                    </ol>
                    <div style="margin-top: 1rem; padding: 10px; background: rgba(234,88,12,0.1); border-radius: 5px;">
                        <strong>⚠️ Note:</strong> Free tier = 200 requests/day. Each import uses ~3-5 requests.
                    </div>
                </div>
                <form method="POST" onsubmit="return confirm('Import latest news from NewsData.io?');">
                    <button type="submit" name="import_news" class="btn btn-primary" style="width:100%;">
                        📥 Import Latest News (Weather, Health, Disaster)
                    </button>
                </form>
            </div>
            
            <?php if (!empty($imported)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>✅ Newly Imported Articles (<?= count($imported) ?>)</h2>
                </div>
                <div class="table-container" style="padding: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Title</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($imported as $item): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-<?= $item['category']==='weather'?'success':($item['category']==='health'?'warning':'danger') ?>">
                                            <?= ucfirst($item['category']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($item['title']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="table-container">
                <h2 style="margin-bottom: 1.5rem;">📰 Recent News in Database</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Title</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($n = $recentNews->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?= $n['category']==='weather'?'success':($n['category']==='health'?'warning':'danger') ?>">
                                        <?= ucfirst($n['category']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($n['title']) ?></td>
                                <td><?= formatDate($n['created_at']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> News Portal. Powered by NewsData.io API</p>
        </div>
    </footer>
</body>
</html>