<?php
$adminFile = __DIR__ . '/admin/order_details.php';
$managerFile = __DIR__ . '/manager/order_details.php';

$adminContent = file_get_contents($adminFile);
$startStr = '<div class="card" style="margin-top: 24px;">';
$startStr2 = '<div class="card-body tracking-container">';
$endStr = '<!-- Order Items -->';

$startPos = strpos($adminContent, 'tracking-container');
$startPos = strrpos(substr($adminContent, 0, $startPos), $startStr);
$endPos = strpos($adminContent, $endStr, $startPos);

if ($startPos !== false && $endPos !== false) {
    $trackingHtml = substr($adminContent, $startPos, $endPos - $startPos);
    
    $managerContent = file_get_contents($managerFile);
    if (strpos($managerContent, 'tracking-container') === false) {
        $managerContent = str_replace($endStr, $trackingHtml . "\n" . $endStr, $managerContent);
        file_put_contents($managerFile, $managerContent);
        echo "Successfully added tracking UI to manager dashboard.";
    } else {
        echo "Tracking UI already exists in manager dashboard.";
    }
} else {
    echo "Could not extract tracking UI from admin dashboard.";
}
