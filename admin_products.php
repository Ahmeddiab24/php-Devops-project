<?php
session_start();
include 'db.php';



// دي الأولى ✅ صحيحة
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}


$message = "";
$message_type = "";

// دالة لضغط وتحجيم الصور - مُصححة ومحدثة للحجم الجديد
function resizeAndCompressImage($source, $destination, $maxWidth = 400, $maxHeight = 300, $quality = 85) {
    $info = getimagesize($source);
    if (!$info) return false;
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];
    
    // حساب الأبعاد الجديدة مع الحفاظ على النسبة - مع التحويل الصريح
    $ratio = $width / $height;
    if ($maxWidth / $maxHeight > $ratio) {
        $newWidth = (int) round($maxHeight * $ratio);
        $newHeight = (int) $maxHeight;
    } else {
        $newWidth = (int) $maxWidth;
        $newHeight = (int) round($maxWidth / $ratio);
    }
    
    // إنشاء صورة جديدة
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // معالجة أنواع الصور المختلفة
    switch ($mime) {
        case 'image/jpeg':
            $source_image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($source);
            // الحفاظ على الشفافية
            imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            break;
        case 'image/gif':
            $source_image = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $source_image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    // تحجيم الصورة
    imagecopyresampled($newImage, $source_image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // حفظ الصورة المضغوطة
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($newImage, $destination, $quality);
            break;
        case 'image/png':
            imagepng($newImage, $destination, 6);
            break;
        case 'image/gif':
            imagegif($newImage, $destination);
            break;
        case 'image/webp':
            imagewebp($newImage, $destination, $quality);
            break;
    }
    
    // تنظيف الذاكرة
    imagedestroy($source_image);
    imagedestroy($newImage);
    
    return true;
}

// إضافة منتج جديد
if (isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 1);
    
    $image_name = "";
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "images/";
        
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array("jpg", "jpeg", "png", "gif", "webp");
        
        if (in_array($file_extension, $allowed_extensions)) {
            $image_name = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $image_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // تصغير الصورة إلى 400×300
                resizeAndCompressImage($target_file, $target_file, 400, 300, 85);
                
                $has_quantity_col = $conn->query("SHOW COLUMNS FROM products LIKE 'quantity'")->num_rows > 0;
                $has_status_col = $conn->query("SHOW COLUMNS FROM products LIKE 'status'")->num_rows > 0;
                $has_category_col = $conn->query("SHOW COLUMNS FROM products LIKE 'category_id'")->num_rows > 0;
                
                if ($has_quantity_col && $has_status_col && $has_category_col) {
                    $stmt = $conn->prepare("INSERT INTO products (name, price, quantity, category_id, image, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $stmt->bind_param("sdiis", $name, $price, $quantity, $category_id, $image_name);
                } else {
                    // حالات أخرى للتوافق مع قواعد البيانات القديمة
                    $stmt = $conn->prepare("INSERT INTO products (name, price, image) VALUES (?, ?, ?)");
                    $stmt->bind_param("sds", $name, $price, $image_name);
                }
                
                if ($stmt->execute()) {
                    $message = "✅ تم إضافة المنتج والصورة بنجاح!";
                    $message_type = "success";
                } else {
                    $message = "❌ حدث خطأ أثناء حفظ المنتج!";
                    $message_type = "error";
                }
            }
        } else {
            $message = "❌ نوع الملف غير مدعوم! استخدم: JPG, PNG, GIF, WEBP";
            $message_type = "error";
        }
    } else {
        $message = "❌ يرجى اختيار صورة للمنتج!";
        $message_type = "error";
    }
}

// تغيير حالة المنتج
if (isset($_GET['change_status'])) {
    $id = intval($_GET['id']);
    $new_status = $_GET['status'];
    
    $allowed_statuses = ['active', 'hidden', 'discontinued'];
    if (in_array($new_status, $allowed_statuses)) {
        $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        
        if ($stmt->execute()) {
            $status_messages = [
                'active' => '✅ تم تفعيل المنتج بنجاح!',
                'hidden' => '🔒 تم إخفاء المنتج بنجاح!',
                'discontinued' => '📦 تم تحديد المنتج كمنتهي الصلاحية!'
            ];
            $message = $status_messages[$new_status];
            $message_type = "success";
        }
    }
}

