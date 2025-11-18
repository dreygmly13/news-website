<?php
/**
 * ================================================================
 * NEWS PORTAL SMS BROADCASTING SYSTEM
 * ================================================================
 * 
 * File: send_sms.php
 * Version: 3.0
 * Description: Advanced SMS broadcasting system with AI translation to Hiligaynon
 * 
 * FEATURES:
 * - Multi-gateway SMS support (IPROG API, SIM800C GSM, Semaphore)
 * - AI-powered summarization and translation
 * - Translation preview with quality metrics
 * - BLEU score calculation for translation quality
 * - Real-time statistics dashboard
 * - Comprehensive error handling and logging
 * - Rate limiting and retry mechanisms
 * - Mobile-responsive design
 * 
 * SUPPORTED GATEWAYS:
 * 1. IPROG (Online) - Internet-based API, fast delivery
 * 2. SIM800C (Offline) - Arduino + GSM module, works without internet
 * 3. Semaphore (Online) - Philippines-optimized API
 * 
 * AUTHOR: News Portal Development Team
 * DATE: <?= date('Y-m-d') ?>
 * 
 * ================================================================
 */

// Load configuration and check authentication
require_once 'config.php';
checkAuth();

// Initialize database connection
$db = getDB();

// Initialize message variables
$success = '';
$error = '';
$warning = '';
$info = '';

// Preview mode variables
$previewMode = false;
$previewTranslation = null;
$previewNews = null;
$previewSummary = null;
$bleuScore = null;
$translationQuality = null;

// Execution metrics
$executionMetrics = [
    'summary_time' => 0,
    'translation_time' => 0,
    'total_time' => 0
];

/**
 * ================================================================
 * UTILITY FUNCTIONS
 * ================================================================
 */

/**
 * Log message with timestamp
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] [$level] $message");
}

/**
 * Clean and validate phone number
 */
function cleanPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Validate length
    if (strlen($phone) < 10 || strlen($phone) > 13) {
        return ['valid' => false, 'error' => 'Invalid phone length: ' . strlen($phone)];
    }
    
    return ['valid' => true, 'number' => $phone];
}

/**
 * ================================================================
 * AI SUMMARIZATION FUNCTION
 * ================================================================
 * 
 * Creates a concise 2-sentence summary from news content
 * Maximum 140 characters to leave room for translation
 */
function createEnglishSummary($content) {
    global $executionMetrics;
    
    $startTime = microtime(true);
    
    logMessage("Starting summarization for content length: " . strlen($content));
    
    // Prepare AI request for summarization
    $summaryData = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a professional news summarization expert specializing in creating concise, informative summaries.

YOUR TASK:
Create a 2-sentence summary that captures the most critical information from the news article.

STRICT REQUIREMENTS:
1. Exactly 2 sentences - no more, no less
2. Maximum 140 characters total (to allow room for translation)
3. Include the most important facts: WHAT happened, WHEN, WHERE, and WHY it matters
4. Use clear, direct, active language
5. Avoid unnecessary adjectives or filler words
6. Focus on actionable information that readers need to know
7. Maintain journalistic objectivity

EXAMPLES OF GOOD SUMMARIES:

Input: Long article about typhoon warning
Output: "Typhoon Emma will hit Eastern Visayas tomorrow morning with winds up to 150 kph. PAGASA urges residents in coastal areas to evacuate immediately."

Input: Article about health advisory
Output: "DOH reports 500 new dengue cases this week across Metro Manila. Health officials recommend 4S strategy for prevention."

Input: Article about earthquake preparedness
Output: "Nationwide earthquake drill scheduled for next Tuesday at 10 AM. Citizens will practice Drop, Cover, and Hold On procedures."

Remember: Be specific, be concise, be informative.'
            ],
            [
                'role' => 'user',
                'content' => "Create a 2-sentence summary (max 140 characters):\n\n" . $content
            ]
        ]
    ];

    // Initialize cURL for AI API
    $ch = curl_init(AI_API_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($summaryData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'customerId: cus_TEa79DTCHfkEKn',
        'Content-Type: application/json',
        'Authorization: Bearer xxx'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Record execution time
    $executionMetrics['summary_time'] = round((microtime(true) - $startTime), 2);

    // Handle cURL errors
    if ($curlError) {
        logMessage("AI Summary cURL Error: $curlError", 'ERROR');
        return false;
    }

    // Handle API errors
    if ($httpCode !== 200) {
        logMessage("AI Summary API Error - HTTP Code: $httpCode", 'ERROR');
        logMessage("Response: " . substr($response, 0, 500), 'ERROR');
        return false;
    }

    // Parse JSON response
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("JSON Decode Error: " . json_last_error_msg(), 'ERROR');
        return false;
    }

    // Extract summary from response
    if (isset($result['choices'][0]['message']['content'])) {
        $summary = trim($result['choices'][0]['message']['content']);
        
        // Validate summary length
        if (strlen($summary) > 140) {
            logMessage("Summary too long (" . strlen($summary) . " chars), trimming...", 'WARNING');
            $summary = substr($summary, 0, 137) . '...';
        }
        
        logMessage("Summary created successfully: $summary (Length: " . strlen($summary) . " chars)");
        return $summary;
    }

    logMessage("Summary extraction failed - no content in response", 'ERROR');
    return false;
}

/**
 * ================================================================
 * AI TRANSLATION FUNCTION
 * ================================================================
 * 
 * Translates English summary to Hiligaynon language
 * Maximum 160 characters (SMS limit)
 */
