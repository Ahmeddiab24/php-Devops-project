<?php
session_start();
include 'db.php';

// لو المستخدم مش عامل لوجين نرجعه لصفحة تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = "";

// متغيرات للفاتورة
$show_invoice = false;
$show_shipping_form = false;
$invoice_data = [];
$order_id = null;
$error_message = "";

// 🆕 تحميل نظام النقاط
include_once 'simple_points.php';
$points_system = new SimplePoints($conn);

// معالجة تحديث الكمية عبر AJAX
if (isset($_POST['action']) && $_POST['action'] == 'update_quantity') {
    $product_id = intval($_POST['product_id']);
    $new_quantity = intval($_POST['quantity']);
    
    if ($new_quantity > 0) {
        $_SESSION['cart'][$product_id] = $new_quantity;
        $success = true;
        $cart_total = array_sum($_SESSION['cart']);
        $message = '✅ تم تحديث الكمية بنجاح!';
    } else {
        unset($_SESSION['cart'][$product_id]);
        $success = true;
        $cart_total = array_sum($_SESSION['cart']);
        $message = '🗑️ تم حذف المنتج من السلة';
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'cart_total' => $cart_total,
        'message' => $message
    ]);
    exit();
}

// معالجة حذف المنتج من السلة عبر AJAX
if (isset($_POST['action']) && $_POST['action'] == 'remove_item') {
    $product_id = intval($_POST['product_id']);
    unset($_SESSION['cart'][$product_id]);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '🗑️ تم حذف المنتج من السلة',
        'cart_total' => array_sum($_SESSION['cart'])
    ]);
    exit();
}

// 🆕 معالجة استبدال النقاط
if (isset($_POST['use_points'])) {
    $points_to_use = intval($_POST['points_to_use']);
    $customer_points = $points_system->getCustomerPoints($user_id);
    $min_redeem = intval($points_system->getSetting('min_points_redeem'));
    $max_per_order = intval($points_system->getSetting('max_points_per_order'));
    
    if ($points_to_use < $min_redeem) {
        $error_message = "❌ الحد الأدنى لاستبدال النقاط هو " . number_format($min_redeem) . " نقطة";
    } elseif ($points_to_use > $customer_points) {
        $error_message = "❌ رصيدك من النقاط غير كافي! رصيدك الحالي: " . number_format($customer_points) . " نقطة";
    } elseif ($points_to_use > $max_per_order) {
        $error_message = "❌ الحد الأقصى لاستبدال النقاط في طلب واحد هو " . number_format($max_per_order) . " نقطة";
    } else {
        // حساب قيمة الخصم
        $points_discount = $points_system->pointsToMoney($points_to_use);
        
        // حفظ في الجلسة
        $_SESSION['points_used'] = $points_to_use;
        $_SESSION['points_discount'] = $points_discount;
        
        $success_message = "✅ تم تطبيق " . number_format($points_to_use) . " نقطة بقيمة " . number_format($points_discount, 2) . " ج.م خصم!";
    }
}

// 🆕 معالجة إلغاء استبدال النقاط
if (isset($_POST['remove_points'])) {
    unset($_SESSION['points_used']);
    unset($_SESSION['points_discount']);
    $success_message = "🗑️ تم إلغاء استبدال النقاط";
}

// معالجة تطبيق الكوبون
if (isset($_POST['apply_coupon'])) {
    $coupon_code = strtoupper(trim($_POST['coupon_code']));
    
    if (!empty($coupon_code)) {
        // حساب إجمالي السلة
        $cart_total = 0;
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($product = $result->fetch_assoc()) {
                    $cart_total += $product['price'] * $quantity;
                }
            }
        }
        
        // جلب الكوبون من قاعدة البيانات
        $stmt = $conn->prepare("SELECT * FROM discounts WHERE coupon_code = ? AND status = 'active'");
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $coupon = $stmt->get_result()->fetch_assoc();
        
        if (!$coupon) {
            $error_message = "❌ كود الخصم غير صحيح أو غير نشط";
        } else {
            // فحص التواريخ
            $today = new DateTime('now');
            $start_date = new DateTime($coupon['start_date']);
            $end_date = new DateTime($coupon['end_date']);
            
            if ($today < $start_date) {
                $error_message = "❌ كود الخصم لم يبدأ بعد - يبدأ في " . $start_date->format('d/m/Y');
            } elseif ($today > $end_date) {
                $error_message = "❌ كود الخصم انتهت صلاحيته في " . $end_date->format('d/m/Y');
            } elseif ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
                $error_message = "❌ تم استخدام هذا الكوبون العدد المسموح به";
            } elseif ($coupon['min_amount'] > 0 && $cart_total < $coupon['min_amount']) {
                $error_message = "❌ الحد الأدنى للطلب " . number_format($coupon['min_amount'], 2) . " ج.م";
            } else {
                // حساب الخصم
                $discount_amount = 0;
                
                if (strpos($coupon['name'], '%') !== false) {
                    // خصم نسبة مئوية
                    $discount_amount = ($cart_total * $coupon['value']) / 100;
                    
                    // تطبيق الحد الأقصى للخصم إذا كان موجود
                    if ($coupon['max_amount'] && $discount_amount > $coupon['max_amount']) {
                        $discount_amount = $coupon['max_amount'];
                    }
                } else {
                    // خصم مبلغ ثابت
                    $discount_amount = $coupon['value'];
                }
                
                // منع الخصم من جعل الإجمالي صفر أو سالب
                $minimum_order_value = 10;
                $max_allowed_discount = $cart_total - $minimum_order_value;
                
                if ($discount_amount > $max_allowed_discount) {
                    $error_message = "❌ قيمة الخصم كبيرة جداً. الحد الأقصى المسموح: " . number_format($max_allowed_discount, 2) . " ج.م";
                } else {
                    $final_total = $cart_total - $discount_amount;
                    
                    if ($final_total < $minimum_order_value) {
                        $error_message = "❌ قيمة الخصم كبيرة جداً. الحد الأقصى المسموح: " . number_format($max_allowed_discount, 2) . " ج.م";
                    } else {
                        // تطبيق الكوبون بنجاح
                        $_SESSION['coupon_code'] = $coupon_code;
                        $_SESSION['coupon_discount'] = $discount_amount;
                        $success_message = "✅ تم تطبيق كوبون الخصم بنجاح! توفير: " . number_format($discount_amount, 2) . " ج.م";
                    }
                }
            }
        }
    }
}

