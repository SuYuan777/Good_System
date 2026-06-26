<?php 
if (!isset($user) || !$user) {
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
    } elseif (file_exists('config.php')) {
        require_once 'config.php';
    }
    $user = getCurrentUser();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>物资管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #0a0f2a 0%, #0a1a3a 100%);
            font-family: 'Microsoft YaHei', 'Segoe UI', 'PingFang SC', Roboto, sans-serif;
            color: #e0e0e0;
        }
        .navbar-dark.bg-dark {
            background: rgba(5, 15, 35, 0.85) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
            padding: 0.3rem 1rem;
        }
        .navbar-brand {
            color: #aaddff !important;
            font-weight: 600;
            font-size: 1.2rem;
            letter-spacing: 1px;
            cursor: default;
        }
        .nav-link {
            color: #aaddff !important;
            font-weight: 500;
            font-size: 0.95rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: inline-block;
        }
        .nav-link:hover {
            transform: translateY(-2px);
            text-shadow: 0 0 5px #0af;
            background: rgba(0, 170, 255, 0.1);
            border-radius: 20px;
        }
        .card, .chart-card {
            background: rgba(10, 25, 55, 0.65);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3), 0 0 15px rgba(0, 200, 255, 0.2);
            transition: all 0.3s;
        }
        .card:hover, .chart-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.4), 0 0 25px rgba(0, 200, 255, 0.5);
        }
        .card-header {
            background: rgba(0, 30, 60, 0.7);
            border-bottom: 1px solid rgba(0, 255, 255, 0.4);
            font-weight: bold;
            color: #bbddff;
        }
        .table {
            color: #e0e0e0;
            background: rgba(10, 25, 55, 0.4);
            border-radius: 16px;
            overflow: hidden;
        }
        .table thead th {
            background: rgba(0, 30, 60, 0.8);
            border-bottom: 1px solid rgba(0, 255, 255, 0.4);
            color: #bbddff;
            font-weight: 500;
        }
        .table tbody tr:hover {
            background: rgba(0, 170, 255, 0.1);
        }
        .table td, .table th {
            border-color: rgba(0, 255, 255, 0.2);
            vertical-align: middle;
        }
        .btn {
            border-radius: 30px;
            padding: 5px 15px;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0af, #0088cc);
            border: none;
            box-shadow: 0 2px 5px rgba(0,170,255,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,170,255,0.5);
            background: linear-gradient(135deg, #1af, #0099dd);
        }
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
        }
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border: none;
            color: #fff;
        }
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
        }
        .btn-secondary {
            background: rgba(100, 100, 140, 0.6);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .form-control, .form-select {
            background: rgba(0, 20, 40, 0.8);
            border: 1px solid rgba(0, 255, 255, 0.4);
            color: #fff;
            border-radius: 30px;
            padding: 6px 15px;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(0, 30, 50, 0.9);
            border-color: #0af;
            box-shadow: 0 0 8px #0af;
            color: #fff;
        }
        .form-label {
            color: #bbddff;
            font-weight: 500;
        }
        .modal-content {
            background: rgba(10, 25, 55, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 255, 255, 0.4);
            border-radius: 20px;
            color: #e0e0e0;
        }
        .modal-header {
            border-bottom: 1px solid rgba(0, 255, 255, 0.3);
        }
        .modal-footer {
            border-top: 1px solid rgba(0, 255, 255, 0.3);
        }
        .pagination .page-link {
            background: rgba(10, 25, 55, 0.7);
            border: 1px solid rgba(0, 255, 255, 0.3);
            color: #bbddff;
        }
        .pagination .page-item.active .page-link {
            background: #0af;
            border-color: #0af;
        }
        .badge {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 20px;
        }
        .alert {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 200, 50, 0.5);
            color: #ffdd99;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <span class="navbar-brand">物资管理系统</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/good_system/index.php">仪表盘</a></li>
                <li class="nav-item"><a class="nav-link" href="/good_system/unit_manage.php">单位管理</a></li>
                <?php if(isset($user) && in_array($user['role'], ['super_admin','unit_admin'])): ?>
                <li class="nav-item"><a class="nav-link" href="/good_system/personnel.php">人员管理</a></li>
                <?php endif; ?>
                <?php if(isset($user) && $user['role'] == 'super_admin'): ?>
                <li class="nav-item"><a class="nav-link" href="/good_system/category_manage.php">品类管理</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="/good_system/material.php">物资管理</a></li>
                <li class="nav-item"><a class="nav-link" href="/good_system/material_query.php">物资查询</a></li>
                <li class="nav-item"><a class="nav-link" href="/good_system/data_manage.php">数据导出</a></li>
                <?php if(isset($user) && $user['role'] == 'super_admin'): ?>
                <li class="nav-item"><a class="nav-link" href="/good_system/log_view.php">日志管理</a></li>
            
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-white-50" style="font-size:0.75rem;">欢迎，<?= htmlspecialchars($user['real_name'] ?? '')?> (<?= $user['role'] ?? ''?>)</span>
            <a href="/good_system/logout.php" class="btn btn-sm btn-outline-info ms-2" style="font-size:0.7rem;">退出</a>
        </div>
    </div>
</nav>
<div class="container-fluid mt-3">