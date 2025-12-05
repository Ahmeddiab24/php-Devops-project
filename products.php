<?php
session_start();
include 'db.php';

// التحقق إذا كان المستخدم مسجل دخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// إنشاء السلة لو مش موجودة
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = "";
$user_id = $_SESSION['user_id'];

// الحصول على اسم المستخدم الحقيقي
$user_name = $_SESSION['user_name'] ?? 'صديق العطور';

// === 🎁 جلب كوبون الترحيب من جدول discount ===
$welcome_coupon = null;
$selected_category = intval($_GET['category'] ?? 0);

if (!isset($_SESSION['welcome_coupon_shown']) && $selected_category == 0) {
    // جلب كوبون فعال من جدول discount
    $coupon_query = "SELECT * FROM discount 
                     WHERE status = 'active' 
                     AND (start_date IS NULL OR start_date <= NOW()) 
                     AND (end_date IS NULL OR end_date > NOW())
                     AND coupon_code IS NOT NULL 
                     AND coupon_code != ''
                     ORDER BY created_at DESC 
                     LIMIT 1";
    
    $coupon_result = $conn->query($coupon_query);
    
    if ($coupon_result && $coupon_result->num_rows > 0) {
        $coupon_data = $coupon_result->fetch_assoc();
        
        // تحديد نوع الخصم وعرضه
        $discount_display = '';
        if ($coupon_data['discount_type_id'] == 1) {
            $discount_display = $coupon_data['value'] . '%';
        } else {
            $discount_display = $coupon_data['value'] . ' ج.م';
        }
        
        $expires_text = $coupon_data['end_date'] ? 
            date('j F Y', strtotime($coupon_data['end_date'])) : 
            'بدون تاريخ انتهاء';
        
        $welcome_coupon = [
            'show' => true,
            'id' => $coupon_data['id'],
            'name' => $coupon_data['name'] ?: 'خصم ترحيبي خاص',
            'code' => $coupon_data['coupon_code'],
            'value' => $coupon_data['value'],
            'discount_type_id' => $coupon_data['discount_type_id'],
            'discount_display' => $discount_display,
            'min_amount' => $coupon_data['min_amount'],
            'title' => 'أهلاً وسهلاً في عالم بَهيّ!',
            'subtitle' => 'هدية ترحيب خاصة لك',
            'expires' => $expires_text,
            'usage_limit' => $coupon_data['usage_limit'],
            'used_count' => $coupon_data['used_count']
        ];
        $_SESSION['welcome_coupon_shown'] = true;
    }
}

// معالجة إضافة المنتج للسلة
if (isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    $product_id = intval($_POST['product_id']);
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]++;
    } else {
        $_SESSION['cart'][$product_id] = 1;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '✅ تم إضافة المنتج للسلة!',
        'cart_count' => array_sum($_SESSION['cart'])
    ]);
    exit();
}

// معالجة المفضلة
if (isset($_POST['action']) && $_POST['action'] == 'toggle_wishlist') {
    $product_id = intval($_POST['product_id']);
    
    $check_stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;
    
    if ($exists) {
        $delete_stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $delete_stmt->bind_param("ii", $user_id, $product_id);
        $delete_stmt->execute();
        $message = "💔 تم حذف المنتج من المفضلة";
        $in_wishlist = false;
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $insert_stmt->bind_param("ii", $user_id, $product_id);
        $insert_stmt->execute();
        $message = "❤️ تم إضافة المنتج للمفضلة!";
        $in_wishlist = true;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'in_wishlist' => $in_wishlist
    ]);
    exit();
}

// === 🎯 معرف الفئة المختارة ===
$selected_category = intval($_GET['category'] ?? 0);

// === 🎯 استعلام الفئات مع أعدادها ===
$categories_query = "SELECT c.*, 
                     COUNT(p.id) as product_count 
                     FROM categories c 
                     LEFT JOIN products p ON c.id = p.category_id AND (p.status = 'active' OR p.status IS NULL)
                     GROUP BY c.id 
                     ORDER BY c.name";
$categories_result = $conn->query($categories_query);

// === 🎯 بيانات الفئات مع الأيقونات ===
$category_data = [
    'عطور رجالي' => ['icon' => '👔', 'desc' => 'عطور رجالية فاخرة وكلاسيكية'],
    'عطور نسائي' => ['icon' => '💎', 'desc' => 'عطور نسائية راقية ومميزة'], 
    'بخور' => ['icon' => '🔮', 'desc' => 'بخور طبيعي وعود أصلي'],
    'عطور الجسد' => ['icon' => '✨', 'desc' => 'عطور منعشة للاستخدام اليومي']
];

// === 🔥 الحل: فصل المحتوى الثابت عن المنتجات ===
$show_products = $selected_category > 0; 
$current_page_title = 'بَهيّ للعطور الأصيلة';
$current_category_name = 'جميع المنتجات';

