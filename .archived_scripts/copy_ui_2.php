<?php
$adminFile = 'D:/Documents/Works/sheeralateen/admin/order_details.php';
$managerFile = 'D:/Documents/Works/sheeralateen/manager/order_details.php';

$adminContent = file_get_contents($adminFile);
$managerContent = file_get_contents($managerFile);

preg_match('/<!-- Leajlak Tracking Progress UI -->.*?<\/style>\s*<div class="card"[^>]*>.*?<\/div>\s*<\/div>/s', $adminContent, $matches);

if (!empty($matches[0])) {
    $trackingHtml = $matches[0];
    
    // Check if it already exists in manager
    if (strpos($managerContent, '<!-- Leajlak Tracking Progress UI -->') === false) {
        // Insert before <!-- Order Items -->
        $managerContent = str_replace('<!-- Order Items -->', $trackingHtml . "\n\n<!-- Order Items -->", $managerContent);
        file_put_contents($managerFile, $managerContent);
        echo "Successfully copied tracking UI to manager dashboard!";
    } else {
        echo "Tracking UI already exists in manager dashboard.";
    }
} else {
    echo "Could not find tracking HTML in admin dashboard using regex.";
}