// معالجة إزالة الكوبون
if (isset($_POST['remove_coupon'])) {
    unset($_SESSION['coupon_code']);
    unset($_SESSION['coupon_discount']);
    $success_message = "🗑️ تم إلغاء كوبون الخصم";
}

// معالجة إضافة منتج للمفضلة من السلة
if (isset($_POST['add_to_wishlist'])) {
    $product_id = intval($_POST['product_id']);
    
    // إنشاء جدول المفضلة إن لم يكن موجود
    $conn->query("CREATE TABLE IF NOT EXISTS wishlist (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_wishlist (user_id, product_id)
    )");
    
    // فحص إذا كان موجود في المفضلة
    $check_stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;
    
    if (!$exists) {
        $insert_stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $insert_stmt->bind_param("ii", $user_id, $product_id);
        $insert_stmt->execute();
        $success_message = "❤️ تم إضافة المنتج للمفضلة!";
    } else {
        $success_message = "💖 المنتج موجود بالفعل في المفضلة";
    }
}

// معالجة الانتقال لنموذج بيانات الشحن
if (isset($_POST['proceed_to_shipping'])) {
    $cart = $_SESSION['cart'] ?? [];
    
    if (!empty($cart)) {
        $show_shipping_form = true;
    } else {
        $error_message = "❌ السلة فارغة!";
    }
}

// تنفيذ الطلب النهائي مع بيانات الشحن
if (isset($_POST['final_checkout'])) {
    $cart = $_SESSION['cart'] ?? [];
    
    // جلب بيانات الشحن من النموذج
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $customer_city = trim($_POST['customer_city'] ?? '');
    $delivery_notes = trim($_POST['delivery_notes'] ?? '');
    
    // فحص البيانات المطلوبة
    if (empty($customer_name) || empty($customer_phone) || empty($customer_address) || empty($customer_city)) {
        $error_message = "❌ يرجى ملء جميع البيانات المطلوبة (الاسم، الجوال، العنوان، المدينة)";
        $show_shipping_form = true;
    } elseif (!empty($cart)) {
        // فحص وجود المستخدم
        $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $user_check->bind_param("i", $user_id);
        $user_check->execute();
        $user_exists = $user_check->get_result()->num_rows > 0;
        
        if (!$user_exists) {
            $error_message = "❌ خطأ: المستخدم غير موجود في النظام";
            session_destroy();
            header("Location: login.php");
            exit();
        }
        
        // إنشاء جدول order_items إن لم يكن موجود
        $conn->query("CREATE TABLE IF NOT EXISTS order_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // فحص توفر الكميات
        $stock_check = true;
        $stock_errors = [];
        $cart_items = [];
        
        $has_quantity = $conn->query("SHOW COLUMNS FROM products LIKE 'quantity'")->num_rows > 0;
        $has_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'")->num_rows > 0;
        
        foreach ($cart as $product_id => $qty) {
            $query = "SELECT name, price" . ($has_quantity ? ", quantity" : "") . " FROM products WHERE id=" . intval($product_id);
            if ($has_status) {
                $query .= " AND status = 'active'";
            }
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                $cart_items[] = [
                    'id' => $product_id,
                    'name' => $row['name'],
                    'price' => floatval($row['price']),
                    'quantity' => $qty
                ];
                
                if ($has_quantity) {
                    $available_qty = intval($row['quantity'] ?? 0);
                    
                    if ($available_qty < $qty) {
                        $stock_check = false;
                        $product_name = $row['name'] ?? 'منتج غير محدد';
                        $stock_errors[] = "المنتج '{$product_name}' متوفر منه $available_qty فقط وأنت طلبت $qty";
                    }
                }
            } else {
                $stock_check = false;
                $stock_errors[] = "المنتج غير موجود أو تم حذفه";
            }
        }
        
        // إذا كان كل شيء متاح
        if ($stock_check) {
            $original_total = 0.0;
            $invoice_items = [];
            
            // حساب المجموع الكلي
            foreach ($cart_items as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $original_total += $subtotal;
                
                $invoice_items[] = [
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal
                ];
            }
            
            // حساب خصم الكوبون
            $coupon_code = $_SESSION['coupon_code'] ?? null;
            $coupon_discount = $_SESSION['coupon_discount'] ?? 0;
            
            // 🆕 حساب خصم النقاط
            $points_used = $_SESSION['points_used'] ?? 0;
            $points_discount = $_SESSION['points_discount'] ?? 0;
            
            // حساب الإجمالي النهائي
            $final_total = $original_total - $coupon_discount - $points_discount;
            
            // فحص أمان
            if ($final_total <= 0) {
                $error_message = "⚠️ خطأ في حساب المبلغ النهائي!";
                $show_shipping_form = true;
            } else {
                // إدخال الطلب مع بيانات الشحن
                $stmt = $conn->prepare("INSERT INTO orders (
                    user_id, 
                    customer_name,
                    customer_phone,
                    customer_address,
                    customer_city,
                    delivery_notes,
                    total, 
                    created_at, 
                    updated_at, 
                    status, 
                    shipping_address, 
                    payment_method, 
                    original_total, 
                    discount_percentage, 
                    discount_amount, 
                    first_order_discount, 
                    coupon_code, 
                    coupon_discount
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $status = 'pending';
                $payment_method = 'cash_on_delivery';
                $total_discount = $coupon_discount + $points_discount;
                $discount_percentage = $original_total > 0 ? ($total_discount / $original_total) * 100 : 0;
                $first_order_discount = 0.0;
                
                $stmt->bind_param("isssssdssssdddsd", 
                    $user_id, 
                    $customer_name,
                    $customer_phone,
                    $customer_address,
                    $customer_city,
                    $delivery_notes,
                    $final_total, 
                    $status, 
                    $customer_address, 
                    $payment_method, 
                    $original_total, 
                    $discount_percentage, 
                    $total_discount, 
                    $first_order_discount, 
                    $coupon_code, 
                    $coupon_discount
                );
                
                $stmt->execute();
                $order_id = $conn->insert_id;
                
                // إدخال تفاصيل المنتجات
                foreach ($cart as $product_id => $qty) {
                    $query = "SELECT price FROM products WHERE id=" . intval($product_id);
                    if ($has_status) {
                        $query .= " AND status = 'active'";
                    }
                    $result = $conn->query($query);
                    
                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $price = floatval($row['price'] ?? 0);
                        
                        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iiid", $order_id, $product_id, $qty, $price);
                        $stmt->execute();
                        
                        // خصم الكمية من المخزون
                        if ($has_quantity) {
                            $update_stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                            $update_stmt->bind_param("ii", $qty, $product_id);
                            $update_stmt->execute();
                        }
                    }
                }
                
                // تحديث عداد استخدام الكوبون
                if ($coupon_code && $coupon_discount > 0) {
                    $stmt = $conn->prepare("UPDATE discounts SET used_count = used_count + 1 WHERE coupon_code = ?");
                    $stmt->bind_param("s", $coupon_code);
                    $stmt->execute();
                }
                
                // 🆕 معالجة النقاط
                if ($points_used > 0) {
                    // خصم النقاط المستخدمة
                    $points_system->spendPoints($user_id, $points_used, "استبدال نقاط في طلب رقم #$order_id", $order_id);
                }
                
                // 🆕 إضافة نقاط للطلب الجديد
                $earned_points = $points_system->calculatePointsFromAmount($final_total);
                if ($earned_points > 0) {
                    $points_system->addPoints($user_id, $earned_points, "نقاط من طلب رقم #$order_id", $order_id);
                }
                
                // تحضير بيانات الفاتورة
                $invoice_data = [
                    'order_id' => $order_id,
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'customer_address' => $customer_address,
                    'customer_city' => $customer_city,
                    'delivery_notes' => $delivery_notes,
                    'original_total' => $original_total,
                    'discounts' => [
                        'coupon' => $coupon_discount,
                        'points' => $points_discount,
                        'total' => $coupon_discount + $points_discount
                    ],
                    'final_total' => $final_total,
                    'items' => $invoice_items,
                    'date' => date('Y-m-d H:i:s'),
                    'coupon_code' => $coupon_code,
                    'points_used' => $points_used,
                    'earned_points' => $earned_points
                ];
                
                // تفريغ السلة والكوبون والنقاط
                unset($_SESSION['cart']);
                unset($_SESSION['coupon_code']);
                unset($_SESSION['coupon_discount']);
                unset($_SESSION['points_used']);
                unset($_SESSION['points_discount']);
                $show_invoice = true;
            }
        } else {
            $error_message = "⚠️ مشاكل في المخزون:<br>" . implode("<br>", $stock_errors);
            $show_shipping_form = true;
        }
    }
}

