<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

// فحص وإنشاء الجداول المطلوبة
$discount_types_check = $conn->query("SHOW TABLES LIKE 'discount_types'");
$discounts_check = $conn->query("SHOW TABLES LIKE 'discounts'");

if ($discount_types_check->num_rows == 0) {
    $conn->query("CREATE TABLE discount_types (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        type ENUM('coupon') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $conn->query("INSERT INTO discount_types (name, description, type) VALUES 
        ('كوبون خصم', 'كود خصم يدخله العميل', 'coupon')");
}

// معالجة إضافة كوبون جديد
if (isset($_POST['add_coupon'])) {
    $name = trim($_POST['name']);
    $value = floatval($_POST['value']);
    $type = $_POST['type']; // percentage أو fixed
    $coupon_code = strtoupper(trim($_POST['coupon_code']));
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $usage_limit = empty($_POST['usage_limit']) ? NULL : intval($_POST['usage_limit']);
    $min_amount = floatval($_POST['min_amount']) ?: 0;
    $max_amount = empty($_POST['max_discount']) ? NULL : floatval($_POST['max_discount']);

    if (!$name || !$value || !$coupon_code || !$start_date || !$end_date || !$type) {
        $message = "❌ يرجى ملء جميع الحقول المطلوبة!";
        $message_type = "error";
    } else {
        // فحص تفرد الكوبون
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM discounts WHERE coupon_code = ?");
        $check_stmt->bind_param("s", $coupon_code);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc()['count'] > 0;

        if ($exists) {
            $message = "❌ هذا الكوبون موجود بالفعل!";
            $message_type = "error";
        } else {
            // إنشاء اسم مفصل
            $display_value = $type == 'percentage' ? $value . "%" : number_format($value, 2) . " ج.م";
            $full_name = $name . " (" . $display_value . ")";

            // تعريف المتغيرات صراحة للـ binding
            $discount_type_id = 1;
            $usage_limit_param = $usage_limit;
            $min_amount_param = $min_amount;
            $max_amount_param = $max_amount;

            $stmt = $conn->prepare("INSERT INTO discounts (name, discount_type_id, value, coupon_code, start_date, end_date, usage_limit, min_amount, max_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sidsssiii", $full_name, $discount_type_id, $value, $coupon_code, $start_date, $end_date, $usage_limit_param, $min_amount_param, $max_amount_param);

            if ($stmt->execute()) {
                $message = "✅ تم إضافة الكوبون '$coupon_code' بنجاح!";
                $message_type = "success";
            } else {
                $message = "❌ حدث خطأ أثناء إضافة الكوبون!";
                $message_type = "error";
            }
        }
    }
}

// معالجة تغيير حالة الكوبون
if (isset($_POST['toggle_status'])) {
    $coupon_id = intval($_POST['coupon_id']);
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE discounts SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $coupon_id);
    
    if ($stmt->execute()) {
        $status_text = $new_status == 'active' ? 'تفعيل' : 'إيقاف';
        $message = "✅ تم $status_text الكوبون بنجاح!";
        $message_type = "success";
    }
}

// معالجة حذف كوبون
if (isset($_POST['delete_coupon'])) {
    $coupon_id = intval($_POST['coupon_id']);
    
    $stmt = $conn->prepare("DELETE FROM discounts WHERE id = ?");
    $stmt->bind_param("i", $coupon_id);
    
    if ($stmt->execute()) {
        $message = "🗑️ تم حذف الكوبون بنجاح!";
        $message_type = "success";
    } else {
        $message = "❌ حدث خطأ أثناء حذف الكوبون!";
        $message_type = "error";
    }
}

// جلب الكوبونات
$coupons = $conn->query("SELECT * FROM discounts WHERE coupon_code IS NOT NULL ORDER BY created_at DESC");

