<?php
/**
 * admin/branches.php
 * Full CRUD for Branches:
 *  - List with search + pagination
 *  - Add / Edit via modal (POST-Redirect-GET)
 *  - Toggle Active / Inactive
 *  - Soft-delete with referential-integrity guard
 *  - Assign branch manager from dropdown
 *  - Activity logging on every mutation
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();

/* ──────────────────────────────────────────────────────────
   POST HANDLER  (all mutations land here, then redirect)
────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Security token mismatch. Please try again.');
        header('Location: branches.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    /* ── ADD / EDIT ── */
    if ($action === 'add' || $action === 'edit') {
        $id          = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $branchName  = trim($_POST['branch_name'] ?? '');
        $branchCode  = strtoupper(trim($_POST['branch_code'] ?? ''));
        $shopifyLocId = !empty($_POST['shopify_location_id']) ? (int)$_POST['shopify_location_id'] : null;
        $address     = trim($_POST['address'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $status      = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $managerId   = !empty($_POST['branch_manager_id']) ? (int) $_POST['branch_manager_id'] : null;

        if ($branchName === '' || $branchCode === '') {
            flash('error', 'Branch name and code are required.');
            header('Location: branches.php');
            exit;
        }

        if ($action === 'add') {
            try {
                $stmt = $db->prepare("
                    INSERT INTO branches
                        (branch_name, branch_code, shopify_location_id, address, phone, email, branch_manager_id, status)
                    VALUES
                        (:name, :code, :shopify_loc, :addr, :phone, :email, :mgr, :status)
                ");
                $stmt->execute([
                    ':name'   => $branchName,
                    ':code'   => $branchCode,
                    ':shopify_loc' => $shopifyLocId,
                    ':addr'   => $address ?: null,
                    ':phone'  => $phone   ?: null,
                    ':email'  => $email   ?: null,
                    ':mgr'    => $managerId,
                    ':status' => $status,
                ]);
                $newId = (int) $db->lastInsertId();
                
                // Sync users.branch_id
                if ($managerId) {
                    $db->prepare("UPDATE users SET branch_id = :id WHERE id = :mgr")->execute([':id' => $newId, ':mgr' => $managerId]);
                }
                
                ActivityLogger::log(
                    (int) $_SESSION['user_id'],
                    'branch_created',
                    "Branch '{$branchName}' ({$branchCode}) was created.",
                    $newId
                );
                flash('success', "Branch '{$branchName}' created successfully.");
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    flash('error', "Branch code '{$branchCode}' already exists. Please use a unique code.");
                } else {
                    flash('error', 'An error occurred. Please try again.');
                }
            }
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE branches
                    SET branch_name = :name,
                        branch_code = :code,
                        shopify_location_id = :shopify_loc,
                        address     = :addr,
                        phone       = :phone,
                        email       = :email,
                        branch_manager_id = :mgr,
                        status      = :status
                    WHERE id = :id AND deleted_at IS NULL
                ");
                $stmt->execute([
                    ':name'   => $branchName,
                    ':code'   => $branchCode,
                    ':shopify_loc' => $shopifyLocId,
                    ':addr'   => $address ?: null,
                    ':phone'  => $phone   ?: null,
                    ':email'  => $email   ?: null,
                    ':mgr'    => $managerId,
                    ':status' => $status,
                    ':id'     => $id,
                ]);
                
                // Sync users.branch_id: Clear old manager for this branch, set new manager
                $db->prepare("UPDATE users SET branch_id = NULL WHERE branch_id = :id")->execute([':id' => $id]);
                if ($managerId) {
                    $db->prepare("UPDATE users SET branch_id = :id WHERE id = :mgr")->execute([':id' => $id, ':mgr' => $managerId]);
                }
                
                ActivityLogger::log(
                    (int) $_SESSION['user_id'],
                    'branch_updated',
                    "Branch '{$branchName}' (ID {$id}) was updated.",
                    $id
                );
                flash('success', "Branch '{$branchName}' updated successfully.");
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    flash('error', "Branch code '{$branchCode}' already exists. Please use a unique code.");
                } else {
                    flash('error', 'An error occurred. Please try again.');
                }
            }
        }

    /* ── TOGGLE STATUS ── */
    } elseif ($action === 'toggle_status') {
        $id        = (int) ($_POST['id'] ?? 0);
        $newStatus = ($_POST['new_status'] ?? 'active') === 'active' ? 'active' : 'inactive';

        $db->prepare("UPDATE branches SET status = :status WHERE id = :id AND deleted_at IS NULL")
           ->execute([':status' => $newStatus, ':id' => $id]);

        $logAction  = $newStatus === 'active' ? 'branch_activated' : 'branch_deactivated';
        $logDesc    = "Branch ID {$id} was set to '{$newStatus}'.";
        ActivityLogger::log((int) $_SESSION['user_id'], $logAction, $logDesc, $id);
        flash('success', "Branch status changed to " . ucfirst($newStatus) . ".");

    /* ── DELETE (soft, with integrity guard) ── */
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        // 1. Managers assigned to this branch
        $mgrCount = (int) $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id = :id AND deleted_at IS NULL")
            ->execute([':id' => $id]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
        $mgrStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id = :id AND deleted_at IS NULL");
        $mgrStmt->execute([':id' => $id]);
        $mgrCount = (int) $mgrStmt->fetchColumn();

        // 2. Orders assigned to this branch
        $ordStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE assigned_branch_id = :id AND deleted_at IS NULL");
        $ordStmt->execute([':id' => $id]);
        $ordCount = (int) $ordStmt->fetchColumn();

        // 3. Historical activity logs referencing this branch
        $logStmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE branch_id = :id");
        $logStmt->execute([':id' => $id]);
        $logCount = (int) $logStmt->fetchColumn();

        $reasons = [];
        if ($mgrCount > 0) $reasons[] = "{$mgrCount} assigned manager(s)";
        if ($ordCount > 0) $reasons[] = "{$ordCount} linked order(s)";
        if ($logCount > 0) $reasons[] = "historical activity records";

        if (!empty($reasons)) {
            flash('error',
                'This branch cannot be deleted because it is referenced by: '
                . implode(', ', $reasons)
                . '. Please <strong>deactivate</strong> the branch instead to preserve historical data integrity.'
            );
        } else {
            $db->prepare("UPDATE branches SET deleted_at = NOW() WHERE id = :id")
               ->execute([':id' => $id]);
            ActivityLogger::log(
                (int) $_SESSION['user_id'],
                'branch_deleted',
                "Branch ID {$id} was permanently removed."
            );
            flash('success', 'Branch deleted successfully.');
        }
    }

    header('Location: branches.php');
    exit;
}

