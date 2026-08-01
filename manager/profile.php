<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('branch_manager', '../login.php');

$db = Database::getConnection();
$userId = (int) $_SESSION['user_id'];

// Fetch current info
$stmt = $db->prepare("SELECT name, email FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Session expired. Please try again.');
    } else {
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            
            if ($name === '') {
                flash('error', 'Name cannot be empty.');
            } else {
                $upd = $db->prepare("UPDATE users SET name = :name WHERE id = :id");
                $upd->execute([':name' => $name, ':id' => $userId]);
                $_SESSION['name'] = $name;
                flash('success', 'Profile updated successfully.');
            }
            
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword     = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Verify current
            $verifyStmt = $db->prepare("SELECT password FROM users WHERE id = :id");
            $verifyStmt->execute([':id' => $userId]);
            $currentHash = $verifyStmt->fetchColumn();
            
            if (!password_verify($currentPassword, $currentHash)) {
                flash('error', 'Current password is incorrect.');
            } elseif (strlen($newPassword) < 8) {
                flash('error', 'New password must be at least 8 characters.');
            } elseif ($newPassword !== $confirmPassword) {
                flash('error', 'New passwords do not match.');
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password = :pwd WHERE id = :id")->execute([':pwd' => $hash, ':id' => $userId]);
                flash('success', 'Password changed successfully.');
            }
        }
    }
    header('Location: profile.php');
    exit;
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Manage your personal account details and password.</p>
    </div>
</div>

<div class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Profile Information</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="profile.php">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled class="form-control" style="background: var(--bg-main);">
                    <small class="text-muted">Email cannot be changed.</small>
                </div>
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required class="form-control">
                </div>
                
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Change Password</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="profile.php">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" class="form-control">
                </div>
                
                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
