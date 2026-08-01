<?php
/**
 * admin/developer.php
 * Developer-only settings (API keys, Webhook Secrets, Cron Secrets, Debug options)
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
        header('Location: developer.php');
        exit;
    }

    $storeUrl = trim($_POST['shopify_store_url'] ?? '');
    $storeUrl = rtrim($storeUrl, '/');
    
    $apiVersion = trim($_POST['shopify_api_version'] ?? '2026-04');
    $syncInterval = trim($_POST['sync_interval_minutes'] ?? '5');
    
    save_setting($db, 'shopify_store_url', $storeUrl);
    save_setting($db, 'shopify_api_version', $apiVersion);
    save_setting($db, 'sync_interval_minutes', $syncInterval);
    
    // Tokens/Secrets only updated if provided
    $apiToken = trim($_POST['shopify_api_token'] ?? '');
    if (!empty($apiToken)) {
        $encryptedToken = encrypt_shopify_token($apiToken);
        save_setting($db, 'shopify_api_token', $encryptedToken);
    }

    $webhookSecret = trim($_POST['shopify_webhook_secret'] ?? '');
    if (!empty($webhookSecret)) {
        save_setting($db, 'shopify_webhook_secret', $webhookSecret);
    }

    $cronSecret = trim($_POST['cron_secret'] ?? '');
    if (!empty($cronSecret)) {
        save_setting($db, 'cron_secret', $cronSecret);
    }

    // Debug Mode (Boolean)
    $debugMode = isset($_POST['debug_mode']) ? '1' : '0';
    save_setting($db, 'debug_mode', $debugMode);

    ActivityLogger::log((int) $_SESSION['user_id'], 'developer_settings_updated', 'Developer settings were updated.');
    flash('success', 'Developer settings saved successfully.');
    header('Location: developer.php');
    exit;
}

/* ──────────────────────────────────────────────────────────
   LOAD CURRENT SETTINGS
────────────────────────────────────────────────────────── */
$s = [
    'shopify_store_url'      => get_setting($db, 'shopify_store_url', ''),
    'shopify_api_version'    => get_setting($db, 'shopify_api_version', '2026-04'),
    'sync_interval_minutes'  => get_setting($db, 'sync_interval_minutes', '5'),
    'shopify_webhook_secret' => get_setting($db, 'shopify_webhook_secret', ''),
    'cron_secret'            => get_setting($db, 'cron_secret', ''),
    'debug_mode'             => get_setting($db, 'debug_mode', '0')
];

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
$pageTitle    = 'Developer Settings';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Developer Settings</h1>
        <p class="page-subtitle">Internal configuration, API tokens, and webhook secrets. <strong>Handle with care!</strong></p>
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
    <div class="card-body">
        <form method="POST" action="developer.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="section-title" style="margin-top:0;">Shopify Integration</div>
            <div class="form-grid" style="margin-bottom:28px;">
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="shopify_store_url">Store URL (e.g., https://my-store.myshopify.com)</label>
                    <input type="url" name="shopify_store_url" id="shopify_store_url"
                           class="form-control" value="<?= e($s['shopify_store_url']) ?>">
                </div>

                <div class="form-group">
                    <label for="shopify_api_version">API Version</label>
                    <input type="text" name="shopify_api_version" id="shopify_api_version"
                           class="form-control" value="<?= e($s['shopify_api_version']) ?>" placeholder="2026-04">
                </div>

                <div class="form-group">
                    <label for="sync_interval_minutes">Cron Sync Interval (Minutes)</label>
                    <input type="number" name="sync_interval_minutes" id="sync_interval_minutes"
                           class="form-control" value="<?= e($s['sync_interval_minutes']) ?>" min="1">
                </div>
            </div>

            <div class="section-title">Secrets & Tokens (Leave blank to keep current)</div>
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="shopify_api_token">Admin API Access Token (shpat_...)</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-key icon-left"></i>
                    <input type="password" name="shopify_api_token" id="shopify_api_token"
                           class="form-control" placeholder="••••••••••••••••••••••••••••••••">
                </div>
                <small class="text-muted">Will be encrypted in the database.</small>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="shopify_webhook_secret">Shopify Webhook Secret</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-lock icon-left"></i>
                    <input type="password" name="shopify_webhook_secret" id="shopify_webhook_secret"
                           class="form-control" placeholder="<?= $s['shopify_webhook_secret'] ? '••••••••••••••••••••••••••••••••' : 'Not Set' ?>">
                </div>
                <small class="text-muted">Used to verify HMAC signatures from Shopify webhooks.</small>
            </div>
            
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="cron_secret">Internal Cron Secret</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-clock icon-left"></i>
                    <input type="password" name="cron_secret" id="cron_secret"
                           class="form-control" placeholder="<?= $s['cron_secret'] ? '••••••••••••••••••••••••••••••••' : 'Not Set' ?>">
                </div>
                <small class="text-muted">Used to authenticate cron requests.</small>
            </div>

            <div class="section-title">Internal Debug</div>
            <div class="form-group">
                <label class="toggle-switch">
                    <input type="checkbox" name="debug_mode" id="debug_mode" value="1" <?= $s['debug_mode'] === '1' ? 'checked' : '' ?>>
                    <span class="slider"></span>
                    <span class="toggle-label">Enable Debug Mode (Logs extra info to activity logs)</span>
                </label>
            </div>

            <div class="form-group" style="margin-top: 32px;">
                <button type="submit" class="btn-primary">Save Developer Settings</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
