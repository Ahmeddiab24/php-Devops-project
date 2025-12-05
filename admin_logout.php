<?php
session_start();


// تسجيل خروج الأدمن والعملاء
session_destroy();
header("Location: login.php");
exit();
?>
