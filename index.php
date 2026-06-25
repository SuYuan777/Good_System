<?php
require_once 'config.php';
$user = checkPermission('operator');
$unit_id = $user['unit_id'];
$selected_unit = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : $unit_id;
$unit_ids = getSubUnitIds($selected_unit, true);
$placeholders = implode(',', array_fill(0, count($unit_ids), '?'));

// ========== 卡片数据 ==========
$stmt = $pdo->prepare("SELECT SUM(quantity) FROM material WHERE unit_id IN ($placeholders)");
$stmt->execute($unit_ids);
$total_stock = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(br.borrow_quantity) FROM borrow_record br JOIN material m ON br.material_id=m.id WHERE m.unit_id IN ($placeholders) AND br.status='借出中'");
$stmt->execute($unit_ids);
$borrowed_total = $stmt->fetchColumn() ?: 0;
$in_stock_total = $total_stock - $borrowed_total;

// 物资品类数：统计启用中的品类总数（不依赖物资表）
$stmt = $pdo->query("SELECT COUNT(*) FROM category WHERE status='启用'");
$category_count = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM storehouse WHERE unit_id IN ($placeholders)");
$stmt->execute($unit_ids);
$storehouse_count = $stmt->fetchColumn() ?: 0;

// ========== 固定品类卡片数据（可自行修改品类名称） ==========
$fixedCategories = ['车辆', '枪支', '弹药'];  // 在这里修改品类名称
$categoryAmounts = [];
foreach ($fixedCategories as $cat) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM material WHERE unit_id IN ($placeholders) AND category = ?");
    $stmt->execute(array_merge($unit_ids, [$cat]));
    $categoryAmounts[$cat] = (int)$stmt->fetchColumn();
}

// ========== 饼图使用的品类数据（动态，所有启用的品类） ==========
$catStmt = $pdo->query("SELECT name FROM category WHERE status='启用' ORDER BY id");
$allCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
$category_pie = [];
foreach ($allCategories as $cat) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM material WHERE unit_id IN ($placeholders) AND category = ?");
    $stmt->execute(array_merge($unit_ids, [$cat]));
    $total = (int)$stmt->fetchColumn();
    if ($total > 0) {
        $category_pie[$cat] = $total;
    }
}

// ========== 下属单位物资分布 ==========
$unit_dist = [];
$stmt = $pdo->prepare("SELECT id, name FROM unit WHERE parent_id = ?");
$stmt->execute([$selected_unit]);
$sub_units = $stmt->fetchAll();
foreach ($sub_units as $sub) {
    $sub_ids = getSubUnitIds($sub['id'], true);
    $sub_placeholders = implode(',', array_fill(0, count($sub_ids), '?'));
    $stmt2 = $pdo->prepare("SELECT SUM(quantity) FROM material WHERE unit_id IN ($sub_placeholders)");
    $stmt2->execute($sub_ids);
    $total = $stmt2->fetchColumn() ?: 0;
    if ($total > 0) $unit_dist[$sub['name']] = $total;
}

// ========== 状态分布 ==========
$statuses = ['新品', '良好', '堪用', '损坏', '报废', '消耗'];
$status_pie = [];
foreach ($statuses as $stat) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM material WHERE unit_id IN ($placeholders) AND status = ?");
    $stmt->execute(array_merge($unit_ids, [$stat]));
    $status_pie[$stat] = (int)$stmt->fetchColumn();
}

// ========== 借出分布 ==========
$borrow_pie = ['在位' => $in_stock_total, '借出' => $borrowed_total];

