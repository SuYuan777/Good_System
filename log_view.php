<?php
require_once 'config.php';
$user = checkPermission('super_admin');
$message = '';

// 分页
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$total = $pdo->query("SELECT COUNT(*) FROM operation_log")->fetchColumn();
$total_pages = ceil($total / $limit);
$stmt = $pdo->prepare("SELECT * FROM operation_log ORDER BY log_time DESC LIMIT $limit OFFSET $offset");
$stmt->execute();
$logs = $stmt->fetchAll();

include 'includes/header.php';
?>

<h2>操作日志管理（仅系统管理员）</h2>
<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
            <tr><th>ID</th><th>用户ID</th><th>用户名</th><th>操作</th><th>IP</th><th>时间</th></tr>
        </thead>
        <tbody>
        <?php foreach($logs as $log): ?>
        <tr>
            <td><?=$log['id']?></td>
            <td><?=$log['user_id']?></td>
            <td><?=htmlspecialchars($log['username'])?></td>
            <td><?=htmlspecialchars($log['action'])?></td>
            <td><?=$log['ip']?></td>
            <td><?=$log['log_time']?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<nav><ul class="pagination">
    <?php for($i=1;$i<=$total_pages;$i++): ?>
        <li class="page-item <?=($i==$page)?'active':''?>"><a class="page-link" href="?page=<?=$i?>"><?=$i?></a></li>
    <?php endfor; ?>
</ul></nav>

<style>
    /* 强制表格文字颜色为浅色，提高对比度 */
    .table, .table tbody, .table tr, .table td, .table th {
        color: #f0f0f0 !important;
        background-color: transparent;
    }
    .table-striped > tbody > tr:nth-of-type(odd) > * {
        background-color: rgba(0, 80, 120, 0.2);
    }
    .table a, .table .btn {
        color: #bbddff;
    }
    .table a:hover, .table .btn:hover {
        color: #ffffff;
    }
    .badge {
        color: #fff;
    }
    /* 分页链接样式统一 */
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