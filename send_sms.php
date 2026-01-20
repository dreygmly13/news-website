<?php
/**
 * ================================================================
 * SEND SMS - NEWS BROADCASTING WITH AI TRANSLATION + BLEU SCORE
 * ================================================================
 * Features: AI translation, BLEU quality metrics, Multi-gateway
 * Version: 2.0
 * ================================================================
 */

require_once 'includes/config.php';
require_once 'sms_gateways.php';
checkAuth();

$db = getDB();
$success = '';
$error = '';
$warning = '';

// Preview variables
$previewMode = false;
$previewTranslation = null;
$previewNews = null;
$previewSummary = null;
$bleuScore = null;
$qualityRating = null;

// SIM800C test variables
$sim800cStatusCheck = null;
$sim800cTestResult = null;

/**
 * ================================================================
 * AI FUNCTIONS
 * ================================================================
 */

function createSummary($content) {
    $data = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Create a 2-sentence news summary. Maximum 140 characters. Include key facts: what, when, where, why.'
            ],
            [
                'role' => 'user',
                'content' => "Summarize (max 140 chars):\n\n" . $content
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);
    
    return trim($result['choices'][0]['message']['content'] ?? '');
}

function translateToHiligaynon($englishText) {
    $data = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Translate to natural Hiligaynon. Keep complete sentences. Maximum 160 characters. Reply with ONLY Hiligaynon text, no quotes.'
            ],
            [
                'role' => 'user',
                'content' => "Translate to Hiligaynon (max 160 chars):\n\n$englishText"
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);
    
    $translation = trim($result['choices'][0]['message']['content'] ?? '');
    $translation = trim($translation, '"\'');
    
    if (strlen($translation) > 160) {
        $sentences = preg_split('/(?<=[.!?])\s+/', $translation);
        $final = '';
        foreach ($sentences as $s) {
            if (strlen($final . $s) <= 160) $final .= $s . ' ';
            else break;
        }
        $translation = trim($final) ?: substr($translation, 0, 157) . '...';
    }
    
    return $translation;
}

function summarizeAndTranslate($content) {
    $summary = createSummary($content);
    return $summary ? translateToHiligaynon($summary) : false;
}

/**
 * ================================================================
 * BLEU SCORE FUNCTIONS
 * ================================================================
 */

function calculateBLEU($reference, $candidate) {
    if (empty($reference) || empty($candidate)) {
        return 0;
    }
    
    $refTokens = preg_split('/\s+/', strtolower(trim($reference)));
    $candTokens = preg_split('/\s+/', strtolower(trim($candidate)));
    
    if (empty($candTokens)) {
        return 0;
    }
    
    $precisions = [];
    
    for ($n = 1; $n <= 4; $n++) {
        $refNgrams = getNgrams($refTokens, $n);
        $candNgrams = getNgrams($candTokens, $n);
        
        if (empty($candNgrams)) {
            $precisions[] = 0.00001;
            continue;
        }
        
        $matches = 0;
        foreach ($candNgrams as $ngram) {
            if (in_array($ngram, $refNgrams)) {
                $matches++;
            }
        }
        
        $precision = $matches / count($candNgrams);
        $precisions[] = $precision > 0 ? $precision : 0.00001;
    }
    
    $geometricMean = 1;
    foreach ($precisions as $p) {
        $geometricMean *= $p;
    }
    $geometricMean = pow($geometricMean, 1/4);
    
    $refLength = count($refTokens);
    $candLength = count($candTokens);
    $brevityPenalty = ($candLength < $refLength) ? exp(1 - $refLength / $candLength) : 1;
    
    $bleuScore = $brevityPenalty * $geometricMean * 100;
    
    return round($bleuScore, 2);
}

function getNgrams($tokens, $n) {
    $ngrams = [];
    $tokenCount = count($tokens);
    
    for ($i = 0; $i <= $tokenCount - $n; $i++) {
        $ngram = '';
        for ($j = 0; $j < $n; $j++) {
            $ngram .= $tokens[$i + $j] . ' ';
        }
        $ngrams[] = trim($ngram);
    }
    
    return $ngrams;
}

function getQualityRating($score) {
    if ($score >= 80) {
        return [
            'label' => 'Excellent',
            'color' => 'success',
            'class' => 'bleu-excellent',
            'description' => 'High-quality translation, very close to reference'
        ];
    } elseif ($score >= 60) {
        return [
            'label' => 'Good',
            'color' => 'info',
            'class' => 'bleu-good',
            'description' => 'Good translation quality with minor differences'
        ];
    } elseif ($score >= 40) {
        return [
            'label' => 'Fair',
            'color' => 'warning',
            'class' => 'bleu-fair',
            'description' => 'Acceptable translation, some improvements possible'
        ];
    } else {
        return [
            'label' => 'Needs Improvement',
            'color' => 'danger',
            'class' => 'bleu-poor',
            'description' => 'Translation quality could be better, consider re-translating'
        ];
    }
}

