<?php
session_start();
include 'db.php';
include_once 'simple_points.php';

// فحص تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$points_system = new SimplePoints($conn);

// جلب نقاط المستخدم
$user_points = $points_system->getCustomerPoints($user_id);

// جلب تاريخ النقاط
$points_history = $points_system->getPointsHistory($user_id, 10);

// جلب إعدادات النقاط
$points_per_egp = $points_system->getSetting('points_per_egp');
$points_to_egp = $points_system->getSetting('points_to_egp');
$min_redeem = $points_system->getSetting('min_points_redeem');

// حساب قيمة النقاط بالجنيه
$points_value = $user_points * $points_to_egp;
$can_redeem = $user_points >= $min_redeem;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 نقاط المكافآت - بَهيّ للعطور</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gold-gradient: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            --info-gradient: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            --warning-gradient: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --border-color: #e2e8f0;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* عنوان الصفحة */
        .page-header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            animation: slideInDown 0.6s ease-out;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* بطاقة النقاط الرئيسية */
        .points-main-card {
            background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
            padding: 40px;
            border-radius: 25px;
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.6s ease-out 0.2s both;
        }

        .points-main-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: sparkle 20s linear infinite;
        }

        @keyframes sparkle {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .points-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .points-number {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .points-value {
            font-size: 1.5rem;
            font-weight: 700;
            opacity: 0.8;
            margin-bottom: 20px;
        }

        .redeem-status {
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            display: inline-block;
            margin-top: 10px;
        }

        .redeem-status.can-redeem {
            background: rgba(86, 171, 47, 0.9);
            color: white;
        }

        .redeem-status.cannot-redeem {
            background: rgba(255, 107, 107, 0.9);
            color: white;
        }

        /* شبكة المعلومات */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            animation: slideInUp 0.6s ease-out 0.4s both;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .info-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: var(--info-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .info-card h3 {
            color: var(--text-primary);
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .info-card p {
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* تاريخ النقاط */
        .history-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            animation: slideInUp 0.6s ease-out 0.6s both;
        }

        .history-section h2 {
            color: var(--text-primary);
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            margin-bottom: 15px;
            background: var(--bg-primary);
            border-radius: 15px;
            border-right: 5px solid;
            transition: all 0.3s ease;
        }

        .history-item:hover {
            transform: translateX(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .history-item.earned {
            border-right-color: #56ab2f;
            background: linear-gradient(90deg, rgba(86, 171, 47, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
        }

        .history-item.spent {
            border-right-color: #ff6b6b;
            background: linear-gradient(90deg, rgba(255, 107, 107, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
        }

        .history-content {
            flex: 1;
        }

        .history-action {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .history-reason {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .history-date {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .history-points {
            font-size: 1.5rem;
            font-weight: 900;
            text-align: center;
        }

        .history-points.earned {
            color: #56ab2f;
        }

        .history-points.spent {
            color: #ff6b6b;
        }

        /* أزرار التنقل */
        .navigation {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            padding: 15px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .nav-btn:hover {
            background: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .nav-btn.primary {
            background: var(--success-gradient);
            color: white;
        }

        /* الرسائل التوضيحية */
        .help-section {
            background: rgba(255, 255, 255, 0.9);
            padding: 25px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-top: 30px;
        }

        .help-section h3 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .help-list {
            list-style: none;
            padding: 0;
        }

        .help-list li {
            padding: 10px 0;
            border-bottom: 1px dashed var(--border-color);
            color: var(--text-secondary);
            font-weight: 600;
        }

        .help-list li:last-child {
            border-bottom: none;
        }

        .help-list li::before {
            content: "💡 ";
            margin-left: 5px;
        }

        /* الرسوم المتحركة */
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

        /* التصميم المتجاوب */
        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .points-main-card {
                padding: 30px 20px;
            }

            .points-number {
                font-size: 3rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .history-item {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .navigation {
                flex-direction: column;
                align-items: center;
            }

            .nav-btn {
                width: 80%;
                justify-content: center;
            }
        }

        /* تأثيرات إضافية */
        .no-history {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .no-history i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- عنوان الصفحة -->
        <div class="page-header">
            <h1>🏆 نقاط المكافآت</h1>
            <p>اجمع النقاط واستمتع بمكافآت رائعة من بَهيّ للعطور</p>
        </div>

        <!-- بطاقة النقاط الرئيسية -->
        <div class="points-main-card">
            <div class="points-icon">🌟</div>
            <div class="points-number"><?= number_format($user_points) ?></div>
            <div class="points-value">قيمتها: <?= number_format($points_value, 2) ?> ج.م</div>
            
            <div class="redeem-status <?= $can_redeem ? 'can-redeem' : 'cannot-redeem' ?>">
                <?php if ($can_redeem): ?>
                    ✅ يمكنك استخدام نقاطك الآن!
                <?php else: ?>
                    🔒 تحتاج <?= number_format($min_redeem - $user_points) ?> نقطة إضافية للاستخدام
                <?php endif; ?>
            </div>
        </div>

        <!-- شبكة المعلومات -->
        <div class="info-grid">
            <div class="info-card">
                <i class="fas fa-coins"></i>
                <h3><?= $points_per_egp ?></h3>
                <p>نقطة لكل جنيه تنفقه</p>
            </div>
            <div class="info-card">
                <i class="fas fa-gift"></i>
                <h3><?= number_format($points_to_egp, 2) ?></h3>
                <p>جنيه خصم لكل نقطة</p>
            </div>
            <div class="info-card">
                <i class="fas fa-lock"></i>
                <h3><?= number_format($min_redeem) ?></h3>
                <p>الحد الأدنى للاستخدام</p>
            </div>
        </div>

        <!-- تاريخ النقاط -->
        <div class="history-section">
            <h2><i class="fas fa-history"></i> تاريخ النقاط</h2>
            
            <?php if (!empty($points_history)): ?>
                <?php foreach ($points_history as $record): ?>
                    <div class="history-item <?= $record['action_type'] ?>">
                        <div class="history-content">
                            <div class="history-action">
                                <?= $record['action_type'] == 'earned' ? 'حصلت على نقاط' : 'استخدمت نقاط' ?>
                            </div>
                            <div class="history-reason"><?= htmlspecialchars($record['reason']) ?></div>
                            <div class="history-date">
                                <?= date('Y/m/d - H:i', strtotime($record['created_at'])) ?>
                            </div>
                        </div>
                        <div class="history-points <?= $record['action_type'] ?>">
                            <?= $record['action_type'] == 'earned' ? '+' : '-' ?><?= number_format($record['points']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-history">
                    <i class="fas fa-star"></i>
                    <h3>ابدأ رحلة النقاط الآن!</h3>
                    <p>اشترِ منتجاتك المفضلة واحصل على نقاط مع كل عملية شراء</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- نصائح مفيدة -->
        <div class="help-section">
            <h3><i class="fas fa-lightbulb"></i> كيف تحصل على المزيد من النقاط؟</h3>
            <ul class="help-list">
                <li>اشترِ من متجر بَهيّ للعطور واحصل على نقطة لكل جنيه</li>
                <li>استخدم النقاط للحصول على خصومات في مشترياتك القادمة</li>
                <li>تابع العروض الخاصة للحصول على نقاط إضافية</li>
                <li>ادع أصدقاءك وأحصل على نقاط ترحيبية</li>
            </ul>
        </div>

        <!-- أزرار التنقل -->
        <div class="navigation">
            <a href="cart.php" class="nav-btn primary">
                <i class="fas fa-shopping-cart"></i>
                استخدم نقاطك في السلة
            </a>
            <a href="products.php" class="nav-btn">
                <i class="fas fa-shopping-bag"></i>
                تسوق الآن
            </a>
            <a href="wishlist.php" class="nav-btn">
                <i class="fas fa-heart"></i>
                القائمة المفضلة
            </a>
            <a href="order_history.php" class="nav-btn">
                <i class="fas fa-list"></i>
                طلباتي السابقة
            </a>
        </div>
    </div>

    <script>
        // تأثيرات تفاعلية
        document.addEventListener('DOMContentLoaded', function() {
            // إضافة تأثير الكرات المتحركة
            const cards = document.querySelectorAll('.info-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.background = 'linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(255, 255, 255, 0.95))';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.background = 'rgba(255, 255, 255, 0.95)';
                });
            });

            // تأثير النقر على تاريخ النقاط
            const historyItems = document.querySelectorAll('.history-item');
            historyItems.forEach(item => {
                item.addEventListener('click', function() {
                    this.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        this.style.transform = 'translateX(-5px)';
                    }, 150);
                });
            });

            console.log('🏆 صفحة نقاط المكافآت جاهزة ومحسنة!');
        });
    </script>
</body>
</html>