function translateToHiligaynon($englishText) {
    global $executionMetrics;
    
    $startTime = microtime(true);
    
    logMessage("Starting translation for text: $englishText");
    
    // Prepare AI request for translation
    $translationData = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a professional Hiligaynon (Ilonggo) translator with deep expertise in Philippine languages and culture.

YOUR MISSION:
Translate English news summaries into natural, conversational Hiligaynon that Filipino readers in Iloilo, Bacolod, Negros, and surrounding areas will understand perfectly.

CRITICAL TRANSLATION PRINCIPLES:

1. NATURALNESS:
   - Use conversational Hiligaynon as spoken in daily life
   - Avoid archaic or overly formal terms
   - Match the speaking patterns of native Hiligaynon speakers
   
2. COMPLETENESS:
   - NEVER cut off mid-sentence
   - Every sentence must be complete and make sense on its own
   - Preserve ALL key information from the English version
   
3. CLARITY:
   - Readers must understand the full message immediately
   - Use simple, common Hiligaynon words
   - Maintain the urgency and importance of the original
   
4. LENGTH CONSTRAINT:
   - Maximum 160 characters (SMS limit)
   - If original translation exceeds limit, rephrase concisely
   - When rephrasing, keep ALL critical facts
   - Prefer shorter synonyms while maintaining meaning

5. GRAMMAR RULES:
   - Verb placement: Follow natural Hiligaynon word order
   - Tense markers: "na" (completed), "ga-" (ongoing), context for future
   - Linking words: "nga", "sang", "sa", "kag" (and)
   - Particles: Use appropriately for natural flow

6. PROPER NOUNS:
   - Keep government agencies in English: PAGASA, DOH, NDRRMC
   - Keep place names as is: Visayas, Manila, Iloilo
   - Keep English technical terms if no clear Hiligaynon equivalent

EXCELLENT TRANSLATION EXAMPLES:

Example 1 - Weather Alert:
English: "PAGASA warns of heavy rainfall in Visayas starting tomorrow bringing 100-200mm of rain. Residents should prepare for flooding and landslides."
Hiligaynon: "Nagpahibalo si PAGASA sang mabaskug nga ulan sa Visayas sugod bukas, 100-200mm. Mag-andam sa baha kag landslide."
(139 characters - clear, complete, informative)

Example 2 - Health Advisory:
English: "Department of Health releases new dengue prevention guidelines this week. Follow the 4S strategy to protect your family from infection."
Hiligaynon: "Ang DOH nagpagwa sang bag-o nga guidelines kontra dengue sini nga semana. Sundon ang 4S strategy para protektahan ang pamilya."
(133 characters - actionable, complete)

Example 3 - Disaster Preparedness:
English: "Nationwide earthquake drill scheduled next Tuesday at 10 AM. Citizens will learn Drop, Cover, and Hold On safety procedures."
Hiligaynon: "Earthquake drill sa tibuok nasyon sunod Martes 10 AM. Matun-an sang mga tawo ang Drop, Cover, kag Hold On para sa kaluwasan."
(138 characters - specific time included, complete)

Example 4 - Emergency Alert:
English: "Strong typhoon expected to make landfall in Eastern Samar tonight. Mandatory evacuation ordered for all coastal communities."
Hiligaynon: "Mabaskug nga bagyo mag-abot sa Eastern Samar karon gab-i. Kinahanglan mag-evacuate tanan sa coastal areas."
(113 characters - urgent, clear command)

Example 5 - Health Warning:
English: "Rising COVID cases reported in Metro Manila hospitals. Health authorities recommend wearing masks in crowded indoor spaces."
Hiligaynon: "Nagtubo ang COVID cases sa Metro Manila. Ang health authorities nagrekomenda nga magmaskara sa mga puno nga indoor spaces."
(135 characters - current issue, clear recommendation)

RESPONSE FORMAT:
Reply with ONLY the Hiligaynon translation. No explanations, no English, no quotation marks - just the pure Hiligaynon text.'
            ],
            [
                'role' => 'user',
                'content' => "Translate this English summary to natural, conversational Hiligaynon (maximum 160 characters, keep complete sentences):\n\n" . $englishText
            ]
        ]
    ];

    // Initialize cURL for AI API
    $ch = curl_init(AI_API_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($translationData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'customerId: cus_TEa79DTCHfkEKn',
        'Content-Type: application/json',
        'Authorization: Bearer xxx'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Record execution time
    $executionMetrics['translation_time'] = round((microtime(true) - $startTime), 2);

    // Handle errors
    if ($curlError) {
        logMessage("AI Translation cURL Error: $curlError", 'ERROR');
        return false;
    }

    if ($httpCode !== 200) {
        logMessage("AI Translation API Error - HTTP Code: $httpCode", 'ERROR');
        return false;
    }

    // Parse response
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("JSON Decode Error: " . json_last_error_msg(), 'ERROR');
        return false;
    }

    if (isset($result['choices'][0]['message']['content'])) {
        $translation = trim($result['choices'][0]['message']['content']);
        
        // Remove surrounding quotes if present
        $translation = trim($translation, '"\'');
        
        logMessage("Raw translation: $translation (Length: " . strlen($translation) . ")");
        
        // Smart trimming if exceeds SMS limit
        if (strlen($translation) > 160) {
            logMessage("Translation exceeds 160 chars, applying smart trimming", 'WARNING');
            
            // Method 1: Try trimming at sentence boundaries
            $sentences = preg_split('/(?<=[.!?])\s+/', $translation);
            $trimmedText = '';
            
            foreach ($sentences as $sentence) {
                $testText = $trimmedText . $sentence . ' ';
                if (strlen(trim($testText)) <= 160) {
                    $trimmedText = $testText;
                } else {
                    break;
                }
            }
            
            $translation = trim($trimmedText);
            
            // Method 2: If no complete sentences fit, word-based trimming
            if (empty($translation) || strlen($translation) > 160) {
                logMessage("Sentence-based trimming failed, using word-based trimming", 'WARNING');
                
                $words = explode(' ', $result['choices'][0]['message']['content']);
                $finalText = '';
                
                foreach ($words as $word) {
                    if (strlen($finalText . $word . ' ') <= 157) {
                        $finalText .= $word . ' ';
                    } else {
                        break;
                    }
                }
                
                $translation = trim($finalText) . '...';
            }
        }
        
        logMessage("Final translation: $translation (Length: " . strlen($translation) . " chars)");
        return $translation;
    }

    logMessage("Translation extraction failed", 'ERROR');
    return false;
}

