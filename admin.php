<?php
// admin.php
require_once 'config.php';
checkAuth();

$db = getDB();
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_news') {
            $category = sanitize($_POST['category']);
            $title = sanitize($_POST['title']);
            $content = sanitize($_POST['content']);
            
            $stmt = $db->prepare("INSERT INTO news (category, title, content) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $category, $title, $content);
            
            if ($stmt->execute()) {
                $success = 'News article added successfully!';
            } else {
                $error = 'Failed to add news article.';
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'delete_news') {
            $id = intval($_POST['id']);
            $db->query("DELETE FROM news WHERE id = $id");
            $success = 'News article deleted successfully!';
        }
    }
}

// Fetch all news
$allNews = $db->query("SELECT * FROM news ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - News Management</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>📊 Admin Dashboard</h1>
            <p>Manage News Articles</p>
        </div>
    </header>

 <nav>
    <div class="container">
        <ul>
            <li><a href="admin.php" class="active">Manage News</a></li>
            <li><a href="subscribers.php">Manage Subscribers</a></li>
            <li><a href="send_sms.php">Send SMS</a></li>
            <li><a href="import_news.php">Import News</a></li> <!-- NEW -->
            <li><a href="index.php">View Site</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
</nav>
    </nav>

    <main>
        <div class="container">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Add News Form -->
            <div class="card">
                <div class="card-header">
                    <h2>Add New Article</h2>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_news">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="weather">Weather</option>
                            <option value="health">Health</option>
                            <option value="disaster">Disaster</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea id="content" name="content" class="form-control" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Add News Article</button>
                </form>
            </div>

            <!-- News List -->
            <div class="table-container">
                <h2 style="margin-bottom: 1.5rem;">All News Articles</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($news = $allNews->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $news['id']; ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $news['category'] === 'weather' ? 'success' : 
                                             ($news['category'] === 'health' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($news['category']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($news['title']); ?></td>
                                <td><?php echo formatDate($news['created_at']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_news">
                                        <input type="hidden" name="id" value="<?php echo $news['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> News Portal Admin. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>