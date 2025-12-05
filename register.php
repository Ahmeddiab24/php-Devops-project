<?php
session_start();
include "db.php";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إنشاء حساب جديد - بَهيّ للعطور</title>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Cairo', Tahoma, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }
    .container {
      background: #fff;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 420px;
      text-align: center;
      animation: fadeIn 1s ease-in-out;
    }
    h2 {
      color: #333;
      margin-bottom: 10px;
      font-size: 28px;
      font-weight: 700;
    }
    .subtitle {
      color: #666;
      margin-bottom: 30px;
      font-size: 14px;
    }
    .form-group {
      margin-bottom: 20px;
      text-align: right;
    }
    input {
      width: 100%;
      padding: 15px;
      border-radius: 12px;
      border: 2px solid #e1e5e9;
      outline: none;
      transition: all 0.3s ease;
      font-size: 16px;
      font-family: 'Cairo', sans-serif;
    }
    input:focus {
      border-color: #667eea;
      box-shadow: 0 0 15px rgba(102, 126, 234, 0.3);
      transform: translateY(-2px);
    }
    input::placeholder {
      color: #999;
    }
    button {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: #fff;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
      font-size: 16px;
      font-weight: 600;
      font-family: 'Cairo', sans-serif;
    }
    button:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }
    button:active {
      transform: translateY(0);
    }
    .login-link {
      display: block;
      margin-top: 20px;
      color: #667eea;
      text-decoration: none;
      transition: 0.3s;
      font-weight: 600;
    }
    .login-link:hover {
      color: #764ba2;
      text-decoration: underline;
    }
    .success {
      color: #22c55e;
      margin-bottom: 15px;
      font-weight: 600;
      padding: 10px;
      background: rgba(34, 197, 94, 0.1);
      border-radius: 8px;
      border-left: 4px solid #22c55e;
    }
    .error {
      color: #ef4444;
      margin-bottom: 15px;
      font-weight: 600;
      padding: 10px;
      background: rgba(239, 68, 68, 0.1);
      border-radius: 8px;
      border-left: 4px solid #ef4444;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .icon {
      font-size: 48px;
      color: #667eea;
      margin-bottom: 20px;
    }
    @media (max-width: 480px) {
      .container { padding: 30px 20px; margin: 10px; }
      h2 { font-size: 24px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="icon">👤</div>
    <h2>إنشاء حساب جديد</h2>
    <p class="subtitle">انضم إلى متجر بَهيّ للعطور واستمتع بعروضنا الحصرية</p>
    
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = trim($_POST["username"]);
        $email = trim($_POST["email"]);
        $phone = trim($_POST["phone"]);
        $address = trim($_POST["address"]);
        $password = $_POST["password"];
        
        // التحقق من وجود البريد الإلكتروني مسبقاً
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $result = $check_email->get_result();
        
        if ($result->num_rows > 0) {
            echo "<p class='error'>❌ البريد الإلكتروني مسجل مسبقاً</p>";
        } elseif (strlen($password) < 6) {
            echo "<p class='error'>❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل</p>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // إضافة العميل الجديد مع الهاتف والعنوان
            $stmt = $conn->prepare("INSERT INTO users (username, email, phone, address, password, is_blocked, first_order) VALUES (?, ?, ?, ?, ?, 0, 0)");
            $stmt->bind_param("sssss", $username, $email, $phone, $address, $hashed_password);
            
            if ($stmt->execute()) {
                echo "<p class='success'>✅ تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول</p>";
            } else {
                echo "<p class='error'>❌ حدث خطأ أثناء إنشاء الحساب، يرجى المحاولة مرة أخرى</p>";
            }
        }
    }
    ?>
    
    <form method="post">
      <div class="form-group">
        <input type="text" name="username" placeholder="اسم المستخدم" required maxlength="50">
      </div>
      
      <div class="form-group">
        <input type="email" name="email" placeholder="البريد الإلكتروني" required>
      </div>
      
      <div class="form-group">
        <input type="tel" name="phone" placeholder="رقم الهاتف (مثال: 01012345678)" required 
               pattern="^(01|02|03|04|05)[0-9]{8,9}$" 
               title="أدخل رقم هاتف مصري صحيح يبدأ بـ 01, 02, 03, 04, أو 05">
      </div>
      
      <div class="form-group">
        <input type="text" name="address" placeholder="العنوان الكامل (الشارع، المنطقة، المدينة)" required maxlength="200">
      </div>
      
      <div class="form-group">
        <input type="password" name="password" placeholder="كلمة المرور (4 أحرف على الأقل)" required minlength="4">
      </div>
      
      <button type="submit">🚀 إنشاء الحساب</button>
    </form>
    
    <a href="login.php" class="login-link">🔑 لديك حساب بالفعل؟ تسجيل الدخول</a>
  </div>

  <script>
    // تحسين تجربة المستخدم
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('input');
      
      inputs.forEach(input => {
        input.addEventListener('focus', function() {
          this.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
          this.style.transform = 'scale(1)';
        });
      });
      
      // تنسيق رقم الهاتف أثناء الكتابة
      const phoneInput = document.querySelector('input[name="phone"]');
      phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // إزالة كل ما ليس رقم
        if (value.length > 11) value = value.substring(0, 11);
        e.target.value = value;
      });
    });
    
    console.log('📝 صفحة التسجيل محسنة مع الهاتف والعنوان!');
  </script>
</body>
</html>
