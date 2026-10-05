<?php
require_once __DIR__ . '/core/bootstrap.php';
$db = Database::getConnection();

$csv = <<<CSV
1,Jeddah,Al Ajaweed,0543448940,Sheera Ajaweed,sheera_ajaweed@sheeralateen.com,https://maps.app.goo.gl/S4VJS3XEDnU2jAQp9,21.4089274,39.2741561,covered
3,Jeddah,Al Samir 2,0543449165,Sheera Al Samir 2,sheera_alsamir2@sheeralateen.com,https://maps.app.goo.gl/PASk54kAdgdB5EHY9,21.5913034,39.1983544,covered
4,Jeddah,Al Samir-3,0557635369,Sheera Al Samir 3,sheera_alsamir3@sheeralateen.com,https://maps.app.goo.gl/afkVH2jT9YU5Wixk9,21.5145988,39.7848358,covered
5,Jeddah,Al-Naseem,0555016370,Sheera Al Naseem,sheera_alnaseem@sheeralateen.com,https://maps.app.goo.gl/fqWnRskTJ3n47bEB7,21.5169399,39.2238201,covered
9,Jeddah,Hamdaniya,0543450643,Sheera Hamdaniya,sheera_hamdaniya@sheeralateen.com,https://maps.app.goo.gl/AiJSEzvJArMBaoN18,21.746645259306,39.1808608829024,covered
12,Jeddah,Khuwaisa-2,0558839972,Sheera Kuwaisa 2,sheera_kuwaisa2@sheeralateen.com,https://maps.app.goo.gl/r9gUtFaSpP8bL5Wc8,21.4982087,39.2540836,covered
13,Jeddah,Lulu - Ameer Fawas ,0558002083,Sheera Ameer Favas Lulu,sheera_amrfvs_lulu@sheeralateen.com,https://maps.app.goo.gl/QLkDRGAkXhfkjwhy5,21.4378469,39.2800342,covered
14,Jeddah,Lulu-Jamia,0552910405,Sheera Lulu Jamia,sheera_jamia_lulu@sheeralateen.com,https://maps.app.goo.gl/X1TXdJz4iqujhPHM8?g_st=iw,21.4819648732726,39.2377570804151,covered
15,Jeddah,Lulu-Ruwais,0562948769,Sheera Ruwais Lulu,sheera_ruwais_lulu@sheeralateen.com,https://maps.app.goo.gl/vRdwNQJEcR9mm5Ys6,21.5171909,39.1654283,covered
16,Jeddah,Al Marwa,0559004483,Sheera Al Marwa,sheera_almarwa@sheeralateen.com,https://maps.app.goo.gl/Rnmro8W7vbfovPpBA,21.6074267,39.1925653,covered
19,Jeddah,Safa,0500015716,Sheera Safa,sheera_safa@sheeralateen.com,https://maps.app.goo.gl/GuCGq4nhVEuCyFqu9,21.5717538,39.2203089,covered
21,Jeddah,Wadi Marikh,0559006986,Sheera Wadi Marikh,sheera_wadimarikh@sheeralateen.com,https://maps.app.goo.gl/Zb4HUTTdxLAb81D2A,21.5568591,39.2538832,covered
22,Makkah,Kakia,0543450612,Sheera Kakia,sheera_kakia@sheeralateen.com,https://maps.app.goo.gl/J2UbY7xaA9o7RydeA,21.3725873,39.7935773,covered
35,Jeddah,Sanabel,0543448423,Sanabel,,https://maps.app.goo.gl/6azF5Z3RWEahzkmH9,21.410663,39.2671003,covered
36,Jeddah,Al-Naeem,0540997448,Naeem,,https://maps.app.goo.gl/3ke5WisuP6mXzJAVA,21.6181551,39.1405162,covered
11,Jeddah,Harasath 2,0559057803,Sheera Harasath 2,sheera_harasath2@sheeralateen.com,https://maps.app.goo.gl/2QBsJFeqMQcPNrEo9,21.4819453,39.3353789,Not Covered
2,Jeddah,Al Qarnia,0559085412,Sheera Al Qarniya,sheera_alqarniya@sheeralateen.com,https://maps.app.goo.gl/17J7UrbYBaGi97Nw6,21.3003756,39.2359937,Not Covered
7,Jeddah,Bahra,0538627250,Sheera Bahra,sheera_bahra@sheeralateen.com,https://maps.app.goo.gl/MPfqMyXB6JwaFS6p9,21.4014449,39.4505122,Not Covered
8,Jeddah,Fadeylah,0551253217,Sheera Fadeylah,sheera_fadeylah@sheeralateen.com,https://maps.app.goo.gl/YNvjnwrd9wTVpY6F9,21.310416,39.2651532,Not Covered
10,Jeddah,Harasath 1,0538275440,Sheera Harasath,sheera_harasath@sheeralateen.com,https://maps.app.goo.gl/P78RnBYiPTLrz8MF6,21.4819453,39.3353789,Not Covered
17,Jeddah,Muhameed,0551248241,Sheera Muhameed,sheera_muhameed@sheeralateen.com,https://maps.app.goo.gl/Ys5auGQ3b3E8u9ev5,21.442582,39.3807062,Not Covered
24,Jumoom,Al-Riyadh (Osfan),0556231646,Sheera Al Riyadh (Osfan),sheera_alriyadh@sheeralateen.com,https://maps.app.goo.gl/khToJ2T5kWLHMvjU8,21.8786154,39.1112229,Not Covered
25,Jumoom,Jumoom,0551256118,Sheera Jumoom,sheera_jumoom@sheeralateen.com,https://maps.app.goo.gl/CV1qecKWoMCg196P9,21.5994956,39.6199392,Not Covered
37,Asfan,Gharan,0545394868,Gharan,,https://maps.app.goo.gl/vAcndcssxDNeEXW19,21.9755077,39.3616149,Not Covered
6,Jeddah,Al-Reheli,0538563025,Sheera Reheli,sheera_reheli@sheeralateen.com,https://maps.app.goo.gl/DDQE7gyzRY7aG3P86,21.7981714,39.1394921,covered
18,Jeddah,Obhur,0559712549,Sheera Obhur,sheera_obhur@sheeralateen.com,https://maps.app.goo.gl/TNweLeFzWFtBrtBf9,21.7889248,39.0953094,covered
20,Jeddah,Thayseer,0555014184,Sheera Thayseer,sheera_thayseer@sheeralateen.com,https://maps.app.goo.gl/fuwrLS8QFns8vHNb9,21.5519371,39.2787517,soon
23,Makkah,Makkah-Taneem,0559653785,SheeraTaneem Makkha,sheera_taneemmakkha@sheeralateen.com,https://maps.app.goo.gl/afkVH2jT9YU5Wixk9,21.5145988,39.7848358,soon
CSV;

