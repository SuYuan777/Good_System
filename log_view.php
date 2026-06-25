<?php
require_once 'config.php';
$user = checkPermission('super_admin');

$filter_username = isset($_GET['username']) ? trim($_GET['username']) : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where = [];
$params = [];
if ($filter_username) {
    $where[] = "ol.username LIKE ?";
    $params[] = "%$filter_username%";
}
if ($filter_date_from) {
    $where[] = "ol.log_time >= ?";
    $params[] = $filter_date_from . ' 00:00:00';
}
if ($filter_date_to) {
    $where[] = "ol.log_time <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
}
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM operation_log ol $where_sql");
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$total_pages = ceil($total / $limit);

$stmt = $pdo->prepare("SELECT ol.*, u.real_name FROM operation_log ol LEFT JOIN user u ON ol.user_id = u.id $where_sql ORDER BY ol.log_time DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$filter_query = http_build_query(array_filter([
    'username' => $filter_username,
    'date_from' => $filter_date_from,
    'date_to' => $filter_date_to,
]));

include 'includes/header.php';
?>

<h2>操作日志管理</h2>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">用户名</label>
                <input type="text" name="username" class="form-control" placeholder="用户名" value="<?=htmlspecialchars($filter_username)?>">
            </div>
            <div class="col-auto">
                <label class="form-label">开始日期</label>
                <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($filter_date_from)?>">
            </div>
            <div class="col-auto">
                <label class="form-label">结束日期</label>
                <input type="date" name="date_to" class="form-control" value="<?=htmlspecialchars($filter_date_to)?>">
            </div>
            <div class="col-auto" style="margin-top:auto">
                <button type="submit" class="btn btn-primary">筛选</button>
                <a href="log_view.php" class="btn btn-secondary">重置</a>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
            <tr><th style="width:145px">操作时间</th><th>姓名</th><th>用户名</th><th>操作详情</th><th>IP地址</th></tr>
        </thead>
        <tbody>
        <?php foreach($logs as $log): ?>
        <tr>
            <td style="white-space:nowrap"><?=$log['log_time']?></td>
            <td><?=htmlspecialchars($log['real_name'] ?? '-')?></td>
            <td><?=htmlspecialchars($log['username'])?></td>
            <td><?=htmlspecialchars($log['action'])?></td>
            <td><?=$log['ip']?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<nav><ul class="pagination">
    <?php for($i=1;$i<=$total_pages;$i++): ?>
        <li class="page-item <?=($i==$page)?'active':''?>">
            <a class="page-link" href="?page=<?=$i?><?=$filter_query ? '&'.$filter_query : ''?>"><?=$i?></a>
        </li>
    <?php endfor; ?>
</ul></nav>

<style>
    .table, .table tbody, .table tr, .table td, .table th {
        color: #f0f0f0 !important;
        background-color: transparent;
    }
    .table-striped > tbody > tr:nth-of-type(odd) > * {
        background-color: rgba(0, 80, 120, 0.2);
    }
    .pagination .page-link {
        background: rgba(10, 25, 55, 0.7);
        border: 1px solid rgba(0, 255, 255, 0.3);
        color: #bbddff;
    }
    .pagination .page-item.active .page-link {
        background: #0af;
        border-color: #0af;
        color: #fff;
    }
</style>

<?php include 'includes/footer.php'; ?>
