<?php
session_start();
include 'db.php';

// دي الأولى ✅ صحيحة
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

// دالة للتحقق من وجود العمود
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return ($result && $result->num_rows > 0);
}

// إضافة مستخدم جديد
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);
    
    if ($stmt->execute()) {
        $message = "✅ تم إضافة المستخدم بنجاح!";
        $message_type = "success";
    } else {
        $message = "❌ حدث خطأ أثناء إضافة المستخدم!";
        $message_type = "error";
    }
}

// حظر/إلغاء حظر مستخدم
if (isset($_GET['block'])) {
    $user_id = intval($_GET['block']);
    
    // جلب حالة الحظر الحالية
    $result = $conn->query("SELECT is_blocked, username FROM users WHERE id = $user_id");
    if ($result && $user = $result->fetch_assoc()) {
        $current_status = intval($user['is_blocked']);
        $new_status = $current_status === 0 ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $user_id);
        
        if ($stmt->execute()) {
            $action = $new_status === 1 ? 'حظر' : 'إلغاء حظر';
            $message = "✅ تم $action المستخدم '{$user['username']}' بنجاح!";
            $message_type = "success";
        } else {
            $message = "❌ حدث خطأ أثناء تحديث حالة المستخدم!";
            $message_type = "error";
        }
    }
}

// حذف مستخدم (مع معالجة Foreign Key)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // تحقق من وجود طلبات للمستخدم
    $orders_check = $conn->query("SELECT COUNT(*) FROM orders WHERE user_id = $id");
    if ($orders_check) {
        $order_count = $orders_check->fetch_row()[0];
        
        if ($order_count > 0) {
            // عرض رسالة تأكيد
            if (!isset($_GET['confirm'])) {
                $message = "⚠️ هذا المستخدم لديه <strong>$order_count طلب/طلبات</strong>!<br>
                          <a href='?delete=$id&confirm=1' onclick='return confirm(\"هل تريد حذف المستخدم وجميع طلباته نهائياً؟\")' 
                             style='background:#dc3545; color:white; padding:8px 15px; border-radius:5px; text-decoration:none; margin:5px;'>
                             🗑️ حذف المستخدم والطلبات
                          </a>
                          <a href='?' style='background:#6c757d; color:white; padding:8px 15px; border-radius:5px; text-decoration:none; margin:5px;'>
                             ❌ إلغاء
                          </a>";
                $message_type = "error";
            } else {
                // حذف الطلبات أولاً (إذا وجد جدول order_items)
                $conn->query("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id = $id)");
                
                // حذف الطلبات
                $conn->query("DELETE FROM orders WHERE user_id = $id");
                
                // ثم حذف المستخدم
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $message = "🗑️ تم حذف المستخدم و $order_count طلب/طلبات بنجاح!";
                    $message_type = "success";
                } else {
                    $message = "❌ حدث خطأ أثناء حذف المستخدم!";
                    $message_type = "error";
                }
            }
        } else {
            // لا توجد طلبات - حذف عادي
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "🗑️ تم حذف المستخدم بنجاح!";
                $message_type = "success";
            } else {
                $message = "❌ حدث خطأ أثناء حذف المستخدم!";
                $message_type = "error";
            }
        }
    } else {
        $message = "❌ خطأ في فحص الطلبات!";
        $message_type = "error";
    }
}

// إحصائيات المستخدمين
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$blocked_users = $conn->query("SELECT COUNT(*) FROM users WHERE is_blocked = 1")->fetch_row()[0];
$active_users = $total_users - $blocked_users;

// إحصائيات حسب التاريخ (إذا كان العمود موجود)
$today_users = 0;
$this_month_users = 0;

