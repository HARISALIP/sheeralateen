<?php
require 'D:\Documents\Works\sheeralateen\config\config.php';
try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $stmt = $db->query("SELECT id, branch_name, latitude, longitude FROM branches");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
