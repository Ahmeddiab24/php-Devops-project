<?php
session_start();
include 'db.php';

// دي الأولى ✅ صحيحة
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛒 إدارة الطلبات - بَهيّ للعطور</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #667eea 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }
        
        .header h1 {
            color: #333;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .nav-links {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .nav-links a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .nav-links a:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        /* 🆕 قسم الوصول السريع للإدارة */
        .admin-quick-access {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
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
            background: linear-gradient(135deg, #10ac84 0%, #1dd1a1 100%); 
        }
        .admin-link.customers { 
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); 
        }
        .admin-link.discounts { 
            background: linear-gradient(135deg, #feca57 0%, #ff9ff3 100%); 
        }
        .admin-link.categories { 
            background: linear-gradient(135deg, #3742fa 0%, #2f3542 100%); 
        }
        .admin-link.points-settings { 
            background: linear-gradient(135deg, #a55eea 0%, #26de81 100%); 
        }
        .admin-link.points-reports { 
            background: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%); 
        }
        .admin-link.customers-points { 
            background: linear-gradient(135deg, #16a085, #48c9b0); 
        }
        
        /* إحصائيات محسنة */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 30px 25px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-accent);
            border-radius: 20px 20px 0 0;
        }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 30px 60px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.3);
        }
        
        .stat-card.revenue {
            --card-accent: linear-gradient(135deg, #10ac84 0%, #1dd1a1 100%);
        }
        
        .stat-card.orders {
            --card-accent: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card.products {
            --card-accent: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        
        .stat-card.customers {
            --card-accent: linear-gradient(135deg, #feca57 0%, #ff9ff3 100%);
        }
        
        .stat-card.avg {
            --card-accent: linear-gradient(135deg, #a55eea 0%, #26de81 100%);
        }
        
        .stat-card.today {
            --card-accent: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%);
        }
        
        .stat-card .icon {
            font-size: 42px;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            background: var(--card-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* قسم أزرار الطباعة محسن */
        .print-section {
            text-align: center;
            margin: 40px 0;
            padding: 35px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }
        
        .print-section h3 {
            color: #333;
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: 700;
        }
        
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 15px 25px;
            border: none;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin: 8px 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .print-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .print-btn.success {
            background: linear-gradient(135deg, #10ac84 0%, #1dd1a1 100%);
            color: white;
        }
        
        .print-btn.info {
            background: linear-gradient(135deg, #3742fa 0%, #2f3542 100%);
            color: white;
        }
        
        .print-btn.warning {
            background: linear-gradient(135deg, #feca57 0%, #ff9ff3 100%);
            color: white;
        }
        
        .print-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        
        .print-btn-small {
            padding: 8px 15px;
            font-size: 12px;
            margin-left: 10px;
            border-radius: 10px;
        }
        
        /* بطاقات الطلبات محسنة */
        .order-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 25px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 30px 60px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.3);
        }
        
        .order-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-header strong {
            font-size: 18px;
            font-weight: 700;
        }
        
        .order-content {
            padding: 25px;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            border: 1px solid #dee2e6;
        }
        
        .customer-info div {
            text-align: center;
        }
        
        .customer-info strong {
            display: block;
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .order-items {
            margin-top: 20px;
        }
        
        .order-items h4 {
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
        }
        
        .order-items table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            width: 100%;
            border-collapse: collapse;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }
        
        .order-items table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 12px;
            text-align: center;
            font-weight: 600;
        }
        
        .order-items table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #f1f2f6;
        }
        
        .order-items table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .order-total {
            background: linear-gradient(135deg, #10ac84 0%, #1dd1a1 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 8px 20px rgba(16, 172, 132, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: #666;
            font-size: 18px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
        }
        
        .empty-state .icon {
            font-size: 100px;
            margin-bottom: 25px;
            opacity: 0.6;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
        }
        
        /* عناصر مخفية للطباعة */
        .print-header {
            display: none;
        }
        
        .print-date {
            display: none;
        }
        
        /* أنماط ملخص الطلبات للطباعة */
        .orders-summary-table {
            display: none;
        }
        
        .summary-header {
            display: none;
        }
        
        /* أنماط الطباعة */
        @media print {
            body { 
                background: white !important; 
                color: black !important; 
                font-family: Arial, sans-serif;
                font-size: 12px;
            }
            
            .header, .nav-links, .stats-grid, .print-section, .print-btn, .admin-quick-access {
                display: none !important;
            }
            
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #000;
                padding-bottom: 20px;
            }
            
            .print-date {
                display: block !important;
                text-align: right;
                margin-bottom: 20px;
                font-size: 10px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
            }
            
            .summary-header {
                display: block !important;
                text-align: center;
                font-size: 16px;
                font-weight: bold;
                margin: 20px 0;
                color: black;
            }
            
            .orders-summary-table {
                display: table !important;
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            
            .orders-summary-table th,
            .orders-summary-table td {
                border: 1px solid #000 !important;
                padding: 8px;
                text-align: center;
                font-size: 11px;
            }
            
            .orders-summary-table th {
                background: #f0f0f0 !important;
                color: black !important;
                font-weight: bold;
            }
            
            .summary-total-row {
                background: #e0e0e0 !important;
                font-weight: bold;
                border-top: 2px solid #000 !important;
            }
            
            .order-card {
                background: white !important;
                box-shadow: none !important;
                border: 2px solid #000;
                margin-bottom: 30px;
                page-break-inside: avoid;
            }
            
            .order-header {
                background: #f0f0f0 !important;
                color: black !important;
                border-bottom: 2px solid #000;
                font-weight: bold;
            }
            
            .customer-info {
                border: 1px solid #000;
                background: #f9f9f9 !important;
                margin: 10px 0;
            }
            
            table {
                border-collapse: collapse;
            }
            
            table, th, td {
                border: 1px solid #000 !important;
            }
            
            th {
                background: #e0e0e0 !important;
                color: black !important;
            }
            
            .order-total {
                background: #e0e0e0 !important;
                color: black !important;
                border: 2px solid #000;
                font-weight: bold;
            }
            
            @page {
                margin: 1.5cm;
                size: A4;
            }
        }
        
        /* تصميم متجاوب */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 24px;
            }
            
            .nav-links {
                flex-direction: column;
                align-items: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .order-header {
                flex-direction: column;
                text-align: center;
            }
            
            .customer-info {
                grid-template-columns: 1fr;
            }
            
            .print-btn {
                margin: 5px 5px;
                font-size: 12px;
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
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛒 إدارة الطلبات والتقارير</h1>
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
        
        <!-- عناصر مخفية للطباعة -->
        <div class="print-header">
            <h1>🛍️ بَهيّ للعطور - تقرير الطلبات الشامل</h1>
            <h3>إدارة ومتابعة طلبات العملاء والمبيعات</h3>
        </div>

        <div class="print-date">
            <strong>تاريخ الطباعة:</strong> <span id="print-date"></span> |
            <strong>المستخدم:</strong> <?= htmlspecialchars($_SESSION['admin_name'] ?? 'المدير العام') ?>
        </div>
        
        <!-- جدول ملخص الطلبات (مخفي ويظهر في الطباعة فقط) -->
        <div class="summary-header">
            <h2>📋 ملخص الطلبات</h2>
        </div>

        <table class="orders-summary-table">
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>اسم العميل</th>
                    <th>عدد المنتجات</th>
                    <th>إجمالي المبلغ (ج.م)</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody id="summary-table-body">
                <!-- سيتم ملء البيانات بـ JavaScript -->
            </tbody>
            <tfoot>
                <tr class="summary-total-row">
                    <td colspan="3"><strong>الإجمالي العام</strong></td>
                    <td id="summary-total-amount"><strong>0.00</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        
        <!-- إحصائيات محسنة وشاملة -->
        <div class="stats-grid">
            <?php
            // الإحصائيات الأساسية
            $total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
            $total_revenue = $conn->query("SELECT SUM(total) as revenue FROM orders")->fetch_assoc()['revenue'] ?: 0;
            $total_customers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
            
            // إجمالي عدد المنتجات المباعة
            $total_products_sold = $conn->query("
                SELECT COALESCE(SUM(oi.quantity), 0) as total_sold 
                FROM order_items oi 
                INNER JOIN orders o ON oi.order_id = o.id
            ")->fetch_assoc()['total_sold'] ?: 0;
            
            // فحص وجود عمود created_at
            $has_created_at = false;
            $columns = $conn->query("SHOW COLUMNS FROM orders LIKE 'created_at'");
            if ($columns->num_rows > 0) {
                $has_created_at = true;
                $orders_today = $conn->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
            } else {
                $orders_today = 0;
            }
            
            $avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
            ?>
            
            <div class="stat-card revenue">
                <div class="icon">💰</div>
                <div class="number"><?= number_format($total_revenue, 2) ?></div>
                <div class="label">إجمالي الدخل (ج.م)</div>
            </div>
            
            <div class="stat-card orders">
                <div class="icon">🛒</div>
                <div class="number"><?= number_format($total_orders) ?></div>
                <div class="label">إجمالي الطلبات</div>
            </div>
            
            <div class="stat-card products">
                <div class="icon">📦</div>
                <div class="number"><?= number_format($total_products_sold) ?></div>
                <div class="label">إجمالي المنتجات المباعة</div>
            </div>
            
            <div class="stat-card customers">
                <div class="icon">👥</div>
                <div class="number"><?= number_format($total_customers) ?></div>
                <div class="label">إجمالي عدد العملاء</div>
            </div>
            
            <div class="stat-card today">
                <div class="icon">📅</div>
                <div class="number"><?= number_format($orders_today) ?></div>
                <div class="label">طلبات اليوم</div>
            </div>
            
            <div class="stat-card avg">
                <div class="icon">📊</div>
                <div class="number"><?= number_format($avg_order_value, 2) ?></div>
                <div class="label">متوسط قيمة الطلب (ج.م)</div>
            </div>
        </div>
        
        <!-- قسم أزرار الطباعة المحسن -->
        <div class="print-section">
            <h3>🖨️ تقارير وخيارات الطباعة المتقدمة</h3>
            <button onclick="printAllOrders()" class="print-btn primary">
                📋 طباعة جميع الطلبات
            </button>
            <button onclick="printTodayOrders()" class="print-btn success">
                📅 طباعة طلبات اليوم
            </button>
            <button onclick="printOrdersSummary()" class="print-btn info">
                📊 طباعة ملخص الطلبات
            </button>
            <button onclick="exportOrdersData()" class="print-btn warning">
                📈 تصدير البيانات
            </button>
        </div>
        
        <!-- قائمة الطلبات -->
        <?php
        // الاستعلام الصحيح باستخدام username
        $orders_query = "
            SELECT o.*, 
                   COALESCE(u.username, 'عميل غير محدد') as customer_name, 
                   u.email as customer_email
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            ORDER BY o.id DESC
        ";
        
        $orders_result = $conn->query($orders_query);
        
        if ($orders_result && $orders_result->num_rows > 0):
            while ($order = $orders_result->fetch_assoc()):
        ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <strong>📋 طلب رقم: #<?= $order['id'] ?></strong>
                    </div>
                    <div>
                        <?php if ($has_created_at && $order['created_at']): ?>
                            📅 <?= date('Y/m/d H:i', strtotime($order['created_at'])) ?>
                        <?php else: ?>
                            📅 غير محدد
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="order-content">
                    <div class="customer-info">
                        <div>
                            <strong>👤 اسم العميل:</strong>
                            <?= htmlspecialchars($order['customer_name']) ?>
                        </div>
                        <div>
                            <strong>📧 البريد الإلكتروني:</strong>
                            <?= htmlspecialchars($order['customer_email'] ?: 'غير متوفر') ?>
                        </div>
                        <div>
                            <strong>🆔 رقم العميل:</strong>
                            <?= $order['user_id'] ?>
                        </div>
                        <div>
                            <strong>💰 قيمة الطلب:</strong>
                            <span style="color: #10ac84; font-weight: bold; font-size: 16px;">
                                <?= number_format($order['total'], 2) ?> ج.م
                            </span>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        <h4>📦 محتويات الطلب:</h4>
                        <?php
                        $order_items_query = "
                            SELECT oi.*, 
                                   COALESCE(p.name, 'منتج محذوف') as product_name 
                            FROM order_items oi 
                            LEFT JOIN products p ON oi.product_id = p.id 
                            WHERE oi.order_id = " . $order['id'];
                        $items_result = $conn->query($order_items_query);
                        
                        if ($items_result && $items_result->num_rows > 0):
                        ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>الكمية</th>
                                        <th>سعر الوحدة</th>
                                        <th>الإجمالي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($item = $items_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td><?= number_format($item['price'], 2) ?> ج.م</td>
                                        <td><strong><?= number_format($item['price'] * $item['quantity'], 2) ?> ج.م</strong></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="color: #666; font-style: italic; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                                ⚠️ لا توجد تفاصيل متاحة لهذا الطلب
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-total">
                        💰 إجمالي الطلب: <?= number_format($order['total'], 2) ?> جنيه مصري
                    </div>
                </div>
            </div>
        <?php 
            endwhile;
        else:
        ?>
            <div class="empty-state">
                <div class="icon">🛒</div>
                <h3>لا توجد طلبات حتى الآن</h3>
                <p>ستظهر الطلبات هنا عندما يقوم العملاء بالتسوق من المتجر</p>
                <br>
                <a href="admin_products.php" style="
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 30px;
                    font-weight: 600;
                    margin-top: 20px;
                    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
                ">📦 إدارة المنتجات</a>
            </div>
        <?php endif; ?>
        
        <!-- نصائح محسنة -->
        <div style="
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-top: 40px; 
            padding: 30px; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        ">
            <h3 style="color: #333; margin-bottom: 25px; font-size: 24px; font-weight: 700;">💡 نصائح لإدارة الطلبات بفعالية</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 25px;">
                <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 20px; border-radius: 15px; border: 1px solid #2196f3;">
                    <strong style="color: #1976d2;">🚀 سرعة الاستجابة:</strong><br>
                    <small style="color: #424242;">تابع الطلبات الجديدة وتعامل معها بسرعة لضمان رضا العملاء</small>
                </div>
                <div style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); padding: 20px; border-radius: 15px; border: 1px solid #9c27b0;">
                    <strong style="color: #7b1fa2;">🖨️ طباعة منتظمة:</strong><br>
                    <small style="color: #424242;">اطبع تقارير الطلبات بانتظام للمتابعة وحفظ السجلات</small>
                </div>
                <div style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%); padding: 20px; border-radius: 15px; border: 1px solid #4caf50;">
                    <strong style="color: #388e3c;">👥 خدمة العملاء:</strong><br>
                    <small style="color: #424242;">تواصل مع العملاء باستمرار لضمان تجربة تسوق ممتازة</small>
                </div>
                <div style="background: linear-gradient(135deg, #fff3e0 0%, #ffcc02 100%); padding: 20px; border-radius: 15px; border: 1px solid #ff9800;">
                    <strong style="color: #f57c00;">📊 تحليل البيانات:</strong><br>
                    <small style="color: #424242;">استخدم الإحصائيات لفهم سلوك العملاء وتحسين المبيعات</small>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // طباعة جميع الطلبات
        function printAllOrders() {
            document.getElementById('print-date').textContent = new Date().toLocaleString('ar-EG');
            const originalTitle = document.title;
            document.title = 'تقرير جميع الطلبات - ' + new Date().toLocaleDateString('ar-EG');
            window.print();
            document.title = originalTitle;
        }

        // طباعة طلبات اليوم فقط
        function printTodayOrders() {
            const today = new Date().toLocaleDateString('en-CA');
            const orderCards = document.querySelectorAll('.order-card');
            const originalDisplay = [];
            
            orderCards.forEach((card, index) => {
                originalDisplay[index] = card.style.display;
                const dateElement = card.querySelector('.order-header div:last-child');
                
                if (dateElement) {
                    const dateText = dateElement.textContent;
                    const orderDate = dateText.includes('📅') ? dateText.replace('📅 ', '').substring(0, 10) : '';
                    
                    if (!orderDate.includes(today.replaceAll('-', '/'))) {
                        card.style.display = 'none';
                    }
                }
            });
            
            document.getElementById('print-date').textContent = new Date().toLocaleString('ar-EG');
            document.title = 'تقرير طلبات اليوم - ' + new Date().toLocaleDateString('ar-EG');
            window.print();
            
            orderCards.forEach((card, index) => {
                card.style.display = originalDisplay[index];
            });
        }

        // طباعة ملخص الطلبات المحسن
        function printOrdersSummary() {
            // إخفاء بطاقات الطلبات التفصيلية
            const orderCards = document.querySelectorAll('.order-card');
            const originalDisplay = [];
            
            orderCards.forEach((card, index) => {
                originalDisplay[index] = card.style.display;
                card.style.display = 'none';
            });
            
            // جمع بيانات الطلبات وإنشاء الجدول
            generateOrdersSummary();
            
            document.getElementById('print-date').textContent = new Date().toLocaleString('ar-EG');
            document.title = 'ملخص الطلبات - ' + new Date().toLocaleDateString('ar-EG');
            window.print();
            
            // إرجاع العرض الأصلي
            orderCards.forEach((card, index) => {
                card.style.display = originalDisplay[index];
            });
        }

        // وظيفة إنشاء ملخص الطلبات
        function generateOrdersSummary() {
            const orderCards = document.querySelectorAll('.order-card');
            const summaryTableBody = document.getElementById('summary-table-body');
            const summaryTotalElement = document.getElementById('summary-total-amount');
            
            // تنظيف الجدول
            summaryTableBody.innerHTML = '';
            let totalAmount = 0;
            
            orderCards.forEach(card => {
                // استخراج بيانات الطلب
                const orderHeader = card.querySelector('.order-header');
                const orderIdElement = orderHeader.querySelector('strong');
                const dateElement = orderHeader.children[1];
                const customerNameElement = card.querySelector('.customer-info div:first-child');
                const totalElement = card.querySelector('.order-total');
                
                if (orderIdElement && customerNameElement && totalElement) {
                    // رقم الطلب
                    const orderId = orderIdElement.textContent.replace('📋 طلب رقم: #', '');
                    
                    // اسم العميل
                    const customerName = customerNameElement.textContent.replace('👤 اسم العميل:', '').trim();
                    
                    // التاريخ
                    const orderDate = dateElement ? dateElement.textContent.replace('📅 ', '').trim() : 'غير محدد';
                    
                    // المبلغ
                    const totalText = totalElement.textContent.replace('💰 إجمالي الطلب: ', '').replace(' جنيه مصري', '').trim();
                    const totalValue = parseFloat(totalText.replace(/,/g, '')) || 0;
                    totalAmount += totalValue;
                    
                    // حساب عدد المنتجات
                    const productsRows = card.querySelectorAll('.order-items tbody tr');
                    let productsCount = 0;
                    productsRows.forEach(row => {
                        const quantityCell = row.children[1];
                        if (quantityCell) {
                            productsCount += parseInt(quantityCell.textContent) || 0;
                        }
                    });
                    
                    // إنشاء صف في الجدول
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>#${orderId}</td>
                        <td>${customerName}</td>
                        <td>${productsCount}</td>
                        <td>${totalValue.toLocaleString('ar-EG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td>${orderDate}</td>
                    `;
                    summaryTableBody.appendChild(row);
                }
            });
            
            // تحديث الإجمالي
            summaryTotalElement.innerHTML = `<strong>${totalAmount.toLocaleString('ar-EG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>`;
        }

        // طباعة طلب واحد
        function printSingleOrder(orderCard) {
            const allOrders = document.querySelectorAll('.order-card');
            const originalDisplay = [];
            
            allOrders.forEach((card, index) => {
                originalDisplay[index] = card.style.display;
                if (card !== orderCard) {
                    card.style.display = 'none';
                }
            });
            
            document.getElementById('print-date').textContent = new Date().toLocaleString('ar-EG');
            const orderId = orderCard.querySelector('.order-header strong').textContent;
            document.title = `${orderId} - ${new Date().toLocaleDateString('ar-EG')}`;
            window.print();
            
            allOrders.forEach((card, index) => {
                card.style.display = originalDisplay[index];
            });
        }

        // تصدير البيانات
        function exportOrdersData() {
            alert('📊 ميزة تصدير البيانات ستكون متاحة قريباً!\n\n💡 نصيحة: يمكنك استخدام "طباعة" ثم "حفظ كـ PDF" من متصفحك حالياً.');
        }

        // إضافة أزرار طباعة للطلبات الفردية
        document.addEventListener('DOMContentLoaded', function() {
            const orderHeaders = document.querySelectorAll('.order-header');
            
            orderHeaders.forEach(header => {
                const printBtn = document.createElement('button');
                printBtn.innerHTML = '🖨️';
                printBtn.className = 'print-btn print-btn-small primary';
                printBtn.title = 'طباعة هذا الطلب فقط';
                
                printBtn.onclick = function(e) {
                    e.stopPropagation();
                    printSingleOrder(header.parentElement);
                };
                
                header.appendChild(printBtn);
            });
        });
    </script>
</body>
</html>