/**
 * ================================================================
 * MAIN TRANSLATION ORCHESTRATOR
 * ================================================================
 * 
 * Combines summarization and translation into one function
 */
function summarizeAndTranslate($content) {
    global $executionMetrics;
    
    $totalStartTime = microtime(true);
    
    logMessage("=== Starting full translation process ===");
    
    // Step 1: Create English summary
    logMessage("Step 1: Creating English summary...");
    $summary = createEnglishSummary($content);
    
    if (!$summary) {
        logMessage("Failed to create summary", 'ERROR');
        return false;
    }
    
    logMessage("Summary created: $summary");
    
    // Step 2: Translate to Hiligaynon
    logMessage("Step 2: Translating to Hiligaynon...");
    $translation = translateToHiligaynon($summary);
    
    if (!$translation) {
        logMessage("Failed to translate to Hiligaynon", 'ERROR');
        return false;
    }
    
    logMessage("Translation completed: $translation");
    
    // Record total time
    $executionMetrics['total_time'] = round((microtime(true) - $totalStartTime), 2);
    
    logMessage("=== Translation process completed in {$executionMetrics['total_time']}s ===");
    
    return $translation;
}

/**
 * ================================================================
 * BLEU SCORE CALCULATION
 * ================================================================
 * 
 * Measures translation quality by comparing with reference translation
 * Returns score from 0-100 (higher is better)
 */
function calculateBLEUScore($referenceText, $candidateText) {
    if (empty($referenceText) || empty($candidateText)) {
        return 0;
    }
    
    // Tokenize both texts
    $refTokens = preg_split('/\s+/', strtolower(trim($referenceText)));
    $candTokens = preg_split('/\s+/', strtolower(trim($candidateText)));
    
    if (empty($candTokens)) {
        return 0;
    }
    
    // Calculate precision for n-grams (1-gram through 4-gram)
    $precisions = [];
    
    for ($n = 1; $n <= 4; $n++) {
        $refNgrams = extractNgrams($refTokens, $n);
        $candNgrams = extractNgrams($candTokens, $n);
        
        if (empty($candNgrams)) {
            $precisions[] = 0.00001; // Avoid division by zero
            continue;
        }
        
        // Count matches
        $matches = 0;
        foreach ($candNgrams as $ngram) {
            if (in_array($ngram, $refNgrams)) {
                $matches++;
            }
        }
        
        $precision = $matches / count($candNgrams);
        $precisions[] = $precision > 0 ? $precision : 0.00001;
    }
    
    // Calculate geometric mean of precisions
    $geometricMean = 1;
    foreach ($precisions as $p) {
        $geometricMean *= $p;
    }
    $geometricMean = pow($geometricMean, 1/count($precisions));
    
    // Apply brevity penalty
    $refLength = count($refTokens);
    $candLength = count($candTokens);
    $brevityPenalty = ($candLength < $refLength) ? exp(1 - $refLength / $candLength) : 1;
    
    // Calculate final BLEU score (0-100 scale)
    $bleuScore = $brevityPenalty * $geometricMean * 100;
    
    return round($bleuScore, 2);
}

/**
 * Extract n-grams from token array
 */
function extractNgrams($tokens, $n) {
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

/**
 * Get quality rating based on BLEU score
 */
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
 * SMS SENDING FUNCTION
 * ================================================================
 */
function sendSMSViaGateway($phone, $message, $gateway = 'IPROG') {
    logMessage("Preparing to send SMS via $gateway to $phone");
    
    // Format phone number based on gateway requirements
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    switch ($gateway) {
        case 'IPROG':
            return sendViaIPROG($phone, $message);
        case 'ARDUINO':
            return sendViaArduinoSIM800C($phone, $message);
        case 'SEMAPHORE':
            return sendViaSemaphore($phone, $message);
        default:
            return ['success' => false, 'error' => 'Unknown gateway'];
    }
}

/**
 * Send via IPROG API
 */
function sendViaIPROG($phone, $message) {
    // IPROG uses 639XXXXXXXXX format
    if (strlen($phone) === 11 && substr($phone, 0, 2) === '09') {
        $phone = '63' . substr($phone, 1);
    } elseif (strlen($phone) === 10) {
        $phone = '63' . $phone;
    }
    
    if (strlen($phone) !== 12 || substr($phone, 0, 2) !== '63') {
        return ['success' => false, 'error' => 'Invalid IPROG phone format: ' . $phone];
    }
    
    $url = 'https://www.iprogsms.com/api/v1/sms_messages';
    
    $data = [
        'api_token' => IPROG_API_TOKEN,
        'message' => $message,
        'phone_number' => $phone
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        if (!isset($result['error'])) {
            return ['success' => true, 'id' => $result['data']['id'] ?? 'iprog_' . time(), 'gateway' => 'IPROG'];
        }
    }
    
    return ['success' => false, 'error' => 'IPROG API error'];
}

/**
 * Send via Arduino SIM800C
 */
function sendViaArduinoSIM800C($phone, $message) {
    require_once 'arduino_sms.php';
    
    $arduino = new ArduinoSMS();
    $connectResult = $arduino->connect(ARDUINO_COM_PORT);
    
    if (!$connectResult['success']) {
        return ['success' => false, 'error' => 'Arduino connection failed'];
    }
    
    return $arduino->sendSMS($phone, $message);
}

/**
 * Send via Semaphore
 */
function sendViaSemaphore($phone, $message, $priority = false) {
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '63') {
        $phone = '0' . substr($phone, 2);
    } elseif (strlen($phone) === 10) {
        $phone = '0' . $phone;
    }
    
    if (strlen($phone) !== 11 || substr($phone, 0, 2) !== '09') {
        return ['success' => false, 'error' => 'Invalid Semaphore phone format'];
    }
    
    $endpoint = $priority ? 'https://semaphore.co/api/v4/priority' : 'https://semaphore.co/api/v4/messages';
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'apikey' => SEMAPHORE_API_KEY,
        'number' => $phone,
        'message' => $message,
        'sendername' => SEMAPHORE_SENDER
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data[0]['message_id'])) {
            return ['success' => true, 'id' => $data[0]['message_id'], 'gateway' => 'Semaphore'];
        }
    }
    
    return ['success' => false, 'error' => 'Semaphore API error'];
}