/**
 * ================================================================
 * REQUEST HANDLERS
 * ================================================================
 */

// SIM800C Status Check
if (isset($_POST['check_sim800c'])) {
    if (SIM800C_ENABLED && SMSGateway::isSIM800CAvailable()) {
        $ch = curl_init(SIM800C_SERVICE_URL . '/status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $sim800cStatusCheck = json_decode($response, true);
        }
    }
}

// SIM800C Test SMS
if (isset($_POST['test_sim800c_sms'])) {
    if (SIM800C_ENABLED && SMSGateway::isSIM800CAvailable()) {
        $testPhone = $_POST['test_phone'];
        $testMessage = "Test from SIM800C - " . date('H:i:s');
        
        $ch = curl_init(SIM800C_SERVICE_URL . '/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'phone' => $testPhone,
            'message' => $testMessage
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $sim800cTestResult = json_decode($response, true);
        }
    }
}

// Preview Translation with BLEU Score
if (isset($_POST['preview_translation'])) {
    $previewMode = true;
    $previewId = intval($_POST['news_id']);
    
    $stmt = $db->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->bind_param("i", $previewId);
    $stmt->execute();
    $previewNews = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($previewNews) {
        $previewSummary = createSummary($previewNews['content']);
        $previewTranslation = $previewSummary ? translateToHiligaynon($previewSummary) : null;
        
        // Calculate BLEU score
        if ($previewTranslation) {
            $referenceTranslation = translateToHiligaynon($previewSummary);
            
            if ($referenceTranslation && $referenceTranslation !== $previewTranslation) {
                $bleuScore = calculateBLEU($referenceTranslation, $previewTranslation);
                $qualityRating = getQualityRating($bleuScore);
            }
        }
    }
}

