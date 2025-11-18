<?php
// subscribers.php
require_once 'config.php';
checkAuth();

$db = getDB();
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_subscriber') {
            $phone = sanitize($_POST['phone_number']);
            $name = sanitize($_POST['name']);
            
            $stmt = $db->prepare("INSERT INTO subscribers (phone_number, name) VALUES (?, ?)");
            $stmt->bind_param("ss", $phone, $name);
            
            if ($stmt->execute()) {
                $success = 'Subscriber added successfully!';
            } else {
                $error = 'Failed to add subscriber. Phone number might already exist.';
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'toggle_status') {
            $id = intval($_POST['id']);
            $db->query("UPDATE subscribers SET active = NOT active WHERE id = $id");
            $success = 'Subscriber status updated!';
        } elseif ($_POST['action'] === 'delete_subscriber') {
            $id = intval($_POST['id']);
            $db->query("DELETE FROM subscribers WHERE id = $id");
            $success = 'Subscriber deleted successfully!';
        }
    }
}

// Fetch all subscribers
$subscribers = $db->query("SELECT * FROM subscribers ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscribers - News Website</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>👥 Subscriber Management</h1>
            <p>Manage SMS Recipients</p>
        </div>
    </header>

    <nav>
        <div class="container">
            <ul>
                <li><a href="admin.php">Manage News</a></li>
                <li><a href="subscribers.php" class="active">Manage Subscribers</a></li>
                <li><a href="send_sms.php">Send SMS</a></li>
                <li><a href="index.php">View Site</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <main>
        <div class="container">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Add Subscriber Form -->
            <div class="card">
                <div class="card-header">
                    <h2>Add New Subscriber</h2>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_subscriber">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Phone Number (with country code)</label>
                        <input type="text" id="phone_number" name="phone_number" 
                               class="form-control" placeholder="+639171234567" required>
                        <small style="color: var(--gray-text);">Format: +639171234567</small>
                    </div>
                    <button type="submit" class="btn btn-success">Add Subscriber</button>
                </form>
            </div>

            <!-- Subscribers List -->
            <div class="table-container">
                <h2 style="margin-bottom: 1.5rem;">All Subscribers</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone Number</th>
                            <th>Status</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sub = $subscribers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $sub['id']; ?></td>
                                <td><?php echo htmlspecialchars($sub['name']); ?></td>
                                <td><?php echo htmlspecialchars($sub['phone_number']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $sub['active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $sub['active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($sub['created_at']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">
                                                <?php echo $sub['active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_subscriber">
                                            <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    </div>
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