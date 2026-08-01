<?php
/**
 * admin/settings.php
 * System settings with tabbed interface:
 *  - Company Information (name, email, phone, address)
 *  - Branding (logo + favicon upload)
 *  - System (currency, timezone, orders per page)
 *  - Shopify (store URL only — integration phase comes later)
 *  - Change Super Admin Password
 *
 * All settings are stored in system_settings (key-value).
 * Files are stored in assets/uploads/branding/.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();

/* ──────────────────────────────────────────────────────────
   POST HANDLER
────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security token mismatch. Please try again.');
        header('Location: settings.php');
        exit;
    }

    $tab = $_POST['tab'] ?? '';

    /* ── COMPANY + BRANDING ── */
    if ($tab === 'company') {
        $fields = ['company_name', 'company_email', 'company_phone', 'company_address'];
        foreach ($fields as $key) {
            save_setting($db, $key, trim($_POST[$key] ?? ''));
        }

        // Handle Logo upload
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $result = _handleBrandingUpload('company_logo', $_FILES['company_logo'], $db);
            if ($result !== true) flash('error', 'Logo: ' . $result);
        }

        // Handle Favicon upload
        if (isset($_FILES['company_favicon']) && $_FILES['company_favicon']['error'] === UPLOAD_ERR_OK) {
            $result = _handleBrandingUpload('company_favicon', $_FILES['company_favicon'], $db, ['ico','png','svg']);
            if ($result !== true) flash('error', 'Favicon: ' . $result);
        }

        ActivityLogger::log((int) $_SESSION['user_id'], 'settings_updated', 'Company information was updated.');
        if (!get_flash('error')) flash('success', 'Company information saved successfully.');

    /* ── SYSTEM ── */
    } elseif ($tab === 'system') {
        $currency   = trim($_POST['currency_symbol'] ?? '₹');
        $perPage    = max(5, min(100, (int) ($_POST['orders_per_page'] ?? 25)));
        $timezone   = trim($_POST['timezone'] ?? 'Asia/Kolkata');
        $autoAssign = isset($_POST['branch_auto_assign']) ? '1' : '0';

        save_setting($db, 'currency_symbol',   $currency);
        save_setting($db, 'orders_per_page',   (string) $perPage);
        save_setting($db, 'timezone',          $timezone);
        save_setting($db, 'branch_auto_assign',$autoAssign);

        ActivityLogger::log((int) $_SESSION['user_id'], 'settings_updated', 'System settings were updated.');
        flash('success', 'System settings saved successfully.');


    /* ── CHANGE PASSWORD ── */
    } elseif ($tab === 'password') {
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd     = $_POST['new_password']      ?? '';
        $confirmPwd = $_POST['confirm_password']  ?? '';

        if (strlen($newPwd) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($newPwd !== $confirmPwd) {
            flash('error', 'New passwords do not match.');
        } else {
            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = :id AND role = 'super_admin'");
            $stmt->execute([':id' => (int) $_SESSION['user_id']]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($currentPwd, $hash)) {
                flash('error', 'Current password is incorrect.');
            } else {
                $db->prepare("UPDATE users SET password = :pwd WHERE id = :id")
                   ->execute([
                       ':pwd' => password_hash($newPwd, PASSWORD_DEFAULT),
                       ':id'  => (int) $_SESSION['user_id'],
                   ]);
                ActivityLogger::log(
                    (int) $_SESSION['user_id'],
                    'password_changed',
                    'Super Admin changed their own password.'
                );
                flash('success', 'Password changed successfully.');
            }
        }
        // Keep user on password tab
        header('Location: settings.php?tab=password');
        exit;
    }

    $redirectTab = $tab !== 'password' ? $tab : 'password';
    header('Location: settings.php?tab=' . $redirectTab);
    exit;
}

/* ──────────────────────────────────────────────────────────
   FILE UPLOAD HELPER
────────────────────────────────────────────────────────── */
function _handleBrandingUpload(
    string $settingKey,
    array  $file,
    PDO    $db,
    array  $allowedExts = ['jpg','jpeg','png','gif','webp','svg']
): bool|string {
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $maxSize = 2 * 1024 * 1024; // 2 MB

    if (!in_array($ext, $allowedExts, true)) {
        return "Invalid file type. Allowed: " . implode(', ', $allowedExts) . ".";
    }
    if ($file['size'] > $maxSize) {
        return "File too large. Maximum size is 2 MB.";
    }

    $uploadDir = __DIR__ . '/../assets/uploads/branding/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return "Could not create upload directory.";
    }

    // Delete old file if exists
    $existing = get_setting($db, $settingKey);
    if ($existing) {
        $oldPath = __DIR__ . '/../assets/' . $existing;
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    $filename = $settingKey . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return "Failed to save the file. Check server permissions.";
    }

    save_setting($db, $settingKey, 'uploads/branding/' . $filename);
    return true;
}

