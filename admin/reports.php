<?php
/**
 * admin/reports.php
 * Live analytics and reporting:
 *  - Summary stat cards (orders, branches, managers)
 *  - Date-range filter
 *  - Orders by branch breakdown
 *  - Orders by status breakdown
 *  - CSV export link
 *  - Shopify analytics placeholder
 */
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole('super_admin', '../login.php');

$db = Database::getConnection();

/* ──────────────────────────────────────────────────────────
   DATE RANGE FILTER  (GET params)
────────────────────────────────────────────────────────── */
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');          // default: 1st of current month
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');            // default: today

// Sanitise: ensure valid dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');

// Include full day for dateTo
$dateToFull = $dateTo . ' 23:59:59';

/* ──────────────────────────────────────────────────────────
   SUMMARY STATS  (all-time totals, not date-filtered)
────────────────────────────────────────────────────────── */

// Orders
$totalOrders     = (int) $db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL")->fetchColumn();
$pendingOrders   = (int) $db->query("SELECT COUNT(*) FROM orders WHERE current_status = 'New' AND deleted_at IS NULL")->fetchColumn();
$deliveredOrders = (int) $db->query("SELECT COUNT(*) FROM orders WHERE current_status = 'Delivered' AND deleted_at IS NULL")->fetchColumn();
$cancelledOrders = (int) $db->query("SELECT COUNT(*) FROM orders WHERE current_status = 'Cancelled' AND deleted_at IS NULL")->fetchColumn();

// Branches
$totalBranches  = (int) $db->query("SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL")->fetchColumn();
$activeBranches = (int) $db->query("SELECT COUNT(*) FROM branches WHERE status='active' AND deleted_at IS NULL")->fetchColumn();

// Managers
$totalManagers  = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='branch_manager' AND deleted_at IS NULL")->fetchColumn();
$activeManagers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='branch_manager' AND status='active' AND deleted_at IS NULL")->fetchColumn();

