<?php
/**
 * admin/includes/header.php
 * Sets up the HTML head, CSS, and opens the main layout wrappers.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?><?= htmlspecialchars(APP_NAME) ?> Admin</title>
    
    <!-- Fonts and Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin.css') ?>">
</head>
<body>
    <div class="admin-layout">