/* ──────────────────────────────────────────────────────────
   GET HANDLER  (list + search + pagination)
────────────────────────────────────────────────────────── */
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = 'b.deleted_at IS NULL';
$params = [];
if ($search !== '') {
    $where          .= ' AND (b.branch_name LIKE :q OR b.branch_code LIKE :q OR b.email LIKE :q)';
    $params[':q']   = '%' . $search . '%';
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM branches b WHERE {$where}");
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);

$listStmt = $db->prepare("
    SELECT b.*, u.name AS manager_name
    FROM   branches b
    LEFT JOIN users u ON b.branch_manager_id = u.id AND u.deleted_at IS NULL
    WHERE  {$where}
    ORDER  BY b.created_at DESC
    LIMIT  {$perPage} OFFSET {$offset}
");
$listStmt->execute($params);
$branches = $listStmt->fetchAll();

// Active managers for dropdown (include currently assigned one even if inactive)
$managers = $db->query("
    SELECT id, name, status FROM users
    WHERE role = 'branch_manager' AND deleted_at IS NULL
    ORDER BY name
")->fetchAll();

// Summary counts for page sub-title
$totalBranches  = (int) $db->query("SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL")->fetchColumn();
$activeBranches = (int) $db->query("SELECT COUNT(*) FROM branches WHERE status='active' AND deleted_at IS NULL")->fetchColumn();

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
$pageTitle    = 'Branches';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Branches</h1>
        <p class="page-subtitle">
            <?= $totalBranches ?> branch(es) total &mdash; <?= $activeBranches ?> active.
            Manage locations, managers and status.
        </p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addBranchModal')" id="btn-add-branch">
            <i class="fa-solid fa-plus"></i> Add Branch
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

<!-- Search Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 14px 24px;">
        <form method="GET" action="branches.php" class="search-form">
            <div class="input-group">
                <i class="fa-solid fa-search"></i>
                <input type="text"
                       name="q"
                       id="search-input"
                       value="<?= e($search) ?>"
                       placeholder="Search by name, code or email…"
                       class="form-control">
            </div>
            <button type="submit" class="btn btn-primary" id="btn-search">Search</button>
            <?php if ($search): ?>
            <a href="branches.php" class="btn btn-outline" id="btn-clear">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Branches Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">All Branches</h2>
        <span class="text-muted" style="font-size:13px;"><?= $totalRows ?> result(s)</span>
    </div>
    <div class="table-responsive">
        <div class="table-responsive">
            <table class="table" id="branches-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Branch Name</th>
                    <th>Code / Shopify Loc</th>
                    <th>Manager</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($branches)): ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fa-solid fa-store"></i>
                        No branches found<?= $search ? " for <strong>" . e($search) . "</strong>" : '' ?>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($branches as $branch): ?>
                <tr>
                    <td><?= $branch['id'] ?></td>
                    <td>
                        <strong><?= e($branch['branch_name']) ?></strong>
                        <?php if ($branch['address']): ?>
                        <br><small class="text-muted"><?= e($branch['address']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code style="background:var(--bg-main);padding:2px 8px;border-radius:4px;font-size:12px;"><?= e($branch['branch_code']) ?></code>
                        <?php if ($branch['shopify_location_id']): ?>
                        <br><small class="text-muted" style="font-size: 11px;">Shopify: <?= e($branch['shopify_location_id']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($branch['manager_name']): ?>
                            <i class="fa-solid fa-user" style="color:var(--text-muted);font-size:11px;margin-right:4px;"></i>
                            <?= e($branch['manager_name']) ?>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:12px;">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $branch['phone'] ? e($branch['phone']) : '<span class="text-muted">—</span>' ?>
                        <?php if ($branch['email']): ?>
                        <br><small class="text-muted"><?= e($branch['email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status <?= e($branch['status']) ?>"><?= ucfirst($branch['status']) ?></span>
                    </td>
                    <td><small class="text-muted"><?= date('d M Y', strtotime($branch['created_at'])) ?></small></td>
                    <td>
                        <div class="action-btns">
                            <!-- Edit -->
                            <button class="btn btn-sm btn-outline"
                                    onclick="editBranch(<?= htmlspecialchars(json_encode($branch), ENT_QUOTES) ?>)"
                                    title="Edit Branch"
                                    id="edit-branch-<?= $branch['id'] ?>">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>

                            <!-- Toggle Status -->
                            <form method="POST" action="branches.php" style="display:inline;" id="toggle-form-<?= $branch['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $branch['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $branch['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit"
                                        class="btn btn-sm <?= $branch['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>"
                                        title="<?= $branch['status'] === 'active' ? 'Deactivate' : 'Activate' ?>"
                                        id="toggle-btn-<?= $branch['id'] ?>">
                                    <i class="fa-solid fa-<?= $branch['status'] === 'active' ? 'toggle-off' : 'toggle-on' ?>"></i>
                                </button>
                            </form>

                            <!-- Delete -->
                            <button class="btn btn-sm btn-danger"
                                    onclick="confirmDelete(<?= $branch['id'] ?>, '<?= addslashes(e($branch['branch_name'])) ?>')"
                                    title="Delete Branch"
                                    id="delete-branch-<?= $branch['id'] ?>">
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

<!-- Add Branch Modal -->
<div id="addBranchModal" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="add-modal-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="add-modal-title">Add New Branch</h3>
            <button class="modal-close" onclick="closeModal('addBranchModal')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="branches.php" id="add-branch-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="add_branch_name">Branch Name <span class="required">*</span></label>
                        <input type="text" name="branch_name" id="add_branch_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="add_branch_code">Branch Code <span class="required">*</span></label>
                        <input type="text" name="branch_code" id="add_branch_code" class="form-control" required placeholder="e.g. MAIN01" style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label for="add_shopify_location_id">Shopify Location ID</label>
                        <input type="number" name="shopify_location_id" id="add_shopify_location_id" class="form-control" placeholder="e.g. 123456789">
                    </div>
                    <div class="form-group full-width">
                        <label for="add_address">Address</label>
                        <textarea name="address" id="add_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="add_phone">Phone</label>
                        <input type="text" name="phone" id="add_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="add_email">Email</label>
                        <input type="email" name="email" id="add_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="add_manager_id">Assign Manager</label>
                        <select name="branch_manager_id" id="add_manager_id" class="form-control">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['name']) ?> <?= $m['status'] === 'inactive' ? '(Inactive)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
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
                <button type="button" class="btn btn-outline" onclick="closeModal('addBranchModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-create-branch">
                    <i class="fa-solid fa-plus"></i> Create Branch
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Branch Modal -->
<div id="editBranchModal" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="edit-modal-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="edit-modal-title">Edit Branch</h3>
            <button class="modal-close" onclick="closeModal('editBranchModal')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="branches.php" id="edit-branch-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_branch_name">Branch Name <span class="required">*</span></label>
                        <input type="text" name="branch_name" id="edit_branch_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_branch_code">Branch Code <span class="required">*</span></label>
                        <input type="text" name="branch_code" id="edit_branch_code" class="form-control" required style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label for="edit_shopify_location_id">Shopify Location ID</label>
                        <input type="number" name="shopify_location_id" id="edit_shopify_location_id" class="form-control">
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_address">Address</label>
                        <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_manager_id">Assign Manager</label>
                        <select name="branch_manager_id" id="edit_manager_id" class="form-control">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['name']) ?> <?= $m['status'] === 'inactive' ? '(Inactive)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editBranchModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-branch">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteModal" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
    <div class="modal-dialog modal-sm">
        <div class="modal-header">
            <h3 id="delete-modal-title">Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:15px;">Are you sure you want to delete <strong id="delete_name_display"></strong>?</p>
            <div class="alert alert-warning" style="margin-top:14px;margin-bottom:0;">
                <i class="fa-solid fa-circle-info"></i>
                If this branch has linked managers, orders, or activity history, deletion will be blocked and you will be asked to <strong>deactivate</strong> it instead.
            </div>
        </div>
        <div class="modal-footer">
            <form method="POST" action="branches.php" id="delete-branch-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id_input">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger" id="btn-confirm-delete">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
/* Modal helpers */
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = '';
}

/* Close on overlay click */
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

/* Close on Escape */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            if (m.style.display !== 'none') closeModal(m.id);
        });
    }
});

/* Populate edit modal */
function editBranch(data) {
    document.getElementById('edit_id').value           = data.id;
    document.getElementById('edit_branch_name').value  = data.branch_name;
    document.getElementById('edit_branch_code').value  = data.branch_code;
    document.getElementById('edit_shopify_location_id').value = data.shopify_location_id || '';
    document.getElementById('edit_address').value      = data.address || '';
    document.getElementById('edit_phone').value        = data.phone   || '';
    document.getElementById('edit_email').value        = data.email   || '';
    document.getElementById('edit_status').value       = data.status;
    document.getElementById('edit_manager_id').value   = data.branch_manager_id || '';
    openModal('editBranchModal');
}

/* Populate delete confirm modal */
function confirmDelete(id, name) {
    document.getElementById('delete_id_input').value        = id;
    document.getElementById('delete_name_display').textContent = name;
    openModal('deleteModal');
}

/* Auto-uppercase branch code inputs */
['add_branch_code','edit_branch_code'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => el.value = el.value.toUpperCase());
});

/* Auto-dismiss flash messages after 5s */
setTimeout(() => {
    ['flash-success','flash-error'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.transition = 'opacity 0.5s', el.style.opacity = '0',
            setTimeout(() => el.remove(), 500);
    });
}, 5000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
