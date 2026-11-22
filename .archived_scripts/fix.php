<?php
$file = __DIR__ . '/admin/order_details.php';
$content = file_get_contents($file);
$content = str_replace('$statusMap[$order[''leajlak_status'']] ?? 1', '$statusMap[strtolower($order[''leajlak_status''])] ?? 1', $content);
file_put_contents($file, $content);
