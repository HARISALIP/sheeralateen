<?php
require 'D:/Documents/Works/sheeralateen/core/bootstrap.php';
require 'D:/Documents/Works/sheeralateen/core/LeajlakService.php';

// I will bypass the private methods by using reflection or just copying the login logic
$db = Database::getConnection();
$service = new LeajlakService($db);

$refClass = new ReflectionClass(LeajlakService::class);
$loginMethod = $refClass->getMethod('login');
$loginMethod->setAccessible(true);
$getFormMethod = $refClass->getMethod('getCreateFormToken');
$getFormMethod->setAccessible(true);

$cookieFile = tempnam(sys_get_temp_dir(), 'leajlak_');
try {
    $loginData = $loginMethod->invoke($service, $cookieFile);
    if (!$loginData['success']) die("Login failed: " . $loginData['error']);
    
    $formData = $getFormMethod->invoke($service, $cookieFile);
    if (!$formData['success']) die("Form failed: " . $formData['error']);
    
    echo "Form HTML snapshot:\n";
    preg_match('/<select[^>]*name="delivery_payment_mode\[\]"[^>]*>(.*?)<\/select>/is', $formData['html'], $matches);
    if ($matches) {
        echo $matches[1];
    } else {
        echo "Could not find select dropdown.";
    }
} finally {
    @unlink($cookieFile);
}
