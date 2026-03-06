<?php
session_start();
require "../config/database.php";

$username = $_POST['username'];
$password = $_POST['password'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['admin'] = $user['username'];
    header("Location: ../dashboard/index.php");
} else {
    echo "<script>alert('Login gagal');location='login.php';</script>";
}