// حذف منتج
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $check_orders = $conn->query("SELECT COUNT(*) as count FROM order_items WHERE product_id = $id");
    $order_count = $check_orders->fetch_assoc()['count'];
    
    if ($order_count > 0) {
        $message = "❌ لا يمكن حذف هذا المنتج لأنه موجود في $order_count طلب/طلبات سابقة!";
        $message_type = "error";
    } else {
        $result = $conn->query("SELECT image FROM products WHERE id = $id");
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            $image_path = "images/" . $product['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        $conn->query("DELETE FROM products WHERE id = $id");
        $message = "🗑️ تم حذف المنتج نهائياً!";
        $message_type = "success";
    }
}

// تعديل منتج
if (isset($_POST['edit_product'])) {
    $id = intval($_POST['product_id']);
    $name = trim($_POST['edit_name']);
    $price = floatval($_POST['edit_price']);
    $quantity = intval($_POST['edit_quantity'] ?? 0);
    $category_id = intval($_POST['edit_category_id'] ?? 1);
    $status = $_POST['edit_status'] ?? 'active';
    
    if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] == 0) {
        $target_dir = "images/";
        $file_extension = strtolower(pathinfo($_FILES['edit_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array("jpg", "jpeg", "png", "gif", "webp");
        
        if (in_array($file_extension, $allowed_extensions)) {
            $result = $conn->query("SELECT image FROM products WHERE id = $id");
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
                $old_image_path = "images/" . $product['image'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            
            $image_name = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $image_name;
            
            if (move_uploaded_file($_FILES['edit_image']['tmp_name'], $target_file)) {
                // تصغير الصورة إلى 400×300
                resizeAndCompressImage($target_file, $target_file, 400, 300, 85);
                
                $stmt = $conn->prepare("UPDATE products SET name=?, price=?, quantity=?, category_id=?, image=?, status=? WHERE id=?");
                $stmt->bind_param("sdiissi", $name, $price, $quantity, $category_id, $image_name, $status, $id);
            }
        }
    } else {
        $stmt = $conn->prepare("UPDATE products SET name=?, price=?, quantity=?, category_id=?, status=? WHERE id=?");
        $stmt->bind_param("sdiisi", $name, $price, $quantity, $category_id, $status, $id);
    }
    
    if (isset($stmt) && $stmt->execute()) {
        $message = "✅ تم تحديث المنتج بنجاح!";
        $message_type = "success";
    }
}

$has_quantity = $conn->query("SHOW COLUMNS FROM products LIKE 'quantity'")->num_rows > 0;
$has_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'")->num_rows > 0;
$has_category = $conn->query("SHOW COLUMNS FROM products LIKE 'category_id'")->num_rows > 0;

// جلب الأقسام
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 إدارة المنتجات - MyShop</title>
    <style>
        /* إخفاء شريط التمرير تماماً */
        html, body {
            -ms-overflow-style: none;
            scrollbar-width: none;
            overflow-x: hidden;
        }

        body::-webkit-scrollbar, *::-webkit-scrollbar {
            display: none;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
            max-width: 100%;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto;
            box-sizing: border-box;
            width: 100%;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
        }
        
        .header h1 { 
            color: #333; 
            font-size: 28px; 
            margin-bottom: 10px; 
            word-wrap: break-word;
        }
        
        .nav-links { 
            margin-top: 15px; 
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .nav-links a {
            display: inline-block;
            margin: 5px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .nav-links a:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); 
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
            width: 100%;
            word-wrap: break-word;
        }
        
        .message.success { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        
        .message.error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        
        .add-form {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
        }
        
        .add-form h3 { 
            color: #333; 
            margin-bottom: 20px; 
            text-align: center; 
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group { 
            margin-bottom: 20px;
            width: 100%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus, .form-group select:focus { 
            outline: none; 
            border-color: #667eea; 
        }
        
        /* مربع رفع الصور المحسن */
        .file-upload-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .file-upload-display {
            text-align: center;
        }

        .file-upload-btn {
            display: block;
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            text-align: center;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid #28a745;
            font-size: 16px;
            font-weight: bold;
            margin: 0 auto 15px;
            box-sizing: border-box;
        }

        .file-upload-btn:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            border-color: #218838;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
        }

        .file-name {
            padding: 15px;
            background: #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            color: #495057;
            text-align: center;
            word-wrap: break-word;
            margin: 10px 0;
            display: none;
        }

        .file-name.selected {
            background: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
            display: block;
        }
        
        .image-preview {
            margin-top: 20px;
            text-align: center;
        }
        
        .image-preview img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        
        .upload-tips {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 14px;
            color: #1565c0;
            max-width: 400px;
            margin: 15px auto 0;
        }
        
        .upload-tips .tip-title {
            font-weight: bold;
            margin-bottom: 8px;
            display: block;
        }
        
        .upload-tips .tip-item {
            margin: 3px 0;
        }
        
        .btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            display: block;
        }
        
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3); 
        }
        
        .products-table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
        }
        
        .products-table h3 {
            background: #333;
            color: white;
            padding: 20px;
            margin: 0;
            text-align: center;
        }
        
        /* جدول بدون scroll أفقي */
        .table-container {
            width: 100%;
            overflow-x: auto;
        }
        
        .table-container::-webkit-scrollbar {
            display: none;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse;
            min-width: 700px;
        }
        
        th, td { 
            padding: 12px; 
            text-align: center; 
            border-bottom: 1px solid #eee;
            word-wrap: break-word;
            white-space: normal;
        }
        
        th { 
            background: #f8f9fa; 
            color: #333; 
            font-weight: bold; 
        }
        
        .product-image { 
            width: 60px; 
            height: 45px; 
            object-fit: cover; 
            border-radius: 8px;
        }
        
        .actions { 
            display: flex; 
            gap: 3px; 
            justify-content: center; 
            flex-wrap: wrap; 
        }
        
        .btn-edit, .btn-hide, .btn-show, .btn-discontinued, .btn-delete {
            padding: 4px 8px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .btn-edit { background: #ffc107; color: #333; }
        .btn-hide { background: #6c757d; color: white; }
        .btn-show { background: #28a745; color: white; }
        .btn-discontinued { background: #fd7e14; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-hidden { background: #f8d7da; color: #721c24; }
        .status-discontinued { background: #fff3cd; color: #856404; }
        
        .category-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-content::-webkit-scrollbar {
            display: none;
        }
        
        .close { 
            color: #aaa; 
            float: left; 
            font-size: 28px; 
            font-weight: bold; 
            cursor: pointer; 
        }
        
        .close:hover { color: #000; }
        
        .empty-state { 
            text-align: center; 
            padding: 50px; 
            color: #666; 
            font-size: 18px; 
        }
        
        /* للأجهزة الصغيرة */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .container { padding: 0; }
            .nav-links { flex-direction: column; align-items: center; }
            .nav-links a { width: 100%; max-width: 200px; text-align: center; }
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .actions { flex-direction: column; gap: 5px; }
            th, td { padding: 8px; font-size: 12px; }
            .table-container { overflow-x: auto; }
        }
       /* 🆕 قسم الوصول السريع للإدارة - تصميم بسيط وأنيق */
.admin-quick-access {
    background: rgba(255, 255, 255, 0.9);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    border: 1px solid rgba(102, 126, 234, 0.2);
}

.admin-quick-access h3 {
    color: #333;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 15px;
    text-align: center;
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
    border-radius: 50px;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s ease;
    box-shadow: 0 3px 8px rgba(102, 126, 234, 0.4);
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.admin-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(102, 126, 234, 0.6);
    color: white;
}

.admin-link i {
    font-size: 14px;
}

/* ألوان مختلفة وبسيطة */
.admin-link.discounts { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.admin-link.customers { background: linear-gradient(135deg, #27ae60, #229954); }
.admin-link.orders { background: linear-gradient(135deg, #f39c12, #d68910); }
.admin-link.categories { background: linear-gradient(135deg, #3498db, #2980b9); }
.admin-link.points-settings { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
.admin-link.points-reports { background: linear-gradient(135deg, #e67e22, #d35400); }
.admin-link.customers-points { background: linear-gradient(135deg, #16a085, #138d75); }

/* تصميم متجاوب */
@media (max-width: 768px) {
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
            <h1>📦 إدارة المنتجات</h1>
            <div class="nav-links">
                <a href="admin_dashboard.php">🏠 الرئيسية</a>
                <a href="logout.php">🚪 خروج</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- 🆕 قسم الوصول السريع للإدارة -->
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<div class="admin-quick-access">
    <h3><i class="fas fa-rocket"></i> الوصول السريع للإدارة</h3>
    <div class="admin-links-grid">
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
       
        <a href="admin_customers_points.php" class="admin-link customers-points">
            <i class="fas fa-users-cog"></i>
            <span>نقاط العملاء</span>
        </a>
    </div>
</div>
<?php endif; ?>

        <!-- نموذج إضافة منتج جديد -->
        <div class="add-form">
            <h3>➕ إضافة منتج جديد</h3>
            <form method="post" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم المنتج:</label>
                        <input type="text" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>السعر (ج.م):</label>
                        <input type="number" step="0.01" name="price" required>
                    </div>
                    
                    <div class="form-group">
                        <label>القسم:</label>
                        <select name="category_id" required>
                            <option value="">-- اختر القسم --</option>
                            <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                                <?php while ($category = $categories_result->fetch_assoc()): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <?php if ($has_quantity): ?>
                    <div class="form-group">
                        <label>الكمية في المخزون:</label>
                        <input type="number" name="quantity" value="0">
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>صورة المنتج:</label>
                    <div class="file-upload-container">
                        <input type="file" id="image" name="image" accept="image/*" required onchange="previewImage(this, 'image-preview')" style="display: none;">
                        
                        <div class="file-upload-display">
                            <button type="button" onclick="document.getElementById('image').click()" class="file-upload-btn">
                                📸 اختر صورة المنتج
                            </button>
                            <div id="file-name" class="file-name">لم يتم اختيار ملف</div>
                        </div>
                    </div>
                    
                    <div id="image-preview" class="image-preview"></div>
                    
                    <div class="upload-tips">
                        <span class="tip-title">💡 نصائح لرفع الصور:</span>
                        <div class="tip-item">📏 الحد الأقصى: 5MB</div>
                        <div class="tip-item">📐 الأبعاد الجديدة: 400×300 بكسل</div>
                        <div class="tip-item">🖼️ الأنواع: JPG, PNG, GIF, WEBP</div>
                        <div class="tip-item">⚡ ستتم معالجة الصور تلقائياً</div>
                    </div>
                </div>
                
                <button type="submit" name="add_product" class="btn">➕ إضافة المنتج</button>
            </form>
        </div>
        
        <!-- جدول المنتجات -->
        <div class="products-table">
            <h3>📋 قائمة المنتجات</h3>
            <?php
            $products_query = "SELECT p.*, c.name as category_name 
                              FROM products p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              ORDER BY p.id DESC";
            $result = $conn->query($products_query);
            if ($result && $result->num_rows > 0):
            ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>اسم المنتج</th>
                            <th>القسم</th>
                            <th>السعر</th>
                            <?php if ($has_quantity): ?><th>الكمية</th><?php endif; ?>
                            <?php if ($has_status): ?><th>الحالة</th><?php endif; ?>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if ($product['image']): ?>
                                    <img src="images/<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">لا توجد صورة</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td>
                                <span class="category-badge">
                                    <?= htmlspecialchars($product['category_name'] ?? 'غير محدد') ?>
                                </span>
                            </td>
                            <td><?= number_format($product['price'], 2) ?> ج.م</td>
                            <?php if ($has_quantity): ?>
                            <td style="color: <?= ($product['quantity'] ?? 0) < 5 ? 'red' : 'green' ?>">
                                <?= $product['quantity'] ?? 0 ?>
                                <?php if (($product['quantity'] ?? 0) < 5): ?>
                                    <br><small style="font-size: 10px;">⚠️ منخفض</small>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <?php if ($has_status): ?>
                            <td>
                                <?php
                                $status = $product['status'] ?? 'active';
                                $status_text = [
                                    'active' => '✅ نشط',
                                    'hidden' => '🔒 مخفي', 
                                    'discontinued' => '📦 منتهي'
                                ];
                                $status_class = 'status-' . $status;
                                ?>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= $status_text[$status] ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td>
                                <div class="actions">
                                    <a href="#" onclick="openEditModal(<?= $product['id'] ?>)" class="btn-edit">✏️ تعديل</a>
                                    
                                    <?php if ($has_status): ?>
                                        <?php if (($product['status'] ?? 'active') == 'active'): ?>
                                            <a href="?change_status=1&id=<?= $product['id'] ?>&status=hidden" class="btn-hide">🔒 إخفاء</a>
                                            <a href="?change_status=1&id=<?= $product['id'] ?>&status=discontinued" class="btn-discontinued">📦 منتهي</a>
                                        <?php elseif ($product['status'] == 'hidden'): ?>
                                            <a href="?change_status=1&id=<?= $product['id'] ?>&status=active" class="btn-show">✅ إظهار</a>
                                            <a href="?change_status=1&id=<?= $product['id'] ?>&status=discontinued" class="btn-discontinued">📦 منتهي</a>
                                        <?php else: ?>
                                            <a href="?change_status=1&id=<?= $product['id'] ?>&status=active" class="btn-show">✅ تفعيل</a>
                                            <a href="?change_status=1&id=<?= $product['id'] ?>&status=hidden" class="btn-hide">🔒 إخفاء</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <a href="?delete=<?= $product['id'] ?>" class="btn-delete" onclick="return confirm('هل تريد حذف هذا المنتج نهائياً؟')">🗑️ حذف</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    📦 لا توجد منتجات حتى الآن<br>
                    <small>ابدأ بإضافة منتجك الأول!</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة التعديل -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3>✏️ تعديل المنتج</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="product_id" id="edit_product_id">
                
                <div class="form-group">
                    <label>اسم المنتج:</label>
                    <input type="text" name="edit_name" id="edit_name" required>
                </div>
                
                <div class="form-group">
                    <label>السعر (ج.م):</label>
                    <input type="number" step="0.01" name="edit_price" id="edit_price" required>
                </div>
                
                <div class="form-group">
                    <label>القسم:</label>
                    <select name="edit_category_id" id="edit_category_id" required>
                        <?php 
                        $categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
                        if ($categories_result && $categories_result->num_rows > 0): 
                        ?>
                            <?php while ($category = $categories_result->fetch_assoc()): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <?php if ($has_quantity): ?>
                <div class="form-group">
                    <label>الكمية في المخزون:</label>
                    <input type="number" name="edit_quantity" id="edit_quantity">
                </div>
                <?php endif; ?>
                
                <?php if ($has_status): ?>
                <div class="form-group">
                    <label>حالة المنتج:</label>
                    <select name="edit_status" id="edit_status">
                        <option value="active">✅ نشط (يظهر للعملاء)</option>
                        <option value="hidden">🔒 مخفي (لا يظهر للعملاء)</option>
                        <option value="discontinued">📦 منتهي الصلاحية</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>تغيير الصورة (اختياري):</label>
                    <div class="file-upload-container">
                        <input type="file" id="edit_image" name="edit_image" accept="image/*" onchange="previewImage(this, 'edit-image-preview')" style="display: none;">
                        
                        <div class="file-upload-display">
                            <button type="button" onclick="document.getElementById('edit_image').click()" class="file-upload-btn">
                                📸 اختر صورة جديدة
                            </button>
                        </div>
                    </div>
                    <div id="edit-image-preview" class="image-preview"></div>
                </div>
                
                <button type="submit" name="edit_product" class="btn">💾 حفظ التعديلات</button>
            </form>
        </div>
    </div>
    
    <script>
        // منع Drag & Drop
        document.addEventListener('DOMContentLoaded', function() {
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(function(input) {
                input.ondragstart = function(e) { e.preventDefault(); return false; };
                input.ondragover = function(e) { e.preventDefault(); return false; };
                input.ondragenter = function(e) { e.preventDefault(); return false; };
                input.ondrop = function(e) { e.preventDefault(); return false; };
                input.ondragleave = function(e) { e.preventDefault(); return false; };
                input.draggable = false;
                input.setAttribute('draggable', 'false');
            });
        });
        
        const products = [
            <?php
            $products_result = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id");
            $products_js = [];
            while ($product = $products_result->fetch_assoc()) {
                $products_js[] = json_encode($product);
            }
            echo implode(',', $products_js);
            ?>
        ];
        
        function previewImage(input, previewId) {
            const previewDiv = document.getElementById(previewId);
            const fileNameDiv = document.getElementById('file-name');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (file.size > maxSize) {
                    alert('❌ حجم الملف كبير جداً! الحد الأقصى 5MB');
                    input.value = '';
                    return;
                }
                
                if (!allowedTypes.includes(file.type)) {
                    alert('❌ نوع الملف غير مدعوم! استخدم: JPG, PNG, GIF, WEBP');
                    input.value = '';
                    return;
                }
                
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                
                if (fileNameDiv) {
                    fileNameDiv.innerHTML = `
                        <div style="color: #28a745; font-weight: bold;">✅ تم اختيار الملف بنجاح:</div>
                        <div style="margin-top: 8px; font-size: 16px; color: #333;"><strong>${fileName}</strong></div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">الحجم: ${fileSize} MB</div>
                        <div style="font-size: 12px; color: #28a745; margin-top: 5px;">📐 سيتم تصغيرها إلى: 400×300 بكسل</div>
                    `;
                    fileNameDiv.className = 'file-name selected';
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.innerHTML = `
                        <img src="${e.target.result}" alt="معاينة الصورة" draggable="false">
                        <div style="margin-top: 10px; color: #666; font-size: 14px;">معاينة الصورة (سيتم تصغيرها إلى 400×300)</div>
                    `;
                }
                reader.readAsDataURL(file);
            } else {
                if (fileNameDiv) {
                    fileNameDiv.innerHTML = 'لم يتم اختيار ملف';
                    fileNameDiv.className = 'file-name';
                }
                previewDiv.innerHTML = '';
            }
        }
        
        function openEditModal(productId) {
            const product = products.find(p => p.id == productId);
            if (product) {
                document.getElementById('edit_product_id').value = product.id;
                document.getElementById('edit_name').value = product.name;
                document.getElementById('edit_price').value = product.price;
                
                if (document.getElementById('edit_category_id')) {
                    document.getElementById('edit_category_id').value = product.category_id || 1;
                }
                
                <?php if ($has_quantity): ?>
                document.getElementById('edit_quantity').value = product.quantity || 0;
                <?php endif; ?>
                
                <?php if ($has_status): ?>
                document.getElementById('edit_status').value = product.status || 'active';
                <?php endif; ?>
                
                const previewDiv = document.getElementById('edit-image-preview');
                if (product.image) {
                    previewDiv.innerHTML = '<img src="images/' + product.image + '" alt="الصورة الحالية" draggable="false"><br><small>الصورة الحالية (400×300)</small>';
                }
                
                document.getElementById('editModal').style.display = 'block';
            }
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('edit-image-preview').innerHTML = '';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