// Send SMS
if (isset($_POST['send_sms'])) {
    $newsId = intval($_POST['news_id']);
    $gateway = $_POST['gateway'] ?? 'IPROG';
    $recipientType = $_POST['recipient_type'] ?? 'all';
    
    $stmt = $db->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->bind_param("i", $newsId);
    $stmt->execute();
    $news = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($news) {
        $translated = summarizeAndTranslate($news['content']);
        
        if ($translated) {
            $recipients = [];
            
            if ($recipientType === 'all') {
                $subs = $db->query("SELECT * FROM subscribers WHERE active = 1");
                while ($s = $subs->fetch_assoc()) $recipients[] = $s;
            } elseif ($recipientType === 'single') {
                $id = intval($_POST['single_subscriber']);
                $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $s = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($s) $recipients[] = $s;
            } elseif ($recipientType === 'selected') {
                $ids = $_POST['selected_subscribers'] ?? [];
                if (!empty($ids)) {
                    $idList = implode(',', array_map('intval', $ids));
                    $subs = $db->query("SELECT * FROM subscribers WHERE id IN ($idList)");
                    while ($s = $subs->fetch_assoc()) $recipients[] = $s;
                }
            }
            
            $sent = 0;
            $failed = 0;
            $errors = [];
            
            foreach ($recipients as $r) {
                $result = SMSGateway::send($r['phone_number'], $translated, $gateway, false);
                
                $status = $result['success'] ? 'sent' : 'failed';
                $stmt = $db->prepare("INSERT INTO sms_logs (subscriber_id, news_id, message, status) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $r['id'], $newsId, $translated, $status);
                $stmt->execute();
                $stmt->close();
                
                $result['success'] ? $sent++ : ($failed++ && $errors[] = "{$r['name']}: {$result['error']}");
                sleep(2);
            }
            
            $gatewayName = $gateway === 'ARDUINO' ? 'USB SIM800C' : $gateway;
            $success = "✅ Sent via $gatewayName to $sent recipient(s)!";
            if ($failed > 0) {
                $success .= " ($failed failed)";
                $error = implode('<br>', array_slice($errors, 0, 3));
            }
        } else {
            $error = '❌ Translation failed';
        }
    }
}

$news = $db->query("SELECT * FROM news ORDER BY created_at DESC");
$allSubs = $db->query("SELECT * FROM subscribers ORDER BY name ASC");
$logs = $db->query("SELECT sl.*, s.name, s.phone_number, n.title, n.category FROM sms_logs sl LEFT JOIN subscribers s ON sl.subscriber_id=s.id LEFT JOIN news n ON sl.news_id=n.id WHERE sl.news_id IS NOT NULL ORDER BY sent_at DESC LIMIT 20");
$stats = $db->query("SELECT COUNT(*) t, SUM(status='sent') s, SUM(status='failed') f FROM sms_logs WHERE news_id IS NOT NULL")->fetch_assoc();
$active = $db->query("SELECT COUNT(*) c FROM subscribers WHERE active=1")->fetch_assoc()['c'];
$sim800cAvailable = SIM800C_ENABLED ? SMSGateway::isSIM800CAvailable() : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send SMS - News Broadcasting</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;margin-bottom:2rem}
        .stat-card{background:var(--white);padding:1.5rem;border-radius:12px;box-shadow:var(--shadow);text-align:center;transition:transform .3s}
        .stat-card:hover{transform:translateY(-3px)}
        .stat-number{font-size:2.5rem;font-weight:700;margin-bottom:.5rem}
        .stat-label{color:var(--gray-text);font-size:.875rem;text-transform:uppercase;font-weight:600}
        .stat-card.primary .stat-number{color:var(--primary-color)}
        .stat-card.success .stat-number{color:var(--success-color)}
        .stat-card.danger .stat-number{color:var(--danger-color)}
        .stat-card.info .stat-number{color:var(--info-color)}
        
        .preview-box{background:linear-gradient(135deg,#eff6ff,#dbeafe);padding:25px;border-radius:12px;border-left:5px solid var(--primary-color);margin:20px 0}
        .translation-box{background:linear-gradient(135deg,#f0fdf4,#dcfce7);padding:20px;border-radius:8px;border-left:4px solid var(--success-color);margin:15px 0}
        .translation-text{font-size:18px;line-height:1.8;font-weight:600}
        .char-counter{display:inline-block;padding:6px 14px;background:var(--success-color);color:white;border-radius:20px;font-size:14px;font-weight:600;margin-top:12px}
        .char-counter.warning{background:var(--warning-color)}
        .char-counter.danger{background:var(--danger-color)}
        
        .bleu-section{margin-top:20px;padding-top:20px;border-top:2px dashed var(--border-color)}
        .bleu-meter{height:30px;background:#e5e7eb;border-radius:15px;overflow:hidden;margin:12px 0;box-shadow:inset 0 2px 4px rgba(0,0,0,0.1)}
        .bleu-fill{height:100%;transition:width .8s ease;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:14px}
        .bleu-excellent{background:linear-gradient(90deg,#10b981,#059669)}
        .bleu-good{background:linear-gradient(90deg,#3b82f6,#2563eb)}
        .bleu-fair{background:linear-gradient(90deg,#f59e0b,#d97706)}
        .bleu-poor{background:linear-gradient(90deg,#ef4444,#dc2626)}
        
        .recipient-box{background:#f9fafb;padding:15px;border-radius:8px;margin:15px 0;border:2px solid var(--border-color)}
        .subscriber-list{max-height:200px;overflow-y:auto;border:1px solid var(--border-color);border-radius:6px;padding:10px;margin-top:10px}
        .subscriber-item{display:flex;align-items:center;gap:10px;padding:8px;border:2px solid var(--border-color);border-radius:6px;margin:5px 0;cursor:pointer;transition:all .2s}
        .subscriber-item:hover{background:#f9fafb;border-color:var(--primary-color)}
        .subscriber-item.selected{background:#eff6ff;border-color:var(--primary-color)}
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>📱 Send SMS - News Broadcast</h1>
            <p>AI Translation to Hiligaynon + BLEU Quality Score</p>
        </div>
    </header>
    
    <nav>
        <div class="container">

            <ul id="nav-menu">
                <li><a href="admin.php">Manage News</a></li>
                <li><a href="subscribers.php">Manage Residents</a></li>
                <li><a href="send_sms.php" class="active">Send SMS</a></li>
                <li><a href="announcement.php">Announcement</a></li>
                <!-- <li><a href="import_news.php">Import News</a></li> -->
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
                <div class="stat-card info">
                    <div class="stat-number"><?= $active ?></div>
                    <div class="stat-label">Active Subscribers</div>
                </div>
                <div class="stat-card primary">
                    <div class="stat-number"><?= $stats['t'] ?></div>
                    <div class="stat-label">Total Broadcasts</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-number"><?= $stats['s'] ?></div>
                    <div class="stat-label">Successful</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-number"><?= $stats['f'] ?></div>
                    <div class="stat-label">Failed</div>
                </div>
            </div>
            
            <!-- SIM800C Panel -->
            <?php if (SIM800C_ENABLED): ?>
            <div class="card" style="border-left:5px solid <?= $sim800cAvailable ? 'var(--success-color)' : 'var(--warning-color)' ?>">
                <div class="card-header"><h2>📡 USB SIM800C Status</h2></div>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px">
                    <div style="padding:20px;background:<?= $sim800cAvailable ? '#d1fae5' : '#fee2e2' ?>;border-radius:10px">
                        <h3 style="margin-bottom:12px">Service</h3>
                        <?php if ($sim800cAvailable): ?>
                            <p style="color:#047857;font-weight:600">✅ Running</p>
                        <?php else: ?>
                            <p style="color:#991b1b;font-weight:600">❌ Offline</p>
                            <p style="color:#7f1d1d;font-size:0.875rem">Check: <code>sms_service.php</code></p>
                        <?php endif; ?>
                    </div>
                    
                    <div style="padding:20px;background:#f9fafb;border-radius:10px">
                        <h3 style="margin-bottom:12px">Check</h3>
                        <form method="POST">
                            <button type="submit" name="check_sim800c" class="btn btn-primary" <?= !$sim800cAvailable ? 'disabled' : '' ?> style="width:100%">
                                🔍 Check
                            </button>
                        </form>
                        <?php if ($sim800cStatusCheck && $sim800cStatusCheck['connected']): ?>
                            <div style="margin-top:10px;padding:8px;background:#d1fae5;border-radius:6px;font-size:0.875rem;color:#047857">
                                ✅ Connected<br>
                                Signal: <?= $sim800cStatusCheck['signal'] ?>/31
                            </div>
                        <?php endif; ?>
                    </div>
                    
               
                
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Preview with BLEU Score -->
            <?php if($previewMode && $previewTranslation && $previewNews): ?>
            <div class="card">
                <div class="card-header"><h2>👁️ Translation Preview + Quality Score</h2></div>
                <div class="preview-box">
                    <div style="background:white;padding:18px;border-radius:8px;margin-bottom:18px">
                        <h3><?= htmlspecialchars($previewNews['title']) ?></h3>
                        <?php if($previewSummary): ?>
                        <div style="background:#fef3c7;padding:10px;border-radius:6px;margin:10px 0">
                            <strong>English Summary:</strong> <?= htmlspecialchars($previewSummary) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background:white;padding:22px;border-radius:10px;border:2px solid var(--primary-color)">
                        <h4 style="color:var(--primary-color);margin-bottom:15px">🇵🇭 Hiligaynon Translation:</h4>
                        <div class="translation-box">
                            <p class="translation-text">"<?= htmlspecialchars($previewTranslation) ?>"</p>
                            <span class="char-counter <?= strlen($previewTranslation)>150?'warning':'' ?> <?= strlen($previewTranslation)>160?'danger':'' ?>">
                                <?= strlen($previewTranslation) ?> / 160 chars
                            </span>
                        </div>
                        
                        <!-- BLEU SCORE SECTION -->
                        <?php if ($bleuScore !== null && $qualityRating): ?>
                        <div class="bleu-section">
                            <h5 style="margin-bottom:10px;color:var(--dark-text);font-weight:700">
                                📊 Translation Quality (BLEU Score):
                            </h5>
                            <div class="bleu-meter">
                                <div class="bleu-fill <?= $qualityRating['class'] ?>" style="width:<?= $bleuScore ?>%">
                                    <?= $bleuScore ?>% - <?= $qualityRating['label'] ?>
                                </div>
                            </div>
                            <p style="font-size:13px;color:var(--gray-text);margin-top:8px;line-height:1.7">
                                <strong style="color:var(--dark-text)"><?= $qualityRating['description'] ?></strong><br>
                                <em>BLEU (Bilingual Evaluation Understudy) measures translation quality by comparing with reference translations. Higher scores indicate better quality.</em>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST" style="margin-top:15px">
                    <input type="hidden" name="news_id" value="<?= $previewNews['id'] ?>">
                    <input type="hidden" name="gateway" value="<?= $_POST['gateway'] ?? 'IPROG' ?>">
                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:10px">
                        <button type="submit" name="preview_translation" class="btn btn-warning">🔄 Re-translate</button>
                        <button type="submit" name="send_sms" class="btn btn-success">✅ Send SMS</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Main Form -->
            <div class="card">
                <div class="card-header"><h2>📤 Broadcast News</h2></div>
                
                <form method="POST">
                    <div class="form-group">
                        <label><strong>📡 Gateway</strong></label>
                        <select name="gateway" class="form-control">
                            <option value="IPROG">🌐 IPROG</option>
                            <option value="ARDUINO">📡 SIM800C <?= $sim800cAvailable?'✅':'⚠️' ?></option>
                            <option value="SEMAPHORE">🇵🇭 Semaphore</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><strong>📰 News</strong></label>
                        <select name="news_id" class="form-control" required>
                            <option value="">-- Select --</option>
                            <?php $news->data_seek(0); while($n=$news->fetch_assoc()): ?>
                                <option value="<?= $n['id'] ?>">[<?= strtoupper($n['category']) ?>] <?= htmlspecialchars($n['title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="recipient-box">
                        <strong>👥 Send To:</strong>
                        <div style="margin-top:10px">
                            <label style="display:block;padding:8px;cursor:pointer">
                                <input type="radio" name="recipient_type" value="all" id="all-radio" checked> 
                                <strong>✅ All (<?= $active ?>)</strong>
                            </label>
                            <label style="display:block;padding:8px;cursor:pointer">
                                <input type="radio" name="recipient_type" value="single" id="single-radio"> 
                                <strong>👤 One</strong>
                            </label>
                            <label style="display:block;padding:8px;cursor:pointer">
                                <input type="radio" name="recipient_type" value="selected" id="selected-radio"> 
                                <strong>📋 Selected</strong>
                            </label>
                        </div>
                        
                        <div id="single-selector" style="display:none;margin-top:10px">
                            <select name="single_subscriber" class="form-control">
                                <?php $allSubs->data_seek(0); while($s=$allSubs->fetch_assoc()): ?>
                                    <option value="<?= $s['id'] ?>"><?= $s['name'] ?> (<?= $s['phone_number'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div id="multi-selector" style="display:none;margin-top:10px">
                            <div class="subscriber-list">
                                <?php $allSubs->data_seek(0); while($s=$allSubs->fetch_assoc()): ?>
                                    <label class="subscriber-item">
                                        <input type="checkbox" name="selected_subscribers[]" value="<?= $s['id'] ?>">
                                        <div style="flex:1"><strong><?= $s['name'] ?></strong><br><small><?= $s['phone_number'] ?></small></div>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-top:20px">
                        <button type="submit" name="preview_translation" class="btn btn-primary">👁️ Preview + BLEU</button>
                        <button type="submit" name="send_sms" class="btn btn-success">📱 Send SMS</button>
                    </div>
                </form>
            </div>
            
            <!-- Logs -->
            <div class="table-container">
                <h2>📋 Recent Broadcasts</h2>
                <?php if($logs->num_rows>0): ?>
                    <table>
                        <tr><th>Time</th><th>Name</th><th>Phone</th><th>Category</th><th>Status</th><th>Message</th></tr>
                        <?php while($l=$logs->fetch_assoc()): ?>
                            <tr>
                                <td style="white-space:nowrap"><?= formatDate($l['sent_at']) ?></td>
                                <td><?= htmlspecialchars($l['name']) ?></td>
                                <td><?= $l['phone_number'] ?></td>
                                <td><span class="badge badge-<?= $l['category']==='weather'?'success':($l['category']==='health'?'warning':'danger') ?>"><?= ucfirst($l['category']) ?></span></td>
                                <td><span class="badge badge-<?= $l['status']==='sent'?'success':'danger' ?>"><?= $l['status']==='sent'?'✅':'❌' ?></span></td>
                                <td style="max-width:350px"><?= htmlspecialchars($l['message']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                <?php else: ?>
                    <div class="empty-state"><div class="empty-state-icon">📭</div><p>No broadcasts yet</p></div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>© <?= date('Y') ?> News Portal</p>
        </div>
    </footer>
    
    <script>
    function toggleNav(){const m=document.getElementById('nav-menu'),i=document.getElementById('nav-icon');m.classList.toggle('active');i.textContent=m.classList.contains('active')?'✕':'☰ Menu'}
    
    document.querySelectorAll('input[name="recipient_type"]').forEach(r=>{
        r.addEventListener('change',function(){
            document.getElementById('single-selector').style.display=this.value==='single'?'block':'none';
            document.getElementById('multi-selector').style.display=this.value==='selected'?'block':'none';
        });
    });
    
    document.querySelectorAll('.subscriber-item').forEach(item=>{
        item.addEventListener('click',function(e){
            if(e.target.tagName!=='INPUT'){
                const cb=this.querySelector('input');
                if(cb){
                    cb.checked=!cb.checked;
                    this.classList.toggle('selected',cb.checked);
                }
            }
        });
    });
    </script>
</body>
</html>