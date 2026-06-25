<?php
require_once 'config.php';
logOperation("退出系统");
session_destroy();
header("Location: login.php");
?>