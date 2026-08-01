<?php
/**
 * admin/managers.php
 * Full CRUD for Branch Managers:
 *  - List with search + pagination
 *  - Add / Edit via modal (POST-Redirect-GET)
 *  - Assign to branch
 *  - Toggle Active / Inactive
 *  - Reset Password via modal
 *  - Soft-delete with referential-integrity guard
 *  - Activity logging on every mutation
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
        header('Location: managers.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    /* ── ADD ── */
    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $status   = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($name === '' || $email === '' || $password === '') {
            flash('error', 'Name, email and password are required.');
            header('Location: managers.php');
            exit;
        }
        if (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
            header('Location: managers.php');
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password, role, status)
                VALUES (:name, :email, :pwd, 'branch_manager', :status)
            ");
            $stmt->execute([
                ':name'      => $name,
                ':email'     => $email,
                ':pwd'       => password_hash($password, PASSWORD_DEFAULT),
                ':status'    => $status,
            ]);
            $newId = (int) $db->lastInsertId();
            ActivityLogger::log(
                (int) $_SESSION['user_id'],
                'manager_created',
                "Manager '{$name}' ({$email}) was created.",
                $branchId
            );
            flash('success', "Manager '{$name}' created successfully.");
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flash('error', "Email '{$email}' is already registered. Please use a different email.");
            } else {
                flash('error', 'An error occurred. Please try again.');
            }
        }

    /* ── EDIT ── */
    } elseif ($action === 'edit') {
        $id       = (int) ($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $status   = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($name === '' || $email === '') {
            flash('error', 'Name and email are required.');
            header('Location: managers.php');
            exit;
        }

        try {
            $stmt = $db->prepare("
                UPDATE users
                SET name = :name, email = :email, status = :status
                WHERE id = :id AND role = 'branch_manager' AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':name'      => $name,
                ':email'     => $email,
                ':status'    => $status,
                ':id'        => $id,
            ]);
            ActivityLogger::log(
                (int) $_SESSION['user_id'],
                'manager_updated',
                "Manager '{$name}' (ID {$id}) was updated.",
                $branchId
            );
            flash('success', "Manager '{$name}' updated successfully.");
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flash('error', "Email '{$email}' is already in use. Please use a different email.");
            } else {
                flash('error', 'An error occurred. Please try again.');
            }
        }

    /* ── TOGGLE STATUS ── */
    } elseif ($action === 'toggle_status') {
        $id        = (int) ($_POST['id'] ?? 0);
        $newStatus = ($_POST['new_status'] ?? 'active') === 'active' ? 'active' : 'inactive';

        // Fetch manager info for logging
        $mgr = $db->prepare("SELECT name, branch_id FROM users WHERE id = :id AND deleted_at IS NULL");
        $mgr->execute([':id' => $id]);
        $mgrData = $mgr->fetch();

        $db->prepare("UPDATE users SET status = :status WHERE id = :id AND deleted_at IS NULL")
           ->execute([':status' => $newStatus, ':id' => $id]);

        $logAction = $newStatus === 'active' ? 'manager_activated' : 'manager_deactivated';
        $logDesc   = "Manager '" . ($mgrData['name'] ?? "ID {$id}") . "' was set to '{$newStatus}'.";
        ActivityLogger::log(
            (int) $_SESSION['user_id'],
            $logAction,
            $logDesc,
            $mgrData['branch_id'] ?? null
        );
        flash('success', "Manager status changed to " . ucfirst($newStatus) . ".");

    /* ── RESET PASSWORD ── */
    } elseif ($action === 'reset_password') {
        $id          = (int) ($_POST['id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($newPassword !== $confirm) {
            flash('error', 'Passwords do not match.');
        } else {
            $db->prepare("UPDATE users SET password = :pwd WHERE id = :id AND deleted_at IS NULL")
               ->execute([':pwd' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $id]);

            $mgrName = $db->prepare("SELECT name FROM users WHERE id = :id");
            $mgrName->execute([':id' => $id]);
            $mgrNameVal = $mgrName->fetchColumn() ?? "ID {$id}";

            ActivityLogger::log(
                (int) $_SESSION['user_id'],
                'manager_password_reset',
                "Password for manager '{$mgrNameVal}' was reset by Super Admin."
            );
            flash('success', "Password reset successfully for manager.");
        }

    /* ── DELETE (soft, with integrity guard) ── */
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        // Fetch manager info
        $mgrStmt = $db->prepare("SELECT name, branch_id FROM users WHERE id = :id AND deleted_at IS NULL");
        $mgrStmt->execute([':id' => $id]);
        $mgrData = $mgrStmt->fetch();

        // 1. Activity log references
        $logStmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = :id");
        $logStmt->execute([':id' => $id]);
        $logCount = (int) $logStmt->fetchColumn();

        // 2. Order status history (changed_by)
        $hStmt = $db->prepare("SELECT COUNT(*) FROM order_status_history WHERE changed_by = :id");
        $hStmt->execute([':id' => $id]);
        $histCount = (int) $hStmt->fetchColumn();

        // 3. Order notes
        $noteStmt = $db->prepare("SELECT COUNT(*) FROM order_notes WHERE user_id = :id");
        $noteStmt->execute([':id' => $id]);
        $noteCount = (int) $noteStmt->fetchColumn();

        // 4. Branch manager assignment
        $branchStmt = $db->prepare("SELECT COUNT(*) FROM branches WHERE branch_manager_id = :id AND deleted_at IS NULL");
        $branchStmt->execute([':id' => $id]);
        $branchCount = (int) $branchStmt->fetchColumn();

        $reasons = [];
        if ($logCount   > 0) $reasons[] = "historical activity log entries ({$logCount})";
        if ($histCount  > 0) $reasons[] = "order status history records ({$histCount})";
        if ($noteCount  > 0) $reasons[] = "order notes ({$noteCount})";
        if ($branchCount > 0) $reasons[] = "currently assigned as branch manager";

        if (!empty($reasons)) {
            flash('error',
                'This manager cannot be deleted because they are linked to: '
                . implode(', ', $reasons)
                . '. Please <strong>deactivate</strong> the manager instead to preserve data integrity.'
            );
        } else {
            $db->prepare("UPDATE users SET deleted_at = NOW() WHERE id = :id AND role = 'branch_manager'")
               ->execute([':id' => $id]);
            ActivityLogger::log(
                (int) $_SESSION['user_id'],
                'manager_deleted',
                "Manager '" . ($mgrData['name'] ?? "ID {$id}") . "' was permanently removed."
            );
            flash('success', 'Manager deleted successfully.');
        }
    }

    header('Location: managers.php');
    exit;
}

