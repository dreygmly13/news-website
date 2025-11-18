<?php
// bleu_checker.php - TRANSLATION QUALITY WITH BLEU SCORE
require_once 'config.php';

// Calculate BLEU Score
function calculateBLEU($reference, $candidate) {
    // Tokenize (split into words)
    $refTokens = preg_split('/\s+/', strtolower(trim($reference)));
    $candTokens = preg_split('/\s+/', strtolower(trim($candidate)));
    
    if (empty($candTokens)) return 0;
    
    // Calculate precision for n-grams (1-gram to 4-gram)
    $precisions = [];
    
    for ($n = 1; $n <= 4; $n++) {
        $refNgrams = getNgrams($refTokens, $n);
        $candNgrams = getNgrams($candTokens, $n);
        
        if (empty($candNgrams)) {
            $precisions[] = 0;
            continue;
        }
        
        $matches = 0;
        foreach ($candNgrams as $ngram) {
            if (in_array($ngram, $refNgrams)) {
                $matches++;
            }
        }
        
        $precisions[] = $matches / count($candNgrams);
    }
    
    // Calculate geometric mean of precisions
    $geometricMean = 1;
    foreach ($precisions as $p) {
        $geometricMean *= ($p > 0) ? $p : 0.00001; // Avoid log(0)
    }
    $geometricMean = pow($geometricMean, 1/4);
    
    // Brevity penalty
    $refLength = count($refTokens);
    $candLength = count($candTokens);
    $bp = ($candLength < $refLength) ? exp(1 - $refLength / $candLength) : 1;
    
    // Final BLEU score
    $bleu = $bp * $geometricMean;
    
    return round($bleu * 100, 2); // Return as percentage
}

// Get n-grams from tokens
function getNgrams($tokens, $n) {
    $ngrams = [];
    $count = count($tokens);
    
    for ($i = 0; $i <= $count - $n; $i++) {
        $ngram = '';
        for ($j = 0; $j < $n; $j++) {
            $ngram .= $tokens[$i + $j] . ' ';
        }
        $ngrams[] = trim($ngram);
    }
    
    return $ngrams;
}

// Translate with quality metrics
function translateWithBLEU($englishText) {
    // First translation attempt
    $data = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert Hiligaynon translator. Translate the English news to natural, fluent Hiligaynon. Keep it informative and complete within 160 characters.'
            ],
            [
                'role' => 'user',
                'content' => "Translate to Hiligaynon:\n\n$englishText"
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $translation = '';
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            $translation = trim($result['choices'][0]['message']['content']);
        }
    }
    
    // Get reference translation for BLEU comparison
    $referenceTranslation = getReferenceTranslation($englishText);
    
    // Calculate BLEU score
    $bleuScore = $referenceTranslation ? calculateBLEU($referenceTranslation, $translation) : 0;
    
    return [
        'translation' => $translation,
        'reference' => $referenceTranslation,
        'bleu_score' => $bleuScore,
        'quality' => getQualityRating($bleuScore)
    ];
}

// Get reference translation for comparison
function getReferenceTranslation($englishText) {
    // This would ideally come from a human translator or database
    // For now, we'll use a second AI translation as reference
    $data = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a professional Hiligaynon translator. Provide the most accurate, natural translation possible.'
            ],
            [
                'role' => 'user',
                'content' => "Translate to Hiligaynon:\n\n$englishText"
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
    
    return $result['choices'][0]['message']['content'] ?? null;
}

// Get quality rating based on BLEU score
function getQualityRating($score) {
    if ($score >= 80) return ['label' => 'Excellent', 'color' => 'success'];
    if ($score >= 60) return ['label' => 'Good', 'color' => 'info'];
    if ($score >= 40) return ['label' => 'Fair', 'color' => 'warning'];
    return ['label' => 'Poor', 'color' => 'danger'];
}

// Handle test translation
$testResult = null;
if (isset($_POST['test_translation'])) {
    $testText = $_POST['test_text'];
    $testResult = translateWithBLEU($testText);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Translation Quality Checker</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .quality-meter {
            height: 30px;
            background: #e5e7eb;
            border-radius: 15px;
            overflow: hidden;
            margin: 10px 0;
        }
        .quality-fill {
            height: 100%;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .quality-excellent { background: linear-gradient(90deg, #10b981, #059669); }
        .quality-good { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .quality-fair { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .quality-poor { background: linear-gradient(90deg, #ef4444, #dc2626); }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>📊 Translation Quality Checker</h1>
            <p>Measure translation quality with BLEU Score</p>
        </div>
    </header>
    
    <nav>
        <div class="container">
            <ul>
                <li><a href="admin.php">Manage News</a></li>
                <li><a href="import_news.php">Import News</a></li>
                <li><a href="send_sms.php">Send SMS</a></li>
                <li><a href="bleu_checker.php" class="active">Translation Quality</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <main>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h2>🔍 Test Translation Quality</h2>
                </div>
                <div class="alert alert-info">
                    <strong>What is BLEU Score?</strong><br>
                    BLEU (Bilingual Evaluation Understudy) measures translation quality by comparing with reference translations.
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <li><strong>80-100:</strong> Excellent translation</li>
                        <li><strong>60-79:</strong> Good translation</li>
                        <li><strong>40-59:</strong> Fair translation</li>
                        <li><strong>0-39:</strong> Poor translation</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label>English Text to Translate</label>
                        <textarea name="test_text" class="form-control" rows="4" required>A nationwide earthquake drill will be conducted next week to enhance disaster preparedness. Citizens will learn proper Drop, Cover, and Hold On procedures.</textarea>
                    </div>
                    <button type="submit" name="test_translation" class="btn btn-primary" style="width:100%;">
                        🔄 Translate & Evaluate Quality
                    </button>
                </form>
            </div>
            
            <?php if ($testResult): ?>
            <div class="card">
                <div class="card-header">
                    <h2>📊 Translation Results</h2>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <h3>Hiligaynon Translation:</h3>
                    <div style="background: #f0fdf4; padding: 20px; border-radius: 8px; border-left: 4px solid #10b981;">
                        <p style="font-size: 18px; line-height: 1.8; color: #065f46;">
                            <strong><?= htmlspecialchars($testResult['translation']) ?></strong>
                        </p>
                        <p style="color: #6b7280; margin-top: 10px;">
                            Length: <?= strlen($testResult['translation']) ?> characters
                        </p>
                    </div>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <h3>BLEU Score:</h3>
                    <div class="quality-meter">
                        <div class="quality-fill quality-<?= strtolower($testResult['quality']['label']) ?>" 
                             style="width: <?= $testResult['bleu_score'] ?>%;">
                            <?= $testResult['bleu_score'] ?>% - <?= $testResult['quality']['label'] ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($testResult['reference']): ?>
                <div>
                    <h3>Reference Translation (for comparison):</h3>
                    <div style="background: #f3f4f6; padding: 15px; border-radius: 8px;">
                        <p style="color: #4b5563;"><?= htmlspecialchars($testResult['reference']) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>© <?= date('Y') ?> News Portal</p>
        </div>
    </footer>
</body>
</html>