$lines = explode("\n", trim($csv));
$stmt = $db->prepare("UPDATE branches SET latitude = :lat, longitude = :lng WHERE email = :email");
$stmtPhone = $db->prepare("UPDATE branches SET latitude = :lat, longitude = :lng WHERE phone = :phone");
$stmtName = $db->prepare("UPDATE branches SET latitude = :lat, longitude = :lng WHERE branch_name = :name");

echo "<div style='font-family: monospace; line-height: 1.6;'>";
echo "<h3>Updating Coordinates...</h3><ul>";

$successCount = 0;
foreach ($lines as $line) {
    $parts = str_getcsv($line);
    if (count($parts) < 9) continue;
    
    $branchName = trim($parts[2]);
    $phone = trim($parts[3]);
    $email = trim($parts[5]);
    $lat = trim($parts[7]);
    $lng = trim($parts[8]);
    
    $updated = false;
    
    if (!empty($email)) {
        $stmt->execute([':lat' => $lat, ':lng' => $lng, ':email' => $email]);
        if ($stmt->rowCount() > 0) $updated = true;
    }
    
    if (!$updated && !empty($phone)) {
        $stmtPhone->execute([':lat' => $lat, ':lng' => $lng, ':phone' => $phone]);
        if ($stmtPhone->rowCount() > 0) $updated = true;
    }
    
    if (!$updated && !empty($branchName)) {
        $stmtName->execute([':lat' => $lat, ':lng' => $lng, ':name' => $branchName]);
        if ($stmtName->rowCount() > 0) $updated = true;
    }
    
    if ($updated) {
        echo "<li><strong style='color: green;'>SUCCESS:</strong> {$branchName} ({$lat}, {$lng})</li>";
        $successCount++;
    } else {
        echo "<li><strong style='color: red;'>FAILED:</strong> Could not find branch {$branchName} in DB (Email: {$email}, Phone: {$phone})</li>";
    }
}

echo "</ul><hr><b>Total branches updated: {$successCount}</b></div>";
