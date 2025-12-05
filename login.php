<?php
session_start();
include 'db.php';

$error = "";

// بيانات الأدمن الثابتة
$admin_email = "admin@myshop.com";
$admin_password = "admin123";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // فحص إذا كان أدمن
    if ($email === $admin_email && $password === $admin_password) {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = "المدير العام";
        $_SESSION['user_email'] = $admin_email;
        $_SESSION['role'] = "admin";
        header("Location: admin_dashboard.php");
        exit();
    } 
    // فحص العملاء العاديين
    else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = "user";
                header("Location: products.php");
                exit();
            } else {
                $error = "❌ كلمة المرور غير صحيحة";
            }
        } else {
            $error = "❌ البريد الإلكتروني غير موجود";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - بَهيّ للعطور</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            direction: rtl;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            width: 400px;
            text-align: center;
            animation: slideIn 0.6s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            font-size: 50px;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #333;
            margin-bottom: 30px;
            font-size: 24px;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .input-group input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .register-link {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            padding: 10px;
            border-radius: 8px;
            display: inline-block;
        }
        
        .register-link:hover {
            color: #764ba2;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .error {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }
        
        @media (max-width: 480px) {
            .login-container {
                width: 90%;
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">🌹</div>
        <h2>بَهيّ للعطور</h2>
        <p style="margin-bottom: 30px; color: #666; font-size: 16px;">أهلاً وسهلاً</p>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="input-group">
                <input type="email" name="email" placeholder="📧 البريد الإلكتروني" required>
            </div>
            
            <div class="input-group">
                <input type="password" name="password" placeholder="🔐 كلمة المرور" required>
            </div>
            
            <button type="submit" class="login-btn">دخول</button>
        </form>
        
        <a href="register.php" class="register-link">إنشاء حساب جديد ✨</a>
    </div>
</body>
</html>
