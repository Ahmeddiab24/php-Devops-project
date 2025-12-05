<?php
session_start();
include 'db.php';
include_once 'simple_points.php';

// 🔐 فحص صلاحيات الأدمن بناءً على الجلسة (مش قاعدة البيانات)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit("⛔ غير مصرح لك بالوصول لهذه الصفحة!");
}

$points_system = new SimplePoints($conn);
$success_message = "";
$error_message = "";

// معالجة تحديث الإعدادات
if (isset($_POST['update_settings'])) {
    $points_per_egp = floatval($_POST['points_per_egp']);
    $points_to_egp = floatval($_POST['points_to_egp']);
    $min_points_redeem = intval($_POST['min_points_redeem']);
    $max_points_per_order = intval($_POST['max_points_per_order']);
    $welcome_bonus_points = intval($_POST['welcome_bonus_points']);
    
    // 🛡️ فحص أمان القيم
    if ($points_per_egp <= 0 || $points_to_egp <= 0) {
        $error_message = "❌ قيم النقاط يجب أن تكون أكبر من صفر!";
    } elseif ($min_points_redeem < 0 || $max_points_per_order < 0 || $welcome_bonus_points < 0) {
        $error_message = "❌ القيم لا يمكن أن تكون سالبة!";
    } elseif ($points_per_egp > 1000 || $points_to_egp > 10) {
        $error_message = "❌ القيم كبيرة جداً! تأكد من الإعدادات.";
    } elseif ($min_points_redeem > 10000 || $max_points_per_order > 50000) {
        $error_message = "❌ الحدود كبيرة جداً!";
    } else {
        // 📝 حفظ القيم القديمة للسجل
        $old_settings = [
            'points_per_egp' => $points_system->getSetting('points_per_egp'),
            'points_to_egp' => $points_system->getSetting('points_to_egp'),
            'min_points_redeem' => $points_system->getSetting('min_points_redeem'),
            'max_points_per_order' => $points_system->getSetting('max_points_per_order'),
            'welcome_bonus_points' => $points_system->getSetting('welcome_bonus_points')
        ];
        
        // تحديث الإعدادات
        $points_system->updateSetting('points_per_egp', $points_per_egp);
        $points_system->updateSetting('points_to_egp', $points_to_egp);
        $points_system->updateSetting('min_points_redeem', $min_points_redeem);
        $points_system->updateSetting('max_points_per_order', $max_points_per_order);
        $points_system->updateSetting('welcome_bonus_points', $welcome_bonus_points);
        
        // 📊 تسجيل التعديل (اختياري)
        $change_log = "تم تعديل إعدادات النقاط بواسطة المدير: " . ($_SESSION['user_name'] ?? 'المدير العام');
        
        $success_message = "✅ تم تحديث إعدادات النقاط بنجاح!";
    }
}

// جلب الإعدادات الحالية
$current_settings = [
    'points_per_egp' => $points_system->getSetting('points_per_egp'),
    'points_to_egp' => $points_system->getSetting('points_to_egp'),
    'min_points_redeem' => $points_system->getSetting('min_points_redeem'),
    'max_points_per_order' => $points_system->getSetting('max_points_per_order'),
    'welcome_bonus_points' => $points_system->getSetting('welcome_bonus_points')
];

// 📊 جلب إحصائيات النقاط
$stats_query = "SELECT 
    COALESCE(SUM(current_points), 0) as total_active_points,
    COALESCE(SUM(total_earned), 0) as total_points_given,
    COALESCE(SUM(total_spent), 0) as total_points_redeemed,
    COUNT(*) as active_customers
