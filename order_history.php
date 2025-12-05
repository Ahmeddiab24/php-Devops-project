<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// إصلاح الطلبات بمبلغ صفر تلقائياً
$fix_query = "
    UPDATE orders o
    SET total = (
        SELECT COALESCE(SUM(oi.quantity * oi.price), 0) - COALESCE(o.coupon_discount, 0)
        FROM order_items oi 
        WHERE oi.order_id = o.id
    )
    WHERE o.user_id = ? AND (o.total <= 0 OR o.total IS NULL)
";

$fix_stmt = $conn->prepare($fix_query);
$fix_stmt->bind_param("i", $user_id);
$fix_stmt->execute();

// جلب طلبات المستخدم مع حساب المجموع الصحيح
$orders_query = "
    SELECT 
        o.id,
        o.original_total,
        o.discount_amount,
        o.coupon_discount,
        o.coupon_code,
        o.total as saved_total,
        COALESCE(SUM(oi.quantity * oi.price), 0) as calculated_total,
        GREATEST(
            o.total,
            COALESCE(SUM(oi.quantity * oi.price), 0) - COALESCE(o.coupon_discount, 0)
        ) as display_total,
        o.created_at,
        COUNT(oi.id) as items_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلباتي | بَهيّ للعطور</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Primary Colors */
            --primary-50: #f0f9ff;
            --primary-100: #e0f2fe;
            --primary-500: #0ea5e9;
            --primary-600: #0284c7;
            --primary-700: #0369a1;
            
            /* Gray Scale */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Status Colors */
            --success-50: #ecfdf5;
            --success-500: #10b981;
            --success-600: #059669;
            
            --warning-50: #fffbeb;
            --warning-500: #f59e0b;
            
            --danger-50: #fef2f2;
            --danger-500: #ef4444;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Cairo', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            color: var(--gray-900);
            line-height: 1.6;
            direction: rtl;
        }

        .page-container {
            min-height: 100vh;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
        }

        .content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        /* Header Section */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-text h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .header-text p {
            color: var(--gray-600);
            font-size: 1.1rem;
            font-weight: 500;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            background: var(--gray-900);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-md);
        }

        .back-button:hover {
            background: var(--gray-700);
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        /* Stats Bar */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-600);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Orders Grid */
        .orders-grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .order-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .order-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-500), var(--primary-600));
        }

        .order-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-info h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .order-date {
            color: var(--gray-500);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-completed {
            background: var(--success-50);
            color: var(--success-600);
            border: 1px solid var(--success-500);
        }

        .status-processing {
            background: var(--warning-50);
            color: var(--warning-500);
            border: 1px solid var(--warning-500);
        }

        .status-cancelled {
            background: var(--danger-50);
            color: var(--danger-500);
            border: 1px solid var(--danger-500);
        }

        /* Total Display */
        .total-display {
            font-size: 1.5rem;
            font-weight: 800;
        }

        .total-normal {
            color: var(--gray-900);
        }

        .total-fixed {
            color: var(--success-600);
            position: relative;
        }

        .total-fixed::after {
            content: 'مُصحح';
            position: absolute;
            top: -0.5rem;
            right: -0.5rem;
            background: var(--success-500);
            color: white;
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-weight: 600;
        }

        .total-zero {
            color: var(--danger-500);
        }

        /* Order Details Grid */
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        .detail-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .icon-items { background: var(--primary-50); color: var(--primary-600); }
        .icon-coupon { background: var(--success-50); color: var(--success-600); }
        .icon-status { background: var(--warning-50); color: var(--warning-600); }
        .icon-time { background: var(--gray-100); color: var(--gray-600); }

        /* Discount Info */
        .discount-info {
            background: linear-gradient(135deg, var(--success-50), var(--success-100));
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--success-200);
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .discount-info .icon {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--success-500);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Empty State */
        .empty-state {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .empty-icon {
            width: 6rem;
            height: 6rem;
            margin: 0 auto 2rem;
            background: var(--gray-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--gray-400);
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .empty-description {
            color: var(--gray-600);
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-button {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-md);
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-container { padding: 1rem; }
            .page-header { padding: 1.5rem; }
            .header-content { flex-direction: column; text-align: center; }
            .header-text h1 { font-size: 2rem; }
            .order-header { flex-direction: column; align-items: flex-start; }
            .order-details { grid-template-columns: 1fr; }
            .stats-section { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .header-text h1 { font-size: 1.75rem; }
            .order-card { padding: 1rem; }
            .total-display { font-size: 1.25rem; }
        }

        /* Animation */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .order-card {
            animation: slideUp 0.6s ease forwards;
        }

        .order-card:nth-child(2) { animation-delay: 0.1s; }
        .order-card:nth-child(3) { animation-delay: 0.2s; }
        .order-card:nth-child(4) { animation-delay: 0.3s; }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="content-wrapper">
            <!-- Page Header -->
            <header class="page-header">
                <div class="header-content">
                    <div class="header-text">
                        <h1>
                            <i class="fas fa-receipt"></i>
                            طلباتي
                        </h1>
                        <p>تتبع جميع مشترياتك من متجر بَهيّ للعطور</p>
                    </div>
                    <a href="products.php" class="back-button">
                        <i class="fas fa-arrow-right"></i>
                        العودة للمتجر
                    </a>
                </div>
            </header>

            <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                <!-- Stats Section -->
                <section class="stats-section">
                    <div class="stat-card">
                        <div class="stat-number"><?= $orders_result->num_rows ?></div>
                        <div class="stat-label">إجمالي الطلبات</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php
                            $total_spent = 0;
                            mysqli_data_seek($orders_result, 0);
                            while ($temp_order = $orders_result->fetch_assoc()) {
                                $display_total = floatval($temp_order['display_total']);
                                $total_spent += $display_total;
                            }
                            mysqli_data_seek($orders_result, 0);
                            echo number_format($total_spent, 0);
                            ?>
                        </div>
                        <div class="stat-label">إجمالي المبلغ المنفق (ج.م)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php
                            $total_savings = 0;
                            mysqli_data_seek($orders_result, 0);
                            while ($temp_order = $orders_result->fetch_assoc()) {
                                $total_savings += floatval($temp_order['coupon_discount'] ?? 0);
                            }
                            mysqli_data_seek($orders_result, 0);
                            echo number_format($total_savings, 0);
                            ?>
                        </div>
                        <div class="stat-label">إجمالي التوفير (ج.م)</div>
                    </div>
                </section>

                <!-- Orders Grid -->
                <section class="orders-grid">
                    <?php while ($order = $orders_result->fetch_assoc()): ?>
                        <?php 
                        $saved_total = floatval($order['saved_total']);
                        $calculated_total = floatval($order['calculated_total']);
                        $display_total = floatval($order['display_total']);
                        $coupon_discount = floatval($order['coupon_discount'] ?? 0);
                        $is_zero_bug = ($saved_total <= 0 && $calculated_total > 0);
                        $days_ago = floor((time() - strtotime($order['created_at'])) / (24 * 3600));
                        ?>
                        
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <h3>طلب رقم #<?= $order['id'] ?></h3>
                                    <div class="order-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?= date('d M Y - H:i', strtotime($order['created_at'])) ?>
                                    </div>
                                </div>
                                
                                <div class="order-status">
                                    <div class="status-badge status-completed">
                                        <i class="fas fa-check-circle"></i>
                                        مكتمل
                                    </div>
                                    <div class="total-display <?= $is_zero_bug ? 'total-fixed' : ($saved_total <= 0 ? 'total-zero' : 'total-normal') ?>">
                                        <?= number_format($display_total, 2) ?> ج.م
                                    </div>
                                </div>
                            </div>
                            
                            <div class="order-details">
                                <div class="detail-item">
                                    <div class="detail-icon icon-items">
                                        <i class="fas fa-box"></i>
                                    </div>
                                    <div>
                                        <strong><?= $order['items_count'] ?></strong> منتج
                                    </div>
                                </div>
                                
                                <?php if ($order['coupon_code']): ?>
                                    <div class="detail-item">
                                        <div class="detail-icon icon-coupon">
                                            <i class="fas fa-tag"></i>
                                        </div>
                                        <div>
                                            كوبون: <strong><?= htmlspecialchars($order['coupon_code']) ?></strong>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <div class="detail-icon icon-status">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div>
                                        تم التسليم
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-icon icon-time">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        منذ <?= $days_ago ?> يوم
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($coupon_discount > 0): ?>
                                <div class="discount-info">
                                    <div class="icon">
                                        <i class="fas fa-piggy-bank"></i>
                                    </div>
                                    <div>
                                        <strong>وفرت <?= number_format($coupon_discount, 2) ?> ج.م</strong>
                                        بفضل كوبون الخصم
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($is_zero_bug): ?>
                                <div class="discount-info" style="background: linear-gradient(135deg, var(--primary-50), var(--primary-100)); border-color: var(--primary-200);">
                                    <div class="icon" style="background: var(--primary-500);">
                                        <i class="fas fa-wrench"></i>
                                    </div>
                                    <div>
                                        <strong>تم إصلاح خطأ في العرض</strong>
                                        (كان يظهر: <?= number_format($saved_total, 2) ?> ج.م)
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </section>

            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h2 class="empty-title">لا توجد طلبات بعد</h2>
                    <p class="empty-description">
                        لم تقم بأي عمليات شراء حتى الآن. 
                        اكتشف مجموعتنا الرائعة من العطور الفاخرة والأصيلة.
                    </p>
                    <a href="products.php" class="cta-button">
                        <i class="fas fa-sparkles"></i>
                        ابدأ التسوق الآن
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // تحسين تجربة المستخدم مع تأثيرات بصرية
        document.addEventListener('DOMContentLoaded', function() {
            // تأثير hover على الكروت
            const orderCards = document.querySelectorAll('.order-card');
            
            orderCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.borderColor = 'var(--primary-200)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                });
            });

            // تأثير النقر على زر العودة
            const backButton = document.querySelector('.back-button');
            if (backButton) {
                backButton.addEventListener('click', function(e) {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            }

            console.log('✨ صفحة طلباتي جاهزة مع التصميم الاحترافي!');
        });
    </script>
</body>
</html>