/* ──────────────────────────────────────────────────────────
   GET HANDLER
────────────────────────────────────────────────────────── */
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = "u.role = 'branch_manager' AND u.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $where        .= ' AND (u.name LIKE :q OR u.email LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);

$listStmt = $db->prepare("
    SELECT u.*, b.branch_name
    FROM   users u
    LEFT JOIN branches b ON u.branch_id = b.id AND b.deleted_at IS NULL
    WHERE  {$where}
    ORDER  BY u.created_at DESC
    LIMIT  {$perPage} OFFSET {$offset}
");
$listStmt->execute($params);
$managers = $listStmt->fetchAll();

// All active branches for dropdown
$branches = $db->query("
    SELECT id, branch_name, branch_code FROM branches
    WHERE deleted_at IS NULL AND status = 'active'
    ORDER BY branch_name
")->fetchAll();

// Summary counts
$totalManagers  = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='branch_manager' AND deleted_at IS NULL")->fetchColumn();
$activeManagers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='branch_manager' AND status='active' AND deleted_at IS NULL")->fetchColumn();

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
$pageTitle    = 'Managers';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Branch Managers</h1>
        <p class="page-subtitle">
            <?= $totalManagers ?> manager(s) total &mdash; <?= $activeManagers ?> active.
            Manage accounts, branch assignments and access.
        </p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addManagerModal')" id="btn-add-manager">
            <i class="fa-solid fa-user-plus"></i> Add Manager
        </button>
    </div>
</div>

<?php if ($flashSuccess): ?>
<div class="alert alert-success" id="flash-success">
    <i class="fa-solid fa-check-circle"></i>
    <?= $flashSuccess ?>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="alert alert-danger" id="flash-error">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <?= $flashError ?>
</div>
<?php endif; ?>

<!-- Search -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:14px 24px;">
        <form method="GET" action="managers.php" class="search-form">
            <div class="input-group">
                <i class="fa-solid fa-search"></i>
                <input type="text" name="q" id="search-input"
                       value="<?= e($search) ?>"
                       placeholder="Search by name or email…"
                       class="form-control">
            </div>
            <button type="submit" class="btn btn-primary" id="btn-search">Search</button>
            <?php if ($search): ?>
            <a href="managers.php" class="btn btn-outline" id="btn-clear">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Managers Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">All Managers</h2>
        <span class="text-muted" style="font-size:13px;"><?= $totalRows ?> result(s)</span>
    </div>
    <div class="table-responsive">
        <div class="table-responsive">
            <table class="table" id="managers-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Branch</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($managers)): ?>
                <tr>
                    <td colspan="7" class="empty-state">
                        <i class="fa-solid fa-users"></i>
                        No managers found<?= $search ? " for <strong>" . e($search) . "</strong>" : '' ?>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($managers as $mgr): ?>
                <tr>
                    <td><?= $mgr['id'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="mini-avatar" style="width:32px;height:32px;font-size:13px;">
                                <?= strtoupper(substr($mgr['name'], 0, 1)) ?>
                            </div>
                            <strong><?= e($mgr['name']) ?></strong>
                        </div>
                    </td>
                    <td><?= e($mgr['email']) ?></td>
                    <td>
                        <?php if ($mgr['branch_name']): ?>
                            <i class="fa-solid fa-store" style="color:var(--text-muted);font-size:11px;margin-right:4px;"></i>
                            <?= e($mgr['branch_name']) ?>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:12px;">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status <?= e($mgr['status']) ?>"><?= ucfirst($mgr['status']) ?></span>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?= $mgr['last_login_at'] ? date('d M Y, H:i', strtotime($mgr['last_login_at'])) : 'Never' ?>
                        </small>
                    </td>
                    <td>
                        <div class="action-btns">
                            <!-- Edit -->
                            <button class="btn btn-sm btn-outline"
                                    onclick="editManager(<?= htmlspecialchars(json_encode($mgr), ENT_QUOTES) ?>)"
                                    title="Edit Manager"
                                    id="edit-manager-<?= $mgr['id'] ?>">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>

                            <!-- Reset Password -->
                            <button class="btn btn-sm btn-info"
                                    onclick="openResetPassword(<?= $mgr['id'] ?>, '<?= addslashes(e($mgr['name'])) ?>')"
                                    title="Reset Password"
                                    id="reset-pwd-<?= $mgr['id'] ?>">
                                <i class="fa-solid fa-key"></i>
                            </button>

                            <!-- Toggle Status -->
                            <form method="POST" action="managers.php" style="display:inline;" id="toggle-form-<?= $mgr['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $mgr['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $mgr['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit"
                                        class="btn btn-sm <?= $mgr['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>"
                                        title="<?= $mgr['status'] === 'active' ? 'Deactivate' : 'Activate' ?>"
                                        id="toggle-btn-<?= $mgr['id'] ?>">
                                    <i class="fa-solid fa-<?= $mgr['status'] === 'active' ? 'toggle-off' : 'toggle-on' ?>"></i>
                                </button>
                            </form>

                            <!-- Delete -->
                            <button class="btn btn-sm btn-danger"
                                    onclick="confirmDelete(<?= $mgr['id'] ?>, '<?= addslashes(e($mgr['name'])) ?>')"
                                    title="Delete Manager"
                                    id="delete-manager-<?= $mgr['id'] ?>">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrapper" id="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
           class="page-btn" id="page-prev">&laquo; Prev</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
           class="page-btn <?= $i === $page ? 'active' : '' ?>"
           id="page-<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
           class="page-btn" id="page-next">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════ -->

<!-- Add Manager Modal -->
<div id="addManagerModal" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="add-mgr-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="add-mgr-title">Add New Manager</h3>
            <button class="modal-close" onclick="closeModal('addManagerModal')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="managers.php" id="add-manager-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="add_name">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="add_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="add_email">Email <span class="required">*</span></label>
                        <input type="email" name="email" id="add_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="add_password">Password <span class="required">*</span></label>
                        <input type="password" name="password" id="add_password" class="form-control" required minlength="8" placeholder="Min. 8 characters">
                    </div>
                    <div class="form-group">
                        <label for="add_status">Status</label>
                        <select name="status" id="add_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addManagerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-create-manager">
                    <i class="fa-solid fa-user-plus"></i> Create Manager
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Manager Modal -->
<div id="editManagerModal" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="edit-mgr-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="edit-mgr-title">Edit Manager</h3>
            <button class="modal-close" onclick="closeModal('editManagerModal')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="managers.php" id="edit-manager-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_mgr_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="edit_name">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email <span class="required">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_mgr_status">Status</label>
                        <select name="status" id="edit_mgr_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="alert alert-info" style="margin-top:16px;margin-bottom:0;">
                    <i class="fa-solid fa-circle-info"></i>
                    To change this manager's password, use the <strong>Reset Password</strong> (key icon) action.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editManagerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-manager">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="reset-pwd-title">
    <div class="modal-dialog modal-sm">
        <div class="modal-header">
            <h3 id="reset-pwd-title">Reset Password</h3>
            <button class="modal-close" onclick="closeModal('resetPasswordModal')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="managers.php" id="reset-password-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="reset_mgr_id">
            <div class="modal-body">
                <p style="margin-bottom:16px;font-size:14px;">
                    Reset password for <strong id="reset_mgr_name"></strong>.
                </p>
                <div class="form-group" style="margin-bottom:14px;">
                    <label for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8" placeholder="Min. 8 characters">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8" placeholder="Repeat the password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('resetPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-warning" id="btn-reset-pwd">
                    <i class="fa-solid fa-key"></i> Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteModal" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="delete-mgr-title">
    <div class="modal-dialog modal-sm">
        <div class="modal-header">
            <h3 id="delete-mgr-title">Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:15px;">Delete manager <strong id="delete_mgr_name"></strong>?</p>
            <div class="alert alert-warning" style="margin-top:14px;margin-bottom:0;">
                <i class="fa-solid fa-circle-info"></i>
                If this manager has activity logs, order notes or history records, deletion will be blocked.
                Please <strong>deactivate</strong> instead.
            </div>
        </div>
        <div class="modal-footer">
            <form method="POST" action="managers.php" id="delete-manager-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_mgr_id">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger" id="btn-confirm-delete">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) closeModal(el.id); });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay').forEach(m => {
        if (m.style.display !== 'none') closeModal(m.id);
    });
});

function editManager(data) {
    document.getElementById('edit_mgr_id').value      = data.id;
    document.getElementById('edit_name').value         = data.name;
    document.getElementById('edit_email').value        = data.email;
    document.getElementById('edit_mgr_status').value  = data.status;
    openModal('editManagerModal');
}

function openResetPassword(id, name) {
    document.getElementById('reset_mgr_id').value  = id;
    document.getElementById('reset_mgr_name').textContent = name;
    document.getElementById('new_password').value      = '';
    document.getElementById('confirm_password').value  = '';
    openModal('resetPasswordModal');
}

function confirmDelete(id, name) {
    document.getElementById('delete_mgr_id').value         = id;
    document.getElementById('delete_mgr_name').textContent = name;
    openModal('deleteModal');
}

// Client-side password match hint
document.getElementById('reset-password-form')?.addEventListener('submit', function(e) {
    const pw  = document.getElementById('new_password').value;
    const cpw = document.getElementById('confirm_password').value;
    if (pw !== cpw) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
    }
});

setTimeout(() => {
    ['flash-success','flash-error'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }
    });
}, 5000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
