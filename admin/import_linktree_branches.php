<?php
/**
 * admin/import_linktree_branches.php
 * ---------------------------------------------------------
 * One-time script to extract branches from Linktree HTML,
 * normalize the names, and import/update them into the DB.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getConnection();

    // 1. Upgrade schema if necessary
    try {
        $db->exec("ALTER TABLE branches ADD COLUMN maps_url TEXT NULL");
    } catch (PDOException $e) { /* Ignore if exists */ }
    try {
        $db->exec("ALTER TABLE branches ADD COLUMN routing_keywords JSON NULL");
    } catch (PDOException $e) { /* Ignore if exists */ }

    // 2. Load HTML
    $htmlPath = __DIR__ . '/../SHEERALATEEN Official_ Linktree.html';
    if (!file_exists($htmlPath)) {
        throw new Exception("Linktree HTML file not found at expected path: " . $htmlPath);
    }
    
    $html = file_get_contents($htmlPath);
    
    // 3. Extract Links
    // Linktree wraps links in <a> tags.
    preg_match_all('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);
    
    $stats = [
        'total_found' => 0,
        'imported' => 0,
        'updated' => 0,
        'duplicates_skipped' => 0,
        'errors' => []
    ];

    // Normalization helper
    function normalize_phrase($text) {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');
        // Replace punctuation and common Arabic diacritics/symbols with spaces
        $text = preg_replace('/[^\p{L}\p{N}]/u', ' ', $text);
        // Split into tokens to remove extra spaces
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return implode(' ', $tokens);
    }

    $existingUrls = [];

    foreach ($matches as $match) {
        $href = $match[1];
        $rawText = strip_tags($match[2]);
        $rawText = trim(preg_replace('/\s+/', ' ', $rawText));

        // Only process Google Maps links
        if (strpos($href, 'maps.app.goo.gl') === false && strpos($href, 'google.com/maps') === false) {
            continue;
        }

        if (empty($rawText) || strpos(strtolower($rawText), 'get directions') !== false) {
            continue; // Skip generic buttons
        }
        
        $stats['total_found']++;

        try {
            // Deduplicate URLs in the same run
            if (in_array($href, $existingUrls)) {
                $stats['duplicates_skipped']++;
                continue;
            }
            $existingUrls[] = $href;

            // Generate keywords
            // Split by commas, hyphens, slashes, or pipes to separate English/Arabic or areas
            $aliases = preg_split('/[\-,\|\/]/u', $rawText);
            
            $keywords = [];
            foreach ($aliases as $alias) {
                $clean = normalize_phrase($alias);
                // Ignore very short keywords (like "st" or "حي" on its own if it gets separated)
                if (mb_strlen($clean) > 2) {
                    $keywords[] = $clean;
                }
            }
            
            // Also add the full raw text normalized, just in case
            $fullClean = normalize_phrase($rawText);
            if (mb_strlen($fullClean) > 2) {
                $keywords[] = $fullClean;
            }

            $keywords = array_values(array_unique(array_filter($keywords)));
            $keywordsJson = json_encode($keywords, JSON_UNESCAPED_UNICODE);

            $branchName = mb_substr($rawText, 0, 150);

            // Check if branch exists by maps_url
            $stmt = $db->prepare("SELECT id FROM branches WHERE maps_url = :url LIMIT 1");
            $stmt->execute([':url' => $href]);
            $existing = $stmt->fetch();

            if ($existing) {
                $upd = $db->prepare("UPDATE branches SET branch_name = :name, routing_keywords = :kw WHERE id = :id");
                $upd->execute([
                    ':name' => $branchName,
                    ':kw' => $keywordsJson,
                    ':id' => $existing['id']
                ]);
                $stats['updated']++;
            } else {
                // Generate a branch code
                $code = 'BR-' . strtoupper(substr(md5($href), 0, 6));
                $ins = $db->prepare("INSERT INTO branches (branch_name, branch_code, maps_url, routing_keywords) VALUES (:name, :code, :url, :kw)");
                $ins->execute([
                    ':name' => $branchName,
                    ':code' => $code,
                    ':url' => $href,
                    ':kw' => $keywordsJson
                ]);
                $stats['imported']++;
            }

        } catch (Exception $e) {
            $stats['errors'][] = "Error on '$rawText': " . $e->getMessage();
        }
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Linktree branch import complete.',
        'summary' => $stats
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