// Revenue (date-filtered)
$revStmt = $db->prepare("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM orders
    WHERE deleted_at IS NULL
      AND created_at BETWEEN :from AND :to
");
$revStmt->execute([':from' => $dateFrom . ' 00:00:00', ':to' => $dateToFull]);
$periodRevenue = (float) $revStmt->fetchColumn();

/* ──────────────────────────────────────────────────────────
   ORDERS BY BRANCH (date-filtered)
────────────────────────────────────────────────────────── */
$byBranchStmt = $db->prepare("
    SELECT
        b.branch_name,
        b.branch_code,
        COUNT(o.id)                          AS total_orders,
        COALESCE(SUM(o.total_amount), 0)     AS revenue,
        SUM(o.current_status = 'Delivered')  AS delivered,
        SUM(o.current_status = 'Cancelled')  AS cancelled
    FROM branches b
    LEFT JOIN orders o
        ON o.assigned_branch_id = b.id
       AND o.deleted_at IS NULL
       AND o.created_at BETWEEN :from AND :to
    WHERE b.deleted_at IS NULL
    GROUP BY b.id
    ORDER BY total_orders DESC
");
$byBranchStmt->execute([':from' => $dateFrom . ' 00:00:00', ':to' => $dateToFull]);
$byBranch = $byBranchStmt->fetchAll();
$maxBranchOrders = !empty($byBranch) ? max(array_column($byBranch, 'total_orders')) : 1;

/* ──────────────────────────────────────────────────────────
   ORDERS BY STATUS (date-filtered)
────────────────────────────────────────────────────────── */
$byStatusStmt = $db->prepare("
    SELECT current_status AS status_label, COUNT(*) AS cnt
    FROM orders
    WHERE deleted_at IS NULL
      AND created_at BETWEEN :from AND :to
    GROUP BY current_status
    ORDER BY cnt DESC
");
$byStatusStmt->execute([':from' => $dateFrom . ' 00:00:00', ':to' => $dateToFull]);
$byStatus = $byStatusStmt->fetchAll();
$totalInPeriod = array_sum(array_column($byStatus, 'cnt'));

/* ──────────────────────────────────────────────────────────
   STATUS COLOUR MAP
────────────────────────────────────────────────────────── */
$statusColors = [
    'New'             => '#3b82f6',
    'Assigned'        => '#8b5cf6',
    'Accepted'        => '#6366f1',
    'Preparing'       => '#f59e0b',
    'Ready'           => '#10b981',
    'Out For Delivery'=> '#06b6d4',
    'Delivered'       => '#10b981',
    'Cancelled'       => '#ef4444',
    'Returned'        => '#f97316',
];

$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reports &amp; Analytics</h1>
        <p class="page-subtitle">Live data from your database. Use the date filter to narrow down period-specific metrics.</p>
    </div>
    <div class="page-actions">
        <a href="reports_export.php?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
           class="btn btn-outline" id="btn-export-csv">
            <i class="fa-solid fa-file-csv"></i> Export CSV
        </a>
    </div>
</div>

<!-- ───────────────────────────────
     DATE RANGE FILTER
──────────────────────────────── -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-body" style="padding:16px 24px;">
        <form method="GET" action="reports.php" class="search-form" id="date-filter-form">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <i class="fa-regular fa-calendar" style="color:var(--text-muted);"></i>
                <label style="font-size:13px;font-weight:600;color:var(--text-muted);">From</label>
                <input type="date" name="date_from" id="date_from" value="<?= e($dateFrom) ?>" class="form-control" style="width:160px;">
                <label style="font-size:13px;font-weight:600;color:var(--text-muted);">To</label>
                <input type="date" name="date_to" id="date_to" value="<?= e($dateTo) ?>" class="form-control" style="width:160px;">
                <button type="submit" class="btn btn-primary" id="btn-apply-filter">
                    <i class="fa-solid fa-filter"></i> Apply Filter
                </button>
                <a href="reports.php" class="btn btn-outline" id="btn-reset-filter">Reset</a>
            </div>
            <span style="font-size:12px;color:var(--text-muted);margin-left:auto;">
                Showing data for: <strong><?= date('d M Y', strtotime($dateFrom)) ?> &ndash; <?= date('d M Y', strtotime($dateTo)) ?></strong>
            </span>
        </form>
    </div>
</div>

<!-- ───────────────────────────────
     STAT CARDS ROW 1 — ORDERS
──────────────────────────────── -->
<div class="reports-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fa-solid fa-box-open"></i></div>
        <div class="stat-details">
            <h3><?= number_format($totalOrders) ?></h3>
            <p>Total Orders</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-details">
            <h3><?= number_format($pendingOrders) ?></h3>
            <p>New / Pending</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-details">
            <h3><?= number_format($deliveredOrders) ?></h3>
            <p>Delivered</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i class="fa-solid fa-ban"></i></div>
        <div class="stat-details">
            <h3><?= number_format($cancelledOrders) ?></h3>
            <p>Cancelled</p>
        </div>
    </div>
</div>

<!-- ───────────────────────────────
     STAT CARDS ROW 2 — BRANCHES & MANAGERS
──────────────────────────────── -->
<div class="reports-grid">
    <div class="stat-card">
        <div class="stat-icon info"><i class="fa-solid fa-store"></i></div>
        <div class="stat-details">
            <h3><?= $totalBranches ?></h3>
            <p>Total Branches</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fa-solid fa-store-slash"></i></div>
        <div class="stat-details">
            <h3><?= $activeBranches ?></h3>
            <p>Active Branches</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fa-solid fa-users"></i></div>
        <div class="stat-details">
            <h3><?= $totalManagers ?></h3>
            <p>Total Managers</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fa-solid fa-user-check"></i></div>
        <div class="stat-details">
            <h3><?= $activeManagers ?></h3>
            <p>Active Managers</p>
        </div>
    </div>
</div>
<div class="reports-grid" style="grid-template-columns:1fr;margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fa-solid fa-indian-rupee-sign"></i></div>
        <div class="stat-details">
            <h3><?= number_format($periodRevenue, 2) ?></h3>
            <p>Revenue (period)</p>
        </div>
    </div>
</div>

<!-- ───────────────────────────────
     BREAKDOWN TABLES
──────────────────────────────── -->
<div class="reports-sections">

    <!-- Orders by Branch -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-store" style="margin-right:8px;color:var(--primary);"></i>Orders by Branch</h2>
            <span style="font-size:12px;color:var(--text-muted);"><?= e($dateFrom) ?> &ndash; <?= e($dateTo) ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($byBranch)): ?>
            <p class="text-muted" style="text-align:center;padding:24px 0;">No data for this period.</p>
            <?php else: ?>
            <?php foreach ($byBranch as $row): ?>
            <div class="report-row">
                <div class="label">
                    <?= e($row['branch_name']) ?>
                    <small style="color:var(--text-muted);margin-left:4px;">(<?= e($row['branch_code']) ?>)</small>
                </div>
                <div class="progress-bar-wrap" style="max-width:120px;">
                    <div class="progress-bar" style="width:<?= $maxBranchOrders > 0 ? round(($row['total_orders'] / $maxBranchOrders) * 100) : 0 ?>%;"></div>
                </div>
                <div class="count"><?= $row['total_orders'] ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Orders by Status -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-chart-pie" style="margin-right:8px;color:var(--primary);"></i>Orders by Status</h2>
            <span style="font-size:12px;color:var(--text-muted);"><?= $totalInPeriod ?> total</span>
        </div>
        <div class="card-body">
            <?php if (empty($byStatus)): ?>
            <p class="text-muted" style="text-align:center;padding:24px 0;">No data for this period.</p>
            <?php else: ?>
            <?php foreach ($byStatus as $row): ?>
            <?php
                $pct = $totalInPeriod > 0 ? round(($row['cnt'] / $totalInPeriod) * 100, 1) : 0;
                $color = $statusColors[$row['status_label']] ?? '#94a3b8';
            ?>
            <div class="report-row">
                <div class="label">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $color ?>;margin-right:8px;vertical-align:middle;"></span>
                    <?= e($row['status_label']) ?>
                </div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div>
                </div>
                <div class="count"><?= $row['cnt'] ?></div>
                <div class="pct"><?= $pct ?>%</div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ───────────────────────────────
     BRANCH DETAIL TABLE
──────────────────────────────── -->
<div class="card" style="margin-top:24px;">
    <div class="card-header">
        <h2 class="card-title">Branch Performance Detail</h2>
        <a href="reports_export.php?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
           class="btn btn-sm btn-outline" id="btn-export-detail">
            <i class="fa-solid fa-download"></i> Export CSV
        </a>
    </div>
    <div class="table-responsive">
        <div class="table-responsive">
            <table class="table" id="branch-detail-table">
            <thead>
                <tr>
                    <th>Branch</th>
                    <th>Code</th>
                    <th>Total Orders</th>
                    <th>Delivered</th>
                    <th>Cancelled</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($byBranch)): ?>
                <tr><td colspan="6" class="empty-state">No branch data for this period.</td></tr>
            <?php else: ?>
                <?php foreach ($byBranch as $row): ?>
                <tr>
                    <td><strong><?= e($row['branch_name']) ?></strong></td>
                    <td><code style="background:var(--bg-main);padding:2px 6px;border-radius:4px;font-size:12px;"><?= e($row['branch_code']) ?></code></td>
                    <td><?= number_format($row['total_orders']) ?></td>
                    <td><span style="color:var(--success);font-weight:600;"><?= $row['delivered'] ?></span></td>
                    <td><span style="color:var(--danger);font-weight:600;"><?= $row['cancelled'] ?></span></td>
                    <td><?= number_format($row['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ───────────────────────────────
     SHOPIFY ANALYTICS PLACEHOLDER
──────────────────────────────── -->
<div class="card" style="margin-top:24px;">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-brands fa-shopify" style="margin-right:8px;color:#96bf48;"></i>
            Shopify Analytics
        </h2>
        <span class="status inactive">Coming Soon</span>
    </div>
    <div class="card-body" style="text-align:center;padding:48px 24px;">
        <div style="width:72px;height:72px;background:var(--bg-main);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;border:2px dashed var(--border);">
            <i class="fa-brands fa-shopify" style="font-size:32px;color:#96bf48;opacity:0.5;"></i>
        </div>
        <h3 style="font-size:16px;font-weight:600;margin-bottom:8px;">Shopify Integration Pending</h3>
        <p class="text-muted" style="font-size:14px;max-width:420px;margin:0 auto;">
            Once Shopify integration is configured in <strong>Settings</strong>, live store analytics including
            revenue trends, product performance, and sync status will appear here.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
