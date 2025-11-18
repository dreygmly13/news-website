<?php
// announcement.php - CUSTOM ANNOUNCEMENTS WITH SELECTIVE MESSAGING
require_once 'config.php';
require_once 'sms_gateways.php';
checkAuth();

$db = getDB();
$success = '';
$error = '';

/**
 * Send SMS via selected gateway
 */
function sendSMS($phone, $message, $gateway = 'IPROG') {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if ($gateway === 'IPROG') {
        if (strlen($phone) === 11 && substr($phone, 0, 2) === '09') {
            $phone = '63' . substr($phone, 1);
        }
        
        $ch = curl_init('https://www.iprogsms.com/api/v1/sms_messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'api_token' => IPROG_API_TOKEN,
            'message' => $message,
            'phone_number' => $phone
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'id' => 'iprog_' . time()];
        }
    } elseif ($gateway === 'ARDUINO') {
        require_once 'arduino_sms.php';
        $arduino = new ArduinoSMS();
        $arduino->connect(ARDUINO_COM_PORT);
        return $arduino->sendSMS($phone, $message);
    }
    
    return ['success' => false, 'error' => 'Gateway error'];
}

/**
 * Handle announcement sending
 */
if (isset($_POST['send_announcement'])) {
    $message = trim($_POST['announcement_message']);
    $recipientType = $_POST['recipient_type'] ?? 'all';
    $gateway = $_POST['gateway'] ?? 'IPROG';
    
    // Validate message
    if (empty($message)) {
        $error = '❌ Announcement message cannot be empty';
    } elseif (strlen($message) > 160) {
        $error = '❌ Message exceeds SMS limit: ' . strlen($message) . ' / 160 characters';
    } else {
        $sent = 0;
        $failed = 0;
        $errors = [];
        $recipients = [];
        
        // Determine recipients based on selection
        if ($recipientType === 'all') {
            $subs = $db->query("SELECT * FROM subscribers WHERE active = 1");
            while ($sub = $subs->fetch_assoc()) {
                $recipients[] = $sub;
            }
        } elseif ($recipientType === 'single') {
            $singleId = intval($_POST['single_subscriber']);
            $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
            $stmt->bind_param("i", $singleId);
            $stmt->execute();
            $sub = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($sub) {
                $recipients[] = $sub;
            }
        } elseif ($recipientType === 'selected') {
            $selectedIds = $_POST['selected_subscribers'] ?? [];
            if (!empty($selectedIds)) {
                $ids = implode(',', array_map('intval', $selectedIds));
                $subs = $db->query("SELECT * FROM subscribers WHERE id IN ($ids)");
                while ($sub = $subs->fetch_assoc()) {
                    $recipients[] = $sub;
                }
            }
        }
        
        // Send to recipients
        if (empty($recipients)) {
            $error = '❌ No recipients selected';
        } else {
            foreach ($recipients as $recipient) {
                $result = sendSMS($recipient['phone_number'], $message, $gateway);
                
                // Log to database (news_id = 0 for announcements)
                $status = $result['success'] ? 'sent' : 'failed';
                $stmt = $db->prepare("INSERT INTO sms_logs (subscriber_id, news_id, message, status) VALUES (?, 0, ?, ?)");
                $stmt->bind_param("iss", $recipient['id'], $message, $status);
                $stmt->execute();
                $stmt->close();
                
                if ($result['success']) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = "{$recipient['name']}: {$result['error']}";
                }
                
                sleep(2); // Rate limiting
            }
            
            $gatewayName = $gateway === 'ARDUINO' ? 'SIM800C' : $gateway;
            $success = "✅ Announcement sent via $gatewayName to $sent recipient(s)!";
            if ($failed > 0) {
                $success .= " ($failed failed)";
                $error = implode('<br>', array_slice($errors, 0, 5));
            }
        }
    }
}