// حذف منتج من السلة
if (isset($_GET['remove'])) {
    $product_id = intval($_GET['remove']);
    unset($_SESSION['cart'][$product_id]);
    header("Location: cart.php");
    exit();
}

// 🆕 حساب إجمالي السلة مع النقاط
function calculateCartSummary($cart, $conn, $user_id) {
    if (empty($cart)) {
        return [
            'original_total' => 0.0, 
            'discounts' => [
                'coupon' => 0,
                'points' => 0,
                'total' => 0
            ], 
            'final_total' => 0.0
        ];
    }
    
    $original_total = 0.0;
    $has_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'")->num_rows > 0;
    
    foreach ($cart as $product_id => $qty) {
        $query = "SELECT price FROM products WHERE id=" . intval($product_id);
        if ($has_status) {
            $query .= " AND status = 'active'";
        }
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $price = floatval($row['price'] ?? 0);
            $original_total += $price * intval($qty);
        }
    }
    
    $coupon_discount = $_SESSION['coupon_discount'] ?? 0;
    $points_discount = $_SESSION['points_discount'] ?? 0;
    $total_discount = $coupon_discount + $points_discount;
    $final_total = max($original_total - $total_discount, 0);
    
    return [
        'original_total' => $original_total,
        'discounts' => [
            'coupon' => $coupon_discount,
            'points' => $points_discount,
            'total' => $total_discount
        ],
        'final_total' => $final_total
    ];
}

$cart_summary = calculateCartSummary($_SESSION['cart'] ?? [], $conn, $user_id);

