<?php
// ✅ بدء الجلسة مع إعدادات أمان محسنة
if (session_status() == PHP_SESSION_NONE) {
    // إعدادات أمان للجلسة
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // اجعلها 1 لو موقعك HTTPS
    session_start();
}

/**
 * دالة فحص صلاحيات الأدمن - محسنة ومطورة
 */
function requireAdmin() {
    // فحص وجود معرف المستخدم
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // مسح الجلسة المعطوبة
        session_unset();
        session_destroy();
        header("Location: login.php?redirect=admin");
        exit();
    }
    
    // فحص صلاحية الأدمن بطرق متعددة للتوافق
    $is_admin = false;
    
    // الطريقة الأولى: role = admin
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $is_admin = true;
    }
    
    // الطريقة الثانية: is_admin = true (للتوافق مع الكود القديم)
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
        $is_admin = true;
    }
    
    // الطريقة الثالثة: user_type = admin
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        $is_admin = true;
    }
    
    // إذا لم يكن أدمن
    if (!$is_admin) {
        // سجل محاولة الدخول غير المشروعة
        logUnauthorizedAccess();
        
        // عرض صفحة خطأ مهذبة
        showAccessDeniedPage();
        exit();
    }
    
    // تحديث آخر نشاط للأدمن
    $_SESSION['last_admin_activity'] = time();
}

/**
 * دالة فحص صلاحيات المستخدم العادي
 */
function requireUser() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    // تحديث آخر نشاط
    $_SESSION['last_activity'] = time();
}

/**
 * دالة فحص انتهاء صلاحية الجلسة (30 دقيقة خمول)
 */
function checkSessionTimeout($timeout = 1800) { // 30 دقيقة = 1800 ثانية
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive >= $timeout) {
            session_unset();
            session_destroy();
            header("Location: login.php?timeout=1");
            exit();
        }
    }
    $_SESSION['last_activity'] = time();
}

/**
 * دالة الحصول على معلومات المستخدم الحالي
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'مستخدم',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['role'] ?? $_SESSION['user_type'] ?? 'user',
        'is_admin' => isAdmin()
    ];
}

/**
 * فحص سريع: هل المستخدم أدمن؟
 */
function isAdmin() {
    return (
        (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
        (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) ||
        (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin')
    );
}

/**
 * فحص سريع: هل المستخدم مسجل دخول؟
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * دالة تسجيل محاولات الدخول غير المشروعة
 */
function logUnauthorizedAccess() {
    $log_file = 'logs/unauthorized_access.log';
    
    // إنشاء مجلد السجلات إذا لم يكن موجود
    if (!file_exists('logs')) {
        mkdir('logs', 0777, true);
    }
    
    $log_entry = date('Y-m-d H:i:s') . " - " . 
                "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - " .
                "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . " - " .
                "Page: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . " - " .
                "User ID: " . ($_SESSION['user_id'] ?? 'guest') . "\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * عرض صفحة رفض الوصول المهذبة
 */
function showAccessDeniedPage() {
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>⛔ وصول مرفوض - بَهيّ للعطور</title>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Cairo', sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                direction: rtl;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                max-width: 500px;
                width: 90%;
            }
            .error-icon { font-size: 80px; margin-bottom: 20px; }
            h1 { color: #dc3545; font-size: 28px; margin-bottom: 15px; font-weight: 800; }
            p { color: #666; font-size: 16px; margin-bottom: 25px; line-height: 1.6; }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 25px;
                font-weight: 700;
                margin: 5px;
                transition: all 0.3s ease;
            }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
            .btn-secondary { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-icon">⛔</div>
            <h1>وصول مرفوض</h1>
            <p>عذراً، ليس لديك صلاحية للوصول إلى هذه الصفحة.<br>هذه الصفحة مخصصة للمديرين فقط.</p>
            
            <div>
                <a href="products.php" class="btn">🛍️ المتجر</a>
                <a href="login.php" class="btn btn-secondary">🔐 تسجيل دخول</a>
            </div>
            
            <?php if (isLoggedIn()): ?>
                <p style="margin-top: 20px; font-size: 14px; color: #999;">
                    مسجل دخول كـ: <strong><?= htmlspecialchars(getCurrentUser()['name']) ?></strong>
                </p>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

/**
 * دالة تنظيف الجلسة عند تسجيل الخروج
 */
function cleanLogout() {
    // إلغاء كل متغيرات الجلسة
    $_SESSION = array();
    
    // حذف كوكي الجلسة
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // إنهاء الجلسة نهائياً
    session_destroy();
}

// ✅ فحص انتهاء صلاحية الجلسة تلقائياً
checkSessionTimeout();

// ✅ دالة مساعدة للتحقق السريع في الصفحات
function quickAdminCheck() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    
    if (!isAdmin()) {
        showAccessDeniedPage();
        exit();
    }
}
?>