/* ──────────────────────────────────────────────────────────
   LOAD CURRENT SETTINGS
────────────────────────────────────────────────────────── */
$s = [
    'company_name'        => get_setting($db, 'company_name',      APP_NAME),
    'company_email'       => get_setting($db, 'company_email',     ''),
    'company_phone'       => get_setting($db, 'company_phone',     ''),
    'company_address'     => get_setting($db, 'company_address',   ''),
    'company_logo'        => get_setting($db, 'company_logo',      ''),
    'company_favicon'     => get_setting($db, 'company_favicon',   ''),
    'currency_symbol'     => get_setting($db, 'currency_symbol',   '₹'),
    'orders_per_page'     => get_setting($db, 'orders_per_page',   '25'),
    'timezone'            => get_setting($db, 'timezone',          'Asia/Kolkata'),
    'branch_auto_assign'  => get_setting($db, 'branch_auto_assign','0'),

];

// Active tab from GET or POST redirect
$activeTab = in_array($_GET['tab'] ?? '', ['company','system','password'])
    ? $_GET['tab']
    : 'company';

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
$pageTitle    = 'Settings';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';

// Available PHP timezones (abbreviated list of common ones)
$timezones = [
    'Asia/Kolkata'       => 'Asia/Kolkata (IST, UTC+5:30)',
    'Asia/Dubai'         => 'Asia/Dubai (GST, UTC+4)',
    'Asia/Riyadh'        => 'Asia/Riyadh (AST, UTC+3)',
    'Asia/Singapore'     => 'Asia/Singapore (SGT, UTC+8)',
    'Asia/Karachi'       => 'Asia/Karachi (PKT, UTC+5)',
    'Asia/Dhaka'         => 'Asia/Dhaka (BST, UTC+6)',
    'Asia/Colombo'       => 'Asia/Colombo (IST, UTC+5:30)',
    'Europe/London'      => 'Europe/London (GMT/BST)',
    'Europe/Paris'       => 'Europe/Paris (CET, UTC+1)',
    'America/New_York'   => 'America/New_York (EST/EDT)',
    'America/Chicago'    => 'America/Chicago (CST/CDT)',
    'America/Los_Angeles'=> 'America/Los_Angeles (PST/PDT)',
    'UTC'                => 'UTC',
];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">System Settings</h1>
        <p class="page-subtitle">Configure global platform settings, branding, and integrations.</p>
    </div>
</div>

<?php if ($flashSuccess): ?>
<div class="alert alert-success" id="flash-success">
    <i class="fa-solid fa-check-circle"></i>
    <?= e($flashSuccess) ?>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="alert alert-danger" id="flash-error">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <?= e($flashError) ?>
</div>
<?php endif; ?>

