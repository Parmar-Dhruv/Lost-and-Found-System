<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();

// ─── WHITELIST: valid action filter values ───────────────────────────────────
$validActions = [
    'register', 'login', 'logout',
    'report_item', 'edit_item', 'delete_item', 'restore_item',
    'submit_claim', 'approve_claim', 'reject_claim'
];

// ─── FILTERS from GET ────────────────────────────────────────────────────────
$filterAction = $_GET['action']   ?? '';
$filterFrom   = $_GET['date_from'] ?? '';
$filterTo     = $_GET['date_to']   ?? '';

// Sanitise action filter against whitelist
if ($filterAction !== '' && !in_array($filterAction, $validActions, true)) {
    $filterAction = '';
}

// ─── PAGINATION ──────────────────────────────────────────────────────────────
$perPage     = 20;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// ─── BUILD WHERE CLAUSES ─────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($filterAction !== '') {
    $where[]  = 'al.action = ?';
    $params[] = $filterAction;
}
if ($filterFrom !== '') {
    $where[]  = 'DATE(al.created_at) >= ?';
    $params[] = $filterFrom;
}
if ($filterTo !== '') {
    $where[]  = 'DATE(al.created_at) <= ?';
    $params[] = $filterTo;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ─── TOTAL COUNT (for pagination maths) ──────────────────────────────────────
$countStmt = $db->prepare("
    SELECT COUNT(*) FROM activity_log al
    $whereSQL
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Clamp current page
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset      = ($currentPage - 1) * $perPage;
}

// ─── FETCH LOGS ───────────────────────────────────────────────────────────────
// LEFT JOIN users so that if user_id is NULL (deleted user) we still get the row
// Build and prepare the final query
$logSQL = "
    SELECT
        al.log_id,
        al.action,
        al.entity,
        al.entity_id,
        al.created_at,
        u.full_name,
        u.email
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.user_id
    $whereSQL
    ORDER BY al.log_id DESC
    LIMIT ? OFFSET ?
";

$logsStmt = $db->prepare($logSQL);

// Bind filter params
$i = 1;
foreach ($params as $p) {
    $logsStmt->bindValue($i++, $p);
}
// Bind LIMIT and OFFSET as integers
$logsStmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$logsStmt->bindValue($i++, $offset,  PDO::PARAM_INT);

$logsStmt->execute();
$logs = $logsStmt->fetchAll();

// ─── PAGINATION QUERY STRING (preserves filters) ─────────────────────────────
function logPageURL(int $page, string $action, string $from, string $to): string {
    $q = ['page' => $page];
    if ($action !== '') $q['action']    = $action;
    if ($from   !== '') $q['date_from'] = $from;
    if ($to     !== '') $q['date_to']   = $to;
    return '?' . http_build_query($q);
}

// ─── ACTION LABEL MAP (makes log readable) ────────────────────────────────────
function actionLabel(string $action): string {
    $map = [
        'register'      => 'Registered',
        'login'         => 'Logged In',
        'logout'        => 'Logged Out',
        'report_item'   => 'Reported Item',
        'edit_item'     => 'Edited Item',
        'delete_item'   => 'Deleted Item',
        'restore_item'  => 'Restored Item',
        'submit_claim'  => 'Submitted Claim',
        'approve_claim' => 'Approved Claim',
        'reject_claim'  => 'Rejected Claim',
    ];
    return $map[$action] ?? ucwords(str_replace('_', ' ', $action));
}

// ─── ACTION BADGE COLOUR ──────────────────────────────────────────────────────
function actionBadgeClass(string $action): string {
    if (in_array($action, ['delete_item', 'reject_claim'], true))      return 'badge-danger';
    if (in_array($action, ['approve_claim', 'restore_item'], true))    return 'badge-success';
    if (in_array($action, ['register', 'report_item', 'submit_claim'], true)) return 'badge-info';
    if (in_array($action, ['login', 'logout'], true))                  return 'badge-secondary';
    return 'badge-warning';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs — Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        /* ── Minimal inline styles — Bootstrap replaces all of this in v16.0 ── */
        body        { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; color: #333; }
        nav         { background: #1a1a2e; padding: 12px 20px; }
        nav a       { color: #eee; text-decoration: none; margin-right: 16px; font-size: 14px; }
        nav a:hover { color: #fff; text-decoration: underline; }
        .container  { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        h1          { margin-bottom: 6px; }
        .subtitle   { color: #666; margin-bottom: 24px; font-size: 14px; }

        /* Filter bar */
        .filter-bar { background: #fff; border: 1px solid #ddd; border-radius: 6px;
                      padding: 16px 20px; margin-bottom: 20px; display: flex;
                      flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-bar label  { font-size: 13px; font-weight: bold; display: block; margin-bottom: 4px; }
        .filter-bar select,
        .filter-bar input  { padding: 7px 10px; border: 1px solid #ccc; border-radius: 4px;
                             font-size: 13px; }
        .filter-bar button { padding: 7px 18px; background: #1a1a2e; color: #fff;
                             border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .filter-bar .reset { background: #666; margin-left: 4px; }

        /* Stats row */
        .stats-row  { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-card  { background: #fff; border: 1px solid #ddd; border-radius: 6px;
                      padding: 14px 20px; flex: 1; min-width: 140px; text-align: center; }
        .stat-card .num  { font-size: 28px; font-weight: bold; color: #1a1a2e; }
        .stat-card .lbl  { font-size: 12px; color: #888; margin-top: 2px; }

        /* Table */
        .table-wrap { overflow-x: auto; }
        table       { width: 100%; border-collapse: collapse; background: #fff;
                      border: 1px solid #ddd; border-radius: 6px; overflow: hidden; }
        th          { background: #1a1a2e; color: #fff; padding: 11px 14px;
                      text-align: left; font-size: 13px; white-space: nowrap; }
        td          { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid #eee;
                      vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9f9f9; }

        /* Badges */
        .badge           { display: inline-block; padding: 3px 9px; border-radius: 12px;
                           font-size: 11px; font-weight: bold; white-space: nowrap; }
        .badge-success   { background: #d4edda; color: #155724; }
        .badge-danger    { background: #f8d7da; color: #721c24; }
        .badge-info      { background: #d1ecf1; color: #0c5460; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        .badge-warning   { background: #fff3cd; color: #856404; }

        /* Entity link */
        .entity-link    { color: #1a1a2e; font-weight: bold; text-decoration: none; }
        .entity-link:hover { text-decoration: underline; }
        .no-entity      { color: #bbb; font-style: italic; font-size: 12px; }

        /* Deleted user */
        .deleted-user   { color: #aaa; font-style: italic; }

        /* Empty state */
        .empty          { text-align: center; padding: 40px; color: #888; font-size: 15px; }

        /* Pagination */
        .pagination     { display: flex; gap: 6px; justify-content: center;
                          align-items: center; margin-top: 24px; flex-wrap: wrap; }
        .pagination a,
        .pagination span { display: inline-block; padding: 6px 14px; border: 1px solid #ccc;
                           border-radius: 4px; font-size: 13px; text-decoration: none;
                           color: #333; background: #fff; }
        .pagination a:hover  { background: #eee; }
        .pagination .current { background: #1a1a2e; color: #fff; border-color: #1a1a2e; font-weight: bold; }
        .pagination .disabled{ color: #ccc; cursor: default; }
        .page-info      { text-align: center; font-size: 13px; color: #888; margin-top: 10px; }
    </style>
</head>
<body>

<nav>
    <a href="<?= BASE_URL ?>pages/home.php">← Back to Site</a> |
    <a href="<?= BASE_URL ?>admin/index.php">Dashboard</a> |
    <a href="<?= BASE_URL ?>admin/items.php">Items</a> |
    <a href="<?= BASE_URL ?>admin/users.php">Users</a> |
    <a href="<?= BASE_URL ?>admin/claims.php">Claims</a> |
    <a href="<?= BASE_URL ?>admin/logs.php">Logs</a> |
    <a href="<?= BASE_URL ?>auth/logout.php">Logout</a>
</nav>

<div class="container">

    <h1>Activity Log</h1>
    <p class="subtitle">
        Read-only record of every action taken in the system.
        Showing <?= number_format($totalRows) ?> total
        entr<?= $totalRows === 1 ? 'y' : 'ies' ?>.
    </p>

    <!-- ── FILTER FORM ─────────────────────────────────────────────────── -->
    <form method="GET" action="">
        <div class="filter-bar">

            <div>
                <label for="action">Action Type</label>
                <select name="action" id="action">
                    <option value="">— All Actions —</option>
                    <?php foreach ($validActions as $a): ?>
                        <option value="<?= $a ?>"
                            <?= $filterAction === $a ? 'selected' : '' ?>>
                            <?= actionLabel($a) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="date_from">From Date</label>
                <input type="date" name="date_from" id="date_from"
                       value="<?= htmlspecialchars($filterFrom) ?>">
            </div>

            <div>
                <label for="date_to">To Date</label>
                <input type="date" name="date_to" id="date_to"
                       value="<?= htmlspecialchars($filterTo) ?>">
            </div>

            <div>
                <button type="submit">Filter</button>
                <a href="<?= BASE_URL ?>admin/logs.php">
                    <button type="button" class="reset">Reset</button>
                </a>
            </div>

        </div>
    </form>

    <!-- ── LOG TABLE ───────────────────────────────────────────────────── -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Entity ID</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" class="empty">
                        No log entries found for the selected filters.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <!-- Log ID -->
                    <td><?= $log['log_id'] ?></td>

                    <!-- User (NULL = deleted user) -->
                    <td>
                        <?php if ($log['full_name'] !== null): ?>
                            <?= htmlspecialchars($log['full_name']) ?>
                            <br>
                            <small style="color:#888"><?= htmlspecialchars($log['email']) ?></small>
                        <?php else: ?>
                            <span class="deleted-user">Deleted User</span>
                        <?php endif; ?>
                    </td>

                    <!-- Action badge -->
                    <td>
                        <span class="badge <?= actionBadgeClass($log['action']) ?>">
                            <?= actionLabel($log['action']) ?>
                        </span>
                    </td>

                    <!-- Entity type -->
                    <td>
                        <?php if ($log['entity'] !== null): ?>
                            <?= htmlspecialchars(ucfirst($log['entity'])) ?>
                        <?php else: ?>
                            <span class="no-entity">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Entity ID (link to item or claim if relevant) -->
                    <td>
                        <?php if ($log['entity_id'] !== null && $log['entity'] === 'item'): ?>
                            <a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= (int)$log['entity_id'] ?>"
                               class="entity-link">#<?= (int)$log['entity_id'] ?></a>
                        <?php elseif ($log['entity_id'] !== null): ?>
                            #<?= (int)$log['entity_id'] ?>
                        <?php else: ?>
                            <span class="no-entity">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Timestamp -->
                    <td><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></td>

                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ── PAGINATION ──────────────────────────────────────────────────── -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">

        <?php if ($currentPage > 1): ?>
            <a href="<?= logPageURL($currentPage - 1, $filterAction, $filterFrom, $filterTo) ?>">
                &laquo; Prev
            </a>
        <?php else: ?>
            <span class="disabled">&laquo; Prev</span>
        <?php endif; ?>

        <?php
        // Show up to 5 page numbers centred around current page
        $start = max(1, $currentPage - 2);
        $end   = min($totalPages, $currentPage + 2);

        if ($start > 1) echo '<span>…</span>';

        for ($p = $start; $p <= $end; $p++):
        ?>
            <?php if ($p === $currentPage): ?>
                <span class="current"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= logPageURL($p, $filterAction, $filterFrom, $filterTo) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $totalPages) echo '<span>…</span>'; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= logPageURL($currentPage + 1, $filterAction, $filterFrom, $filterTo) ?>">
                Next &raquo;
            </a>
        <?php else: ?>
            <span class="disabled">Next &raquo;</span>
        <?php endif; ?>

    </div>

    <p class="page-info">
        Page <?= $currentPage ?> of <?= $totalPages ?>
        &nbsp;·&nbsp;
        <?= number_format($totalRows) ?> total entr<?= $totalRows === 1 ? 'y' : 'ies' ?>
    </p>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
