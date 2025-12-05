<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// إضافة منتج للمفضلة
if (isset($_POST['add_to_wishlist'])) {
    $product_id = intval($_POST['product_id']);
    
    $check = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check->bind_param("ii", $user_id, $product_id);
    $check->execute();
    
    if ($check->get_result()->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $success_message = "تم إضافة المنتج للمفضلة ❤️";
    } else {
        $error_message = "المنتج موجود بالفعل في المفضلة";
    }
}

// حذف منتج من المفضلة
if (isset($_GET['remove'])) {
    $product_id = intval($_GET['remove']);
    $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    header("Location: wishlist.php");
    exit();
}

// جلب المنتجات المفضلة
$wishlist_query = "
    SELECT p.*, w.created_at as added_date 
    FROM wishlist w 
    JOIN products p ON w.product_id = p.id 
    WHERE w.user_id = ? 
    ORDER BY w.created_at DESC
";
$stmt = $conn->prepare($wishlist_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wishlist_items = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>المفضلة - MyShop</title>
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            direction: rtl;
        }
        
        .wishlist-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .wishlist-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .product-price {
            font-size: 18px;
            color: #28a745;
            font-weight: bold;
        }
        
        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-cart {
            background: #28a745;
            color: white;
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
        }
        
        .empty-wishlist {
            text-align: center;
            background: white;
            padding: 60px;
            border-radius: 20px;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="wishlist-container">
        <h1 style="text-align: center; color: white; margin-bottom: 30px;">
            ❤️ قائمة المفضلة
        </h1>
        
        <?php if ($wishlist_items->num_rows > 0): ?>
            <?php while ($item = $wishlist_items->fetch_assoc()): ?>
            <div class="wishlist-item">
                <?php if ($item['image']): ?>
                <img src="images/<?= htmlspecialchars($item['image']) ?>" 
                     alt="<?= htmlspecialchars($item['name']) ?>" 
                     class="product-image">
                <?php endif; ?>
                
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="product-price"><?= number_format($item['price'], 2) ?> ج.م</div>
                    <small>أضيف في: <?= date('Y/m/d', strtotime($item['added_date'])) ?></small>
                </div>
                
                <div class="actions">
                    <form method="post" action="cart.php">
                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" name="add_to_cart" class="btn btn-cart">
                            🛒 أضف للسلة
                        </button>
                    </form>
                    
                    <a href="?remove=<?= $item['id'] ?>" 
                       class="btn btn-remove"
                       onclick="return confirm('هل تريد حذف هذا المنتج من المفضلة؟')">
                        🗑️ حذف
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-wishlist">
                <h2>💔 قائمة المفضلة فارغة</h2>
                <p>لم تضف أي منتجات للمفضلة بعد</p>
                <a href="products.php" class="btn btn-cart">تصفح المنتجات</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
