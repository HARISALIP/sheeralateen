<?php
/**
 * admin/activity.php
 * ---------------------------------------------------------
 * Full-featured System Activity Log Viewer.
 * Track user logins, order dispatches, status changes,
 * inventory adjustments, branch assignments, and API events.
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();

// --- FILTERS & SEARCH ---
$search = trim($_GET['search'] ?? '');
$filterAction = trim($_GET['action_type'] ?? '');
$filterUser = trim($_GET['user_id'] ?? '');
$filterDate = trim($_GET['date_range'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(al.description LIKE :s1 OR al.action LIKE :s2 OR u.name LIKE :s3 OR o.order_number LIKE :s4 OR al.ip_address LIKE :s5)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
    $params[':s5'] = "%$search%";
}

if ($filterAction !== '') {
    if ($filterAction === 'orders') {
        $where[] = "(al.action LIKE 'order_%' OR al.action LIKE 'payment_%')";
    } elseif ($filterAction === 'leajlak') {
        $where[] = "al.action LIKE 'leajlak_%'";
    } elseif ($filterAction === 'inventory') {
        $where[] = "(al.action LIKE 'inventory_%' OR al.action LIKE 'stock_%')";
    } elseif ($filterAction === 'auth') {
        $where[] = "al.action IN ('login', 'logout', 'password_changed', 'login_failed')";
    } elseif ($filterAction === 'errors') {
        $where[] = "(al.action LIKE '%failed%' OR al.action LIKE '%error%' OR al.description LIKE '%error%' OR al.description LIKE '%failed%')";
    } else {
        $where[] = "al.action = :act";
        $params[':act'] = $filterAction;
    }
}

if ($filterUser !== '') {
    if ($filterUser === 'system') {
        $where[] = "al.user_id IS NULL";
    } else {
        $where[] = "al.user_id = :uid";
        $params[':uid'] = (int)$filterUser;
    }
}

if ($filterDate !== '') {
    if ($filterDate === 'today') {
        $where[] = "DATE(al.created_at) = CURDATE()";
    } elseif ($filterDate === 'yesterday') {
        $where[] = "DATE(al.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($filterDate === 'week') {
        $where[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($filterDate === 'month') {
        $where[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
}

$whereClause = implode(' AND ', $where);

// --- TOTAL COUNT ---
$countSql = "
    SELECT COUNT(*) 
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN orders o ON al.order_id = o.id
    WHERE {$whereClause}
";
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

// --- FETCH LOGS ---
$sql = "
    SELECT 
        al.id, al.action, al.description, al.ip_address, al.user_agent, al.created_at,
        al.order_id, o.order_number,
        al.branch_id, b.branch_name,
        al.user_id, u.name AS user_name, u.role AS user_role, u.email AS user_email
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN branches b ON al.branch_id = b.id
    LEFT JOIN orders o ON al.order_id = o.id
    WHERE {$whereClause}
    ORDER BY al.id DESC
    LIMIT :limit OFFSET :offset
";

$stmtLogs = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmtLogs->bindValue($k, $v);
}
$stmtLogs->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtLogs->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtLogs->execute();
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// --- STATS ---
$totalEventsToday = (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalErrors = (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action LIKE '%failed%' OR action LIKE '%error%'")->fetchColumn();
$totalLogins = (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login'")->fetchColumn();
$totalAllEvents = (int)$db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

// --- USERS FOR FILTER DROPDOWN ---
$filterUsersList = $db->query("SELECT id, name, role FROM users WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Helper for action badges
function renderActionBadge(string $action): string {
    $actionLower = strtolower($action);
    $icon = 'fa-circle-info';
    $badgeClass = 'badge-info';
    $label = ucwords(str_replace('_', ' ', $action));

    if (str_contains($actionLower, 'failed') || str_contains($actionLower, 'error')) {
        $icon = 'fa-triangle-exclamation';
        $badgeClass = 'badge-danger';
    } elseif (str_contains($actionLower, 'cancel')) {
        $icon = 'fa-ban';
        $badgeClass = 'badge-warning';
    } elseif (str_contains($actionLower, 'login')) {
        $icon = 'fa-arrow-right-to-bracket';
        $badgeClass = 'badge-primary';
    } elseif (str_contains($actionLower, 'created') || str_contains($actionLower, 'paid') || str_contains($actionLower, 'delivered')) {
        $icon = 'fa-check';
        $badgeClass = 'badge-success';
    } elseif (str_contains($actionLower, 'inventory') || str_contains($actionLower, 'stock')) {
        $icon = 'fa-boxes-stacked';
        $badgeClass = 'badge-amber';
    } elseif (str_contains($actionLower, 'order')) {
        $icon = 'fa-box';
        $badgeClass = 'badge-info';
    } elseif (str_contains($actionLower, 'branch')) {
        $icon = 'fa-store';
        $badgeClass = 'badge-purple';
    }

    return "<span class=\"activity-pill {$badgeClass}\"><i class=\"fa-solid {$icon}\"></i> " . htmlspecialchars($label) . "</span>";
}

$pageTitle = 'Activity Log';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<style>
/* Activity Log Specific Styling */
.activity-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 9px;
    border-radius: 9999px;
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1.3;
    white-space: nowrap;
}
.activity-pill.badge-primary { background: #e0e7ff; color: #4338ca; }
.activity-pill.badge-success { background: #dcfce7; color: #15803d; }
.activity-pill.badge-warning { background: #fef3c7; color: #b45309; }
.activity-pill.badge-danger  { background: #fee2e2; color: #b91c1c; }
.activity-pill.badge-info    { background: #e0f2fe; color: #0369a1; }
.activity-pill.badge-amber   { background: #ffedd5; color: #c2410c; }
.activity-pill.badge-purple  { background: #f3e8ff; color: #7e22ce; }

.user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-avatar-mini {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    border: 1px solid #e2e8f0;
}
.user-meta-name {
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--text-color);
}
.user-meta-role {
    font-size: 0.72rem;
    color: var(--text-muted);
}
.log-order-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 2px 7px;
    border-radius: 5px;
    font-size: 0.75rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
}
.log-order-tag:hover {
    background: var(--primary-light);
    color: var(--primary);
    border-color: var(--primary);
}
.log-desc-text {
    font-size: 0.86rem;
    line-height: 1.45;
    color: #1e293b;
    word-break: break-word;
}
.log-ip-badge {
    font-family: monospace;
    font-size: 0.74rem;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 2px 6px;
    border-radius: 4px;
}
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary); margin-right: 8px;"></i>Activity Log</h1>
        <p class="page-subtitle">Track real-time system events, staff actions, status updates, and delivery integration logs.</p>
    </div>
    <div>
        <a href="dashboard.php" class="btn btn-outline"><i class="fa-solid fa-house" style="margin-right: 5px;"></i>Dashboard</a>
    </div>
</div>

<!-- STAT CARDS ROW -->
<div class="dashboard-grid dashboard-grid-4" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#e0e7ff; color:#4338ca;">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($totalAllEvents) ?></h3>
            <p>Total Events Logged</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7; color:#15803d;">
            <i class="fa-solid fa-calendar-day"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($totalEventsToday) ?></h3>
            <p>Events Today</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#e0f2fe; color:#0369a1;">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($totalLogins) ?></h3>
            <p>Staff Logins</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2; color:#b91c1c;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($totalErrors) ?></h3>
            <p>Warnings & Errors</p>
        </div>
    </div>
</div>

<!-- FILTER CARD -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="activity.php" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            
            <!-- Search -->
            <div style="flex: 1 1 220px;">
                <label style="display: block; font-size: 0.78rem; font-weight: 600; margin-bottom: 4px; color: var(--text-muted);">SEARCH LOGS</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 10px; top: 10px; color: #94a3b8; font-size: 0.85rem;"></i>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Keywords, order #, user, IP..." 
                           class="form-control" style="padding-left: 32px; width: 100%; height: 38px;">
                </div>
            </div>

            <!-- Action Filter -->
            <div style="flex: 0 1 180px;">
                <label style="display: block; font-size: 0.78rem; font-weight: 600; margin-bottom: 4px; color: var(--text-muted);">EVENT TYPE</label>
                <select name="action_type" class="form-control" style="width: 100%; height: 38px;">
                    <option value="">All Event Types</option>
                    <option value="orders" <?= $filterAction === 'orders' ? 'selected' : '' ?>>Order & Payment Updates</option>
                    <option value="leajlak" <?= $filterAction === 'leajlak' ? 'selected' : '' ?>>Leajlak Delivery Events</option>
                    <option value="inventory" <?= $filterAction === 'inventory' ? 'selected' : '' ?>>Inventory Adjustments</option>
                    <option value="auth" <?= $filterAction === 'auth' ? 'selected' : '' ?>>Logins & Security</option>
                    <option value="errors" <?= $filterAction === 'errors' ? 'selected' : '' ?>>⚠️ Errors & Warnings Only</option>
                </select>
            </div>

            <!-- User Filter -->
            <div style="flex: 0 1 180px;">
                <label style="display: block; font-size: 0.78rem; font-weight: 600; margin-bottom: 4px; color: var(--text-muted);">PERFORMED BY</label>
                <select name="user_id" class="form-control" style="width: 100%; height: 38px;">
                    <option value="">All Users & System</option>
                    <option value="system" <?= $filterUser === 'system' ? 'selected' : '' ?>>🤖 System & Webhooks</option>
                    <?php foreach ($filterUsersList as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filterUser === (string)$u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?> (<?= $u['role'] === 'super_admin' ? 'Admin' : 'Manager' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Range Filter -->
            <div style="flex: 0 1 150px;">
                <label style="display: block; font-size: 0.78rem; font-weight: 600; margin-bottom: 4px; color: var(--text-muted);">DATE RANGE</label>
                <select name="date_range" class="form-control" style="width: 100%; height: 38px;">
                    <option value="">All Time</option>
                    <option value="today" <?= $filterDate === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $filterDate === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="week" <?= $filterDate === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="month" <?= $filterDate === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                </select>
            </div>

            <!-- Buttons -->
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 16px;">
                    <i class="fa-solid fa-filter" style="margin-right: 5px;"></i>Filter
                </button>
                <?php if ($search !== '' || $filterAction !== '' || $filterUser !== '' || $filterDate !== ''): ?>
                    <a href="activity.php" class="btn btn-outline" style="height: 38px; display: inline-flex; align-items: center;" title="Clear Filters">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </div>

        </form>
    </div>
</div>

<!-- LOGS TABLE CARD -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="card-title"><i class="fa-solid fa-list-check" style="margin-right: 8px;"></i>System Activity Records (<?= number_format($totalRows) ?>)</h2>
        <span class="text-muted" style="font-size: 0.82rem;">Page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    
    <div class="table-responsive">
        <table class="table" style="vertical-align: middle;">
            <thead>
                <tr>
                    <th style="width: 135px;">Timestamp</th>
                    <th style="width: 190px;">Initiator</th>
                    <th style="width: 170px;">Event Action</th>
                    <th>Activity Description</th>
                    <th style="width: 140px;">Related Order</th>
                    <th style="width: 110px;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="empty-state" style="padding: 40px; text-align: center;">
                            <i class="fa-solid fa-folder-open" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                            <strong style="color: var(--text-color); font-size: 1rem;">No activity log records found</strong>
                            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 4px 0 0;">Try adjusting your search criteria or date filter.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $timeStr = strtotime($log['created_at']);
                        $isToday = date('Y-m-d', $timeStr) === date('Y-m-d');
                        $userInitials = $log['user_name'] ? mb_strtoupper(mb_substr($log['user_name'], 0, 1)) : '🤖';
                    ?>
                    <tr>
                        <!-- Timestamp -->
                        <td style="white-space: nowrap;">
                            <div style="font-weight: 600; font-size: 0.84rem; color: #0f172a;">
                                <?= date('h:i A', $timeStr) ?>
                            </div>
                            <div style="font-size: 0.74rem; color: var(--text-muted);">
                                <?= $isToday ? '<span style="color:#10b981; font-weight:600;">Today</span>' : date('M d, Y', $timeStr) ?>
                            </div>
                        </td>

                        <!-- Initiator -->
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-mini" <?= empty($log['user_name']) ? 'style="background:#f1f5f9; font-size:1rem;"' : '' ?>>
                                    <?= $userInitials ?>
                                </div>
                                <div>
                                    <div class="user-meta-name">
                                        <?= $log['user_name'] ? e($log['user_name']) : '<span style="color:#64748b; font-style:italic;">System / Webhook</span>' ?>
                                    </div>
                                    <div class="user-meta-role">
                                        <?php if ($log['user_role'] === 'super_admin'): ?>
                                            <span style="color:#6366f1; font-weight:500;">Super Admin</span>
                                        <?php elseif ($log['user_role'] === 'branch_manager'): ?>
                                            <span style="color:#0284c7; font-weight:500;">Branch Manager</span>
                                        <?php else: ?>
                                            <span>Automated Task</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Event Action Badge -->
                        <td>
                            <?= renderActionBadge($log['action']) ?>
                        </td>

                        <!-- Description -->
                        <td>
                            <div class="log-desc-text">
                                <?php
                                    $desc = e($log['description']);
                                    // Make order numbers clickable in description if order_id is known
                                    if (!empty($log['order_id']) && !empty($log['order_number'])) {
                                        $onum = e($log['order_number']);
                                        $cleanNum = str_replace('#', '', $onum);
                                        $linkTag = "<a href=\"order_details.php?id={$log['order_id']}\" style=\"color:var(--primary); font-weight:600; text-decoration:none;\">{$onum}</a>";
                                        $desc = str_replace(["Order {$onum}", "order {$onum}", "Order #{$cleanNum}", "order #{$cleanNum}"], "Order {$linkTag}", $desc);
                                    }
                                    echo $desc;
                                ?>
                            </div>
                            <?php if (!empty($log['branch_name'])): ?>
                                <div style="margin-top: 3px; font-size: 0.75rem; color: #64748b;">
                                    <i class="fa-solid fa-store" style="margin-right: 4px; opacity: 0.7;"></i><?= e($log['branch_name']) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Related Order -->
                        <td>
                            <?php if (!empty($log['order_id'])): ?>
                                <a href="order_details.php?id=<?= (int)$log['order_id'] ?>" class="log-order-tag" title="View Order Details">
                                    <i class="fa-solid fa-file-invoice" style="font-size: 0.7rem;"></i>
                                    <?= e($log['order_number'] ?: ('#' . $log['order_id'])) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 0.8rem;">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- IP Address -->
                        <td>
                            <?php if (!empty($log['ip_address'])): ?>
                                <span class="log-ip-badge" title="IP Address"><?= e($log['ip_address']) ?></span>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 0.8rem;">Local</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer" style="display:flex; justify-content:center; align-items:center; padding: 14px 20px;">
        <div style="display:flex; gap: 4px;">
            <?php 
                $queryParams = $_GET;
                unset($queryParams['page']);
                $buildPageUrl = function($p) use ($queryParams) {
                    $queryParams['page'] = $p;
                    return '?' . http_build_query($queryParams);
                };
            ?>
            <?php if ($page > 1): ?>
                <a href="<?= $buildPageUrl($page - 1) ?>" class="btn btn-sm btn-outline">&laquo; Prev</a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);

            if ($startPage > 1) {
                echo '<a href="' . $buildPageUrl(1) . '" class="btn btn-sm btn-outline">1</a>';
                if ($startPage > 2) {
                    echo '<span style="padding: 4px 8px; color: var(--text-muted);">...</span>';
                }
            }

            for ($i = $startPage; $i <= $endPage; $i++) {
                $activeClass = $i === $page ? 'btn-primary' : 'btn-outline';
                echo '<a href="' . $buildPageUrl($i) . '" class="btn btn-sm ' . $activeClass . '">' . $i . '</a>';
            }

            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    echo '<span style="padding: 4px 8px; color: var(--text-muted);">...</span>';
                }
                echo '<a href="' . $buildPageUrl($totalPages) . '" class="btn btn-sm btn-outline">' . $totalPages . '</a>';
            }
            ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= $buildPageUrl($page + 1) ?>" class="btn btn-sm btn-outline">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