if (columnExists($conn, 'users', 'created_at')) {
    $today = date('Y-m-d');
    $this_month = date('Y-m');
    
    $today_users = $conn->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = '$today'")->fetch_row()[0];
    $this_month_users = $conn->query("SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = '$this_month'")->fetch_row()[0];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 إدارة المستخدمين - بَهيّ للعطور</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* إخفاء شريط التمرير */
        html, body {
            -ms-overflow-style: none;
            scrollbar-width: none;
            overflow-x: hidden;
        }
        html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar {
            display: none;
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
            max-width: 100%;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto;
            width: 100%;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 { 
            color: #333; 
            font-size: 28px; 
            margin-bottom: 10px; 
        }
        
        .nav-links { 
            margin-top: 15px; 
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .nav-links a {
            display: inline-block;
            margin: 5px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .nav-links a:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); 
        }

        /* 🆕 قسم الوصول السريع للإدارة */
        .admin-quick-access {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .admin-quick-access h3 {
            color: #333;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .admin-links-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }

        .admin-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .admin-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.5);
            color: white;
        }

        .admin-link i {
            font-size: 14px;
        }

        /* ألوان متناسقة مع تصميم الصفحة */
        .admin-link.products { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
        }
        .admin-link.discounts { 
            background: linear-gradient(135deg, #fd7e14 0%, #f8b500 100%); 
        }
        .admin-link.orders { 
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%); 
        }
        .admin-link.categories { 
            background: linear-gradient(135deg, #6f42c1 0%, #8e44ad 100%); 
        }
        .admin-link.points-settings { 
            background: linear-gradient(135deg, #17a2b8 0%, #1abc9c 100%); 
        }
        .admin-link.points-reports { 
            background: linear-gradient(135deg, #ffc107 0%, #f39c12 100%); 
        }
        .admin-link.customers-points { 
            background: linear-gradient(135deg, #e83e8c 0%, #e91e63 100%); 
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
            width: 100%;
            line-height: 1.6;
        }
        
        .message.success { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        
        .message.error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-number.total { color: #667eea; }
        .stat-number.active { color: #28a745; }
        .stat-number.blocked { color: #dc3545; }
        .stat-number.today { color: #ffc107; }
        .stat-number.month { color: #6f42c1; }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .add-form {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .add-form h3 { 
            color: #333; 
            margin-bottom: 20px; 
            text-align: center; 
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group { 
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus { 
            outline: none; 
            border-color: #667eea; 
        }
        
        .btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 200px;
            margin: 0 auto;
            display: block;
        }
        
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3); 
        }
        
        .users-table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .users-table h3 {
            background: #333;
            color: white;
            padding: 20px;
            margin: 0;
            text-align: center;
        }
        
        .table-container {
            width: 100%;
            overflow-x: auto;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse;
            min-width: 700px;
        }
        
        th, td { 
            padding: 12px; 
            text-align: center; 
            border-bottom: 1px solid #eee;
            word-wrap: break-word;
        }
        
        th { 
            background: #f8f9fa; 
            color: #333; 
            font-weight: bold; 
        }
        
        .btn-delete, .btn-block, .btn-unblock {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin: 2px;
            display: inline-block;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-block {
            background: #fd7e14;
            color: white;
        }
        
        .btn-block:hover {
            background: #e66100;
        }
        
        .btn-unblock {
            background: #28a745;
            color: white;
        }
        
        .btn-unblock:hover {
            background: #218838;
        }
        
        .user-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-blocked {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-count {
            background: #fff3cd;
            color: #856404;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-top: 4px;
            display: inline-block;
        }
        
        .empty-state { 
            text-align: center; 
            padding: 50px; 
            color: #666; 
            font-size: 18px; 
        }
        
        @media (max-width: 768px) {
            body { padding: 10px; }
            .nav-links { flex-direction: column; align-items: center; }
            .nav-links a { width: 100%; max-width: 200px; text-align: center; }
            .form-grid { grid-template-columns: 1fr; }
            th, td { padding: 8px; font-size: 12px; }
            .btn-delete, .btn-block, .btn-unblock { font-size: 10px; padding: 4px 8px; }
            
            .admin-links-grid {
                flex-direction: column;
                align-items: center;
            }
            
            .admin-link {
                width: 90%;
                justify-content: center;
                padding: 12px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 إدارة المستخدمين</h1>
            <div class="nav-links">
                <a href="admin_dashboard.php">🏠 الرئيسية</a>
                <a href="logout.php">🚪 خروج</a>
            </div>
        </div>

        <!-- 🆕 قسم الوصول السريع للإدارة -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="admin-quick-access">
            <h3><i class="fas fa-rocket"></i> الوصول السريع للإدارة</h3>
            <div class="admin-links-grid">
                <a href="admin_products.php" class="admin-link products">
                    <i class="fas fa-boxes"></i>
                    <span>إدارة المنتجات</span>
                </a>
                <a href="admin_discounts.php" class="admin-link discounts">
                    <i class="fas fa-tags"></i>
                    <span>إدارة الخصومات</span>
                </a>
                <a href="admin_orders.php" class="admin-link orders">
                    <i class="fas fa-shopping-cart"></i>
                    <span>إدارة الطلبات</span>
                </a>
                <a href="admin_categories.php" class="admin-link categories">
                    <i class="fas fa-list"></i>
                    <span>إدارة الفئات</span>
                </a>
                <a href="admin_points_settings.php" class="admin-link points-settings">
                    <i class="fas fa-cogs"></i>
                    <span>إعدادات النقاط</span>
                </a>
                
                <a href="admin_customers_points.php" class="admin-link customers-points">
                    <i class="fas fa-users-cog"></i>
                    <span>نقاط العملاء</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- إحصائيات المستخدمين -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number total"><?= $total_users ?></div>
                <div class="stat-label">إجمالي المستخدمين</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number active"><?= $active_users ?></div>
                <div class="stat-label">مستخدمين فعالين</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number blocked"><?= $blocked_users ?></div>
                <div class="stat-label">مستخدمين محظورين</div>
            </div>
            
            <?php if (columnExists($conn, 'users', 'created_at')): ?>
            <div class="stat-card">
                <div class="stat-number today"><?= $today_users ?></div>
                <div class="stat-label">مستخدمين اليوم</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number month"><?= $this_month_users ?></div>
                <div class="stat-label">مستخدمين هذا الشهر</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- نموذج إضافة مستخدم جديد -->
        <div class="add-form">
            <h3>➕ إضافة مستخدم جديد</h3>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم المستخدم:</label>
                        <input type="text" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label>البريد الإلكتروني:</label>
                        <input type="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label>كلمة المرور:</label>
                        <input type="password" name="password" required>
                    </div>
                </div>
                
                <button type="submit" name="add_user" class="btn">➕ إضافة المستخدم</button>
            </form>
        </div>
        
        <!-- جدول المستخدمين -->
        <div class="users-table">
            <h3>📋 قائمة المستخدمين</h3>
            <?php
            // بناء الاستعلام الآمن للأعمدة الموجودة
            $query = "SELECT u.id, u.username, u.email, u.is_blocked";
            if (columnExists($conn, 'users', 'created_at')) {
                $query .= ", u.created_at";
            }
            // إضافة عدد الطلبات لكل مستخدم
            $query .= ", (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count";
            $query .= " FROM users u ORDER BY u.id DESC";
            
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0):
            ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>اسم المستخدم</th>
                            <th>البريد الإلكتروني</th>
                            <?php if (columnExists($conn, 'users', 'created_at')): ?>
                            <th>تاريخ التسجيل</th>
                            <?php endif; ?>
                            <th>الحالة</th>
                            <th>عدد الطلبات</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <?php if (columnExists($conn, 'users', 'created_at')): ?>
                            <td>
                                <?= isset($user['created_at']) ? date('Y-m-d', strtotime($user['created_at'])) : 'غير محدد' ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <?php if ($user['is_blocked']): ?>
                                    <span class="user-status status-blocked">🚫 محظور</span>
                                <?php else: ?>
                                    <span class="user-status status-active">✅ فعال</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $user['order_count'] ?>
                                <?php if ($user['order_count'] > 0): ?>
                                    <div class="order-count">⚠️ له طلبات</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_blocked']): ?>
                                    <a href="?block=<?= $user['id'] ?>" class="btn-unblock" 
                                       onclick="return confirm('هل تريد إلغاء حظر هذا المستخدم؟')">
                                        🔓 إلغاء الحظر
                                    </a>
                                <?php else: ?>
                                    <a href="?block=<?= $user['id'] ?>" class="btn-block" 
                                       onclick="return confirm('هل تريد حظر هذا المستخدم؟')">
                                        🚫 حظر
                                    </a>
                                <?php endif; ?>
                                
                                <a href="?delete=<?= $user['id'] ?>" class="btn-delete">
                                    🗑️ حذف
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    👥 لا يوجد مستخدمين حتى الآن<br>
                    <small>ابدأ بإضافة مستخدمك الأول!</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