// ========== 逾期提醒 ==========
$stmt = $pdo->prepare("SELECT br.*, m.name as mname FROM borrow_record br 
    JOIN material m ON br.material_id=m.id 
    WHERE br.status='借出中' AND m.unit_id IN ($placeholders) AND (br.due_time < CURDATE() OR DATEDIFF(br.due_time, CURDATE()) <= 5)");
$stmt->execute($unit_ids);
$alert_list = $stmt->fetchAll();

$units = $pdo->query("SELECT id, name FROM unit")->fetchAll();

// 地图图片路径（请修改为您的实际图片路径）
$mapImagePath = "1111.png";
if (!file_exists($mapImagePath)) {
    $mapImagePath = "https://picsum.photos/id/104/800/450";
}

// 为品类卡片准备不同的渐变背景（对应四个固定品类，可自定义）
$cardGradients = [
    '车辆' => 'linear-gradient(135deg, rgba(0, 180, 200, 0.7), rgba(0, 120, 140, 0.7))',
    '枪支' => 'linear-gradient(135deg, rgba(150, 0, 200, 0.7), rgba(100, 0, 150, 0.7))',
    '弹药' => 'linear-gradient(135deg, rgba(230, 120, 0, 0.7), rgba(180, 80, 0, 0.7))',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>物资管理系统 - 智能仪表盘</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        body {
            background: linear-gradient(135deg, #0a0f2a 0%, #0a1a3a 100%);
            font-family: 'Microsoft YaHei', 'Segoe UI', 'PingFang SC', Roboto, sans-serif;
            color: #e0e0e0;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        /* 导航栏样式 */
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
        .btn-outline-info {
            border-radius: 20px;
            transition: all 0.25s ease;
        }
        .btn-outline-info:hover {
            transform: translateY(-2px);
            background: #0af;
            border-color: #0af;
            box-shadow: 0 2px 10px rgba(0, 170, 255, 0.5);
        }
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0 15px 8px 15px;
        }
        .cards-row {
            margin-bottom: 6px;
        }
        .stat-card {
            background: rgba(10, 25, 55, 0.65);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 16px;
            padding: 5px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        .stat-card h6 {
            font-size: 0.8rem;
            margin-bottom: 0;
            color: #bbddff;
        }
        .data-card-value {
            font-size: 1.4rem;
            font-weight: bold;
            background: linear-gradient(135deg, #fff, #0af);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            line-height: 1;
        }
        .stat-card-category {
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 5px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        .stat-card-category h6 {
            font-size: 0.8rem;
            margin-bottom: 0;
            color: #fff;
            text-shadow: 0 0 2px rgba(0,0,0,0.5);
        }
        .category-value {
            font-size: 1.4rem;
            font-weight: bold;
            line-height: 1;
            color: #fff;
        }
        .unit-selector-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 20, 40, 0.6);
            padding: 4px 12px;
            border-radius: 30px;
            border: 1px solid rgba(0, 255, 255, 0.3);
        }
        .unit-selector-wrapper label {
            font-size: 0.85rem;
            margin-bottom: 0;
            color: #bbddff;
        }
        .unit-selector {
            background: rgba(0, 20, 40, 0.8);
            border: 1px solid #0af;
            color: #fff;
            border-radius: 30px;
            padding: 3px 12px;
            font-size: 0.8rem;
            width: auto;
        }
        .alert-compact {
            background: rgba(255, 200, 50, 0.2);
            border: 1px solid #ffaa00;
            color: #ffdd99;
            padding: 4px 8px;
            margin-bottom: 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        .alert-compact ul {
            margin-top: 2px;
            margin-bottom: 0;
            padding-left: 20px;
        }
        .alert-compact li {
            font-size: 0.7rem;
        }
        .chart-card {
            background: rgba(10, 25, 55, 0.65);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 16px;
            padding: 6px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .echart {
            width: 100%;
            flex: 1;
            min-height: 0;
        }
        .map-container {
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 16px;
            padding: 5px;
            height: 100%;
        }
        .map-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 12px;
            border: 1px solid #0af;
            box-shadow: 0 0 12px rgba(0,170,255,0.3);
        }
        .left-col, .right-col {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        .left-col .chart-card, .right-col .chart-card {
            flex: 1;
            margin-bottom: 8px;
        }
        .left-col .chart-card:last-child, .right-col .chart-card:last-child {
            margin-bottom: 0;
        }
        .middle-col {
            height: 100%;
        }
        .row.g-0 {
            margin-right: 0;
            margin-left: 0;
        }
        .col, .col-md-3, .col-md-6 {
            padding-right: 5px;
            padding-left: 5px;
        }
        .main-content {
            overflow-y: auto;
            scrollbar-width: none;
        }
        .main-content::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <span class="navbar-brand">物资管理系统</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link active" href="index.php">仪表盘</a></li>
                <li class="nav-item"><a class="nav-link" href="unit_manage.php">单位管理</a></li>
                <?php if(in_array($user['role'], ['super_admin','unit_admin'])): ?>
                <li class="nav-item"><a class="nav-link" href="personnel.php">人员管理</a></li>
                <?php endif; ?>
                <?php if($user['role'] == 'super_admin'): ?>
                <li class="nav-item"><a class="nav-link" href="category_manage.php">品类管理</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="material.php">物资管理</a></li>
                <li class="nav-item"><a class="nav-link" href="data_manage.php">数据管理</a></li>
                <?php if($user['role'] == 'super_admin'): ?>
                <li class="nav-item"><a class="nav-link" href="log_view.php">日志管理</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-white-50" style="font-size:0.75rem;">欢迎，<?= htmlspecialchars($user['real_name'])?> (<?= $user['role']?>)</span>
            <a href="logout.php" class="btn btn-sm btn-outline-info ms-2" style="font-size:0.7rem;">退出</a>
        </div>
    </div>
</nav>

<div class="main-content">
    <!-- 第一行：单位选择 + 基础统计卡片 -->
    <div class="row cards-row align-items-center g-0 mb-1">
        <div class="col-md-2">
            <div class="unit-selector-wrapper">
                <label>单位</label>
                <select id="unitSelector" class="form-select unit-selector">
                    <?php foreach($units as $u): ?>
                        <option value="<?=$u['id']?>" <?=$selected_unit==$u['id']?'selected':''?>><?=htmlspecialchars($u['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-2"><div class="stat-card"><h6>库存总数</h6><div class="data-card-value"><?=$total_stock?></div></div></div>
        <div class="col-md-2"><div class="stat-card"><h6>在库数量</h6><div class="data-card-value"><?=$in_stock_total?></div></div></div>
        <div class="col-md-2"><div class="stat-card"><h6>借出数量</h6><div class="data-card-value"><?=$borrowed_total?></div></div></div>
        <div class="col-md-2"><div class="stat-card"><h6>物资品类数</h6><div class="data-card-value"><?=$category_count?></div></div></div>
        <div class="col-md-2"><div class="stat-card"><h6>库房总数</h6><div class="data-card-value"><?=$storehouse_count?></div></div></div>
    </div>

    <!-- 第二行：固定品类卡片（通信、器材、营房、供应） -->
    <div class="row cards-row g-0 mb-1">
        <?php foreach ($fixedCategories as $cat):
            $gradient = $cardGradients[$cat] ?? 'linear-gradient(135deg, rgba(80,80,120,0.7), rgba(40,40,80,0.7))';
        ?>
        <div class="col-md-4">
            <div class="stat-card-category" style="background: <?= $gradient ?>;">
                <h6><?= $cat ?>物资总数</h6>
                <div class="category-value"><?= $categoryAmounts[$cat] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 借出提醒 -->
    <?php if($alert_list): ?>
    <div class="row g-0 mb-1">
        <div class="col-12">
            <div class="alert-compact">
                <strong>借出提醒</strong> (逾期或距归还不足5天)
                <ul>
                <?php foreach($alert_list as $item): ?>
                    <li>物资【<?=htmlspecialchars($item['mname'])?>】数量<?=$item['borrow_quantity']?>，借用单位：<?=htmlspecialchars($item['borrower_unit'])?>，应还日期：<?=$item['due_time']?> <?php if($item['due_time'] < date('Y-m-d')) echo '<span class="badge bg-danger">逾期</span>'; else echo '<span class="badge bg-warning">即将到期</span>'; ?></li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 核心布局：左侧柱状图+品类饼图，中间地图，右侧状态饼图+借出饼图 -->
    <div class="row g-0" style="flex: 1; min-height: 0;">
        <div class="col-md-3 left-col" style="padding-right: 5px;">
            <div class="chart-card"><div id="unitBarChart" class="echart"></div></div>
            <div class="chart-card"><div id="categoryPieChart" class="echart"></div></div>
        </div>
        <div class="col-md-6 middle-col" style="padding: 0 5px;">
            <div class="map-container" style="height: 100%;">
                <img src="<?= $mapImagePath ?>" alt="石家庄地图" class="map-img">
            </div>
        </div>
        <div class="col-md-3 right-col" style="padding-left: 5px;">
            <div class="chart-card"><div id="statusPieChart" class="echart"></div></div>
            <div class="chart-card"><div id="borrowPieChart" class="echart"></div></div>
        </div>
    </div>
</div>

<script>
$(function() {
    // 下属单位柱状图
    var unitNames = <?= json_encode(array_keys($unit_dist)) ?>;
    var unitValues = <?= json_encode(array_values($unit_dist)) ?>;
    var barChart = echarts.init(document.getElementById('unitBarChart'));
    barChart.setOption({
        title: { text: '下属单位物资分布', textStyle: { color: '#aaf', fontSize: 11 }, left: 'center', top: 0 },
        tooltip: { trigger: 'axis' },
        grid: { containLabel: true, top: 26, bottom: 4, left: 35, right: 5 },
        xAxis: { type: 'category', data: unitNames, axisLabel: { rotate: 25, color: '#ccc', fontSize: 8 }, axisLine: { lineStyle: { color: '#0af' } } },
        yAxis: { type: 'value', name: '数量', nameTextStyle: { color: '#ccc', fontSize: 8 }, axisLabel: { color: '#ccc', fontSize: 8 }, splitLine: { lineStyle: { color: '#2266aa' } } },
        series: [{ type: 'bar', data: unitValues, itemStyle: { borderRadius: [3,3,0,0], color: new echarts.graphic.LinearGradient(0,0,0,1, [
            { offset: 0, color: '#0af' }, { offset: 1, color: '#00c8ff' } ]) } }]
    });
    // 品类分布饼图（动态，所有启用品类）
    var catData = <?= json_encode($category_pie) ?>;
    var pie1 = echarts.init(document.getElementById('categoryPieChart'));
    pie1.setOption({
        title: { text: '物资品类分布', textStyle: { color: '#aaf', fontSize: 11 }, left: 'center', top: 0 },
        tooltip: {},
        series: [{ type: 'pie', data: Object.entries(catData).map(([n,v])=>({name:n, value:v})), label: { color: '#fff', fontSize: 9 }, itemStyle: { borderRadius: 5, borderColor: '#0a1a3a', borderWidth: 1 } }]
    });
    // 状态分布饼图
    var statusData = <?= json_encode($status_pie) ?>;
    var pie2 = echarts.init(document.getElementById('statusPieChart'));
    pie2.setOption({ title:{ text:'物资状态分布', textStyle:{ color:'#aaf', fontSize:11 }, left:'center', top:0 }, series:[{ type:'pie', data:Object.entries(statusData).map(([n,v])=>({name:n,value:v})), label:{ color:'#fff', fontSize:9 } }] });
    // 借出分布饼图
    var borrowData = <?= json_encode($borrow_pie) ?>;
    var pie3 = echarts.init(document.getElementById('borrowPieChart'));
    pie3.setOption({ title:{ text:'借出/在库分布', textStyle:{ color:'#aaf', fontSize:11 }, left:'center', top:0 }, series:[{ type:'pie', data:Object.entries(borrowData).map(([n,v])=>({name:n,value:v})), label:{ color:'#fff', fontSize:9 } }] });
});
$('#unitSelector').change(function(){ window.location.href='index.php?unit_id='+$(this).val(); });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- 技术服务联系人 -->
<div style="position: fixed; bottom: 10px; right: 25px; color: rgba(170, 221, 255, 0.7); font-size: 0.75rem; font-weight: 400; text-align: right; line-height: 1.4; z-index: 1000;">
    技术服务联系人：王靖文<br>
    电话：17787127675
</div>

</body>
</html>