<?php
session_start();
include 'db.php';
include 'discount_manager.php';

if(isset($_POST['coupon_code'])) {
    $coupon_code = trim($_POST['coupon_code']);
    $discount_manager = new DiscountManager($conn);
    
    // حساب إجمالي السلة
    $cart_total = 0;
    if(!empty($_SESSION['cart'])) {
        foreach($_SESSION['cart'] as $product_id => $quantity) {
            $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if($product = $result->fetch_assoc()) {
                $cart_total += $product['price'] * $quantity;
            }
        }
    }
    
    // تطبيق الكوبون
    $coupon_discount = $discount_manager->applyCoupon($coupon_code, $cart_total);
    
    if($coupon_discount > 0) {
        $_SESSION['coupon_code'] = $coupon_code;
        $_SESSION['message'] = "✅ تم تطبيق كوبون الخصم بنجاح!";
    } else {
        $_SESSION['error'] = "❌ كود الخصم غير صالح أو منتهي الصلاحية!";
    }
}

header("Location: cart.php");
exit();
?>