/**
 * ================================================================
 * HANDLE TRANSLATION PREVIEW REQUEST
 * ================================================================
 */
if (isset($_POST['preview_translation'])) {
    $previewMode = true;
    $previewId = intval($_POST['news_id']);
    
    logMessage("=== PREVIEW MODE ACTIVATED ===");
    logMessage("Previewing translation for news ID: $previewId");
    
    // Get news article
    $stmt = $db->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->bind_param("i", $previewId);
    $stmt->execute();
    $previewNews = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($previewNews) {
        logMessage("News article found: " . $previewNews['title']);
        
        // Create summary
        $previewSummary = createEnglishSummary($previewNews['content']);
        
        if ($previewSummary) {
            logMessage("Summary for preview: $previewSummary");
            
            // Translate to Hiligaynon
            $previewTranslation = translateToHiligaynon($previewSummary);
            
            if ($previewTranslation) {
                logMessage("Preview translation successful");
                
                // Calculate BLEU score for quality assessment
                // Generate a second translation as reference
                $referenceTranslation = translateToHiligaynon($previewSummary);
                
                if ($referenceTranslation && $referenceTranslation !== $previewTranslation) {
                    $bleuScore = calculateBLEUScore($referenceTranslation, $previewTranslation);
                    $translationQuality = getQualityRating($bleuScore);
                    logMessage("BLEU Score calculated: $bleuScore ({$translationQuality['label']})");
                } else {
                    logMessage("BLEU score calculation skipped (identical translations)");
                }
                
                $info = "✅ Preview generated successfully in {$executionMetrics['total_time']}s";
            } else {
                $error = '❌ Failed to translate to Hiligaynon. Please try again.';
                logMessage("Preview translation failed", 'ERROR');
            }
        } else {
            $error = '❌ Failed to create summary. Please try again.';
            logMessage("Preview summary creation failed", 'ERROR');
        }
    } else {
        $error = '❌ News article not found';
        logMessage("News ID $previewId not found", 'ERROR');
    }
}

/**
 * ================================================================
 * HANDLE SMS BROADCAST REQUEST
 * ================================================================
 */
if (isset($_POST['send_sms'])) {
    $newsId = intval($_POST['news_id']);
    $selectedGateway = $_POST['gateway'] ?? SMS_GATEWAY;
    
    logMessage("=== SMS BROADCAST INITIATED ===");
    logMessage("News ID: $newsId, Gateway: $selectedGateway");
    
    // Validate gateway
    $validGateways = ['IPROG', 'ARDUINO', 'SEMAPHORE'];
    if (!in_array($selectedGateway, $validGateways)) {
        $error = '❌ Invalid gateway selected: ' . $selectedGateway;
        logMessage("Invalid gateway: $selectedGateway", 'ERROR');
    } else {
        // Get news article
        $stmt = $db->prepare("SELECT * FROM news WHERE id = ?");
        $stmt->bind_param("i", $newsId);
        $stmt->execute();
        $news = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$news) {
            $error = '❌ News article not found';
            logMessage("News ID $newsId not found in database", 'ERROR');
        } else {
            logMessage("News article retrieved: " . $news['title']);
            
            // Translate content
            $broadcastStartTime = microtime(true);
            $translated = summarizeAndTranslate($news['content']);
            
            if (!$translated) {
                $error = '❌ Translation failed. Please check logs or try again.';
                logMessage("Translation failed for broadcast", 'ERROR');
            } else {
                // Validate translation length
                if (strlen($translated) > 160) {
                    $warning = "⚠️ Translation was automatically trimmed to 160 characters";
                    logMessage("Translation trimmed from original length", 'WARNING');
                }
                
                // Get active subscribers
                $subsResult = $db->query("SELECT * FROM subscribers WHERE active = 1");
                $totalSubscribers = $subsResult->num_rows;
                
                if ($totalSubscribers === 0) {
                    $error = '❌ No active subscribers. Please add subscribers first.';
                    logMessage("No active subscribers found", 'WARNING');
                } else {
                    logMessage("Found $totalSubscribers active subscribers");
                    
                    // Initialize counters
                    $sentCount = 0;
                    $failedCount = 0;
                    $errorDetails = [];
                    
                    // Process each subscriber
                    while ($subscriber = $subsResult->fetch_assoc()) {
                        $subStartTime = microtime(true);
                        
                        logMessage("Sending to: {$subscriber['name']} ({$subscriber['phone_number']})");
                        
                        // Send SMS
                        $result = sendSMSViaGateway($subscriber['phone_number'], $translated, $selectedGateway);
                        
                        $subDuration = round((microtime(true) - $subStartTime), 2);
                        
                        // Determine status
                        $deliveryStatus = $result['success'] ? 'sent' : 'failed';
                        
                        // Log to database
                        $logStmt = $db->prepare("INSERT INTO sms_logs (subscriber_id, news_id, message, status) VALUES (?, ?, ?, ?)");
                        $logStmt->bind_param("iiss", $subscriber['id'], $newsId, $translated, $deliveryStatus);
                        $logStmt->execute();
                        $logStmt->close();
                        
                        // Update counters
                        if ($result['success']) {
                            $sentCount++;
                            logMessage("✓ SMS sent successfully in {$subDuration}s");
                        } else {
                            $failedCount++;
                            $errorMsg = "{$subscriber['name']} ({$subscriber['phone_number']}): {$result['error']}";
                            $errorDetails[] = $errorMsg;
                            logMessage("✗ SMS failed: {$result['error']}", 'ERROR');
                        }
                        
                        // Rate limiting: 2 second delay between each SMS
                        // This prevents API rate limits and allows GSM module time to process
                        sleep(2);
                    }
                    
                    // Calculate total broadcast time
                    $totalBroadcastTime = round((microtime(true) - $broadcastStartTime), 2);
                    
                    logMessage("=== BROADCAST COMPLETED ===");
                    logMessage("Sent: $sentCount, Failed: $failedCount, Time: {$totalBroadcastTime}s");
                    
                    // Build success message
                    $gatewayDisplayName = $selectedGateway === 'ARDUINO' ? 'SIM800C GSM Module' : $selectedGateway;
                    
                    $success = "✅ <strong>SMS Broadcast Completed!</strong><br>";
                    $success .= "━━━━━━━━━━━━━━━━━━━━━━━<br>";
                    $success .= "• <strong>Gateway Used:</strong> $gatewayDisplayName<br>";
                    $success .= "• <strong>Successfully Sent:</strong> $sentCount subscriber(s)<br>";
                    
                    if ($failedCount > 0) {
                        $success .= "• <strong>Failed:</strong> $failedCount subscriber(s)<br>";
                        
                        $error = "<strong>📋 Failed Delivery Details:</strong><br>";
                        $error .= "━━━━━━━━━━━━━━━━━━━━━━━<br>";
                        $error .= implode('<br>', array_slice($errorDetails, 0, 5));
                        
                        if (count($errorDetails) > 5) {
                            $error .= "<br>... and " . (count($errorDetails) - 5) . " more failures";
                        }
                    }
                    
                    $success .= "• <strong>Total Time:</strong> {$totalBroadcastTime} seconds<br>";
                    $success .= "• <strong>Message:</strong> " . htmlspecialchars(substr($translated, 0, 50)) . "...";
                }
            }
        }
    }
}