FROM customer_points";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_active_points' => 0,
    'total_points_given' => 0,
    'total_points_redeemed' => 0,
    'active_customers' => 0
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ إعدادات نظام النقاط - بَهيّ للعطور</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            --gradient-warning: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            --gradient-info: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
            margin: 0;
        }

        .container { max-width: 1200px; margin: 0 auto; }

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

        .header p {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
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

        /* 🆕 قسم الوصول السريع للإدارة */
        .admin-quick-access {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
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

        /* ألوان متناسقة مع تصميم الصفحة */
        .admin-link.products { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
        }
        .admin-link.customers { 
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); 
        }
        .admin-link.orders { 
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); 
        }
        .admin-link.categories { 
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); 
        }
        .admin-link.discounts { 
            background: linear-gradient(135deg, #fd7e14 0%, #e66100 100%); 
        }
        .admin-link.points-reports { 
            background: linear-gradient(135deg, #e83e8c 0%, #d91a72 100%); 
        }
        .admin-link.customers-points { 
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); 
        }

        .message {
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 700;
            font-size: 16px;
            backdrop-filter: blur(10px);
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

        .admin-info {
            background: var(--gradient-info);
            color: white;
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-card h3 {
            font-size: 28px;
            font-weight: 900;
            color: #667eea;
            margin-bottom: 8px;
        }

        .stat-card p {
            color: #666;
            font-size: 14px;
            font-weight: 600;
        }

        .settings-form {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 40px var(--shadow-color);
            backdrop-filter: blur(10px);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 12px;
            color: #333;
            font-weight: 700;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group input {
            padding: 15px 20px;
            border: 3px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .help-text {
            font-size: 13px;
            color: #718096;
            margin-top: 8px;
            line-height: 1.5;
        }

        .btn {
            background: var(--gradient-success);
            color: white;
            padding: 18px 40px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(86, 171, 47, 0.3);
        }

        .preview-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
            border-right: 5px solid #667eea;
        }

        .preview-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px dashed #ccc;
            font-weight: 600;
        }

        .preview-item:last-child { border-bottom: none; }

        .preview-item strong {
            color: #333;
        }

        .preview-value {
            color: #667eea;
            font-weight: 700;
        }

        .warning-box {
            background: rgba(255, 227, 173, 0.9);
            color: #133e7c;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-right: 5px solid #fdcb6e;
            backdrop-filter: blur(10px);
        }

        .warning-box strong {
            color: #b8860b;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ إعدادات نظام النقاط والمكافآت</h1>
            <div class="nav-links">
                <a href="admin_dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
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
                <a href="admin_customers.php" class="admin-link customers">
                    <i class="fas fa-users"></i>
                    <span>إدارة العملاء</span>
                </a>
                <a href="admin_categories.php" class="admin-link categories">
                    <i class="fas fa-list"></i>
                    <span>إدارة الفئات</span>
                </a>
              
                <a href="admin_customers_points.php" class="admin-link customers-points">
                    <i class="fas fa-users-cog"></i>
                    <span>نقاط العملاء</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- معلومات المدير الحالي -->
        <div class="admin-info">
            👋 مرحباً <?= htmlspecialchars($_SESSION['user_name'] ?? 'المدير العام') ?> 
        </div>
        
        <?php if ($success_message): ?>
            <div class="message success"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error"><?= $error_message ?></div>
        <?php endif; ?>
        
        <div class="warning-box">
            <strong>⚠️ تحذير هام:</strong> تغيير هذه الإعدادات سيؤثر على جميع العمليات المستقبلية للنقاط. النقاط الموجودة بالفعل لن تتأثر.
            <br><strong>نصيحة:</strong> اختبر الإعدادات على حساب تجريبي قبل تطبيقها على العملاء الحقيقيين.
        </div>
        
        <!-- إحصائيات النقاط -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= number_format($stats['total_active_points']) ?></h3>
                <p>إجمالي النقاط النشطة</p>
            </div>
            <div class="stat-card">
                <h3><?= number_format($stats['total_points_given']) ?></h3>
                <p>النقاط الممنوحة للعملاء</p>
            </div>
            <div class="stat-card">
                <h3><?= number_format($stats['total_points_redeemed']) ?></h3>
                <p>النقاط المستبدلة</p>
            </div>
            <div class="stat-card">
                <h3><?= number_format($stats['active_customers']) ?></h3>
                <p>عملاء لديهم نقاط</p>
            </div>
        </div>
        
        <div class="settings-form">
            <h2>🎯 إعدادات النقاط الرئيسية</h2>
            
            <form method="post" id="settingsForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="points_per_egp">🏆 عدد النقاط لكل جنيه مصري</label>
                        <input type="number" name="points_per_egp" id="points_per_egp" 
                               value="<?= htmlspecialchars($current_settings['points_per_egp']) ?>" 
                               min="0.1" max="100" step="0.1" required>
                        <div class="help-text">مثال: 1 = العميل يحصل على نقطة واحدة لكل جنيه ينفقه</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="points_to_egp">💰 قيمة النقطة بالجنيه المصري</label>
                        <input type="number" name="points_to_egp" id="points_to_egp" 
                               value="<?= htmlspecialchars($current_settings['points_to_egp']) ?>" 
                               min="0.01" max="1" step="0.01" required>
                        <div class="help-text">مثال: 0.10 = كل 10 نقاط تساوي جنيه واحد خصم</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="min_points_redeem">🔒 الحد الأدنى لاستبدال النقاط</label>
                        <input type="number" name="min_points_redeem" id="min_points_redeem" 
                               value="<?= htmlspecialchars($current_settings['min_points_redeem']) ?>" 
                               min="0" max="1000" required>
                        <div class="help-text">العميل لا يستطيع استبدال النقاط إلا إذا وصل لهذا العدد</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_points_per_order">🚫 الحد الأقصى للنقاط في طلب واحد</label>
                        <input type="number" name="max_points_per_order" id="max_points_per_order" 
                               value="<?= htmlspecialchars($current_settings['max_points_per_order']) ?>" 
                               min="0" max="10000" required>
                        <div class="help-text">منع العميل من استخدام أكثر من هذا العدد في طلب واحد</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="welcome_bonus_points">🎁 نقاط الترحيب للعضو الجديد</label>
                        <input type="number" name="welcome_bonus_points" id="welcome_bonus_points" 
                               value="<?= htmlspecialchars($current_settings['welcome_bonus_points']) ?>" 
                               min="0" max="1000" required>
                        <div class="help-text">النقاط التي يحصل عليها العميل عند إنشاء حساب جديد</div>
                    </div>
                </div>
                
                <button type="submit" name="update_settings" class="btn" onclick="return confirmUpdate()">
                    <i class="fas fa-save"></i> حفظ الإعدادات
                </button>
            </form>
            
            <!-- معاينة الإعدادات الحالية -->
            <div class="preview-box">
                <h3>📋 معاينة الإعدادات الحالية:</h3>
                
                <div class="preview-item">
                    <strong>شراء بقيمة 100 جنيه:</strong>
                    <span class="preview-value" id="preview-earn"><?= $current_settings['points_per_egp'] * 100 ?> نقطة</span>
                </div>
                
                <div class="preview-item">
                    <strong>استبدال 100 نقطة:</strong>
                    <span class="preview-value" id="preview-redeem"><?= $current_settings['points_to_egp'] * 100 ?> جنيه خصم</span>
                </div>
                
                <div class="preview-item">
                    <strong>الحد الأدنى للاستبدال:</strong>
                    <span class="preview-value"><?= $current_settings['min_points_redeem'] ?> نقطة = <?= number_format($current_settings['points_to_egp'] * $current_settings['min_points_redeem'], 2) ?> جنيه</span>
                </div>
                
                <div class="preview-item">
                    <strong>نقاط الترحيب:</strong>
                    <span class="preview-value"><?= $current_settings['welcome_bonus_points'] ?> نقطة = <?= number_format($current_settings['points_to_egp'] * $current_settings['welcome_bonus_points'], 2) ?> جنيه</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmUpdate() {
            return confirm('هل أنت متأكد من تحديث إعدادات النقاط؟\nهذا التغيير سيؤثر على جميع العمليات المستقبلية.');
        }

        // تحديث المعاينة فوراً عند تغيير القيم
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[type="number"]');
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    updatePreview();
                });
            });
            
            function updatePreview() {
                const pointsPerEgp = parseFloat(document.getElementById('points_per_egp').value) || 0;
                const pointsToEgp = parseFloat(document.getElementById('points_to_egp').value) || 0;
                
                // تحديث المعاينة
                document.getElementById('preview-earn').textContent = (pointsPerEgp * 100) + ' نقطة';
                document.getElementById('preview-redeem').textContent = (pointsToEgp * 100).toFixed(2) + ' جنيه خصم';
            }
        });
    </script>
</body>
</html>