<div class="card">
    <!-- Tab Navigation -->
    <div class="card-body" style="padding:0 24px;">
        <div class="tab-nav" id="settings-tabs" role="tablist">
            <button class="tab-btn <?= $activeTab === 'company'  ? 'active' : '' ?>"
                    onclick="switchTab('company')"  id="tab-company"
                    role="tab" aria-selected="<?= $activeTab === 'company' ? 'true' : 'false' ?>">
                <i class="fa-solid fa-building"></i> Company &amp; Branding
            </button>
            <button class="tab-btn <?= $activeTab === 'system'   ? 'active' : '' ?>"
                    onclick="switchTab('system')"   id="tab-system"
                    role="tab" aria-selected="<?= $activeTab === 'system' ? 'true' : 'false' ?>">
                <i class="fa-solid fa-sliders"></i> System
            </button>

            <button class="tab-btn <?= $activeTab === 'password' ? 'active' : '' ?>"
                    onclick="switchTab('password')" id="tab-password"
                    role="tab" aria-selected="<?= $activeTab === 'password' ? 'true' : 'false' ?>">
                <i class="fa-solid fa-lock"></i> Change Password
            </button>
        </div>
    </div>

    <div class="card-body" style="padding:28px 24px;">

        <!-- ══════════════════════════════════
             TAB: Company & Branding
        ═══════════════════════════════════ -->
        <div id="pane-company" class="tab-pane <?= $activeTab === 'company' ? 'active' : '' ?>" role="tabpanel">
            <form method="POST" action="settings.php" enctype="multipart/form-data" id="form-company">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="tab" value="company">

                <div class="section-title">Company Information</div>
                <div class="form-grid" style="margin-bottom:28px;">
                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <input type="text" name="company_name" id="company_name"
                               class="form-control" value="<?= e($s['company_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_email">Company Email</label>
                        <input type="email" name="company_email" id="company_email"
                               class="form-control" value="<?= e($s['company_email']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_phone">Phone Number</label>
                        <input type="text" name="company_phone" id="company_phone"
                               class="form-control" value="<?= e($s['company_phone']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="company_address">Address</label>
                        <textarea name="company_address" id="company_address"
                                  class="form-control" rows="2"><?= e($s['company_address']) ?></textarea>
                    </div>
                </div>

                <div class="section-title">Branding</div>
                <div class="form-grid">
                    <!-- Logo -->
                    <div class="form-group">
                        <label for="company_logo">Company Logo</label>
                        <?php if ($s['company_logo']): ?>
                        <div style="margin-bottom:10px;">
                            <img src="../assets/<?= e($s['company_logo']) ?>"
                                 alt="Current Logo"
                                 class="upload-preview"
                                 id="logo-preview">
                        </div>
                        <?php else: ?>
                        <div style="margin-bottom:10px;">
                            <div class="upload-preview" style="display:flex;align-items:center;justify-content:center;" id="logo-preview-placeholder">
                                <i class="fa-regular fa-image" style="font-size:28px;color:var(--border);"></i>
                            </div>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="company_logo" id="company_logo"
                               accept="image/*" class="form-control"
                               onchange="previewImage(this,'logo-preview')">
                        <p class="upload-hint">JPG, PNG, SVG, WebP. Max 2 MB. Recommended: 300×100 px.</p>
                    </div>

                    <!-- Favicon -->
                    <div class="form-group">
                        <label for="company_favicon">Favicon</label>
                        <?php if ($s['company_favicon']): ?>
                        <div style="margin-bottom:10px;">
                            <img src="../assets/<?= e($s['company_favicon']) ?>"
                                 alt="Current Favicon"
                                 class="upload-preview favicon-preview"
                                 id="favicon-preview">
                        </div>
                        <?php else: ?>
                        <div style="margin-bottom:10px;">
                            <div class="upload-preview favicon-preview" style="display:flex;align-items:center;justify-content:center;" id="favicon-preview-placeholder">
                                <i class="fa-regular fa-image" style="font-size:18px;color:var(--border);"></i>
                            </div>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="company_favicon" id="company_favicon"
                               accept="image/png,image/svg+xml,.ico"
                               class="form-control"
                               onchange="previewImage(this,'favicon-preview')">
                        <p class="upload-hint">PNG, ICO, SVG. Max 2 MB. Recommended: 32×32 px.</p>
                    </div>
                </div>

                <div style="margin-top:24px;text-align:right;">
                    <button type="submit" class="btn btn-primary" id="btn-save-company">
                        <i class="fa-solid fa-floppy-disk"></i> Save Company &amp; Branding
                    </button>
                </div>
            </form>
        </div>

        <!-- ══════════════════════════════════
             TAB: System Settings
        ═══════════════════════════════════ -->
        <div id="pane-system" class="tab-pane <?= $activeTab === 'system' ? 'active' : '' ?>" role="tabpanel">
            <form method="POST" action="settings.php" id="form-system">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="tab" value="system">

                <div class="section-title">Display &amp; Locale</div>
                <div class="form-grid" style="margin-bottom:28px;">
                    <div class="form-group">
                        <label for="currency_symbol">Currency Symbol</label>
                        <input type="text" name="currency_symbol" id="currency_symbol"
                               class="form-control" value="<?= e($s['currency_symbol']) ?>" maxlength="5">
                        <span class="upload-hint">e.g. ₹, $, €, AED</span>
                    </div>
                    <div class="form-group">
                        <label for="timezone">Timezone</label>
                        <select name="timezone" id="timezone" class="form-control">
                            <?php foreach ($timezones as $tz => $label): ?>
                            <option value="<?= e($tz) ?>" <?= $s['timezone'] === $tz ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="orders_per_page">Orders Per Page</label>
                        <input type="number" name="orders_per_page" id="orders_per_page"
                               class="form-control" value="<?= e($s['orders_per_page']) ?>" min="5" max="100">
                        <span class="upload-hint">Between 5 and 100.</span>
                    </div>
                </div>

                <div class="section-title">Branch Allocation</div>
                <div style="display:flex;align-items:center;gap:14px;padding:16px 0;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;">
                        <input type="checkbox" name="branch_auto_assign" id="branch_auto_assign"
                               value="1" <?= $s['branch_auto_assign'] === '1' ? 'checked' : '' ?>
                               style="width:18px;height:18px;cursor:pointer;">
                        <span>
                            <strong>Enable Auto Branch Assignment</strong><br>
                            <span class="text-muted" style="font-size:13px;">
                                Automatically assign new orders to the nearest available active branch.
                                (Requires Shopify integration.)
                            </span>
                        </span>
                    </label>
                </div>

                <div style="margin-top:24px;text-align:right;">
                    <button type="submit" class="btn btn-primary" id="btn-save-system">
                        <i class="fa-solid fa-floppy-disk"></i> Save System Settings
                    </button>
                </div>
            </form>
        </div>



        <!-- ══════════════════════════════════
             TAB: Change Password
        ═══════════════════════════════════ -->
        <div id="pane-password" class="tab-pane <?= $activeTab === 'password' ? 'active' : '' ?>" role="tabpanel">
            <form method="POST" action="settings.php" id="form-password">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="tab" value="password">

                <div class="section-title">Super Admin Password</div>

                <div style="max-width:460px;">
                    <div class="form-group" style="margin-bottom:18px;">
                        <label for="current_password">Current Password <span class="required">*</span></label>
                        <input type="password" name="current_password" id="current_password"
                               class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="form-group" style="margin-bottom:18px;">
                        <label for="new_password_settings">New Password <span class="required">*</span></label>
                        <input type="password" name="new_password" id="new_password_settings"
                               class="form-control" required minlength="8"
                               placeholder="Min. 8 characters" autocomplete="new-password">
                    </div>
                    <div class="form-group" style="margin-bottom:24px;">
                        <label for="confirm_password_settings">Confirm New Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password_settings"
                               class="form-control" required minlength="8"
                               placeholder="Repeat the new password" autocomplete="new-password">
                    </div>
                    <div class="alert alert-warning" style="margin-bottom:20px;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        After changing your password you will remain logged in. Use the new password on your next login.
                    </div>
                    <button type="submit" class="btn btn-warning" id="btn-change-password">
                        <i class="fa-solid fa-key"></i> Change Password
                    </button>
                </div>
            </form>
        </div>

    </div><!-- /card-body -->