/**
 * ================================================================
 * FETCH DATA FOR UI DISPLAY
 * ================================================================
 */

// Fetch all news articles for dropdown
$newsListQuery = $db->query("SELECT id, category, title, created_at FROM news ORDER BY created_at DESC");

// Fetch recent SMS logs with full details
$recentLogsQuery = $db->query("
    SELECT 
        sl.id as log_id,
        sl.sent_at,
        sl.message,
        sl.status,
        s.name as subscriber_name,
        s.phone_number,
        n.title as news_title,
        n.category as news_category,
        n.created_at as news_date
    FROM sms_logs sl 
    JOIN subscribers s ON sl.subscriber_id = s.id 
    JOIN news n ON sl.news_id = n.id 
    ORDER BY sl.sent_at DESC 
    LIMIT 25
");

// Get comprehensive statistics
$statisticsQuery = $db->query("
    SELECT 
        COUNT(*) as total_messages,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful_messages,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_messages,
        COUNT(DISTINCT subscriber_id) as unique_recipients,
        COUNT(DISTINCT news_id) as unique_news_broadcast,
        MIN(sent_at) as first_sms_date,
        MAX(sent_at) as last_sms_date
    FROM sms_logs
");
$fullStats = $statisticsQuery->fetch_assoc();

// Get active subscriber count
$activeSubsCount = $db->query("SELECT COUNT(*) as count FROM subscribers WHERE active = 1")->fetch_assoc()['count'];

// Get today's SMS count
$todaySMSCount = $db->query("SELECT COUNT(*) as count FROM sms_logs WHERE DATE(sent_at) = CURDATE()")->fetch_assoc()['count'];

// Get this week's SMS count
$weekSMSCount = $db->query("SELECT COUNT(*) as count FROM sms_logs WHERE YEARWEEK(sent_at) = YEARWEEK(NOW())")->fetch_assoc()['count'];

// Calculate success rate
$successRate = $fullStats['total_messages'] > 0 
    ? round(($fullStats['successful_messages'] / $fullStats['total_messages']) * 100, 1)
    : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send SMS - Hybrid SMS Broadcasting System</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ================================================
           CUSTOM STYLES FOR SMS SEND PAGE
           ================================================ */
        
        /* Statistics Grid Layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        /* Individual Stat Card */
        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            color: var(--gray-text);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        /* Stat Card Color Variants */
        .stat-card.primary .stat-number { color: var(--primary-color); }
        .stat-card.success .stat-number { color: var(--success-color); }
        .stat-card.danger .stat-number { color: var(--danger-color); }
        .stat-card.info .stat-number { color: var(--info-color); }
        .stat-card.warning .stat-number { color: var(--warning-color); }
        
        /* Preview Container */
        .preview-container {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            padding: 30px;
            border-radius: 12px;
            border-left: 6px solid var(--primary-color);
            margin: 25px 0;
            box-shadow: var(--shadow-lg);
        }
        
        /* Original News Display */
        .preview-original {
            background: var(--white);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--gray-text);
            box-shadow: var(--shadow);
        }
        
        .preview-original h3 {
            color: var(--dark-text);
            margin-bottom: 15px;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .preview-original .category-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.813rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        
        /* Translation Display Box */
        .preview-translation {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 3px solid var(--primary-color);
            box-shadow: var(--shadow-md);
        }
        
        .translation-display-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            padding: 22px;
            border-radius: 10px;
            border-left: 5px solid var(--success-color);
            margin: 18px 0;
        }
        
        .translation-text {
            font-size: 19px;
            line-height: 1.9;
            color: var(--dark-text);
            font-weight: 600;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Character Counter Badge */
        .char-counter {
            display: inline-block;
            padding: 8px 16px;
            background: var(--success-color);
            color: white;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 700;
            margin-top: 15px;
            box-shadow: var(--shadow);
        }
        
        .char-counter.warning {
            background: var(--warning-color);
            animation: pulse 2s infinite;
        }
        
        .char-counter.danger {
            background: var(--danger-color);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* BLEU Score Meter */
        .bleu-score-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed var(--border-color);
        }
        
        .bleu-meter-container {
            position: relative;
            height: 35px;
            background: #e5e7eb;
            border-radius: 20px;
            overflow: hidden;
            margin: 12px 0;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .bleu-meter-fill {
            height: 100%;
            transition: width 0.8s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 15px;
            position: relative;
        }
        
        .bleu-meter-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .bleu-excellent { background: linear-gradient(90deg, #10b981, #059669); }
        .bleu-good { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .bleu-fair { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .bleu-poor { background: linear-gradient(90deg, #ef4444, #dc2626); }
        
        /* Gateway Info Box */
        .gateway-info {
            margin-top: 12px;
            padding: 15px;
            background: linear-gradient(135deg, #f9fafb, #f3f4f6);
            border-radius: 8px;
            font-size: 14px;
            color: var(--gray-text);
            border-left: 4px solid var(--primary-color);
            line-height: 1.7;
        }
        
        .gateway-info strong {
            color: var(--dark-text);
        }
        
        /* Action Buttons Grid */
        .action-buttons-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 12px;
            margin-top: 20px;
        }
        
        /* Gateway Badge */
        .gateway-badge-header {
            display: inline-block;
            padding: 8px 18px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
            box-shadow: var(--shadow);
        }
        
        /* System Info Grid */
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 20px;
        }
        
        .system-info-card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        .system-info-card h4 {
            color: var(--dark-text);
            margin-bottom: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .system-info-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .system-info-card li {
            padding: 8px 0;
            color: var(--gray-text);
            font-size: 14px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .system-info-card li:last-child {
            border-bottom: none;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons-grid {
                grid-template-columns: 1fr;
            }
            
            .system-info-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-number {
                font-size: 2.2rem;
            }
            
            .translation-text {
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            header h1 {
                font-size: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- ================================================
         HEADER SECTION
         ================================================ -->
    <header>
        <div class="container">
            <h1>📱 Send SMS Notifications</h1>
            <p>Advanced Hybrid SMS Broadcasting System - Online APIs + Offline SIM800C GSM Module 🇵🇭</p>
        </div>
    </header>
    
    <!-- ================================================
         NAVIGATION MENU
         ================================================ -->
    <nav>
        <div class="container">
            <ul>
                <li><a href="admin.php">Manage News</a></li>
                <li><a href="subscribers.php">Manage Subscribers</a></li>
                <li><a href="send_sms.php" class="active">Send SMS</a></li>
                <li><a href="import_news.php">Import News</a></li>
                <li><a href="announcement.php">Message</a></li>
                <li><a href="test_sim800c.php">Test SIM800C</a></li>
                <li><a href="index.php">View Site</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <!-- ================================================
         MAIN CONTENT AREA
         ================================================ -->
    <main>
        <div class="container">
            <!-- Alert Messages Section -->
            <?php if($success): ?>
                <div class="alert alert-success">
                    <?= $success ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if($warning): ?>
                <div class="alert alert-warning">
                    <?= $warning ?>
                </div>
            <?php endif; ?>
            
            <?php if($info): ?>
                <div class="alert alert-info">
                    <?= $info ?>
                </div>
            <?php endif; ?>
            
            <!-- ================================================
                 STATISTICS DASHBOARD
                 ================================================ -->
            <div class="stats-grid">
                <!-- Active Subscribers -->
                <div class="stat-card info">
                    <div class="stat-number"><?= $activeSubsCount ?></div>
                    <div class="stat-label">Active Subscribers</div>
                </div>
                
                <!-- Total Messages -->
                <div class="stat-card primary">
                    <div class="stat-number"><?= $fullStats['total_messages'] ?></div>
                    <div class="stat-label">Total SMS Sent</div>
                </div>
                
                <!-- Successful Deliveries -->
                <div class="stat-card success">
                    <div class="stat-number"><?= $fullStats['successful_messages'] ?></div>
                    <div class="stat-label">Successful</div>
                </div>
                
                <!-- Failed Deliveries -->
                <div class="stat-card danger">
                    <div class="stat-number"><?= $fullStats['failed_messages'] ?></div>
                    <div class="stat-label">Failed</div>
                </div>
                
                <!-- Today's Count -->
                <div class="stat-card warning">
                    <div class="stat-number"><?= $todaySMSCount ?></div>
                    <div class="stat-label">Sent Today</div>
                </div>
                
                <!-- Success Rate -->
                <div class="stat-card info">
                    <div class="stat-number"><?= $successRate ?>%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>
            
            <!-- ================================================
                 TRANSLATION PREVIEW SECTION
                 ================================================ -->
            <?php if ($previewMode && $previewTranslation && $previewNews): ?>
            <div class="card">
                <div class="card-header">
                    <h2>👁️ Translation Preview & Quality Analysis</h2>
                </div>
                
                <div class="preview-container">
                    <!-- Original News Content -->
                    <div class="preview-original">
                        <span class="category-badge" style="
                            background: <?= $previewNews['category'] === 'weather' ? 'var(--weather-light)' : ($previewNews['category'] === 'health' ? 'var(--health-light)' : 'var(--disaster-light)') ?>;
                            color: <?= $previewNews['category'] === 'weather' ? 'var(--weather-dark)' : ($previewNews['category'] === 'health' ? 'var(--health-dark)' : 'var(--disaster-dark)') ?>;
                            border: 2px solid <?= $previewNews['category'] === 'weather' ? 'var(--weather-color)' : ($previewNews['category'] === 'health' ? 'var(--health-color)' : 'var(--disaster-color)') ?>;
                        ">
                            <?php 
                            $categoryIcons = [
                                'weather' => '⛅',
                                'health' => '🏥',
                                'disaster' => '🚨'
                            ];
                            echo $categoryIcons[$previewNews['category']] . ' ' . strtoupper($previewNews['category']);
                            ?>
                        </span>
                        
                        <h3><?= htmlspecialchars($previewNews['title']) ?></h3>
                        
                        <?php if ($previewSummary): ?>
                        <div style="background: #fef3c7; padding: 12px; border-radius: 6px; margin: 12px 0; border-left: 3px solid #f59e0b;">
                            <strong style="color: #92400e;">📝 English Summary:</strong>
                            <p style="color: #78350f; margin: 8px 0 0 0; line-height: 1.6;">
                                <?= htmlspecialchars($previewSummary) ?>
                            </p>
                            <small style="color: #a16207;">Length: <?= strlen($previewSummary) ?> characters</small>
                        </div>
                        <?php endif; ?>
                        
                        <p style="color: var(--gray-text); line-height: 1.7; margin-top: 12px;">
                            <strong>Full Original Content:</strong><br>
                            <?= htmlspecialchars(substr($previewNews['content'], 0, 350)) ?>...
                        </p>
                    </div>
                    
                    <!-- Hiligaynon Translation -->
                    <div class="preview-translation">
                        <h4 style="color: var(--primary-color); margin-bottom: 20px; font-size: 1.2rem; font-weight: 700;">
                            🇵🇭 Hiligaynon Translation (For SMS Broadcast):
                        </h4>
                        
                        <div class="translation-display-box">
                            <p class="translation-text">
                                "<?= htmlspecialchars($previewTranslation) ?>"
                            </p>
                            
                            <div style="margin-top: 18px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <span class="char-counter <?= strlen($previewTranslation) > 150 ? 'warning' : '' ?> <?= strlen($previewTranslation) > 160 ? 'danger' : '' ?>">
                                    <?= strlen($previewTranslation) ?> / 160 characters
                                </span>
                                
                                <?php if (strlen($previewTranslation) <= 140): ?>
                                    <span style="color: var(--success-color); font-weight: 700; font-size: 14px;">
                                        ✅ Optimal SMS length
                                    </span>
                                <?php elseif (strlen($previewTranslation) <= 160): ?>
                                    <span style="color: var(--warning-color); font-weight: 700; font-size: 14px;">
                                        ⚠️ Near SMS limit
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--danger-color); font-weight: 700; font-size: 14px;">
                                        ❌ Exceeds SMS limit!
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- BLEU Score Display (if calculated) -->
                        <?php if ($bleuScore !== null && $translationQuality): ?>
                        <div class="bleu-score-section">
                            <h5 style="margin-bottom: 12px; color: var(--dark-text); font-size: 1rem; font-weight: 600;">
                                📊 Translation Quality Score (BLEU Metric):
                            </h5>
                            
                            <div class="bleu-meter-container">
                                <div class="bleu-meter-fill <?= $translationQuality['class'] ?>" 
                                     style="width: <?= $bleuScore ?>%;">
                                    <?= $bleuScore ?>% - <?= $translationQuality['label'] ?>
                                </div>
                            </div>
                            
                            <p style="font-size: 13px; color: var(--gray-text); margin-top: 10px; line-height: 1.6;">
                                <strong>Quality Assessment:</strong> <?= $translationQuality['description'] ?><br>
                                <em>BLEU score measures similarity to reference translation (higher = better quality)</em>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Preview Action Buttons -->
                <form method="POST" style="margin-top: 25px;">
                    <input type="hidden" name="news_id" value="<?= $previewNews['id'] ?>">
                    <input type="hidden" name="gateway" value="<?= $_POST['gateway'] ?? SMS_GATEWAY ?>">
                    
                    <div class="action-buttons-grid">
                        <button type="submit" name="preview_translation" class="btn btn-warning">
                            🔄 Re-translate
                        </button>
                        <button type="submit" name="send_sms" class="btn btn-success" 
                                onclick="return confirm('📱 Confirm SMS Broadcast\n\nTranslation: <?= addslashes($previewTranslation) ?>\n\nSend to <?= $activeSubsCount ?> subscribers?\nEstimated time: ~<?= $activeSubsCount * 2 ?> seconds');">
                            ✅ Translation Approved - Send SMS to All (<?= $activeSubsCount ?>)
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- ================================================
                 MAIN SMS BROADCAST FORM
                 ================================================ -->
            <div class="card">
                <div class="card-header">
                    <h2>📤 Broadcast News via SMS</h2>
                    <span class="gateway-badge-header">
                        Active Gateway: <?= SMS_GATEWAY ?>
                    </span>
                </div>
                
                <div class="alert alert-info">
                    <strong>📋 SMS Broadcasting Process:</strong>
                    <ol style="margin: 0.8rem 0 0 1.8rem; line-height: 2;">
                        <li><strong>Select Gateway:</strong> Choose between Online API (IPROG/Semaphore) or Offline GSM (SIM800C)</li>
                        <li><strong>Choose News:</strong> Pick the news article you want to broadcast</li>
                        <li><strong>Preview Translation:</strong> Click "Preview" to see the Hiligaynon version and check quality</li>
                        <li><strong>Review Quality:</strong> Check character count and BLEU score</li>
                        <li><strong>Broadcast:</strong> Click "Send SMS" to deliver to all active subscribers</li>
                    </ol>
                    
                    <?php if ($activeSubsCount > 0): ?>
                    <div style="margin-top: 1.2rem; padding: 14px; background: rgba(234,88,12,0.1); border-radius: 8px; border-left: 4px solid var(--warning-color);">
                        <strong>⏱️ Estimated Broadcast Time:</strong> Approximately <?= $activeSubsCount * 2 ?> seconds 
                        <br><small style="color: var(--gray-text);">
                        (<?= $activeSubsCount ?> active subscribers × 2 seconds delay per SMS for rate limiting)
                        </small>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning" style="margin-top: 1.2rem;">
                        <strong>⚠️ No Active Subscribers!</strong><br>
                        You need to add subscribers before you can send SMS broadcasts.<br>
                        <a href="subscribers.php" style="font-weight: 700; color: var(--primary-color); text-decoration: underline;">
                            → Go to Manage Subscribers
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="" id="smsForm">
                    <!-- Gateway Selection -->
                    <div class="form-group">
                        <label for="gateway">
                            <strong>📡 Select SMS Gateway</strong>
                        </label>
                        <select id="gateway" name="gateway" class="form-control">
                            <option value="IPROG" <?= SMS_GATEWAY === 'IPROG' ? 'selected' : '' ?>>
                                🌐 IPROG (Online API) - Internet Required, Fast Delivery
                            </option>
                            <option value="ARDUINO" <?= SMS_GATEWAY === 'ARDUINO' ? 'selected' : '' ?>>
                                📡 SIM800C GSM Module (Offline) - No Internet Needed, Hardware Required
                            </option>
                            <option value="SEMAPHORE" <?= SMS_GATEWAY === 'SEMAPHORE' ? 'selected' : '' ?>>
                                🇵🇭 Semaphore (Online API) - Philippines Optimized
                            </option>
                        </select>
                        
                        <div id="gatewayInfoBox" class="gateway-info"></div>
                    </div>
                    
                    <!-- News Article Selection -->
                    <div class="form-group">
                        <label for="news_id">
                            <strong>📰 Select News Article to Broadcast</strong>
                        </label>
                        <select id="news_id" name="news_id" class="form-control" required>
                            <option value="">-- Select a News Article --</option>
                            <?php 
                            $newsListQuery->data_seek(0); // Reset query pointer
                            while($article = $newsListQuery->fetch_assoc()): 
                            ?>
                                <option value="<?= $article['id'] ?>" 
                                        <?= ($previewNews && $previewNews['id'] == $article['id']) ? 'selected' : '' ?>>
                                    [<?= strtoupper($article['category']) ?>] 
                                    <?= htmlspecialchars($article['title']) ?> 
                                    (<?= date('M j, Y - g:i A', strtotime($article['created_at'])) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        
                        <small style="display: block; margin-top: 8px; color: var(--gray-text);">
                            💡 Tip: Preview the translation first to ensure quality before broadcasting
                        </small>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons-grid">
                        <button type="submit" name="preview_translation" class="btn btn-primary" id="previewBtn">
                            👁️ Preview Translation
                        </button>
                        <button type="submit" name="send_sms" class="btn btn-success" id="sendBtn"
                                onclick="return confirm('📱 Confirm SMS Broadcast\n\nSend to <?= $activeSubsCount ?> active subscribers?\n\nEstimated time: ~<?= $activeSubsCount * 2 ?> seconds\n\nProceed?');"
                                <?= $activeSubsCount === 0 ? 'disabled title="No active subscribers"' : '' ?>>
                            📱 Send SMS to All Subscribers (<?= $activeSubsCount ?>)
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- ================================================
                 SMS ACTIVITY LOG TABLE
                 ================================================ -->
            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 style="margin: 0;">📋 Recent SMS Activity Log</h2>
                    <div>
                        <span class="badge badge-success" style="font-size: 0.875rem;">
                            <?= $fullStats['successful_messages'] ?> Delivered
                        </span>
                        <span class="badge badge-danger" style="margin-left: 8px; font-size: 0.875rem;">
                            <?= $fullStats['failed_messages'] ?> Failed
                        </span>
                        <span class="badge badge-info" style="margin-left: 8px; font-size: 0.875rem;">
                            <?= $weekSMSCount ?> This Week
                        </span>
                    </div>
                </div>
                
                <?php if($recentLogsQuery->num_rows > 0): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="min-width: 150px;">Date/Time</th>
                                    <th>Subscriber</th>
                                    <th style="min-width: 130px;">Phone Number</th>
                                    <th>Category</th>
                                    <th style="min-width: 200px;">News Title</th>
                                    <th>Status</th>
                                    <th style="min-width: 300px;">Message (Hiligaynon)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($log = $recentLogsQuery->fetch_assoc()): ?>
                                    <tr>
                                        <td style="white-space: nowrap; font-size: 0.875rem;">
                                            <?= formatDate($log['sent_at']) ?>
                                        </td>
                                        <td style="font-weight: 600;">
                                            <?= htmlspecialchars($log['subscriber_name']) ?>
                                        </td>
                                        <td style="font-family: 'Courier New', monospace; font-size: 0.875rem;">
                                            <?= htmlspecialchars($log['phone_number']) ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $log['news_category'] === 'weather' ? 'success' : ($log['news_category'] === 'health' ? 'warning' : 'danger') ?>">
                                                <?php 
                                                $icons = ['weather' => '⛅', 'health' => '🏥', 'disaster' => '🚨'];
                                                echo $icons[$log['news_category']] . ' ' . ucfirst($log['news_category']);
                                                ?>
                                            </span>
                                        </td>
                                        <td style="max-width: 250px; line-height: 1.5;">
                                            <?= htmlspecialchars($log['news_title']) ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $log['status'] === 'sent' ? 'success' : 'danger' ?>">
                                                <?= $log['status'] === 'sent' ? '✅ Sent' : '❌ Failed' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="max-width: 380px; line-height: 1.7; font-size: 14px; color: var(--dark-text);">
                                                <?= htmlspecialchars($log['message']) ?>
                                            </div>
                                            <small style="color: var(--gray-text); display: block; margin-top: 5px;">
                                                Length: <?= strlen($log['message']) ?> chars
                                            </small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon" style="font-size: 5rem; opacity: 0.3;">📭</div>
                        <h3 style="color: var(--gray-text); margin-top: 1rem;">No SMS Activity Yet</h3>
                        <p style="font-size: 1.1rem; color: var(--gray-text); margin: 1rem 0;">
                            Start broadcasting news summaries to your subscribers!
                        </p>
                        <?php if ($activeSubsCount === 0): ?>
                        <a href="subscribers.php" class="btn btn-primary" style="margin-top: 1.5rem;">
                            👥 Add Subscribers First
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ================================================
                 SYSTEM INFORMATION PANEL
                 ================================================ -->
            <div class="card" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid var(--border-color);">
                <div class="card-header">
                    <h2>ℹ️ System Information & Configuration</h2>
                </div>
                
                <div class="system-info-grid">
                    <!-- Current Configuration -->
                    <div class="system-info-card">
                        <h4>⚙️ Current Configuration</h4>
                        <ul>
                            