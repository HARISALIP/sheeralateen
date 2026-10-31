<?php
require_once __DIR__ . '/core/bootstrap.php';

header('Content-Type: text/plain');

$queries = [
    'الحرازات, حي الاجواد, 23466, جدة, Saudi Arabia',
    'الحرازات, حي الاجواد, جدة, Saudi Arabia',
    'الحرازات, جدة, Saudi Arabia',
    'حي الاجواد, جدة, Saudi Arabia',
    'Al Harazat, Jeddah, Saudi Arabia',
    '23466, جدة, Saudi Arabia',
    'جدة, Saudi Arabia'
];

foreach ($queries as $q) {
    $res = GeoService::geocodeText($q, 'sa');
    echo "Query: '$q'\n";
    if ($res) {
        echo "  -> Lat: {$res['lat']}, Lng: {$res['lng']}\n";
    } else {
        echo "  -> FAILED (null)\n";
    }
}