// Fetch all subscribers
$allSubscribers = $db->query("SELECT * FROM subscribers ORDER BY name ASC");

// Get recent announcements
$recentAnnouncements = $db->query("
    SELECT sl.*, s.name, s.phone_number 
    FROM sms_logs sl 
    LEFT JOIN subscribers s ON sl.subscriber_id = s.id 
    WHERE sl.news_id = 0 
    ORDER BY sl.sent_at DESC 
    LIMIT 15
");

// Stats
$stats = $db->query("SELECT COUNT(*) t, SUM(status='sent') s FROM sms_logs WHERE news_id=0")->fetch_assoc();
$active = $db->query("SELECT COUNT(*) c FROM subscribers WHERE active=1")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - News Portal</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;margin-bottom:2rem}
        .stat-card{background:var(--white);padding:1.5rem;border-radius:12px;box-shadow:var(--shadow);text-align:center;transition:transform .3s}
        .stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md)}
        .stat-number{font-size:2.8rem;font-weight:700;margin-bottom:.5rem}
        .stat-label{color:var(--gray-text);font-size:.875rem;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
        .stat-card.primary .stat-number{color:var(--primary-color)}
        .stat-card.success .stat-number{color:var(--success-color)}
        
        .subscriber-checkbox{display:flex;align-items:center;gap:12px;padding:12px;background:white;border:2px solid var(--border-color);border-radius:8px;margin:8px 0;cursor:pointer;transition:all .2s}
        .subscriber-checkbox:hover{background:#f9fafb;border-color:var(--primary-color);transform:translateX(5px)}
        .subscriber-checkbox input{width:20px;height:20px;cursor:pointer;accent-color:var(--primary-color)}
        .subscriber-checkbox.selected{background:#eff6ff;border-color:var(--primary-color)}
        
        .recipient-section{background:#f9fafb;padding:20px;border-radius:10px;margin:20px 0;border:2px solid var(--border-color)}
        .recipient-option{display:flex;align-items:center;gap:10px;padding:12px;margin:8px 0;border-radius:6px;cursor:pointer;transition:background .2s}
        .recipient-option:hover{background:white}
        .recipient-option input{width:18px;height:18px;cursor:pointer}
        
        .select-all-btn{background:var(--primary-color);color:white;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:600;margin:10px 0}
        .select-all-btn:hover{background:var(--secondary-color)}
        
        .char-counter-live{float:right;font-weight:600;padding:5px 12px;border-radius:15px;font-size:14px}
        .char-ok{background:#d1fae5;color:#065f46}
        .char-warn{background:#fed7aa;color:#92400e}
        .char-danger{background:#fee2e2;color:#991b1b}
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>📢 Send Announcements</h1>
            <p>Send custom SMS messages to your subscribers</p>
        </div>
    </header>
    
    <nav>
        <div class="container">
            <ul>
                <li><a href="admin.php">Manage News</a></li>
                <li><a href="subscribers.php">Manage Subscribers</a></li>
                <li><a href="send_sms.php">Send SMS</a></li>
                <li><a href="announcement.php" class="active">Announcement</a></li>
                <li><a href="import_news.php">Import News</a></li>
                <li><a href="test_sim800c.php">Test SIM800C</a></li>
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
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-number"><?= $stats['t'] ?></div>
                    <div class="stat-label">Total Announcements</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-number"><?= $stats['s'] ?></div>
                    <div class="stat-label">Successfully Sent</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-number"><?= $active ?></div>
                    <div class="stat-label">Active Subscribers</div>
                </div>
            </div>
            
            <!-- Main Form -->
            <div class="card">
                <div class="card-header">
                    <h2>📝 Create Announcement</h2>
                </div>
                
                <div class="alert alert-info">
                    <strong>📢 How to use:</strong>
                    <ol style="margin:.5rem 0 0 1.5rem;line-height:1.8">
                        <li>Write your announcement message (Hiligaynon or English)</li>
                        <li>Choose SMS gateway (Online or Offline)</li>
                        <li>Select recipients (All, Single, or Multiple)</li>
                        <li>Click "Send Announcement"</li>
                    </ol>
                </div>
                
                <form method="POST">
                    <!-- Message Input -->
                    <div class="form-group">
                        <label for="announcement_message">
                            <strong>💬 Your Announcement Message</strong>
                            <span id="liveCounter" class="char-counter-live char-ok">0 / 160</span>
                        </label>
                        <textarea 
                            id="announcement_message" 
                            name="announcement_message" 
                            class="form-control" 
                            rows="4" 
                            maxlength="160" 
                            placeholder="Type your announcement here in Hiligaynon or English...&#10;&#10;Example: Importante nga pahibalo: Ang opisina sarado bukas para sa holiday. Salamat!"
                            required></textarea>
                        <small style="color:var(--gray-text);display:block;margin-top:8px">
                            💡 Write in Hiligaynon for direct delivery, or English (will be sent as-is)
                        </small>
                    </div>
                    
                    <!-- Gateway Selection -->
                    <div class="form-group">
                        <label><strong>📡 SMS Gateway</strong></label>
                        <select name="gateway" class="form-control">
                            <option value="IPROG">🌐 IPROG (Online)</option>
                            <option value="ARDUINO">📡 SIM800C (Offline)</option>
                            <option value="SEMAPHORE">🇵🇭 Semaphore</option>
                        </select>
                    </div>
                    
                    <!-- Recipients Selection -->
                    <div class="recipient-section">
                        <h3 style="margin-bottom:15px;color:var(--dark-text)">👥 Select Recipients</h3>
                        
                        <!-- Recipient Type Options -->
                        <div class="recipient-option">
                            <input type="radio" name="recipient_type" value="all" id="all_recipients" checked>
                            <label for="all_recipients" style="cursor:pointer;flex:1;font-weight:600">
                                ✅ Send to All Active Subscribers (<?= $active ?>)
                            </label>
                        </div>
                        
                        <div class="recipient-option">
                            <input type="radio" name="recipient_type" value="single" id="single_recipient">
                            <label for="single_recipient" style="cursor:pointer;flex:1;font-weight:600">
                                👤 Send to Single Subscriber
                            </label>
                        </div>
                        
                        <div class="recipient-option">
                            <input type="radio" name="recipient_type" value="selected" id="selected_recipients">
                            <label for="selected_recipients" style="cursor:pointer;flex:1;font-weight:600">
                                📋 Send to Selected Subscribers
                            </label>
                        </div>
                        
                        <!-- Single Subscriber Selector -->
                        <div id="single_selector" style="display:none;margin-top:15px">
                            <label><strong>Choose Subscriber:</strong></label>
                            <select name="single_subscriber" class="form-control">
                                <?php 
                                $allSubscribers->data_seek(0);
                                while($sub = $allSubscribers->fetch_assoc()): 
                                ?>
                                    <option value="<?= $sub['id'] ?>">
                                        <?= htmlspecialchars($sub['name']) ?> (<?= $sub['phone_number'] ?>)
                                        <?= $sub['active'] ? '' : ' - INACTIVE' ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Multiple Subscribers Selector -->
                        <div id="multiple_selector" style="display:none;margin-top:15px">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                                <strong>Choose Subscribers:</strong>
                                <button type="button" class="select-all-btn" onclick="toggleSelectAll(this)">
                                    Select All
                                </button>
                            </div>
                            <div style="max-height:300px;overflow-y:auto;border:1px solid var(--border-color);border-radius:8px;padding:10px">
                                <?php 
                                $allSubscribers->data_seek(0);
                                while($sub = $allSubscribers->fetch_assoc()): 
                                ?>
                                    <label class="subscriber-checkbox" data-id="<?= $sub['id'] ?>">
                                        <input type="checkbox" name="selected_subscribers[]" value="<?= $sub['id'] ?>">
                                        <div style="flex:1">
                                            <strong><?= htmlspecialchars($sub['name']) ?></strong>
                                            <br>
                                            <small style="color:var(--gray-text)"><?= $sub['phone_number'] ?></small>
                                        </div>
                                        <span class="badge badge-<?= $sub['active'] ? 'success' : 'danger' ?>">
                                            <?= $sub['active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                            <p id="selectedCount" style="margin-top:10px;color:var(--gray-text);font-weight:600">
                                0 subscribers selected
                            </p>
                        </div>
                    </div>
                    
                    <!-- Send Button -->
                    <button type="submit" name="send_announcement" class="btn btn-success" style="width:100%;font-size:1.1rem;padding:1rem">
                        📢 Send Announcement
                    </button>
                </form>
            </div>
            
            <!-- Recent Announcements -->
            <div class="table-container">
                <h2>📋 Recent Announcements</h2>
                <?php if($recentAnnouncements->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Recipient</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($log = $recentAnnouncements->fetch_assoc()): ?>
                                <tr>
                                    <td style="white-space:nowrap"><?= formatDate($log['sent_at']) ?></td>
                                    <td><?= htmlspecialchars($log['name'] ?? 'Unknown') ?></td>
                                    <td><?= $log['phone_number'] ?? 'N/A' ?></td>
                                    <td>
                                        <span class="badge badge-<?= $log['status']==='sent'?'success':'danger' ?>">
                                            <?= $log['status']==='sent'?'✅ Sent':'❌ Failed' ?>
                                        </span>
                                    </td>
                                    <td style="max-width:400px;line-height:1.6">
                                        <?= htmlspecialchars($log['message']) ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>No announcements sent yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> News Portal. Announcement System</p>
        </div>
    </footer>
    
    <script>
    // Live character counter
    const textarea = document.getElementById('announcement_message');
    const counter = document.getElementById('liveCounter');
    
    textarea.addEventListener('input', function() {
        const len = this.value.length;
        counter.textContent = len + ' / 160';
        counter.className = 'char-counter-live ' + (len > 150 ? (len > 160 ? 'char-danger' : 'char-warn') : 'char-ok');
    });
    
    // Recipient type selector
    document.querySelectorAll('input[name="recipient_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('single_selector').style.display = this.value === 'single' ? 'block' : 'none';
            document.getElementById('multiple_selector').style.display = this.value === 'selected' ? 'block' : 'none';
        });
    });
    
    // Select all checkbox handler
    function toggleSelectAll(btn) {
        const checkboxes = document.querySelectorAll('#multiple_selector input[type="checkbox"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
            updateCheckboxStyle(cb);
        });
        
        btn.textContent = allChecked ? 'Select All' : 'Deselect All';
        updateSelectedCount();
    }
    
    // Update checkbox styling
    function updateCheckboxStyle(checkbox) {
        const label = checkbox.closest('.subscriber-checkbox');
        if (checkbox.checked) {
            label.classList.add('selected');
        } else {
            label.classList.remove('selected');
        }
    }
    
    // Update selected count
    function updateSelectedCount() {
        const checked = document.querySelectorAll('#multiple_selector input[type="checkbox"]:checked').length;
        document.getElementById('selectedCount').textContent = checked + ' subscriber' + (checked !== 1 ? 's' : '') + ' selected';
    }
    
    // Add event listeners to checkboxes
    document.querySelectorAll('#multiple_selector input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', function() {
            updateCheckboxStyle(this);
            updateSelectedCount();
        });
    });
    
    // Click on label to toggle checkbox
    document.querySelectorAll('.subscriber-checkbox').forEach(label => {
        label.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT') {
                const checkbox = this.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                updateCheckboxStyle(checkbox);
                updateSelectedCount();
            }
        });
    });
    </script>
</body>
</html>