// حساب الإحصائيات
$stats_result = $conn->query("SELECT 
    COUNT(*) as total_coupons,
    SUM(CASE WHEN status = 'active' AND end_date >= CURDATE() THEN 1 ELSE 0 END) as active_coupons,
    SUM(used_count) as total_uses,
    AVG(value) as avg_discount
    FROM discounts WHERE coupon_code IS NOT NULL");
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✨ إدارة كوبونات الخصم - بَهيّ للعطور</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* ألوان محسنة ومتناسقة */
            --primary: #667eea;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --secondary: #ec4899;
            --secondary-light: #f472b6;
            --accent: #06b6d4;
            --accent-light: #22d3ee;
            --success: #10b981;
            --success-light: #34d399;
            --danger: #ef4444;
            --danger-light: #f87171;
            --warning: #f59e0b;
            --warning-light: #fbbf24;
            
            /* ألوان محايدة */
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --white: #ffffff;
            
            /* تدرجات لونية */
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            --gradient-secondary: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-light) 100%);
            --gradient-success: linear-gradient(135deg, var(--success) 0%, var(--success-light) 100%);
            --gradient-danger: linear-gradient(135deg, var(--danger) 0%, var(--danger-light) 100%);
            --gradient-warning: linear-gradient(135deg, var(--warning) 0%, var(--warning-light) 100%);
            --gradient-bg: linear-gradient(135deg, #f8fafc 0%, #e6ecf4 25%, #ddd6fe 50%, #fdf4ff 75%, #f8fafc 100%);
            
            /* ظلال */
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 20px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 15px 35px rgba(0, 0, 0, 0.15);
            --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--gradient-bg);
            color: var(--gray-800);
            direction: rtl;
            min-height: 100vh;
            padding: 15px;
            font-weight: 500;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 10px;
        }

        /* زر العودة */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--white);
            color: var(--primary);
            text-decoration: none;
            border-radius: 15px;
            font-weight: 700;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            font-size: 14px;
        }

        .back-btn:hover {
            background: var(--gray-50);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: var(--primary-dark);
        }

        /* قسم الوصول السريع للإدارة */
        .admin-quick-access {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 18px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .admin-quick-access h3 {
            color: var(--gray-800);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 15px;
            text-align: center;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .admin-links-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }

        .admin-link {
            background: var(--gradient-primary);
            color: var(--white);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 8px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .admin-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.5);
            color: var(--white);
        }

        .admin-link i {
            font-size: 12px;
        }

        /* ألوان متناسقة مع تصميم الصفحة */
        .admin-link.products { background: var(--gradient-success); }
        .admin-link.customers { background: var(--gradient-secondary); }
        .admin-link.orders { background: var(--gradient-warning); }
        .admin-link.categories { background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%); }
        .admin-link.points-settings { background: var(--gradient-primary); }
        .admin-link.points-reports { background: var(--gradient-danger); }
        .admin-link.customers-points { background: linear-gradient(135deg, #16a085, #48c9b0); }

        /* الهيدر */
        .header {
            background: var(--gradient-primary);
            color: var(--white);
            padding: 40px 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.95;
            font-weight: 400;
            max-width: 600px;
            margin: 0 auto;
        }

        /* الإحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--white);
            padding: 25px;
            border-radius: 18px;
            text-align: center;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            color: var(--white);
        }

        .stat-icon.primary { background: var(--gradient-primary); }
        .stat-icon.success { background: var(--gradient-success); }
        .stat-icon.warning { background: var(--gradient-warning); }
        .stat-icon.secondary { background: var(--gradient-secondary); }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--gray-800);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--gray-600);
            font-weight: 600;
            font-size: 14px;
        }

        /* البطاقات */
        .card {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* النماذج */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: var(--gray-700);
            font-size: 14px;
        }

        .form-label.required::after {
            content: '*';
            color: var(--danger);
            margin-right: 5px;
            font-weight: 800;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-300);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            background: var(--white);
            color: var(--gray-800);
            transition: all 0.3s ease;
            font-family: 'Cairo', sans-serif;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: var(--gray-50);
        }

        /* الأزرار */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-family: 'Cairo', sans-serif;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: var(--white);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.5);
        }

        .btn-success {
            background: var(--gradient-success);
            color: var(--white);
        }

        .btn-warning {
            background: var(--gradient-warning);
            color: var(--white);
        }

        .btn-danger {
            background: var(--gradient-danger);
            color: var(--white);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }

        /* الرسائل */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            border-right: 4px solid;
            font-size: 14px;
        }

        .alert-success {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-color: var(--success);
            color: #065f46;
        }

        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            border-color: var(--danger);
            color: #991b1b;
        }

        /* الجداول */
        .table-container {
            overflow-x: auto;
            border: 1px solid var(--gray-200);
            border-radius: 15px;
            background: var(--white);
            box-shadow: var(--shadow);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .table th, .table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid var(--gray-200);
            font-weight: 500;
            font-size: 13px;
        }

        .table th {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            font-weight: 800;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.02) 0%, rgba(236, 72, 153, 0.02) 100%);
        }

        .coupon-code {
            background: var(--gradient-primary);
            color: var(--white);
            padding: 6px 12px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-weight: 800;
            letter-spacing: 1px;
            font-size: 12px;
            box-shadow: 0 3px 8px rgba(102, 126, 234, 0.3);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 25px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: var(--gradient-success);
            color: var(--white);
        }

        .status-inactive {
            background: var(--gradient-danger);
            color: var(--white);
        }

        .status-expired {
            background: var(--gradient-warning);
            color: var(--white);
        }

        .actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
            color: var(--gray-400);
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--gray-700);
        }

        .value-display {
            font-weight: 800;
            font-size: 14px;
        }

        .value-percentage {
            color: var(--success);
        }

        .value-fixed {
            color: var(--primary);
        }

        /* الحركات */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card, .stat-card {
            animation: slideInUp 0.5s ease-out;
        }

        /* التصميم المتجاوب */
        @media (max-width: 768px) {
            .container {
                padding: 0 5px;
            }

            .header {
                padding: 25px 20px;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .actions {
                flex-direction: column;
                gap: 5px;
            }

            .table th, .table td {
                padding: 8px 6px;
                font-size: 11px;
            }

            .card {
                padding: 20px;
            }

            .admin-links-grid {
                flex-direction: column;
                align-items: center;
            }
            
            .admin-link {
                width: 90%;
                justify-content: center;
                padding: 10px 16px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- زر العودة -->
        <a href="admin_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-right"></i>
            العودة لوحة التحكم
        </a>

        <!-- قسم الوصول السريع للإدارة -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="admin-quick-access">
            <h3><i class="fas fa-rocket"></i> الوصول السريع للإدارة</h3>
            <div class="admin-links-grid">
                <a href="admin_products.php" class="admin-link products">
                    <i class="fas fa-boxes"></i>
                    <span>إدارة المنتجات</span>
                </a>
                <a href="admin_orders.php" class="admin-link orders">
                    <i class="fas fa-shopping-cart"></i>
                    <span>إدارة الطلبات</span>
                </a>
                <a href="admin_customers.php" class="admin-link customers">
                    <i class="fas fa-users"></i>
                    <span>إدارة العملاء</span>
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

        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <h1>✨ إدارة كوبونات الخصم</h1>
                <p>منصة متطورة لإنشاء وإدارة كوبونات الخصم الذكية لتحفيز المبيعات وإسعاد العملاء</p>
            </div>
        </div>

        <!-- رسائل التنبيه -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-number"><?= $stats['total_coupons'] ?? 0 ?></div>
                <div class="stat-label">إجمالي الكوبونات</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?= $stats['active_coupons'] ?? 0 ?></div>
                <div class="stat-label">الكوبونات النشطة</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number"><?= $stats['total_uses'] ?? 0 ?></div>
                <div class="stat-label">مرات الاستخدام</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['avg_discount'] ?? 0, 1) ?></div>
                <div class="stat-label">متوسط قيمة الخصم</div>
            </div>
        </div>

        <!-- إضافة كوبون جديد -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-plus-circle"></i>
                إضافة كوبون خصم جديد
            </h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">اسم الكوبون</label>
                        <input type="text" name="name" class="form-input" 
                               placeholder="مثال: خصم الجمعة البيضاء" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">كود الكوبون</label>
                        <input type="text" name="coupon_code" class="form-input" 
                               placeholder="مثال: SAVE20" maxlength="15" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">نوع الخصم</label>
                        <select name="type" class="form-select" required>
                            <option value="">اختر نوع الخصم</option>
                            <option value="percentage">نسبة مئوية (%)</option>
                            <option value="fixed">مبلغ ثابت (ج.م)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">قيمة الخصم</label>
                        <input type="number" name="value" class="form-input" 
                               step="0.01" min="0.01" placeholder="مثال: 20" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الحد الأدنى للمبلغ</label>
                        <input type="number" name="min_amount" class="form-input" 
                               step="0.01" min="0" placeholder="مثال: 100.00">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الحد الأقصى للخصم</label>
                        <input type="number" name="max_discount" class="form-input" 
                               step="0.01" min="0" placeholder="للنسبة المئوية فقط">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">عدد مرات الاستخدام</label>
                        <input type="number" name="usage_limit" class="form-input" 
                               min="1" placeholder="اتركه فارغاً للاستخدام المفتوح">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">تاريخ البداية</label>
                        <input type="date" name="start_date" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">تاريخ النهاية</label>
                        <input type="date" name="end_date" class="form-input" required>
                    </div>
                </div>
                
                <button type="submit" name="add_coupon" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    إضافة الكوبون
                </button>
            </form>
        </div>

        <!-- جدول الكوبونات -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                قائمة الكوبونات المتاحة
            </h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>اسم الكوبون</th>
                            <th>الكود</th>
                            <th>القيمة</th>
                            <th>الحد الأدنى</th>
                            <th>فترة الصلاحية</th>
                            <th>الاستخدام</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($coupons && $coupons->num_rows > 0): ?>
                            <?php while ($coupon = $coupons->fetch_assoc()): ?>
                            <?php
                            // تحديد نوع الخصم من الاسم
                            $is_percentage = strpos($coupon['name'], '%') !== false;
                            $current_date = date('Y-m-d');
                            $is_expired = $coupon['end_date'] < $current_date;
                            $status_class = $is_expired ? 'expired' : $coupon['status'];
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($coupon['name']) ?></strong></td>
                                <td>
                                    <span class="coupon-code"><?= htmlspecialchars($coupon['coupon_code']) ?></span>
                                </td>
                                <td>
                                    <span class="value-display <?= $is_percentage ? 'value-percentage' : 'value-fixed' ?>">
                                        <?= $is_percentage ? $coupon['value'] . '%' : number_format($coupon['value'], 2) . ' ج.م' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $coupon['min_amount'] > 0 ? number_format($coupon['min_amount'], 2) . ' ج.م' : '-' ?>
                                </td>
                                <td>
                                    <small style="line-height: 1.4;">
                                        <strong>من:</strong> <?= date('Y/m/d', strtotime($coupon['start_date'])) ?><br>
                                        <strong>إلى:</strong> <?= date('Y/m/d', strtotime($coupon['end_date'])) ?>
                                        <?php if ($is_expired): ?>
                                            <br><span style="color: var(--danger); font-weight: bold; font-size: 11px;">منتهي الصلاحية</span>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <strong style="color: var(--primary); font-size: 14px;"><?= $coupon['used_count'] ?></strong>
                                    <span style="color: var(--gray-500);"> / </span>
                                    <span style="color: var(--gray-600);"><?= $coupon['usage_limit'] ? $coupon['usage_limit'] : '∞' ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $status_class ?>">
                                        <?php
                                        if ($is_expired) {
                                            echo 'منتهي';
                                        } else {
                                            echo $coupon['status'] == 'active' ? 'نشط' : 'متوقف';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if (!$is_expired): ?>
                                        <!-- تغيير الحالة -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= $coupon['status'] == 'active' ? 'inactive' : 'active' ?>">
                                            <button type="submit" name="toggle_status" 
                                                    class="btn btn-sm <?= $coupon['status'] == 'active' ? 'btn-warning' : 'btn-success' ?>">
                                                <i class="fas fa-<?= $coupon['status'] == 'active' ? 'pause' : 'play' ?>"></i>
                                                <?= $coupon['status'] == 'active' ? 'إيقاف' : 'تفعيل' ?>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <!-- حذف -->
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('⚠️ تأكيد الحذف\n\nهل أنت متأكد من حذف الكوبون: <?= htmlspecialchars($coupon['coupon_code']) ?>؟\n\nهذا الإجراء لا يمكن التراجع عنه!')">
                                            <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                                            <button type="submit" name="delete_coupon" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                                حذف
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-ticket-alt"></i>
                                    <h3>لا توجد كوبونات حتى الآن</h3>
                                    <p>أضف أول كوبون خصم لبدء تشجيع المبيعات!</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // تحويل كود الكوبون لأحرف كبيرة وإزالة المسافات والرموز
        const couponInput = document.querySelector('input[name="coupon_code"]');
        if (couponInput) {
            couponInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
        }

        // إخفاء الرسائل تلقائياً بعد 5 ثوان
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(100px)';
                alert.style.transition = 'all 0.4s ease';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);

        // تحديد تاريخ البداية لليوم الحالي افتراضياً
        const today = new Date().toISOString().split('T')[0];
        const startDateInput = document.querySelector('input[name="start_date"]');
        if (startDateInput && !startDateInput.value) {
            startDateInput.value = today;
        }
        
        // تحديد تاريخ النهاية لشهر من الآن افتراضياً
        const nextMonth = new Date();
        nextMonth.setMonth(nextMonth.getMonth() + 1);
        const endDateInput = document.querySelector('input[name="end_date"]');
        if (endDateInput && !endDateInput.value) {
            endDateInput.value = nextMonth.toISOString().split('T')[0];
        }

        // التحقق من صحة التواريخ
        if (startDateInput) {
            startDateInput.addEventListener('change', function() {
                const startDate = new Date(this.value);
                const minEndDate = new Date(startDate);
                minEndDate.setDate(minEndDate.getDate() + 1);
                
                endDateInput.min = minEndDate.toISOString().split('T')[0];
                
                if (new Date(endDateInput.value) <= startDate) {
                    endDateInput.value = minEndDate.toISOString().split('T')[0];
                }
            });
        }

        // تأثيرات تفاعلية للبطاقات
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });

        // تأثيرات الأزرار
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                if (!this.disabled) {
                    this.style.transform = 'translateY(-1px) scale(1.02)';
                }
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
        });

        console.log('✨ نظام إدارة الكوبونات محسن وجاهز!');
        console.log('📊 الإحصائيات الحالية:', {
            total: <?= $stats['total_coupons'] ?? 0 ?>,
            active: <?= $stats['active_coupons'] ?? 0 ?>,
            uses: <?= $stats['total_uses'] ?? 0 ?>
        });
    </script>
</body>
</html>
