<?php
$servername = "localhost";
$username   = "root";   // في Laragon أو XAMPP غالبًا بيكون root
$password   = "";       // افتراضي فاضي
$dbname     = "myshop";

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// فحص الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}
?>