// 🆕 جلب نقاط العميل
$customer_points = $points_system->getCustomerPoints($user_id);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛒 سلة المشتريات - بَهيّ للعطور</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            /* Light Mode */
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-success: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            --gradient-warning: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            --gradient-info: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
        }

        /* Dark Mode */
        body.dark-mode {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #e2e8f0;
            --text-muted: #a0aec0;
            --border-color: #4a5568;
            --shadow-color: rgba(0, 0, 0, 0.3);
        }

        body {
            font-family: 'Cairo', Tahoma, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 20px;
            direction: rtl;
            min-height: 100vh;
            margin: 0;
            transition: all 0.3s ease;
            font-weight: 600 !important;
        }

        /* زر تبديل الوضع الداكن */
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

        /* زر الرجوع المحسن */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: var(--gradient-secondary);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 700 !important;
            font-size: 14px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(240, 147, 251, 0.3);
            border: none;
            cursor: pointer;
        }

        .back-button:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.4);
            background: linear-gradient(135deg, #e17055 0%, #ff7675 100%);
        }

        .back-button i {
            font-size: 16px;
        }

        /* التنقل المحسن */
        .nav-container {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px var(--shadow-color);
            border: 1px solid var(--border-color);
        }

        .nav-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .nav-link {
            padding: 10px 20px;
            background: var(--bg-primary);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600 !important;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--gradient-primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* 🆕 شارة النقاط */
        .points-badge {
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 5px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h2 {
            text-align: center;
            color: var(--text-primary);
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 800 !important;
            text-shadow: 0 2px 4px var(--shadow-color);
        }

        .success-message {
            background: var(--gradient-success);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 700 !important;
            animation: slideIn 0.5s ease;
            box-shadow: 0 4px 15px rgba(86, 171, 47, 0.3);
        }
        
        .error-message {
            background: var(--gradient-danger);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 700 !important;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        
        .cart-table, .invoice-container, .shipping-form {
            background: var(--bg-secondary);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px var(--shadow-color);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        /* تصميم نموذج بيانات الشحن */
        .shipping-form {
            padding: 40px;
        }

        .shipping-form h3 {
            color: var(--text-primary);
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 800 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 700 !important;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600 !important;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        /* قسم الكوبونات */
        .coupon-section {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px var(--shadow-color);
            border: 1px solid var(--border-color);
        }

        .coupon-section h3 {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-weight: 700 !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .coupon-input {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .coupon-input input {
            flex: 1;
            min-width: 200px;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600 !important;
            background: var(--bg-primary);
            color: var(--text-primary);
            text-transform: uppercase;
        }

        .coupon-input input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .apply-coupon-btn {
            padding: 12px 20px;
            background: var(--gradient-info);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700 !important;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .apply-coupon-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(116, 185, 255, 0.4);
        }

        .active-coupon {
            background: var(--gradient-success);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700 !important;
            margin-top: 10px;
        }

        .remove-coupon-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 5px;
            padding: 4px 8px;
            color: white;
            cursor: pointer;
            font-weight: 700 !important;
        }

        /* 🆕 قسم النقاط */
        .points-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px var(--shadow-color);
        }

        .points-section h3 {
            margin-bottom: 15px;
            font-weight: 700 !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .points-balance {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .points-balance .balance-number {
            font-size: 24px;
            font-weight: 900 !important;
            display: block;
        }

        .points-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .points-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .points-input-group input {
            flex: 1;
            min-width: 200px;
            padding: 12px 15px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600 !important;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
        }

        .btn-use-points {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700 !important;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-use-points:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .active-points {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 15px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700 !important;
            margin-top: 10px;
        }

        .points-insufficient {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
        }

        /* قسم الخصومات */
        .discount-section {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .discount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 16px;
            font-weight: 600 !important;
            color: var(--text-primary);
        }
        
        .discount-row.subtotal {
            font-size: 18px;
            font-weight: 700 !important;
        }
        
        .discount-row.discount {
            color: white;
            font-weight: 700 !important;
            background: var(--gradient-success);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            border: none;
            position: relative;
            box-shadow: 0 4px 15px rgba(86, 171, 47, 0.3);
        }
        
        .discount-row.discount::before {
            content: '🎉 ';
            font-size: 18px;
        }

        .discount-row.coupon {
            background: var(--gradient-secondary);
            color: white;
        }

        .discount-row.coupon::before {
            content: '🏷️ ';
        }

        .discount-row.points {
            background: var(--gradient-primary);
            color: white;
        }

        .discount-row.points::before {
            content: '🏆 ';
        }
        
        .discount-row.final-total {
            font-size: 20px;
            font-weight: 800 !important;
            color: var(--text-primary);
            border-bottom: none;
            border-top: 3px solid #667eea;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background: var(--gradient-primary);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 700 !important;
        }
        
        table td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-primary);
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
            justify-content: flex-start;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow-color);
        }

        .product-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .wishlist-btn {
            background: transparent;
            color: #dc3545;
            border: 2px solid #dc3545;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700 !important;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .wishlist-btn:hover {
            background: #dc3545;
            color: white;
            transform: scale(1.05);
        }
        
        /* أزرار الكمية المحسنة */
        .quantity-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 8px;
            box-shadow: 0 2px 10px var(--shadow-color);
        }
        
        .quantity-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 20px;
            font-weight: 700 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.4);
        }

        .quantity-btn:hover {
            background: var(--gradient-danger);
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.5);
        }

        .quantity-btn:active {
            transform: scale(0.95);
        }
        
        .quantity-input {
            width: 60px;
            height: 40px;
            text-align: center;
            padding: 8px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 700 !important;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .quantity-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.2);
        }
        
        .remove-btn {
            background: var(--gradient-danger);
            color: white;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-weight: 700 !important;
            border: none;
            cursor: pointer;
        }
        
        .remove-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }
        
        .total-row {
            background: var(--bg-primary);
            font-weight: 700 !important;
            font-size: 18px;
        }
        
        .buttons-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin: 30px 0;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700 !important;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-checkout {
            background: var(--gradient-success);
            color: white;
        }
        
        .btn-checkout:disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-continue {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        
        .btn-print {
            background: var(--gradient-info);
            color: white;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px var(--shadow-color);
        }
        
        .invoice-container {
            padding: 40px;
        }
        
        .invoice-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }
        
        .invoice-header h1 {
            color: var(--text-primary);
            font-size: 32px;
            font-weight: 900 !important;
            margin-bottom: 10px;
        }
        
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
            color: var(--text-primary);
        }

        /* قسم بيانات الشحن في الفاتورة */
        .shipping-details {
            background: var(--bg-primary);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-right: 5px solid #28a745;
        }

        .shipping-details h4 {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-weight: 700 !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .shipping-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
        }

        .shipping-item strong {
            color: var(--text-secondary);
        }

        /* تصميم الفاتورة مع كوبونات الخصم */
        .invoice-discount-section {
            background: var(--bg-primary);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-right: 5px solid #28a745;
        }

        .invoice-discount-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .invoice-discount-item:last-child {
            border-bottom: none;
            font-weight: 700 !important;
            font-size: 18px;
        }
        
        .total-section {
            text-align: center;
            font-size: 24px;
            font-weight: 800 !important;
            margin: 30px 0;
            padding: 20px;
            background: var(--gradient-success);
            color: white;
            border-radius: 15px;
        }
        
        .invoice-success-message {
            background: var(--gradient-success);
            color: white;
            text-align: center;
            font-weight: 700 !important;
            font-size: 20px;
            margin: 30px 0;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(86, 171, 47, 0.4);
        }
        
        .empty-cart {
            text-align: center;
            background: var(--bg-secondary);
            padding: 60px;
            border-radius: 20px;
            box-shadow: 0 8px 25px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .empty-cart-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        /* إشعارات AJAX */
        .notification {
            position: fixed;
            top: 100px;
            right: 20px;
            background: var(--gradient-success);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            font-weight: 700 !important;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
            z-index: 1001;
            animation: slideInRight 0.4s ease;
        }

        .notification.error {
            background: var(--gradient-danger);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }

        /* تحذير الإجمالي الصفر */
        .zero-total-warning {
            background: var(--gradient-danger);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 15px 0;
            text-align: center;
            font-weight: 700 !important;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @media print {
            body { 
                background: white !important;
                color: black !important;
            }
            .buttons-container, .nav-container, .theme-toggle, .back-button { 
                display: none !important;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                flex-direction: column;
                align-items: center;
            }

            .product-info {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .buttons-container {
                flex-direction: column;
                align-items: center;
            }
            
            .discount-row {
                font-size: 14px;
            }
            
            .discount-section, .coupon-section, .points-section {
                padding: 15px;
            }

            .quantity-controls {
                flex-direction: row;
                gap: 5px;
            }

            .quantity-btn {
                width: 35px;
                height: 35px;
                font-size: 16px;
            }

            .quantity-input {
                width: 50px;
                height: 35px;
            }

            .coupon-input, .points-input-group {
                flex-direction: column;
            }

            .coupon-input input, .points-input-group input {
                min-width: auto;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- زر تبديل الوضع الداكن -->
    <button class="theme-toggle" onclick="toggleDarkMode()" id="theme-toggle">🌙</button>

    <div class="container">
        <!-- زر الرجوع -->
        <a href="products.php" class="back-button">
            <i class="fas fa-arrow-right"></i>
            العودة للمنتجات
        </a>

        <!-- التنقل المحسن -->
        <nav class="nav-container">
            <div class="nav-links">
                <a href="products.php" class="nav-link">
                    <span>🛍️</span> المنتجات
                </a>
                <a href="cart.php" class="nav-link active">
                    <span>🛒</span> السلة
                    <?php if (!empty($_SESSION['cart'])): ?>
                        <span style="background: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 11px;">
                            <?= array_sum($_SESSION['cart']) ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="wishlist.php" class="nav-link">
                    <span>❤️</span> المفضلة
                </a>
                <a href="order_history.php" class="nav-link">
                    <span>📋</span> طلباتي
                </a>
                <!-- 🆕 رابط النقاط -->
                <a href="my_points.php" class="nav-link">
                    <span>🏆</span> نقاطي
                    <?php if ($customer_points > 0): ?>
                        <span class="points-badge"><?= number_format($customer_points) ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="nav-link">
                    <span>🚪</span> خروج
                </a>
            </div>
        </nav>

        <h2>🛒 سلة المشتريات</h2>

        <?php if ($success_message): ?>
            <div class="success-message"><?= $success_message ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?= $error_message ?></div>
        <?php endif; ?>
        
        <?php if ($show_invoice): ?>
            <!-- عرض الفاتورة بعد الطلب الناجح -->
            <div class="invoice-success-message">
                🎉 تم تنفيذ طلبك بنجاح! شكراً لتسوقك معنا
                <?php if ($invoice_data['discounts']['total'] > 0): ?>
                    <br>🎊 إجمالي التوفير: <?= number_format($invoice_data['discounts']['total'], 2) ?> ج.م
                <?php endif; ?>
                <?php if ($invoice_data['earned_points'] > 0): ?>
                    <br>🏆 حصلت على <?= number_format($invoice_data['earned_points']) ?> نقطة جديدة!
                <?php endif; ?>
            </div>
            
            <div class="invoice-container">
                <div class="invoice-header">
                    <h1>🧾 فاتورة الشراء</h1>
                    <h3>بَهيّ للعطور - حيث يجتمع الفن والمعنى العربي الأصيل</h3>
                </div>
                
                <div class="invoice-details">
                    <div>
                        <strong>📋 رقم الفاتورة:</strong> #<?= $invoice_data['order_id'] ?>
                    </div>
                    <div>
                        <strong>📅 التاريخ:</strong> <?= date('Y/m/d - H:i', strtotime($invoice_data['date'])) ?>
                    </div>
                    <div>
                        <strong>👤 العميل:</strong> <?= htmlspecialchars($invoice_data['customer_name']) ?>
                    </div>
                </div>
                
                <!-- بيانات الشحن -->
                <div class="shipping-details">
                    <h4>🚚 بيانات الشحن والتوصيل</h4>
                    
                    <div class="shipping-item">
                        <span><strong>الاسم:</strong></span>
                        <span><?= htmlspecialchars($invoice_data['customer_name']) ?></span>
                    </div>
                    
                    <div class="shipping-item">
                        <span><strong>رقم الجوال:</strong></span>
                        <span><?= htmlspecialchars($invoice_data['customer_phone']) ?></span>
                    </div>
                    
                    <div class="shipping-item">
                        <span><strong>العنوان:</strong></span>
                        <span><?= htmlspecialchars($invoice_data['customer_address']) ?></span>
                    </div>
                    
                    <div class="shipping-item">
                        <span><strong>المدينة:</strong></span>
                        <span><?= htmlspecialchars($invoice_data['customer_city']) ?></span>
                    </div>
                    
                    <?php if (!empty($invoice_data['delivery_notes'])): ?>
                    <div class="shipping-item">
                        <span><strong>ملاحظات التوصيل:</strong></span>
                        <span><?= htmlspecialchars($invoice_data['delivery_notes']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المنتج</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoice_data['items'] as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($item['name'] ?? 'منتج غير محدد') ?></td>
                            <td><?= intval($item['quantity']) ?></td>
                            <td><?= number_format($item['price'], 2) ?> ج.م</td>
                            <td><?= number_format($item['subtotal'], 2) ?> ج.م</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($invoice_data['discounts']['total'] > 0): ?>
                    <div class="invoice-discount-section">
                        <div class="invoice-discount-item">
                            <span><strong>الإجمالي الفرعي:</strong></span>
                            <span><?= number_format($invoice_data['original_total'], 2) ?> ج.م</span>
                        </div>
                        
                        <?php if ($invoice_data['discounts']['coupon'] > 0): ?>
                        <div class="invoice-discount-item" style="color: #e74c3c;">
                            <span>🏷️ كوبون الخصم (<?= $invoice_data['coupon_code'] ?>):</span>
                            <span>-<?= number_format($invoice_data['discounts']['coupon'], 2) ?> ج.م</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($invoice_data['discounts']['points'] > 0): ?>
                        <div class="invoice-discount-item" style="color: #667eea;">
                            <span>🏆 خصم النقاط (<?= number_format($invoice_data['points_used']) ?> نقطة):</span>
                            <span>-<?= number_format($invoice_data['discounts']['points'], 2) ?> ج.م</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="invoice-discount-item" style="color: #27ae60;">
                            <span><strong>إجمالي التوفير:</strong></span>
                            <span><strong>-<?= number_format($invoice_data['discounts']['total'], 2) ?> ج.م</strong></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="total-section">
                    💰 المجموع الإجمالي: <?= number_format($invoice_data['final_total'], 2) ?> جنيه مصري
                    <?php if ($invoice_data['discounts']['total'] > 0): ?>
                        <div style="font-size: 16px; margin-top: 10px; opacity: 0.9;">
                            (وفرت <?= number_format($invoice_data['discounts']['total'], 2) ?> ج.م)
                        </div>
                    <?php endif; ?>
                    <?php if ($invoice_data['earned_points'] > 0): ?>
                        <div style="font-size: 16px; margin-top: 5px; opacity: 0.9;">
                            🏆 كسبت <?= number_format($invoice_data['earned_points']) ?> نقطة جديدة!
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="buttons-container">
                    <button onclick="window.print()" class="btn btn-print">
                        <span>🖨️</span> طباعة الفاتورة
                    </button>
                    <a href="products.php" class="btn btn-continue">
                        <span>🛍️</span> متابعة التسوق
                    </a>
                </div>
            </div>
            
        <?php elseif ($show_shipping_form): ?>
            <!-- نموذج بيانات الشحن -->
            <div class="shipping-form">
                <h3>🚚 بيانات الشحن والتوصيل</h3>
                
                <form method="post">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="customer_name">👤 الاسم الكامل *</label>
                            <input type="text" name="customer_name" id="customer_name" required 
                                   placeholder="أدخل اسمك الكامل" maxlength="255">
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_phone">📱 رقم الجوال *</label>
                            <input type="tel" name="customer_phone" id="customer_phone" required 
                                   placeholder="01xxxxxxxxx" pattern="[0-9]{11}" maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_city">🏙️ المحافظة/المدينة *</label>
                            <select name="customer_city" id="customer_city" required>
                                <option value="">اختر المحافظة</option>
                                <option value="القاهرة">القاهرة</option>
                                <option value="الجيزة">الجيزة</option>
                                <option value="الإسكندرية">الإسكندرية</option>
                                <option value="القليوبية">القليوبية</option>
                                <option value="البحيرة">البحيرة</option>
                                <option value="المنوفية">المنوفية</option>
                                <option value="الغربية">الغربية</option>
                                <option value="كفر الشيخ">كفر الشيخ</option>
                                <option value="الدقهلية">الدقهلية</option>
                                <option value="دمياط">دمياط</option>
                                <option value="بورسعيد">بورسعيد</option>
                                <option value="الإسماعيلية">الإسماعيلية</option>
                                <option value="السويس">السويس</option>
                                <option value="شمال سيناء">شمال سيناء</option>
                                <option value="جنوب سيناء">جنوب سيناء</option>
                                <option value="الشرقية">الشرقية</option>
                                <option value="بني سويف">بني سويف</option>
                                <option value="الفيوم">الفيوم</option>
                                <option value="المنيا">المنيا</option>
                                <option value="أسيوط">أسيوط</option>
                                <option value="سوهاج">سوهاج</option>
                                <option value="قنا">قنا</option>
                                <option value="الأقصر">الأقصر</option>
                                <option value="أسوان">أسوان</option>
                                <option value="البحر الأحمر">البحر الأحمر</option>
                                <option value="الوادي الجديد">الوادي الجديد</option>
                                <option value="مطروح">مطروح</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="customer_address">📍 العنوان التفصيلي *</label>
                            <textarea name="customer_address" id="customer_address" required 
                                      placeholder="الشارع، المنطقة، رقم المبنى، الدور، رقم الشقة..." 
                                      style="min-height: 100px;"></textarea>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="delivery_notes">📝 ملاحظات التوصيل (اختياري)</label>
                            <textarea name="delivery_notes" id="delivery_notes" 
                                      placeholder="أي تعليمات خاصة للتوصيل، أوقات مفضلة، إلخ..."></textarea>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="final_checkout" class="btn btn-checkout">
                            <span>✅</span> تأكيد الطلب وإتمام الشراء
                        </button>
                        <a href="cart.php" class="btn btn-continue">
                            <span>←</span> الرجوع للسلة
                        </a>
                    </div>
                </form>
            </div>
            
        <?php elseif (!empty($_SESSION['cart'])): ?>
            <!-- نموذج الكوبونات -->
            <div class="coupon-section">
                <h3>🎫 كود الخصم</h3>
                
                <?php if (isset($_SESSION['coupon_code'])): ?>
                    <div class="active-coupon">
                        🏷️ كود فعال: <?= htmlspecialchars($_SESSION['coupon_code']) ?>
                        <span style="margin-right: 10px;">توفير: <?= number_format($_SESSION['coupon_discount'] ?? 0, 2) ?> ج.م</span>
                        <form method="post" style="display: inline; margin: 0;">
                            <button type="submit" name="remove_coupon" class="remove-coupon-btn">✕</button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <div class="coupon-input">
                            <input type="text" name="coupon_code" placeholder="أدخل كود الخصم هنا..." required>
                            <button type="submit" name="apply_coupon" class="apply-coupon-btn">
                                <i class="fas fa-check"></i> تطبيق
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- 🆕 قسم النقاط -->
            <div class="points-section">
                <h3>🏆 استخدام نقاط المكافآت</h3>
                
                <div class="points-balance">
                    <span class="balance-number"><?= number_format($customer_points) ?></span>
                    <span>نقطة متاحة (قيمتها: <?= number_format($points_system->pointsToMoney($customer_points), 2) ?> ج.م)</span>
                </div>
                
                <?php if (isset($_SESSION['points_used'])): ?>
                    <div class="active-points">
                        🏆 نقاط مستخدمة: <?= number_format($_SESSION['points_used']) ?> نقطة
                        <span style="margin-right: 10px;">توفير: <?= number_format($_SESSION['points_discount'], 2) ?> ج.م</span>
                        <form method="post" style="display: inline; margin: 0;">
                            <button type="submit" name="remove_points" class="remove-coupon-btn">✕</button>
                        </form>
                    </div>
                <?php else: ?>
                    <?php 
                    $min_redeem = intval($points_system->getSetting('min_points_redeem'));
                    $max_per_order = intval($points_system->getSetting('max_points_per_order'));
                    ?>
                    
                    <?php if ($customer_points >= $min_redeem): ?>
                        <form method="post" class="points-form">
                            <div class="points-input-group">
                                <input type="number" name="points_to_use" 
                                       min="<?= $min_redeem ?>" 
                                       max="<?= min($customer_points, $max_per_order) ?>" 
                                       step="10" 
                                       placeholder="مثال: 100"
                                       required>
                                <button type="submit" name="use_points" class="btn-use-points">
                                    💎 استخدام النقاط
                                </button>
                            </div>
                            <div class="points-help">
                                <small>💡 كل <?= 1 / floatval($points_system->getSetting('points_to_egp')) ?> نقطة = 1 جنيه خصم | الحد الأدنى: <?= number_format($min_redeem) ?> نقطة</small>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="points-insufficient">
                            <p>🔒 تحتاج <?= number_format($min_redeem - $customer_points) ?> نقطة إضافية للاستبدال</p>
                            <small>تسوق أكثر واحصل على نقاط! 🛍️</small>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- عرض محتويات السلة -->
            <form method="post">
                <div class="cart-table">
                    <table>
                        <thead>
                            <tr>
                                <th>المنتج</th>
                                <th>الكمية</th>
                                <th>سعر الوحدة</th>
                                <th>الإجمالي</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $grand_total = 0;
                            $has_quantity = $conn->query("SHOW COLUMNS FROM products LIKE 'quantity'")->num_rows > 0;
                            $has_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'")->num_rows > 0;
                            
                            foreach ($_SESSION['cart'] as $product_id => $qty):
                                $query = "SELECT name, price, image" . ($has_quantity ? ", quantity" : "") . " FROM products WHERE id=" . intval($product_id);
                                if ($has_status) {
                                    $query .= " AND status = 'active'";
                                }
                                $result = $conn->query($query);
                                
                                if ($result && $result->num_rows > 0) {
                                    $row = $result->fetch_assoc();
                                    $product_name = $row['name'] ?? 'منتج غير محدد';
                                    $product_price = floatval($row['price'] ?? 0);
                                    $product_image = $row['image'] ?? '';
                                    $subtotal = $product_price * $qty;
                                    $grand_total += $subtotal;
                                    $available_qty = $has_quantity ? intval($row['quantity'] ?? 0) : 999;

                                    // فحص إذا كان في المفضلة
                                    $wishlist_check = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
                                    $wishlist_check->bind_param("ii", $user_id, $product_id);
                                    $wishlist_check->execute();
                                    $in_wishlist = $wishlist_check->get_result()->num_rows > 0;
                            ?>
                            <tr id="cart-item-<?= $product_id ?>">
                                <td>
                                    <div class="product-info">
                                        <?php if (!empty($product_image)): ?>
                                            <img src="images/<?= htmlspecialchars($product_image) ?>" 
                                                 alt="<?= htmlspecialchars($product_name) ?>" 
                                                 class="product-image"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($product_name) ?></strong>
                                            <?php if ($has_quantity && $available_qty < $qty): ?>
                                                <div style="color: #e74c3c; font-size: 12px;">
                                                    ⚠️ متوفر فقط <?= $available_qty ?> قطع
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="quantity-controls">
                                        <button type="button" class="quantity-btn" onclick="updateQuantity(<?= $product_id ?>, -1)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" 
                                               id="qty-<?= $product_id ?>" 
                                               class="quantity-input" 
                                               value="<?= $qty ?>" 
                                               min="1" 
                                               onchange="updateQuantityDirect(<?= $product_id ?>, this.value)">
                                        <button type="button" class="quantity-btn" onclick="updateQuantity(<?= $product_id ?>, 1)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </td>
                                <td><?= number_format($product_price, 2) ?> ج.م</td>
                                <td id="subtotal-<?= $product_id ?>"><?= number_format($subtotal, 2) ?> ج.م</td>
                                <td>
                                    <div class="product-actions">
                                        <?php if (!$in_wishlist): ?>
                                            <form method="post" style="margin: 0;">
                                                <input type="hidden" name="product_id" value="<?= $product_id ?>">
                                                <button type="submit" name="add_to_wishlist" class="wishlist-btn">
                                                    ❤️ للمفضلة
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #28a745; font-size: 12px; font-weight: bold;">
                                                ❤️ في المفضلة
                                            </span>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="remove-btn" onclick="removeItem(<?= $product_id ?>)">
                                            🗑️ حذف
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                } else {
                                    unset($_SESSION['cart'][$product_id]);
                                }
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- قسم حساب الخصومات -->
                <div class="discount-section">
                    <div class="discount-row subtotal">
                        <span>الإجمالي الفرعي:</span>
                        <span><?= number_format($cart_summary['original_total'], 2) ?> ج.م</span>
                    </div>
                    
                    <!-- خصم الكوبون -->
                    <?php if ($cart_summary['discounts']['coupon'] > 0): ?>
                        <div class="discount-row discount coupon">
                            <span>كوبون الخصم (<?= $_SESSION['coupon_code'] ?>)</span>
                            <span>-<?= number_format($cart_summary['discounts']['coupon'], 2) ?> ج.م</span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- 🆕 خصم النقاط -->
                    <?php if ($cart_summary['discounts']['points'] > 0): ?>
                        <div class="discount-row discount points">
                            <span>خصم النقاط (<?= number_format($_SESSION['points_used']) ?> نقطة)</span>
                            <span>-<?= number_format($cart_summary['discounts']['points'], 2) ?> ج.م</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($cart_summary['discounts']['total'] > 0): ?>
                        <div class="discount-row discount" style="background: var(--gradient-success); color: white; font-weight: 800 !important;">
                            <span>🎊 إجمالي التوفير:</span>
                            <span>-<?= number_format($cart_summary['discounts']['total'], 2) ?> ج.م</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="discount-row final-total">
                        <span>المجموع الإجمالي:</span>
                        <span><?= number_format($cart_summary['final_total'], 2) ?> ج.م</span>
                    </div>
                    
                    <!-- تحذير إذا كان المجموع سالب -->
                    <?php if ($cart_summary['final_total'] <= 0): ?>
                        <div class="zero-total-warning">
                            ⚠️ تنبيه: المجموع النهائي صفر أو سالب بسبب الخصم الكبير!
                            <br>لا يمكن إتمام الطلب بهذا المبلغ.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="buttons-container">
                    <button type="submit" name="proceed_to_shipping" class="btn btn-checkout" <?= ($cart_summary['final_total'] <= 0) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                        <span>🚚</span> متابعة إلى بيانات الشحن
                        <?php if ($cart_summary['discounts']['total'] > 0 && $cart_summary['final_total'] > 0): ?>
                            <small style="font-size: 12px; display: block; margin-top: 4px;">
                                مع توفير <?= number_format($cart_summary['discounts']['total'], 2) ?> ج.م
                            </small>
                        <?php endif; ?>
                    </button>
                    <a href="products.php" class="btn btn-continue">
                        <span>←</span> متابعة التسوق
                    </a>
                </div>
            </form>
            
        <?php else: ?>
            <!-- السلة فارغة -->
            <div class="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <h3>سلة التسوق فارغة!</h3>
                <p>لم تضف أي منتجات بعد. تصفح متجرنا واختر ما يعجبك.</p>
                <br>
                <a href="products.php" class="btn btn-continue">
                    <span>🛍️</span> ابدأ التسوق الآن
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // وظائف الوضع الداكن
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

        // تحديث الكمية مع AJAX
        function updateQuantity(productId, change) {
            const input = document.getElementById('qty-' + productId);
            const currentQuantity = parseInt(input.value) || 1;
            const newQuantity = Math.max(1, currentQuantity + change);
            
            input.value = newQuantity;
            sendQuantityUpdate(productId, newQuantity);
        }

        // تحديث الكمية المباشر
        function updateQuantityDirect(productId, newValue) {
            const newQuantity = Math.max(1, parseInt(newValue) || 1);
            document.getElementById('qty-' + productId).value = newQuantity;
            sendQuantityUpdate(productId, newQuantity);
        }

        // إرسال طلب تحديث الكمية
        function sendQuantityUpdate(productId, newQuantity) {
            const formData = new FormData();
            formData.append('action', 'update_quantity');
            formData.append('product_id', productId);
            formData.append('quantity', newQuantity);
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const price = parseFloat(document.querySelector(`#cart-item-${productId} td:nth-child(3)`).textContent.replace(/[^\d.-]/g, ''));
                    const newSubtotal = (price * newQuantity).toFixed(2);
                    document.getElementById(`subtotal-${productId}`).textContent = newSubtotal + ' ج.م';
                    
                    location.reload();
                }
            })
            .catch(error => {
                console.error('خطأ:', error);
                showNotification('حدث خطأ في تحديث الكمية', 'error');
            });
        }

        // حذف منتج من السلة
        function removeItem(productId) {
            if (confirm('هل تريد حذف هذا المنتج من السلة؟')) {
                const formData = new FormData();
                formData.append('action', 'remove_item');
                formData.append('product_id', productId);
                
                fetch('cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('خطأ:', error);
                });
            }
        }

        // إظهار الإشعارات
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // تحميل الإعدادات عند بدء الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            const darkMode = localStorage.getItem('darkMode');
            const toggle = document.getElementById('theme-toggle');
            
            if (darkMode === 'enabled') {
                document.body.classList.add('dark-mode');
                toggle.textContent = '☀️';
            }

            // تحويل كود الكوبون لأحرف كبيرة
            const couponInput = document.querySelector('input[name="coupon_code"]');
            if (couponInput) {
                couponInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                });
            }

            console.log('✅ سلة المشتريات مع نظام النقاط جاهزة للاستخدام!');
        });
    </script>
</body>
</html>