</div><!-- /card -->

<script>
/* ── Tab switching ── */
function switchTab(name) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
    });
    document.getElementById('pane-' + name).classList.add('active');
    const btn = document.getElementById('tab-' + name);
    btn.classList.add('active');
    btn.setAttribute('aria-selected', 'true');

    // Update URL without reload for bookmarkability
    const url = new URL(window.location);
    url.searchParams.set('tab', name);
    window.history.replaceState({}, '', url);
}

/* ── Image preview before upload ── */
function previewImage(input, previewId) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        let img = document.getElementById(previewId);
        if (!img || img.tagName !== 'IMG') {
            // Replace placeholder div with img
            const placeholder = document.getElementById(previewId + '-placeholder') ||
                                 document.getElementById(previewId);
            const newImg = document.createElement('img');
            newImg.id        = previewId;
            newImg.alt       = 'Preview';
            newImg.className = placeholder ? placeholder.className : 'upload-preview';
            placeholder?.replaceWith(newImg);
            img = newImg;
        }
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

/* ── Client-side password match check ── */
document.getElementById('form-password')?.addEventListener('submit', function(e) {
    const np = document.getElementById('new_password_settings').value;
    const cp = document.getElementById('confirm_password_settings').value;
    if (np !== cp) {
        e.preventDefault();
        alert('New passwords do not match. Please check and try again.');
    }
});



/* ── Auto-dismiss flash ── */
setTimeout(() => {
    ['flash-success','flash-error'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }
    });
}, 6000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
