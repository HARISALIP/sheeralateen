<?php
require_once __DIR__ . '/../core/bootstrap.php';

$auth = new Auth(Database::getConnection());
$auth->logout();

header('Location: ../login.php');
exit;
