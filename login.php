<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ? AND status = '启用'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && $user['password'] === hash('sha256', $password)) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['unit_id'] = $user['unit_id'];
        $_SESSION['role'] = $user['role'];
        logOperation("登录系统");
        header("Location: index.php");
        exit;
    } else {
        $error = "用户名或密码错误，或账号被停用";
    }
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>物资管理系统登录</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css"></head>
<body class="bg-light">
<div class="container mt-5" style="max-width:400px">
    <h3 class="text-center">物资管理系统</h3>
    <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="post">
        <div class="mb-3"><label>用户名</label><input type="text" name="username" class="form-control" required></div>
        <div class="mb-3"><label>密码</label><input type="password" name="password" class="form-control" required></div>
        <button type="submit" class="btn btn-primary w-100">登录</button>
    </form>
</div>
</body>
</html>