// === 🔥 جلب المنتجات إذا كان فيه فئة مختارة ===
$products_result = null;
if ($show_products) {
    // استعلام منتجات الفئة المحددة
    $products_query = "SELECT p.*, c.name as category_name,
                       (SELECT COUNT(*) FROM wishlist w WHERE w.product_id = p.id AND w.user_id = ?) as in_wishlist
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id
                       WHERE p.category_id = ? AND (p.status = 'active' OR p.status IS NULL)
                       ORDER BY p.name";
    $products_stmt = $conn->prepare($products_query);
    $products_stmt->bind_param("ii", $user_id, $selected_category);
    $products_stmt->execute();
    $products_result = $products_stmt->get_result();
    
    // الحصول على اسم الفئة
    $category_name_query = "SELECT name FROM categories WHERE id = ?";
    $category_name_stmt = $conn->prepare($category_name_query);
    $category_name_stmt->bind_param("i", $selected_category);
    $category_name_stmt->execute();
    $category_name_result = $category_name_stmt->get_result();
    if ($category_name_result->num_rows > 0) {
        $current_category_name = $category_name_result->fetch_assoc()['name'];
        $current_page_title = 'بَهيّ - ' . $current_category_name;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌹 <?= htmlspecialchars($current_page_title) ?> - حيث يجتمع الفن والمعنى العربي الأصيل</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-success: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            --gradient-luxury: linear-gradient(135deg, #c9b037 0%, #f7ef8a 100%);
            --gradient-cart: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-coupon: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.10);
            --shadow-xl: 0 12px 24px rgba(0, 0, 0, 0.12);
            --shadow-2xl: 0 16px 32px rgba(0, 0, 0, 0.15);
            --shadow-mega: 0 25px 50px rgba(0, 0, 0, 0.25);
            --shadow-glow: 0 0 30px rgba(102, 126, 234, 0.4);
            
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 0.75rem;
            --space-lg: 1rem;
            --space-xl: 1.25rem;
            --space-2xl: 1.5rem;
            --space-3xl: 2rem;
            
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;
            --radius-3xl: 24px;
            --radius-full: 9999px;
            
            --font-body: 'Cairo', 'Segoe UI', sans-serif;
            --font-arabic: 'Amiri', 'Cairo', serif;
            --transition-normal: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bouncy: 400ms cubic-bezier(0.68, -0.55, 0.265, 1.55);
            --transition-smooth: 350ms cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        body.dark-mode {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #e2e8f0;
            --text-muted: #a0aec0;
            --border-color: #4a5568;
        }

        /* === تحسينات التأثيرات البصرية === */
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        @keyframes magicalGlow {
            0%, 100% { 
                box-shadow: 0 0 20px rgba(102, 126, 234, 0.5),
                           0 0 40px rgba(102, 126, 234, 0.3),
                           0 0 60px rgba(102, 126, 234, 0.1);
            }
            25% { 
                box-shadow: 0 0 25px rgba(255, 107, 107, 0.6),
                           0 0 50px rgba(255, 107, 107, 0.4),
                           0 0 75px rgba(255, 107, 107, 0.2);
            }
            50% { 
                box-shadow: 0 0 30px rgba(78, 205, 196, 0.7),
                           0 0 60px rgba(78, 205, 196, 0.5),
                           0 0 90px rgba(78, 205, 196, 0.3);
            }
            75% { 
                box-shadow: 0 0 35px rgba(255, 234, 167, 0.8),
                           0 0 70px rgba(255, 234, 167, 0.6),
                           0 0 105px rgba(255, 234, 167, 0.4);
            }
        }

        @keyframes couponEntrance {
            0% {
                opacity: 0;
                transform: scale(0.3) rotate(-10deg);
            }
            50% {
                opacity: 1;
                transform: scale(1.1) rotate(5deg);
            }
            100% {
                opacity: 1;
                transform: scale(1) rotate(0deg);
            }
        }

        @keyframes couponPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 30px rgba(102, 126, 234, 0.6);
            }
            50% {
                transform: scale(1.02);
                box-shadow: 0 0 50px rgba(240, 147, 251, 0.8);
            }
        }

        @keyframes sparkle {
            0%, 100% { opacity: 0; transform: scale(0); }
            50% { opacity: 1; transform: scale(1); }
        }

        @keyframes productFloatMagic {
            0%, 100% { 
                transform: translateY(0px) rotateY(0deg);
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
            }
            50% { 
                transform: translateY(-8px) rotateY(2deg);
                box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
            }
        }

        @keyframes shimmerWave {
            0% { 
                background-position: -200px 0; 
            }
            100% { 
                background-position: 200px 0; 
            }
        }

        @keyframes priceSparkle {
            0%, 100% { 
                transform: scale(1);
                color: #ff6b6b;
                text-shadow: 0 0 5px rgba(255, 107, 107, 0.3);
            }
            50% { 
                transform: scale(1.05);
                color: #4ecdc4;
                text-shadow: 0 0 8px rgba(78, 205, 196, 0.5);
            }
        }

        @keyframes heartBeat {
            0%, 100% { 
                transform: scale(1); 
            }
            50% { 
                transform: scale(1.1); 
            }
        }

        @keyframes particleFloat {
            0%, 100% {
                transform: translateY(0px) translateX(0px) scale(0);
                opacity: 0;
            }
            10% {
                transform: translateY(-10px) translateX(5px) scale(1);
                opacity: 1;
            }
            90% {
                transform: translateY(-50px) translateX(-5px) scale(1);
                opacity: 0.7;
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translate3d(0, 30px, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale3d(0.9, 0.9, 0.9);
            }
            to {
                opacity: 1;
                transform: scale3d(1, 1, 1);
            }
        }

        @keyframes floatGentle {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translate3d(-50px, 0, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translate3d(50px, 0, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        /* === تأثيرات جديدة محسّنة === */
        @keyframes luxuryGlow {
            0%, 100% {
                box-shadow: 0 0 15px rgba(201, 176, 55, 0.4),
                           0 0 30px rgba(247, 239, 138, 0.3);
            }
            50% {
                box-shadow: 0 0 25px rgba(201, 176, 55, 0.6),
                           0 0 50px rgba(247, 239, 138, 0.5);
            }
        }

        @keyframes categoryFloatEnhanced {
            0%, 100% { 
                transform: translateY(0px) scale(1);
                box-shadow: var(--shadow-lg);
            }
            50% { 
                transform: translateY(-12px) scale(1.02);
                box-shadow: 0 25px 50px rgba(102, 126, 234, 0.3);
            }
        }

        @keyframes slideUpBounce {
            0% {
                opacity: 0;
                transform: translateY(50px) scale(0.8);
            }
            60% {
                opacity: 1;
                transform: translateY(-10px) scale(1.05);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* === 🎁 كوبون الترحيب === */
        .coupon-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(15px) saturate(180%);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            animation: fadeInScale 0.6s ease-out forwards;
            padding: var(--space-xl);
        }

        .coupon-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-3xl);
            padding: var(--space-3xl);
            max-width: 500px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-mega), 0 0 50px rgba(102, 126, 234, 0.4);
            animation: couponEntrance 0.8s ease-out 0.3s both;
            border: 2px solid rgba(102, 126, 234, 0.2);
        }

        .coupon-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: var(--gradient-coupon);
            opacity: 0.1;
            animation: floatGentle 8s ease-in-out infinite;
        }

        .coupon-sparkles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .sparkle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: #667eea;
            border-radius: 50%;
            animation: sparkle 2s infinite;
        }

        .sparkle:nth-child(1) { top: 20%; left: 20%; animation-delay: 0s; }
        .sparkle:nth-child(2) { top: 30%; left: 80%; animation-delay: 0.5s; }
        .sparkle:nth-child(3) { top: 70%; left: 15%; animation-delay: 1s; }
        .sparkle:nth-child(4) { top: 80%; left: 75%; animation-delay: 1.5s; }
        .sparkle:nth-child(5) { top: 50%; left: 50%; animation-delay: 0.7s; }

        .coupon-content {
            position: relative;
            z-index: 2;
        }

        .coupon-icon {
            font-size: 4rem;
            margin-bottom: var(--space-lg);
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: heartBeat 2s infinite;
            filter: drop-shadow(0 4px 8px rgba(102, 126, 234, 0.3));
        }

        .coupon-title {
            font-size: 1.8rem;
            font-weight: 900 !important;
            color: var(--text-primary);
            margin-bottom: var(--space-md);
            font-family: var(--font-arabic) !important;
            line-height: 1.3;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .coupon-subtitle {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: var(--space-xl);
            font-weight: 600 !important;
        }

        .coupon-offer {
            background: var(--gradient-primary);
            color: white;
            padding: var(--space-xl);
            border-radius: var(--radius-2xl);
            margin: var(--space-xl) 0;
            position: relative;
            animation: couponPulse 3s infinite;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .coupon-discount {
            font-size: 3rem;
            font-weight: 900 !important;
            line-height: 1;
            margin-bottom: var(--space-sm);
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .coupon-name {
            font-size: 1.1rem;
            font-weight: 700 !important;
            margin-bottom: var(--space-md);
            opacity: 0.95;
        }

        .coupon-min-amount {
            font-size: 0.85rem;
            opacity: 0.8;
            margin: var(--space-sm) 0;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--space-sm) var(--space-md);
        }

        .coupon-code-section {
            background: rgba(255, 255, 255, 0.15);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            margin: var(--space-lg) 0;
            backdrop-filter: blur(10px);
        }

        .coupon-code-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: var(--space-sm);
        }

        .coupon-code {
            font-size: 1.8rem;
            font-weight: 900 !important;
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
            color: #fff;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .coupon-expiry {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: var(--space-lg) 0;
            opacity: 0.8;
        }

        .coupon-usage-info {
            font-size: 0.75rem;
            opacity: 0.7;
            margin: var(--space-sm) 0;
            background: var(--bg-primary);
            color: var(--text-secondary);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-lg);
        }

        .coupon-actions {
            display: flex;
            gap: var(--space-md);
            margin-top: var(--space-xl);
        }

        .coupon-btn {
            flex: 1;
            padding: var(--space-lg) var(--space-xl);
            border: none;
            border-radius: var(--radius-full);
            font-weight: 800 !important;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all var(--transition-bouncy);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .coupon-copy-btn {
            background: var(--gradient-luxury);
            color: #333;
        }

        .coupon-shop-btn {
            background: var(--gradient-primary);
            color: white;
        }

        .coupon-close-btn {
            background: var(--bg-primary);
            color: var(--text-muted);
            border: 2px solid var(--border-color);
        }

        .coupon-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        .coupon-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }

        .coupon-btn:hover::before {
            left: 100%;
        }

        /* جزيئات سحرية */
        .magical-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
            z-index: 1;
            opacity: 0;
            transition: opacity var(--transition-smooth);
        }

        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: radial-gradient(circle, #667eea, transparent);
            border-radius: 50%;
            animation: particleFloat 2s infinite;
        }

        .particle:nth-child(1) { left: 20%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 50%; animation-delay: 0.5s; }
        .particle:nth-child(3) { left: 80%; animation-delay: 1s; }

        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.6);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body, h1, h2, h3, h4, h5, h6, p, span, a, button, div, label {
            font-weight: 600 !important;
            font-family: var(--font-body) !important;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e6ecf4 100%);
            color: var(--text-primary);
            direction: rtl;
            overflow-x: hidden;
            line-height: 1.5;
            font-size: 14px;
            transition: all var(--transition-smooth);
            animation: fadeInScale 0.8s ease-out;
            min-height: 100vh;
        }

        /* خلفية جميلة */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 200, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        /* Header محسّن */
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(248, 250, 252, 0.95);
            backdrop-filter: blur(25px) saturate(180%);
            border-bottom: 1px solid rgba(102, 126, 234, 0.15);
            box-shadow: var(--shadow-lg), 0 0 20px rgba(102, 126, 234, 0.1);
            animation: slideInUp 0.6s ease-out;
            transition: all var(--transition-smooth);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
            opacity: 0.8;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-lg) var(--space-xl);
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: var(--space-xl);
        }

        .brand-section {
            justify-self: start;
            animation: slideInLeft 0.8s ease-out;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            font-family: var(--font-arabic) !important;
            font-size: 2.2rem;
            font-weight: 900 !important;
            background: var(--gradient-primary);
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all var(--transition-bouncy);
            position: relative;
            letter-spacing: 2px;
            animation: shimmerWave 3s ease-in-out infinite;
            text-shadow: 0 0 10px rgba(102, 126, 234, 0.3);
        }

        .brand-logo:hover {
            transform: scale(1.05);
            filter: drop-shadow(0 6px 20px rgba(102, 126, 234, 0.6));
            animation: magicalGlow 2s infinite;
        }

        .brand-icon {
            font-size: 2.5rem;
            margin-left: var(--space-md);
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 3px 12px rgba(102, 126, 234, 0.4));
            animation: floatGentle 3s ease-in-out infinite;
        }

        .brand-tagline {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 700 !important;
            margin-top: var(--space-xs);
            font-family: var(--font-arabic) !important;
            opacity: 0.9;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600 !important;
            margin-top: var(--space-xs);
            font-family: var(--font-body) !important;
        }

        .nav-links {
            justify-self: center;
            display: flex;
            gap: var(--space-md);
            align-items: center;
            background: rgba(248, 250, 252, 0.8);
            padding: var(--space-md);
            border-radius: var(--radius-full);
            box-shadow: var(--shadow-sm), inset 0 1px 0 rgba(255,255,255,0.1);
            border: 1px solid rgba(102, 126, 234, 0.1);
            animation: slideInUp 0.8s ease-out 0.2s both;
            backdrop-filter: blur(10px);
        }

        .nav-link {
            padding: var(--space-md) var(--space-xl);
            background: var(--bg-secondary);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--radius-full);
            font-weight: 700 !important;
            font-size: 0.9rem;
            transition: all var(--transition-bouncy);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            box-shadow: var(--shadow-sm);
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.2), transparent);
            transition: left var(--transition-smooth);
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link:hover {
            background: var(--gradient-primary);
            color: white;
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            border-color: transparent;
            animation: magicalGlow 1.5s infinite;
        }

        .nav-link:active {
            transform: translateY(-1px) scale(1.02);
        }

        .nav-actions {
            justify-self: end;
            display: flex;
            gap: var(--space-lg);
            align-items: center;
            animation: slideInRight 0.8s ease-out 0.4s both;
        }

        .nav-btn {
            position: relative;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-lg) var(--space-2xl);
            background: var(--gradient-cart);
            border: none;
            border-radius: var(--radius-full);
            text-decoration: none;
            color: white;
            font-weight: 800 !important;
            font-size: 0.9rem;
            transition: all var(--transition-bouncy);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            overflow: visible;
        }

        .nav-btn:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.6);
            animation: magicalGlow 1s infinite;
        }

        .nav-btn:active {
            transform: translateY(-2px) scale(1.02);
        }

        .nav-btn i {
            font-size: 1rem;
        }

        .cart-indicator {
            position: absolute;
            top: -12px;
            right: -12px;
            min-width: 30px;
            height: 30px;
            padding: 0 8px;
            background: #FF0000;
            color: #FFFFFF;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 900 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 4px 12px rgba(255, 0, 0, 0.8),
                0 0 20px rgba(255, 0, 0, 0.6),
                0 0 0 4px white;
            z-index: 15;
            animation: heartBeat 2s infinite;
            white-space: nowrap;
            text-align: center;
            line-height: 1;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.7);
            transition: all var(--transition-smooth);
            user-select: none;
            pointer-events: none;
        }

        .cart-indicator.large-count {
            min-width: 38px;
            padding: 0 10px;
            font-size: 15px;
        }

        .cart-indicator.extra-large-count {
            min-width: 44px;
            padding: 0 12px;
            font-size: 14px;
        }

        .nav-btn:hover .cart-indicator {
            background: #00FF00 !important;
            color: #000000 !important;
            transform: scale(1.3) rotate(10deg) !important;
            box-shadow: 
                0 6px 20px rgba(0, 255, 0, 1),
                0 0 25px rgba(0, 255, 0, 0.8),
                0 0 0 4px white !important;
            animation: heartBeat 0.5s ease !important;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--bg-secondary);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: var(--radius-full);
            padding: 12px 16px;
            cursor: pointer;
            font-size: 20px;
            z-index: 1000;
            transition: all var(--transition-bouncy);
            box-shadow: var(--shadow-lg), 0 0 15px rgba(102, 126, 234, 0.2);
        }

        .theme-toggle:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-xl), 0 0 25px rgba(102, 126, 234, 0.4);
            animation: magicalGlow 1s infinite;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-2xl) var(--space-xl);
            animation: slideInUp 0.8s ease-out 0.2s both;
        }

        /* === 🔥 المحتوى الثابت محسّن === */
        .welcome-hero {
            background: var(--gradient-primary);
            color: white;
            padding: var(--space-3xl) var(--space-2xl);
            border-radius: var(--radius-3xl);
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: var(--space-2xl);
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.4), 
                       inset 0 1px 0 rgba(255,255,255,0.1);
            animation: fadeInScale 1s ease-out 0.3s both;
        }

        .welcome-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: floatGentle 8s ease-in-out infinite;
        }

        .welcome-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
        }

        .welcome-icon {
            font-size: 4.5rem;
            margin-bottom: var(--space-lg);
            display: block;
            animation: heartBeat 3s infinite;
            filter: drop-shadow(0 4px 20px rgba(255,255,255,0.3));
        }

        .welcome-title {
            font-size: 3rem;
            font-weight: 900 !important;
            font-family: var(--font-arabic) !important;
            margin-bottom: var(--space-md);
            line-height: 1.3;
            text-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .welcome-subtitle {
            font-size: 1.4rem;
            font-weight: 700 !important;
            opacity: 0.95;
            margin-bottom: var(--space-xl);
            line-height: 1.6;
            font-family: var(--font-arabic) !important;
        }

        .tagline-section {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-3xl);
            padding: var(--space-xl);
            margin: var(--space-xl) 0;
            backdrop-filter: blur(20px);
            transition: all var(--transition-bouncy);
        }

        .tagline-section:hover {
            transform: scale(1.02);
            animation: magicalGlow 2s infinite;
        }

        .tagline-text {
            font-size: 1.3rem;
            font-weight: 800 !important;
            font-family: var(--font-arabic) !important;
            margin-bottom: var(--space-sm);
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .contact-info {
            display: flex;
            justify-content: center;
            gap: var(--space-2xl);
            margin-top: var(--space-xl);
            flex-wrap: wrap;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-lg) var(--space-xl);
            background: rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-2xl);
            font-weight: 700 !important;
            transition: all var(--transition-bouncy);
            backdrop-filter: blur(10px);
        }

        .contact-item:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255,255,255,0.2);
        }

        .contact-icon {
            font-size: 1.3rem;
        }

        .contact-text {
            font-size: 1rem;
        }

        /* === 🔥 المميزات الثابتة محسّنة === */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: var(--space-xl);
            margin-top: var(--space-3xl);
        }

        .feature-card {
            padding: var(--space-3xl);
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.25);
            border-radius: var(--radius-3xl);
            text-align: center;
            transition: all var(--transition-bouncy);
            opacity: 0;
            transform: translateY(30px);
            animation: slideUpBounce 0.6s ease-out forwards;
            cursor: pointer;
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s ease;
        }

        .feature-card:hover::before {
            left: 100%;
        }

        .feature-card:nth-child(1) { animation-delay: 0.4s; }
        .feature-card:nth-child(2) { animation-delay: 0.5s; }
        .feature-card:nth-child(3) { animation-delay: 0.6s; }

        .feature-card:hover {
            transform: translateY(-12px) scale(1.05);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(255, 255, 255, 0.3);
            animation: magicalGlow 1.5s infinite;
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: var(--space-lg);
            display: block;
            filter: drop-shadow(0 2px 10px rgba(255,255,255,0.3));
            animation: floatGentle 4s ease-in-out infinite;
        }

        .feature-title {
            font-size: 1.2rem;
            font-weight: 800 !important;
            margin-bottom: var(--space-sm);
            font-family: var(--font-arabic) !important;
        }

        .feature-text {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.6;
            font-weight: 600 !important;
        }

        /* === 🔥 قسم الفئات محسّن === */
        .browse-section {
            background: rgba(248, 250, 252, 0.8);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-3xl);
            padding: var(--space-3xl);
            margin-top: var(--space-3xl);
            box-shadow: var(--shadow-2xl), 0 0 30px rgba(102, 126, 234, 0.1);
            text-align: center;
            animation: slideInUp 1s ease-out 0.6s both;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .browse-title {
            font-size: 2.2rem;
            font-weight: 800 !important;
            color: var(--text-primary);
            margin-bottom: var(--space-xl);
            font-family: var(--font-arabic) !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .categories-showcase {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: var(--space-xl);
            margin-top: var(--space-2xl);
        }

        .category-preview {
            display: block;
            padding: var(--space-3xl);
            background: var(--bg-secondary);
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: var(--radius-3xl);
            text-decoration: none;
            color: var(--text-primary);
            transition: all var(--transition-bouncy);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(10px);
        }

        .category-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-primary);
            opacity: 0;
            transition: opacity var(--transition-smooth);
            z-index: 1;
        }

        .category-preview:hover::before {
            opacity: 0.1;
        }

        .category-preview:hover {
            transform: translateY(-15px) scale(1.03);
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.3);
            border-color: rgba(102, 126, 234, 0.5);
            animation: categoryFloatEnhanced 3s ease-in-out infinite;
            color: var(--text-primary);
        }

        .category-preview-content {
            position: relative;
            z-index: 2;
        }

        .category-preview-icon {
            font-size: 3.5rem;
            margin-bottom: var(--space-lg);
            display: block;
            animation: floatGentle 4s ease-in-out infinite;
            filter: drop-shadow(0 4px 8px rgba(102, 126, 234, 0.2));
        }

        .category-preview-name {
            font-size: 1.3rem;
            font-weight: 800 !important;
            margin-bottom: var(--space-md);
            font-family: var(--font-arabic) !important;
        }

        .category-preview-desc {
            font-size: 1rem;
            opacity: 0.8;
            margin-bottom: var(--space-lg);
            line-height: 1.6;
        }

        .category-preview-count {
            font-size: 0.85rem;
            font-weight: 700 !important;
            color: var(--text-secondary);
            background: rgba(248, 250, 252, 0.8);
            padding: var(--space-sm) var(--space-lg);
            border-radius: var(--radius-full);
            display: inline-block;
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(10px);
        }

        /* === صفحة المنتجات محسّنة === */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-lg) var(--space-2xl);
            background: var(--gradient-secondary);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-full);
            font-weight: 700 !important;
            font-size: 1rem;
            margin-bottom: var(--space-xl);
            transition: all var(--transition-bouncy);
            box-shadow: var(--shadow-md);
            animation: slideInLeft 0.6s ease-out;
        }

        .back-button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 30px rgba(240, 147, 251, 0.5);
        }

        .products-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--space-2xl);
            padding-bottom: var(--space-lg);
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
            animation: slideInUp 0.6s ease-out 0.2s both;
        }

        .products-title-group {
            display: flex;
            align-items: center;
            gap: var(--space-lg);
        }

        .products-icon {
            font-size: 2.2rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 2px 4px rgba(102, 126, 234, 0.3));
        }

        .products-title {
            font-size: 2rem;
            font-weight: 800 !important;
            color: var(--text-primary);
            line-height: 1.2;
            font-family: var(--font-arabic) !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .products-subtitle {
            font-size: 1rem;
            color: var(--text-muted);
            margin-top: var(--space-xs);
        }

        .products-stats {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .stats-badge {
            background: var(--gradient-primary);
            color: white;
            padding: var(--space-md) var(--space-xl);
            border-radius: var(--radius-full);
            font-weight: 700 !important;
            font-size: 0.9rem;
            box-shadow: var(--shadow-md);
        }

        /* === تحسينات المنتجات === */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--space-2xl);
            padding: var(--space-lg) 0;
        }

        .product-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-3xl);
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            border: 2px solid rgba(102, 126, 234, 0.1);
            transition: all var(--transition-bouncy);
            position: relative;
            animation: slideUpBounce 0.6s ease forwards;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.4), 
                transparent
            );
            transition: left 0.8s ease;
            z-index: 2;
            pointer-events: none;
        }

        .product-card:hover::before {
            left: 100%;
        }

        .product-card:hover {
            transform: translateY(-20px) scale(1.03);
            box-shadow: 0 30px 60px rgba(102, 126, 234, 0.25);
            border-color: rgba(102, 126, 234, 0.4);
            animation: productFloatMagic 3s ease-in-out infinite;
        }

        .product-card:hover .magical-particles {
            opacity: 1;
        }

        .product-image-container {
            position: relative;
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(248, 250, 252, 0.5), rgba(230, 236, 244, 0.5));
            padding: var(--space-xl);
            overflow: hidden;
        }

        .product-image-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, 
                rgba(102, 126, 234, 0.1) 0%, 
                transparent 70%
            );
            animation: productFloatMagic 6s ease-in-out infinite reverse;
            pointer-events: none;
        }

        .product-image {
            max-width: 180px;
            max-height: 180px;
            width: auto;
            height: auto;
            object-fit: contain;
            transition: all var(--transition-bouncy);
            border-radius: var(--radius-xl);
            position: relative;
            z-index: 1;
        }

        .product-card:hover .product-image {
            transform: scale(1.15) rotateY(10deg);
            filter: drop-shadow(0 15px 30px rgba(102, 126, 234, 0.3));
        }

        .placeholder-image {
            width: 180px;
            height: 180px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
            color: var(--text-muted);
            font-size: 3.5rem;
            text-align: center;
            border-radius: var(--radius-xl);
        }

        .placeholder-text {
            font-size: 0.8rem;
            margin-top: var(--space-sm);
        }

        .product-badges {
            position: absolute;
            top: var(--space-lg);
            right: var(--space-lg);
            display: flex;
            gap: var(--space-xs);
            z-index: 10;
        }

        .product-badge {
            padding: var(--space-sm) var(--space-md);
            background: var(--gradient-luxury);
            color: #333;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 700 !important;
            box-shadow: var(--shadow-sm);
            animation: luxuryGlow 2s ease-in-out infinite;
        }

        .product-details {
            padding: var(--space-2xl);
            position: relative;
            z-index: 3;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 800 !important;
            color: var(--text-primary);
            margin-bottom: var(--space-lg);
            line-height: 1.4;
            min-height: 3.5rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-price-section {
            display: flex;
            align-items: baseline;
            gap: var(--space-sm);
            margin-bottom: var(--space-xl);
        }

        .product-price {
            font-size: 1.8rem;
            font-weight: 900 !important;
            background: var(--gradient-danger);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all var(--transition-bouncy);
            filter: drop-shadow(0 2px 4px rgba(255, 107, 107, 0.2));
        }

        .product-card:hover .product-price {
            animation: priceSparkle 2s infinite;
        }

        .price-currency {
            font-size: 1rem;
            font-weight: 600 !important;
            color: var(--text-secondary);
        }

        .stock-indicator {
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-xl);
            font-size: 0.85rem;
            font-weight: 700 !important;
            margin-bottom: var(--space-xl);
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .stock-available {
            background: var(--gradient-success);
            color: white;
            box-shadow: 0 4px 15px rgba(86, 171, 47, 0.3);
        }

        .stock-low {
            background: var(--gradient-secondary);
            color: white;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
        }

        .stock-out {
            background: var(--gradient-danger);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .product-actions {
            display: flex;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .wishlist-btn {
            flex: 1;
            position: relative;
            background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
            border: none;
            border-radius: var(--radius-2xl);
            padding: var(--space-lg) var(--space-xl);
            color: #333;
            font-weight: 700 !important;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all var(--transition-bouncy);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            box-shadow: 0 6px 20px rgba(250, 177, 160, 0.3);
        }

        .wishlist-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.6), 
                transparent
            );
            transition: left 0.6s ease;
        }

        .wishlist-btn:hover::before {
            left: 100%;
        }

        .wishlist-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(255, 118, 117, 0.5);
            background: linear-gradient(135deg, #ff7675 0%, #e17055 100%);
            color: white;
        }

        .wishlist-btn.active {
            background: linear-gradient(135deg, #ff7675 0%, #e17055 100%);
            color: white;
            animation: heartBeat 2s infinite;
            box-shadow: 0 0 20px rgba(255, 75, 75, 0.5);
        }

        .wishlist-btn:hover i {
            animation: heartBeat 1s infinite;
        }

        .add-cart-btn {
            width: 100%;
            padding: var(--space-xl) var(--space-2xl);
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--radius-2xl);
            font-size: 1.1rem;
            font-weight: 800 !important;
            cursor: pointer;
            transition: all var(--transition-bouncy);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .add-cart-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.3), 
                transparent
            );
            transition: left 0.6s ease;
        }

        .add-cart-btn:hover::before {
            left: 100%;
        }

        .add-cart-btn:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }

        .add-cart-btn:active {
            transform: translateY(-4px) scale(1.01);
        }

        .add-cart-btn:disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            transform: none;
        }

        .btn-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-md);
            transition: all var(--transition-bouncy);
        }

        .add-cart-btn:hover .btn-content {
            transform: scale(1.05);
        }

        .no-products {
            grid-column: 1 / -1;
            text-align: center;
            padding: var(--space-3xl);
            background: var(--bg-secondary);
            border-radius: var(--radius-3xl);
            box-shadow: var(--shadow-lg);
        }

        .no-products-icon {
            font-size: 5rem;
            color: var(--text-muted);
            margin-bottom: var(--space-xl);
            animation: floatGentle 4s ease-in-out infinite;
        }

        .no-products-title {
            font-size: 1.6rem;
            color: var(--text-primary);
            margin-bottom: var(--space-md);
            font-weight: 700 !important;
        }

        .no-products-text {
            color: var(--text-muted);
            font-size: 1rem;
        }

        /* إشعارات محسّنة */
        .success-notification,
        .error-notification {
            position: fixed;
            top: 100px;
            right: var(--space-xl);
            background: var(--gradient-success);
            color: white;
            padding: var(--space-lg) var(--space-xl);
            border-radius: var(--radius-2xl);
            font-weight: 700 !important;
            font-size: 1rem;
            box-shadow: var(--shadow-2xl), 0 0 25px rgba(86, 171, 47, 0.4);
            z-index: 1001;
            animation: slideUpBounce 0.7s ease forwards;
            display: flex;
            align-items: center;
            gap: var(--space-md);
            max-width: 400px;
            backdrop-filter: blur(10px);
        }

        .error-notification {
            background: var(--gradient-danger) !important;
            box-shadow: var(--shadow-2xl), 0 0 25px rgba(255, 107, 107, 0.4) !important;
        }

        .success-notification i,
        .error-notification i {
            font-size: 1.3rem;
            animation: heartBeat 2s infinite;
        }

        /* Responsive محسّن */
        @media (max-width: 768px) {
            .coupon-card {
                margin: var(--space-lg);
                padding: var(--space-2xl);
            }

            .coupon-title {
                font-size: 1.5rem;
            }

            .coupon-discount {
                font-size: 2.5rem;
            }

            .coupon-actions {
                flex-direction: column;
            }

            .header-content {
                grid-template-columns: 1fr;
                gap: var(--space-md);
                text-align: center;
                padding: var(--space-md);
            }
            
            .nav-links {
                order: 2;
                flex-wrap: wrap;
                justify-content: center;
                padding: var(--space-sm);
            }

            .nav-link {
                padding: var(--space-sm) var(--space-md);
                font-size: 0.8rem;
            }
            
            .nav-actions {
                order: 3;
                justify-self: center;
                gap: var(--space-sm);
            }
            
            .main-container {
                padding: var(--space-xl) var(--space-md);
            }
            
            .welcome-title {
                font-size: 2.2rem;
            }
            
            .contact-info {
                flex-direction: column;
                align-items: center;
                gap: var(--space-lg);
            }
            
            .features-grid, .categories-showcase {
                grid-template-columns: 1fr;
                gap: var(--space-lg);
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
                gap: var(--space-lg);
            }

            .product-image-container {
                height: 180px;
            }

            .product-image {
                max-width: 140px;
                max-height: 140px;
            }

            .cart-indicator {
                top: -10px;
                right: -10px;
                min-width: 26px;
                height: 26px;
                font-size: 14px;
            }

            .brand-logo {
                font-size: 1.8rem;
            }

            .brand-icon {
                font-size: 2rem;
            }
        }

        /* تأثيرات الجزيئات محسّنة */
        .product-card:hover .particle {
            animation-play-state: running;
        }

        .product-card .particle {
            animation-play-state: paused;
        }
    </style>
