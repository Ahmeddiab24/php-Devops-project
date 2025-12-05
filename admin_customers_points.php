<?php
session_start();
include 'db.php';
include_once 'simple_points.php';

// 🔐 فحص صلاحيات الأدمن
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit("⛔ غير مصرح لك بالوصول لهذه الصفحة!");
}

$points_system = new SimplePoints($conn);

// معالجة إضافة/خصم النقاط
$success_message = "";
$error_message = "";

if (isset($_POST['modify_points'])) {
    $user_id = intval($_POST['user_id']);
    $points_amount = intval($_POST['points_amount']);
    $action_type = $_POST['action_type'];
    $reason = trim($_POST['reason']);
    
    if (empty($reason)) {
        $reason = $action_type == 'add' ? 'إضافة نقاط من الإدارة' : 'خصم نقاط من الإدارة';
    }
    
    if ($user_id > 0 && $points_amount > 0) {
        if ($action_type == 'add') {
            $result = $points_system->addPoints($user_id, $points_amount, $reason);
            $success_message = "✅ تم إضافة " . number_format($points_amount) . " نقطة للعميل بنجاح!";
        } else {
            $result = $points_system->spendPoints($user_id, $points_amount, $reason);
            if ($result['success']) {
                $success_message = "✅ تم خصم " . number_format($points_amount) . " نقطة من العميل بنجاح!";
            } else {
                $error_message = "❌ " . $result['message'];
            }
        }
    } else {
        $error_message = "❌ يرجى إدخال بيانات صحيحة!";
    }
}

// 🔧 جلب بيانات العملاء مع النقاط - محسن للأداء
$customers_query = "SELECT u.id, u.username, u.email, u.is_blocked,
                    COALESCE(cp.current_points, 0) as current_points,
                    COALESCE(cp.total_earned, 0) as total_earned,
                    COALESCE(cp.total_spent, 0) as total_spent,
                    cp.created_at as points_created_at
                    FROM users u 
                    LEFT JOIN customer_points cp ON u.id = cp.user_id
                    ORDER BY cp.current_points DESC, u.username ASC
                    LIMIT 100";
$customers_result = $conn->query($customers_query);

// إحصائيات محسنة للأداء
$stats_query = "SELECT 
    COUNT(DISTINCT cp.user_id) as customers_with_points,
    COALESCE(SUM(cp.current_points), 0) as total_active_points,
    COALESCE(SUM(cp.total_earned), 0) as total_earned_points,
    COALESCE(SUM(cp.total_spent), 0) as total_spent_points,
    COALESCE(AVG(cp.current_points), 0) as avg_points_per_customer
FROM customer_points cp WHERE cp.current_points > 0";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'customers_with_points' => 0,
    'total_active_points' => 0,
    'total_earned_points' => 0,
    'total_spent_points' => 0,
    'avg_points_per_customer' => 0
];

// جلب المستخدمين للقائمة المنسدلة - بدون فلتر role
$users_query = "SELECT id, username, email, is_blocked FROM users 
                WHERE is_blocked = 0 
                ORDER BY username 
                LIMIT 200";
