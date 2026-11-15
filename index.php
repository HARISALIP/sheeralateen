<?php
/**
 * index.php
 * ---------------------------------------------------------
 * Entry point for Sheera Lateen Branch Management System.
 * Immediately routes authenticated users to their role dashboard
 * and unauthenticated users straight to the login screen.
 */

require_once __DIR__ . '/core/bootstrap.php';

if (Auth::isLoggedIn()) {
    if (($_SESSION['role'] ?? '') === 'super_admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: manager/dashboard.php');
    }
    exit;
}

// Unauthenticated users are sent directly to the login page
header('Location: login.php');
exit;
