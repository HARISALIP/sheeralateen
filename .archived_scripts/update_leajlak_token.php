<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();

$baseUrl = "https://app.leajlak.com/api/partner-v2";
$payload = json_encode([
    "email" => "adhil@sheeralateen.com",
    "password" => "adhil@sheeralateen"
]);

$ch = curl_init($baseUrl . "/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<div style=\"font-family: monospace; font-size: 16px;\">";
echo "<h3>Authenticating with Leajlak Production...</h3>";

if ($httpCode >= 200 && $httpCode < 300) {
    $data = json_decode($response, true);
    if (isset($data["token"])) {
        $token = $data["token"];
        
        $stmt = $db->prepare("UPDATE system_settings SET setting_value = :val WHERE setting_key = 'leajlak_api_token'");
        $stmt->execute([":val" => $token]);
        
        if ($stmt->rowCount() === 0) {
            $stmtInsert = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('leajlak_api_token', :val)");
            $stmtInsert->execute([":val" => $token]);
        }
        
        echo "<strong style=\"color: green;\">SUCCESS:</strong> Production API token successfully retrieved and saved to the database!<br>";
        echo "<small>Token ends with: " . substr($token, -6) . "</small>";
    } else {
        echo "<strong style=\"color: red;\">ERROR:</strong> Login succeeded but no token was returned in the response payload.<br>";
        echo "Response: " . htmlspecialchars($response);
    }
} else {
    echo "<strong style=\"color: red;\">FAILED:</strong> Production login returned HTTP " . $httpCode . "<br>";
    echo "Response: " . htmlspecialchars($response);
}
echo "</div>";