$all_users_result = $conn->query($users_query);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 إدارة نقاط العملاء - بَهيّ للعطور</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            --gradient-warning: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            --gradient-info: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
            margin: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 900;
        }

        .nav-links {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .nav-links a {
            padding: 12px 25px;
            background: var(--gradient-primary);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .nav-links a.active {
            background: var(--gradient-warning);
            color: #333;
        }

        /* قسم الوصول السريع للإدارة */
        .admin-quick-access {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 15px 40px var(--shadow-color);
            backdrop-filter: blur(10px);
        }

        .admin-quick-access h3 {
            color: #333;
            font-size: 18px;
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
            gap: 12px;
        }

        .admin-link {
            background: var(--gradient-primary);
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

        .admin-link.products { background: var(--gradient-success); }
        .admin-link.customers { background: var(--gradient-info); }
        .admin-link.orders { background: var(--gradient-danger); }
        .admin-link.categories { background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%); }
        .admin-link.points-settings { background: var(--gradient-warning); }
        .admin-link.points-reports { background: linear-gradient(135deg, #e83e8c 0%, #d91a72 100%); }
        .admin-link.discounts { background: linear-gradient(135deg, #fd7e14 0%, #e66100 100%); }

        .message {
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 700;
            font-size: 16px;
        }

        .success {
            background: rgba(86, 171, 47, 0.9);
            color: white;
            box-shadow: 0 8px 25px rgba(86, 171, 47, 0.3);
        }

        .error {
            background: rgba(255, 107, 107, 0.9);
            color: white;
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px var(--shadow-color);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            border-left: 5px solid #667eea;
        }

        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
        }

        .stat-card .icon {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 32px;
            font-weight: 900;
            color: #667eea;
            margin-bottom: 8px;
        }

        .stat-card p {
            color: #666;
            font-size: 14px;
            font-weight: 600;
        }

        .content-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 40px var(--shadow-color);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .modify-points-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-right: 5px solid #667eea;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            color: #333;
            font-weight: 700;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            background: white;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            background: var(--gradient-success);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(86, 171, 47, 0.3);
        }

        .btn-danger {
            background: var(--gradient-danger);
        }

        .btn-danger:hover {
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
        }

        /* Print Button */
        .print-buttons {
            display: flex;
            gap: 10px;
        }

        .print-btn {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 25px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .customers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .customers-table th {
            background: var(--gradient-primary);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
        }

        .customers-table td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-weight: 500;
        }

        .customers-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .points-badge {
            background: var(--gradient-info);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 12px;
            box-shadow: 0 4px 12px rgba(116, 185, 255, 0.3);
        }

        .points-badge.high {
            background: var(--gradient-success);
            box-shadow: 0 4px 12px rgba(86, 171, 47, 0.3);
        }

        .points-badge.medium {
            background: var(--gradient-warning);
            color: #333;
            box-shadow: 0 4px 12px rgba(255, 234, 167, 0.3);
        }

        .points-badge.zero {
            background: #ccc;
            color: #666;
            box-shadow: 0 4px 12px rgba(204, 204, 204, 0.3);
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-badge.blocked {
            background: #fee2e2;
            color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            color: #9ca3af;
        }

        .empty-state .icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.4;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            margin: 0;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                flex-direction: column;
                align-items: center;
            }

            .admin-links-grid {
                flex-direction: column;
                align-items: center;
            }
            
            .admin-link {
                width: 90%;
                justify-content: center;
                padding: 12px 20px;
            }

            .customers-table {
                font-size: 12px;
            }

            .customers-table th,
            .customers-table td {
                padding: 8px;
            }

            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .print-buttons {
                align-self: center;
            }
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 إدارة نقاط العملاء</h1>
            <p>إدارة النقاط لجميع عملاء متجر بَهيّ للعطور</p>
            <div class="nav-links">
                <a href="admin_dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
            </div>
        </div>

        <!-- قسم الوصول السريع للإدارة -->
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
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="message success fade-in"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error fade-in"><?= $error_message ?></div>
        <?php endif; ?>

        <!-- إحصائيات سريعة -->
        <div class="stats-grid">
            <div class="stat-card fade-in">
                <div class="icon"><i class="fas fa-users"></i></div>
                <h3><?= number_format($stats['customers_with_points']) ?></h3>
                <p>عملاء لديهم نقاط</p>
            </div>
            <div class="stat-card fade-in">
                <div class="icon"><i class="fas fa-coins"></i></div>
                <h3><?= number_format($stats['total_active_points']) ?></h3>
                <p>إجمالي النقاط النشطة</p>
            </div>
            <div class="stat-card fade-in">
                <div class="icon"><i class="fas fa-gift"></i></div>
                <h3><?= number_format($stats['total_earned_points']) ?></h3>
                <p>إجمالي النقاط المكتسبة</p>
            </div>
            <div class="stat-card fade-in">
                <div class="icon"><i class="fas fa-shopping-bag"></i></div>
                <h3><?= number_format($stats['total_spent_points']) ?></h3>
                <p>إجمالي النقاط المستبدلة</p>
            </div>
            <div class="stat-card fade-in">
                <div class="icon"><i class="fas fa-chart-line"></i></div>
                <h3><?= number_format($stats['avg_points_per_customer']) ?></h3>
                <p>متوسط النقاط لكل عميل</p>
            </div>
        </div>

        <div class="content-section fade-in">
            <!-- نموذج إضافة/خصم النقاط -->
            <div class="modify-points-form">
                <h3>⚡ إضافة أو خصم نقاط العملاء</h3>
                <p style="color: #666; margin-bottom: 20px;">يمكنك إضافة أو خصم النقاط من حساب أي عميل نشط</p>
                
                <form method="post" id="pointsForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="user_id">العميل:</label>
                            <select name="user_id" id="user_id" required>
                                <option value="">اختر العميل</option>
                                <?php
                                if ($all_users_result && $all_users_result->num_rows > 0) {
                                    while ($user = $all_users_result->fetch_assoc()):
                                ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['username'] ?? $user['email']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                    </option>
                                <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="action_type">نوع العملية:</label>
                            <select name="action_type" id="action_type" required>
                                <option value="add">إضافة نقاط</option>
                                <option value="subtract">خصم نقاط</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="points_amount">عدد النقاط:</label>
                            <input type="number" name="points_amount" id="points_amount" 
                                   min="1" max="10000" required placeholder="مثال: 100">
                        </div>
                        
                        <div class="form-group">
                            <label for="reason">السبب (اختياري):</label>
                            <input type="text" name="reason" id="reason" 
                                   placeholder="مثال: مكافأة خاصة أو تصحيح خطأ">
                        </div>
                    </div>
                    
                    <button type="submit" name="modify_points" class="btn" id="submitBtn">
                        <i class="fas fa-plus-circle"></i> إضافة النقاط
                    </button>
                </form>
            </div>

            <!-- قائمة العملاء -->
            <div class="table-header">
                <h3>📋 قائمة أفضل العملاء ونقاطهم (أعلى 100)</h3>
                <div class="print-buttons">
                    <button class="print-btn" onclick="printCustomersReport()">
                        <i class="fas fa-print"></i>
                        طباعة التقرير
                    </button>
                </div>
            </div>
            
            <?php if ($customers_result && $customers_result->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="customers-table">
                        <thead>
                            <tr>
                                <th>الترتيب</th>
                                <th>رقم العميل</th>
                                <th>اسم المستخدم</th>
                                <th>البريد الإلكتروني</th>
                                <th>حالة الحساب</th>
                                <th>النقاط الحالية</th>
                                <th>النقاط المكتسبة</th>
                                <th>النقاط المستبدلة</th>
                                <th>معدل الاستخدام</th>
                                <th>تاريخ الانضمام للنقاط</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            while ($customer = $customers_result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><strong><?= $rank++ ?></strong></td>
                                <td><strong>#<?= htmlspecialchars($customer['id']) ?></strong></td>
                                <td><?= htmlspecialchars($customer['username'] ?? 'غير محدد') ?></td>
                                <td><?= htmlspecialchars($customer['email']) ?></td>
                                <td>
                                    <?php if ($customer['is_blocked'] == 1): ?>
                                        <span class="status-badge blocked">محظور</span>
                                    <?php else: ?>
                                        <span class="status-badge active">نشط</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $current_points = intval($customer['current_points']);
                                    $badge_class = $current_points == 0 ? 'zero' : ($current_points >= 1000 ? 'high' : ($current_points >= 500 ? 'medium' : ''));
                                    ?>
                                    <span class="points-badge <?= $badge_class ?>">
                                        <?= number_format($current_points) ?> نقطة
                                    </span>
                                </td>
                                <td><?= number_format(intval($customer['total_earned'])) ?></td>
                                <td><?= number_format(intval($customer['total_spent'])) ?></td>
                                <td>
                                    <?php 
                                    $usage_rate = $customer['total_earned'] > 0 ? ($customer['total_spent'] / $customer['total_earned']) * 100 : 0;
                                    ?>
                                    <strong><?= number_format($usage_rate, 1) ?>%</strong>
                                </td>
                                <td>
                                    <?= $customer['points_created_at'] 
                                        ? date('d/m/Y', strtotime($customer['points_created_at'])) 
                                        : 'لم ينضم بعد' ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <h3>لا يوجد عملاء مسجلين حتى الآن</h3>
                    <p>عندما يسجل العملاء ويحصلون على نقاط، ستظهر بياناتهم هنا</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            const form = document.getElementById('pointsForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function() {
                submitBtn.classList.add('loading');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري المعالجة...';
            });
        });

        document.getElementById('action_type').addEventListener('change', function() {
            const btn = document.getElementById('submitBtn');
            if (this.value === 'subtract') {
                btn.className = 'btn btn-danger';
                btn.innerHTML = '<i class="fas fa-minus-circle"></i> خصم النقاط';
            } else {
                btn.className = 'btn';
                btn.innerHTML = '<i class="fas fa-plus-circle"></i> إضافة النقاط';
            }
        });

        document.getElementById('pointsForm').addEventListener('submit', function(e) {
            const actionType = document.getElementById('action_type').value;
            const pointsAmount = document.getElementById('points_amount').value;
            const userName = document.getElementById('user_id').selectedOptions[0].text;
            
            const actionText = actionType === 'add' ? 'إضافة' : 'خصم';
            const confirmMessage = `هل أنت متأكد من ${actionText} ${pointsAmount} نقطة ${actionType === 'add' ? 'إلى' : 'من'} ${userName}؟`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });

        // طباعة تقرير العملاء والنقاط - جذابة ومتطورة
        function printCustomersReport() {
            const printWindow = window.open('', '_blank');
            const table = document.querySelector('.customers-table').outerHTML;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <meta charset="utf-8">
                    <title>تقرير نقاط العملاء</title>
                    <style>
                        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
                        
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        
                        body {
                            font-family: 'Cairo', Arial, sans-serif;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: #333;
                            padding: 40px;
                            min-height: 100vh;
                        }
                        
                        .report-container {
                            background: white;
                            border-radius: 20px;
                            padding: 40px;
                            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
                            max-width: 1400px;
                            margin: 0 auto;
                        }
                        
                        .report-header {
                            text-align: center;
                            margin-bottom: 40px;
                            padding-bottom: 30px;
                            border-bottom: 3px solid #667eea;
                        }
                        
                        .report-header h1 {
                            font-size: 2.5rem;
                            color: #1f2937;
                            margin-bottom: 10px;
                            font-weight: 800;
                        }
                        
                        .report-header p {
                            color: #6b7280;
                            font-size: 1.1rem;
                            font-weight: 600;
                        }
                        
                        .report-info {
                            background: #f8fafc;
                            padding: 20px;
                            border-radius: 12px;
                            margin-bottom: 30px;
                            text-align: center;
                        }
                        
                        .report-info strong {
                            color: #667eea;
                            font-size: 1.1rem;
                        }
                        
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 20px 0;
                            border-radius: 12px;
                            overflow: hidden;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                        }
                        
                        th {
                            background: linear-gradient(135deg, #667eea, #764ba2);
                            color: white;
                            padding: 18px 12px;
                            text-align: center;
                            font-weight: 700;
                            font-size: 14px;
                        }
                        
                        td {
                            padding: 15px 12px;
                            text-align: center;
                            border-bottom: 1px solid #e5e7eb;
                            color: #374151;
                            font-weight: 500;
                        }
                        
                        tbody tr:nth-child(even) {
                            background: #f9fafb;
                        }
                        
                        .points-badge {
                            display: inline-block;
                            padding: 6px 12px;
                            border-radius: 20px;
                            font-size: 12px;
                            font-weight: 700;
                            color: white;
                            background: #3b82f6;
                        }
                        
                        .points-badge.high { background: #10b981; }
                        .points-badge.medium { background: #f59e0b; color: #1f2937; }
                        .points-badge.zero { background: #6b7280; }
                        
                        .status-badge {
                            padding: 4px 8px;
                            border-radius: 15px;
                            font-size: 10px;
                            font-weight: 700;
                        }
                        
                        .status-badge.active {
                            background: #dcfce7;
                            color: #16a34a;
                        }
                        
                        .status-badge.blocked {
                            background: #fee2e2;
                            color: #dc2626;
                        }
                        
                        .report-footer {
                            margin-top: 40px;
                            text-align: center;
                            color: #9ca3af;
                            font-size: 14px;
                            padding-top: 20px;
                            border-top: 1px solid #e5e7eb;
                        }
                        
                        @media print {
                            body { background: white !important; padding: 20px !important; }
                            .report-container { box-shadow: none !important; }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-container">
                        <div class="report-header">
                            <h1>👥 تقرير نقاط العملاء</h1>
                            <p>متجر بَهيّ للعطور - نظام إدارة نقاط الولاء</p>
                        </div>
                        
                        <div class="report-info">
                            <strong>تاريخ التقرير:</strong> ${new Date().toLocaleDateString('ar-EG', {
                                weekday: 'long',
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric'
                            })}
                        </div>
                        
                        ${table}
                        
                        <div class="report-footer">
                            <p>© ${new Date().getFullYear()} متجر بَهيّ للعطور - جميع الحقوق محفوظة</p>
                            <p>تم إنشاء هذا التقرير بواسطة نظام إدارة النقاط</p>
                        </div>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        console.log('🔥 صفحة إدارة نقاط العملاء جاهزة مع الطباعة الجذابة!');
    </script>
</body>
</html>