</head>
<body>
    <!-- === 🎁 كوبون الترحيب من الداتابيز === -->
    <?php if ($welcome_coupon && $welcome_coupon['show']): ?>
    <div id="welcome-coupon-modal" class="coupon-modal">
        <div class="coupon-card">
            <!-- جزيئات سحرية -->
            <div class="coupon-sparkles">
                <div class="sparkle"></div>
                <div class="sparkle"></div>
                <div class="sparkle"></div>
                <div class="sparkle"></div>
                <div class="sparkle"></div>
            </div>

            <div class="coupon-content">
                <div class="coupon-icon">🎁</div>
                
                <h2 class="coupon-title"><?= htmlspecialchars($welcome_coupon['title']) ?></h2>
                <p class="coupon-subtitle"><?= htmlspecialchars($welcome_coupon['subtitle']) ?></p>
                
                <div class="coupon-offer">
                    <div class="coupon-discount"><?= htmlspecialchars($welcome_coupon['discount_display']) ?></div>
                    <div class="coupon-name"><?= htmlspecialchars($welcome_coupon['name']) ?></div>
                    
                    <?php if ($welcome_coupon['min_amount'] > 0): ?>
                        <div class="coupon-min-amount">
                            🛒 للطلبات أكثر من <?= number_format($welcome_coupon['min_amount'], 2) ?> ج.م
                        </div>
                    <?php endif; ?>
                    
                    <div class="coupon-code-section">
                        <div class="coupon-code-label">استخدم الكود:</div>
                        <div class="coupon-code" id="coupon-code-text"><?= htmlspecialchars($welcome_coupon['code']) ?></div>
                    </div>
                </div>
                
                <div class="coupon-expiry">
                    ⏰ ينتهي في: <?= htmlspecialchars($welcome_coupon['expires']) ?>
                </div>
                
                <?php if ($welcome_coupon['usage_limit'] > 0): ?>
                <div class="coupon-usage-info">
                    📊 متبقي <?= ($welcome_coupon['usage_limit'] - $welcome_coupon['used_count']) ?> استخدام من أصل <?= $welcome_coupon['usage_limit'] ?>
                </div>
                <?php endif; ?>
                
                <div class="coupon-actions">
                    <button class="coupon-btn coupon-copy-btn" onclick="copyCouponCode()">
                        <i class="fas fa-copy"></i>
                        نسخ الكود
                    </button>
                    <button class="coupon-btn coupon-shop-btn" onclick="startShopping()">
                        <i class="fas fa-shopping-bag"></i>
                        تسوق الآن
                    </button>
                    <button class="coupon-btn coupon-close-btn" onclick="closeCoupon()">
                        <i class="fas fa-times"></i>
                        إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Theme Toggle -->
    <button class="theme-toggle" onclick="toggleDarkMode()" id="theme-toggle">
        🌙
    </button>

    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="brand-section">
                <a href="products.php" class="brand-logo">
                    <span class="brand-icon">🌹</span>
                    <span>بَهيّ</span>
                </a>
                <div class="brand-tagline">حيث يجتمع الفن والمعنى العربي الأصيل</div>
                <div class="brand-subtitle">مرحباً <?= htmlspecialchars($user_name) ?></div>
            </div>
            
            <nav class="nav-links">
                <a href="products.php" class="nav-link">🛍️ المنتجات</a>
                <a href="wishlist.php" class="nav-link">❤️ المفضلة</a>
                <a href="order_history.php" class="nav-link">📋 طلباتي</a>
                <a href="my_points.php" class="nav-link">🏆 نقاطي</a>

            </nav>
            
            <nav class="nav-actions">
                <a href="cart.php" class="nav-btn" id="cart-btn">
                    <i class="fas fa-shopping-cart"></i>
                    سلة التسوق
                    <?php 
                    $cart_count = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
                    if ($cart_count > 0): 
                        $count_class = '';
                        if ($cart_count > 99) {
                            $count_class = 'extra-large-count';
                            $display_count = '99+';
                        } elseif ($cart_count > 9) {
                            $count_class = 'large-count';
                            $display_count = $cart_count;
                        } else {
                            $display_count = $cart_count;
                        }
                    ?>
                        <span class="cart-indicator <?= $count_class ?>" id="cart-counter"><?= $display_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="nav-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    خروج
                </a>
            </nav>
        </div>
    </header>

    <div class="main-container">
        <!-- === 🔥 المحتوى الثابت - يظهر فقط في الصفحة الرئيسية === -->
        <?php if (!$show_products): ?>
        <div class="welcome-hero">
            <div class="welcome-content">
                <span class="welcome-icon">🌹</span>
                <h1 class="welcome-title">أهلاً وسهلاً <?= htmlspecialchars($user_name) ?></h1>
                <p class="welcome-subtitle">
                    في بَهيّ للعطور العربية الأصيلة<br>
                    رحلة عطرية فاخرة تأخذك إلى عالم من الجمال والأناقة
                </p>
                
                <div class="tagline-section">
                    <div class="tagline-text">حيث يجتمع الفن والمعنى العربي الأصيل</div>
                    <div style="font-size: 1rem; opacity: 0.9; font-family: var(--font-body) !important;">مصر - أرض الحضارة والعراقة</div>
                </div>
                
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-phone contact-icon"></i>
                        <span class="contact-text">01020625895</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt contact-icon"></i>
                        <span class="contact-text">مصر</span>
                    </div>
                </div>
                
                <!-- === 🔥 المميزات تظهر فقط في الصفحة الرئيسية === -->
                <div class="features-grid">
                    <div class="feature-card">
                        <span class="feature-icon">🏺</span>
                        <h4 class="feature-title">تراث عربي أصيل</h4>
                        <p class="feature-text">عطور مستوحاة من التراث العربي العريق</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">💎</span>
                        <h4 class="feature-title">جودة فائقة</h4>
                        <p class="feature-text">أجود أنواع العطور والبخور الطبيعي</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">🎨</span>
                        <h4 class="feature-title">فن وإبداع</h4>
                        <p class="feature-text">تركيبات فنية تعكس الذوق الرفيع</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- === قسم الفئات - يظهر فقط في الصفحة الرئيسية === -->
        <div class="browse-section">
            <h2 class="browse-title">استكشف مجموعاتنا الفاخرة</h2>
            
            <div class="categories-showcase">
                <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                    <?php 
                    $categories_result = $conn->query($categories_query);
                    while ($category = $categories_result->fetch_assoc()): 
                    ?>
                        <a href="?category=<?= $category['id'] ?>" class="category-preview">
                            <div class="category-preview-content">
                                <span class="category-preview-icon">
                                    <?= $category_data[$category['name']]['icon'] ?? '🏷️' ?>
                                </span>
                                <h3 class="category-preview-name"><?= htmlspecialchars($category['name']) ?></h3>
                                <p class="category-preview-desc"><?= $category_data[$category['name']]['desc'] ?? 'تشكيلة مميزة من العطور الفاخرة' ?></p>
                                <div class="category-preview-count"><?= $category['product_count'] ?> منتج متوفر</div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: var(--space-3xl);">
                        <h3 style="color: var(--text-muted); font-size: 1.2rem;">لا توجد فئات متاحة حالياً</h3>
                        <p style="color: var(--text-muted); margin-top: var(--space-sm);">سيتم إضافة المنتجات قريباً</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===  تظهر فقط عند اختيار فئة === -->
        <?php if ($show_products): ?>
        <a href="products.php" class="back-button">
            <i class="fas fa-arrow-right"></i>
            العودة للصفحة الرئيسية
        </a>

        <div class="products-header">
            <div class="products-title-group">
                <div class="products-icon">
                    <?= $category_data[$current_category_name]['icon'] ?? '🏷️' ?>
                </div>
                <div>
                    <h2 class="products-title"><?= htmlspecialchars($current_category_name) ?></h2>
                    <p class="products-subtitle"><?= $category_data[$current_category_name]['desc'] ?? 'تشكيلة مميزة من العطور' ?></p>
                </div>
            </div>
            <div class="products-stats">
                <div class="stats-badge">
                    <i class="fas fa-gem" style="margin-left: 4px;"></i>
                    <?= $products_result->num_rows ?> منتج
                </div>
            </div>
        </div>
        
        <div class="products-grid">
            <?php if ($products_result && $products_result->num_rows > 0): ?>
                <?php while ($product = $products_result->fetch_assoc()): ?>
                    <article class="product-card">
                        <!-- جزيئات سحرية -->
                        <div class="magical-particles">
                            <div class="particle"></div>
                            <div class="particle"></div>
                            <div class="particle"></div>
                        </div>

                        <div class="product-image-container">
                            <?php if (!empty($product['image'])): ?>
                                <img src="images/<?= htmlspecialchars($product['image']) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>" 
                                     class="product-image"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="placeholder-image" style="display: none;">
                                    <i class="fas fa-image"></i>
                                    <div class="placeholder-text">صورة غير متوفرة</div>
                                </div>
                            <?php else: ?>
                                <div class="placeholder-image">
                                    <i class="fas fa-image"></i>
                                    <div class="placeholder-text">صورة غير متوفرة</div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-badges">
                                <span class="product-badge">
                                    <i class="fas fa-star" style="margin-left: 3px;"></i>
                                    مميز
                                </span>
                            </div>
                        </div>
                        
                        <div class="product-details">
                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            
                            <div class="product-price-section">
                                <span class="product-price"><?= number_format($product['price'], 2) ?></span>
                                <span class="price-currency">ج.م</span>
                            </div>
                            
                            <?php if (isset($product['quantity'])): ?>
                                <div class="stock-indicator <?= $product['quantity'] > 10 ? 'stock-available' : ($product['quantity'] > 0 ? 'stock-low' : 'stock-out') ?>">
                                    <?php if ($product['quantity'] > 10): ?>
                                        <i class="fas fa-check-circle" style="margin-left: 4px;"></i>
                                        متوفر في المخزون
                                    <?php elseif ($product['quantity'] > 0): ?>
                                        <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i>
                                        آخر <?= $product['quantity'] ?> قطع
                                    <?php else: ?>
                                        <i class="fas fa-times-circle" style="margin-left: 4px;"></i>
                                        نفد من المخزون
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="product-actions">
                                <button type="button" 
                                        class="wishlist-btn <?= $product['in_wishlist'] > 0 ? 'active' : '' ?>" 
                                        data-product-id="<?= $product['id'] ?>"
                                        onclick="toggleWishlist(this)">
                                    <i class="<?= $product['in_wishlist'] > 0 ? 'fas' : 'far' ?> fa-heart"></i>
                                    <span><?= $product['in_wishlist'] > 0 ? 'في المفضلة' : 'أضف للمفضلة' ?></span>
                                </button>
                            </div>
                            
                            <button type="button" 
                                    class="add-cart-btn" 
                                    data-product-id="<?= $product['id'] ?>"
                                    onclick="addToCart(this)"
                                    <?= (isset($product['quantity']) && $product['quantity'] <= 0) ? 'disabled' : '' ?>>
                                <span class="btn-content">
                                    <?php if (!isset($product['quantity']) || $product['quantity'] > 0): ?>
                                        <i class="fas fa-cart-plus"></i>
                                        أضف للسلة
                                    <?php else: ?>
                                        <i class="fas fa-ban"></i>
                                        غير متوفر
                                    <?php endif; ?>
                                </span>
                            </button>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-products">
                    <div class="no-products-icon">
                        <i class="fas fa-gem"></i>
                    </div>
                    <h3 class="no-products-title">لا توجد منتجات في هذا القسم</h3>
                    <p class="no-products-text">سيتم إضافة منتجات جديدة قريباً</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // تحميل Dark Mode من localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const darkMode = localStorage.getItem('darkMode');
            const toggle = document.getElementById('theme-toggle');
            
            if (darkMode === 'enabled') {
                document.body.classList.add('dark-mode');
                toggle.textContent = '☀️';
            }

            // إضافة تأثيرات الريبل للأزرار
            const buttons = document.querySelectorAll('.add-cart-btn, .wishlist-btn, .nav-btn, .category-preview, .coupon-btn');
            buttons.forEach(button => {
                button.addEventListener('click', createRipple);
            });

            // === 🎁 عرض كوبون الترحيب ===
            const couponModal = document.getElementById('welcome-coupon-modal');
            if (couponModal) {
                setTimeout(() => {
                    couponModal.style.display = 'flex';
                }, 2000); // يظهر بعد ثانيتين
            }
        });

        // === 🎁 دوال كوبون الترحيب ===
        function copyCouponCode() {
            const codeText = document.getElementById('coupon-code-text').textContent;
            navigator.clipboard.writeText(codeText).then(() => {
                showNotification('✅ تم نسخ كود الخصم: ' + codeText, 'success');
                
                // تأثير بصري للنسخ
                const btn = event.target.closest('.coupon-copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> تم النسخ!';
                btn.style.background = 'var(--gradient-success)';
                btn.style.color = 'white';
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = 'var(--gradient-luxury)';
                    btn.style.color = '#333';
                }, 2000);
            });
        }

        function startShopping() {
            closeCoupon();
            showNotification('🛍️ استمتع بالتسوق مع كود الخصم!', 'success');
            
            // التمرير للأسفل لرؤية الفئات
            setTimeout(() => {
                const browseSection = document.querySelector('.browse-section');
                if (browseSection) {
                    browseSection.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }, 500);
        }

        function closeCoupon() {
            const modal = document.getElementById('welcome-coupon-modal');
            if (modal) {
                modal.style.animation = 'fadeInScale 0.4s ease reverse';
                setTimeout(() => {
                    modal.remove();
                }, 400);
            }
        }

        // دالة تأثير الريبل
        function createRipple(event) {
            const button = event.currentTarget;
            const circle = document.createElement("span");
            const diameter = Math.max(button.clientWidth, button.clientHeight);
            const radius = diameter / 2;

            circle.style.width = circle.style.height = `${diameter}px`;
            circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
            circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
            circle.classList.add("ripple");

            const ripple = button.getElementsByClassName("ripple")[0];
            if (ripple) {
                ripple.remove();
            }

            button.appendChild(circle);

            setTimeout(() => {
                circle.remove();
            }, 600);
        }

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

        // Add to Cart Function
        function addToCart(button) {
            if (button.disabled) return;
            
            createRipple(event);
            
            const productId = button.getAttribute('data-product-id');
            const originalContent = button.innerHTML;
            
            button.innerHTML = '<span class="btn-content"><i class="fas fa-spinner fa-spin"></i> جاري الإضافة...</span>';
            button.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', productId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = '<span class="btn-content"><i class="fas fa-check"></i> تمت الإضافة</span>';
                    showNotification(data.message, 'success');
                    updateCartCounter(data.cart_count);
                    
                    setTimeout(() => {
                        button.innerHTML = originalContent;
                        button.disabled = false;
                    }, 2000);
                } else {
                    throw new Error('Failed to add to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = originalContent;
                button.disabled = false;
                showNotification('حدث خطأ، حاول مرة أخرى', 'error');
            });
        }

        // Toggle Wishlist Function
        function toggleWishlist(button) {
            createRipple(event);
            
            const productId = button.getAttribute('data-product-id');
            const icon = button.querySelector('i');
            const text = button.querySelector('span');
            const originalIcon = icon.className;
            const originalText = text.textContent;
            
            // تأثير التحميل
            icon.className = 'fas fa-spinner fa-spin';
            text.textContent = 'جاري المعالجة...';
            button.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'toggle_wishlist');
            formData.append('product_id', productId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.in_wishlist) {
                        button.classList.add('active');
                        icon.className = 'fas fa-heart';
                        text.textContent = 'في المفضلة';
                    } else {
                        button.classList.remove('active');
                        icon.className = 'far fa-heart';
                        text.textContent = 'أضف للمفضلة';
                    }
                    showNotification(data.message, 'success');
                } else {
                    throw new Error('Failed to toggle wishlist');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                icon.className = originalIcon;
                text.textContent = originalText;
                showNotification('حدث خطأ، حاول مرة أخرى', 'error');
            })
            .finally(() => {
                button.disabled = false;
            });
        }

        // Show Notification Function
        function showNotification(message, type = 'success') {
            // إزالة الإشعارات الموجودة
            const existingNotifications = document.querySelectorAll('.success-notification, .error-notification');
            existingNotifications.forEach(notification => notification.remove());
            
            const notification = document.createElement('div');
            notification.className = type === 'success' ? 'success-notification' : 'error-notification';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideInUp 0.4s ease reverse';
                setTimeout(() => {
                    notification.remove();
                }, 400);
            }, 3000);
        }

        // Update Cart Counter Function
        function updateCartCounter(count) {
            const counter = document.getElementById('cart-counter');
            const cartBtn = document.getElementById('cart-btn');
            
            if (count > 0) {
                if (counter) {
                    counter.textContent = count > 99 ? '99+' : count;
                    
                    // تحديث الكلاسات حسب العدد
                    counter.className = 'cart-indicator';
                    if (count > 99) {
                        counter.classList.add('extra-large-count');
                    } else if (count > 9) {
                        counter.classList.add('large-count');
                    }
                } else {
                    // إنشاء العداد إذا لم يكن موجود
                    const newCounter = document.createElement('span');
                    newCounter.id = 'cart-counter';
                    newCounter.className = 'cart-indicator';
                    newCounter.textContent = count > 99 ? '99+' : count;
                    
                    if (count > 99) {
                        newCounter.classList.add('extra-large-count');
                    } else if (count > 9) {
                        newCounter.classList.add('large-count');
                    }
                    
                    cartBtn.appendChild(newCounter);
                }
            } else {
                if (counter) {
                    counter.remove();
                }
            }
        }
    </script>
</body>
</html>
