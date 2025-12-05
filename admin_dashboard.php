<?php
include 'admin_check.php';
requireAdmin(); // التحقق من صلاحيات الأدمن

include 'db.php';

// 🆕 تحميل نظام النقاط
include_once 'simple_points.php';
$points_system = new SimplePoints($conn);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'المدير';

// حساب الإحصائيات العامة
$stats = [];

// إحصائيات المنتجات
$products_result = $conn->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $products_result->fetch_assoc()['total'];

// إحصائيات المستخدمين
$users_result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $users_result->fetch_assoc()['total'];

// إحصائيات الطلبات
$orders_result = $conn->query("SELECT COUNT(*) as total, SUM(total) as revenue FROM orders");
$orders_data = $orders_result->fetch_assoc();
$stats['total_orders'] = $orders_data['total'] ?? 0;
$stats['total_revenue'] = $orders_data['revenue'] ?? 0;

// إحصائيات الخصومات (إذا كان الجدول موجود)
$discounts_check = $conn->query("SHOW TABLES LIKE 'discounts'");
if ($discounts_check->num_rows > 0) {
    $discounts_result = $conn->query("
        SELECT 
            COUNT(*) as total_discounts,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_discounts,
            SUM(used_count) as total_uses
        FROM discounts
    ");
    $discounts_data = $discounts_result->fetch_assoc();
    $stats['total_discounts'] = $discounts_data['total_discounts'] ?? 0;
    $stats['active_discounts'] = $discounts_data['active_discounts'] ?? 0;
    $stats['discount_uses'] = $discounts_data['total_uses'] ?? 0;
} else {
    $stats['total_discounts'] = 0;
    $stats['active_discounts'] = 0;
    $stats['discount_uses'] = 0;
}

// 🆕 إحصائيات النقاط الجديدة
$points_check = $conn->query("SHOW TABLES LIKE 'customer_points'");
if ($points_check->num_rows > 0) {
    $points_stats_query = "SELECT 
        COALESCE(SUM(current_points), 0) as total_active_points,
        COALESCE(SUM(total_earned), 0) as total_points_earned,
        COALESCE(SUM(total_spent), 0) as total_points_spent,
        COUNT(*) as customers_with_points
    FROM customer_points";
    $points_stats_result = $conn->query($points_stats_query);
    $points_stats = $points_stats_result->fetch_assoc();
} else {
    $points_stats = [
        'total_active_points' => 0,
        'total_points_earned' => 0,
        'total_points_spent' => 0,
        'customers_with_points' => 0
    ];
}

// آخر الطلبات
$recent_orders = $conn->query("
    SELECT o.*, COALESCE(u.username, u.email, 'عميل غير معروف') as user_name 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");

// المنتجات الأكثر مبيعاً
$top_products = $conn->query("
    SELECT p.name, SUM(oi.quantity) as total_sold 
    FROM products p 
    JOIN order_items oi ON p.id = oi.product_id 
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏢 لوحة التحكم الإدارية - بَهيّ للعطور</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            --gradient-warning: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            --gradient-info: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-points: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }

        body.dark-mode {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #e2e8f0;
            --text-muted: #a0aec0;
            --border-color: #4a5568;
            --shadow-color: rgba(0, 0, 0, 0.3);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            direction: rtl;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 50px;
            padding: 12px 16px;
            cursor: pointer;
            font-size: 20px;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px var(--shadow-color);
        }

        .theme-toggle:hover {
            transform: scale(1.1);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            background: var(--gradient-primary);
            color: white;
            padding: 2.5rem 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            animation: slideInDown 0.6s ease-out;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .welcome-section {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px var(--shadow-color);
            text-align: center;
        }

        .welcome-section h2 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .admin-nav {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            animation: slideInUp 0.6s ease-out 0.2s both;
        }

        .admin-nav-item {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
            border: 2px solid var(--border-color);
            box-shadow: 0 4px 15px var(--shadow-color);
        }

        .admin-nav-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px var(--shadow-color);
        }

        .admin-nav-item i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .admin-nav-item.products { background: var(--gradient-info); color: white; }
        .admin-nav-item.users { background: var(--gradient-success); color: white; }
        .admin-nav-item.orders { background: var(--gradient-warning); color: #333; }
        .admin-nav-item.discounts { background: var(--gradient-secondary); color: white; }
        .admin-nav-item.categories { background: var(--gradient-primary); color: white; }
        /* 🆕 تنسيق أزرار النقاط */
        .admin-nav-item.points { background: var(--gradient-points); color: white; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            animation: slideInUp 0.6s ease-out 0.4s both;
        }
        
        .stat-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px var(--shadow-color);
            position: relative;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 15px 15px 0 0;
        }

        .stat-card.products::before { background: var(--gradient-info); }
        .stat-card.users::before { background: var(--gradient-success); }
        .stat-card.orders::before { background: var(--gradient-warning); }
        .stat-card.revenue::before { background: var(--gradient-danger); }
        .stat-card.discounts::before { background: var(--gradient-secondary); }
        /* 🆕 تنسيق كاردات النقاط */
        .stat-card.points::before { background: var(--gradient-points); }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 600;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            animation: slideInUp 0.6s ease-out 0.6s both;
        }

        .content-section {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px var(--shadow-color);
            transition: all 0.3s ease;
        }

        .content-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--shadow-color);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .data-list {
            list-style: none;
        }

        .data-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .data-item:hover {
            background: rgba(102, 126, 234, 0.05);
            padding: 0.75rem 0.5rem;
            border-radius: 8px;
        }

        .data-item:last-child {
            border-bottom: none;
        }

        .data-name {
            font-weight: 600;
        }

        .data-value {
            font-weight: 700;
            color: var(--text-secondary);
        }

        .no-data {
            text-align: center;
            color: var(--text-muted);
            padding: 2rem;
            font-style: italic;
        }

        /* 🆕 قسم النقاط المميز */
        .points-highlight {
            background: var(--gradient-points);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin: 2rem 0;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .points-highlight h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .points-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .points-actions a {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .points-actions a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .admin-nav {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 2rem;
            }

            .points-actions {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- زر تبديل الوضع الداكن -->
    <button class="theme-toggle" onclick="toggleDarkMode()" id="theme-toggle">🌙</button>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🏢 لوحة التحكم الإدارية</h1>
            <p>بَهيّ للعطور العربية الأصيلة</p>
        </div>

        <!-- الترحيب -->
        <div class="welcome-section">
            <h2>مرحباً <?= htmlspecialchars($user_name) ?> 👋</h2>
            <p>هيا بنا نستعرض أحوال متجرنا اليوم</p>
        </div>

        <!-- التنقل السريع -->
<div class="admin-nav">
    <a href="admin_products.php" class="admin-nav-item products">
        <i class="fas fa-boxes"></i>
        <div>إدارة المنتجات</div>
    </a>
    <a href="admin_discounts.php" class="admin-nav-item discounts">
        <i class="fas fa-tags"></i>
        <div>إدارة الخصومات</div>
    </a>
    <a href="admin_orders.php" class="admin-nav-item orders">
        <i class="fas fa-shopping-cart"></i>
        <div>إدارة الطلبات</div>
    </a>
    <a href="admin_customers.php" class="admin-nav-item users">
        <i class="fas fa-users"></i>
        <div>إدارة العملاء</div>
    </a>
    <a href="admin_categories.php" class="admin-nav-item categories">
        <i class="fas fa-list"></i>
        <div>إدارة الفئات</div>
    </a>
    
    <!-- 🆕 أزرار إدارة النقاط المُصححة -->
    <a href="admin_points_settings.php" class="admin-nav-item points">
        <i class="fas fa-cogs"></i>
        <div>إعدادات النقاط</div>
    </a>
    
    <a href="admin_customers_points.php" class="admin-nav-item points"> <!-- ✅ تأكد من الاسم الصحيح -->
        <i class="fas fa-users-cog"></i>
        <div>نقاط العملاء</div>
    </a>
</div>


        <!-- إحصائيات عامة -->
        <div class="stats-grid">
            <div class="stat-card products">
                <div class="stat-number" style="color: #74b9ff;">
                    <i class="fas fa-boxes"></i>
                    <?= number_format($stats['total_products']) ?>
                </div>
                <div class="stat-label">إجمالي المنتجات</div>
            </div>
            <div class="stat-card users">
                <div class="stat-number" style="color: #56ab2f;">
                    <i class="fas fa-users"></i>
                    <?= number_format($stats['total_users']) ?>
                </div>
                <div class="stat-label">المستخدمين المسجلين</div>
            </div>
            <div class="stat-card orders">
                <div class="stat-number" style="color: #fdcb6e;">
                    <i class="fas fa-shopping-cart"></i>
                    <?= number_format($stats['total_orders']) ?>
                </div>
                <div class="stat-label">إجمالي الطلبات</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-number" style="color: #ff6b6b;">
                    <i class="fas fa-dollar-sign"></i>
                    <?= number_format($stats['total_revenue'], 2) ?>
                </div>
                <div class="stat-label">إجمالي المبيعات (ج.م)</div>
            </div>
            <div class="stat-card discounts">
                <div class="stat-number" style="color: #f093fb;">
                    <i class="fas fa-tags"></i>
                    <?= number_format($stats['active_discounts']) ?>
                </div>
                <div class="stat-label">الخصومات النشطة</div>
            </div>
            <div class="stat-card discounts">
                <div class="stat-number" style="color: #667eea;">
                    <i class="fas fa-chart-line"></i>
                    <?= number_format($stats['discount_uses']) ?>
                </div>
                <div class="stat-label">استخدامات الخصومات</div>
            </div>
            
            <!-- 🆕 إحصائيات النقاط الجديدة -->
            <div class="stat-card points">
                <div class="stat-number" style="color: #667eea;">
                    <i class="fas fa-star"></i>
                    <?= number_format($points_stats['total_active_points']) ?>
                </div>
                <div class="stat-label">النقاط النشطة</div>
            </div>
            <div class="stat-card points">
                <div class="stat-number" style="color: #56ab2f;">
                    <i class="fas fa-trophy"></i>
                    <?= number_format($points_stats['total_points_earned']) ?>
                </div>
                <div class="stat-label">النقاط المكتسبة</div>
            </div>
            <div class="stat-card points">
                <div class="stat-number" style="color: #ff6b6b;">
                    <i class="fas fa-coins"></i>
                    <?= number_format($points_stats['total_points_spent']) ?>
                </div>
                <div class="stat-label">النقاط المستبدلة</div>
            </div>
            <div class="stat-card points">
                <div class="stat-number" style="color: #74b9ff;">
                    <i class="fas fa-user-friends"></i>
                    <?= number_format($points_stats['customers_with_points']) ?>
                </div>
                <div class="stat-label">عملاء لديهم نقاط</div>
            </div>
        </div>

        <div class="content-grid">
            <!-- آخر الطلبات -->
            <div class="content-section">
                <h3 class="section-title">
                    <i class="fas fa-clock"></i>
                    آخر الطلبات
                </h3>
                <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
                    <ul class="data-list">
                        <?php while ($order = $recent_orders->fetch_assoc()): ?>
                            <li class="data-item">
                                <div class="data-name">
                                    #<?= $order['id'] ?> - <?= htmlspecialchars($order['user_name'] ?? 'عميل') ?>
                                </div>
                                <div class="data-value">
                                    <?= number_format($order['total'], 2) ?> ج.م
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="no-data">لا توجد طلبات حتى الآن</div>
                <?php endif; ?>
            </div>

            <!-- المنتجات الأكثر مبيعاً -->
            <div class="content-section">
                <h3 class="section-title">
                    <i class="fas fa-star"></i>
                    المنتجات الأكثر مبيعاً
                </h3>
                <?php if ($top_products && $top_products->num_rows > 0): ?>
                    <ul class="data-list">
                        <?php while ($product = $top_products->fetch_assoc()): ?>
                            <li class="data-item">
                                <div class="data-name">
                                    <?= htmlspecialchars($product['name']) ?>
                                </div>
                                <div class="data-value">
                                    <?= $product['total_sold'] ?> قطعة
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="no-data">لا توجد مبيعات حتى الآن</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ملاحظة مهمة -->
        <div style="background: var(--gradient-info); color: white; padding: 1.5rem; border-radius: 15px; margin-top: 2rem; text-align: center;">
            <h3 style="margin-bottom: 0.5rem;">🎯 لوحة تحكم الخصومات متاحة الآن!</h3>
            <p>يمكنك الآن إدارة جميع أنواع الخصومات والعروض من خلال لوحة التحكم المخصصة</p>
            <a href="admin_discounts.php" style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: bold; margin-top: 10px; display: inline-block;">
                <i class="fas fa-arrow-left"></i> اذهب إلى إدارة الخصومات
            </a>
        </div>

        <!-- قسم إضافي: زر الخروج -->
        <div style="text-align: center; margin-top: 2rem;">
            <a href="logout.php" style="background: var(--gradient-danger); color: white; padding: 12px 24px; border-radius: 25px; text-decoration: none; font-weight: bold; transition: all 0.3s ease; display: inline-block;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-sign-out-alt"></i> تسجيل خروج
            </a>
        </div>
    </div>

    <script>
        // Dark Mode Toggle
        function toggleDarkMode() {
            const body = document.body;
            const toggle = document.getElementById('theme-toggle');
            
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                toggle.textContent = '☀️';
                localStorage.setItem('darkMode', 'enabled');
            } else {
                toggle.textContent = '🌙';
                localStorage.setItem('darkMode', 'disabled');
            }
        }

        // تحميل الإعدادات عند بدء الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            const darkMode = localStorage.getItem('darkMode');
            const toggle = document.getElementById('theme-toggle');
            
            if (darkMode === 'enabled') {
                document.body.classList.add('dark-mode');
                toggle.textContent = '☀️';
            }

            // تأثيرات تفاعلية بسيطة
            document.querySelectorAll('.stat-card, .admin-nav-item, .content-section').forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            console.log('✅ لوحة التحكم الإدارية مع نظام النقاط جاهزة!');
            console.log('📊 المنتجات:', <?= $stats['total_products'] ?>);
            console.log('👥 المستخدمين:', <?= $stats['total_users'] ?>);
            console.log('🛒 الطلبات:', <?= $stats['total_orders'] ?>);
            console.log('🎯 الخصومات النشطة:', <?= $stats['active_discounts'] ?>);
            console.log('🏆 النقاط النشطة:', <?= $points_stats['total_active_points'] ?>);
        });
    </script>
</body>
</html>
