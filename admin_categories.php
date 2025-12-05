<?php
session_start();
include 'db.php';

// فحص تسجيل دخول الأدمن
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

// إضافة فئة جديدة
if (isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    // فحص إذا الفئة موجودة
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE name = ?");
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc()['count'] > 0;
    
    if ($exists) {
        $message = "❌ هذه الفئة موجودة بالفعل!";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        
        if ($stmt->execute()) {
            $message = "✅ تم إضافة الفئة '$name' بنجاح!";
            $message_type = "success";
        } else {
            $message = "❌ حدث خطأ أثناء إضافة الفئة!";
            $message_type = "error";
        }
    }
}

// تعديل فئة
if (isset($_POST['edit_category'])) {
    $id = intval($_POST['category_id']);
    $name = trim($_POST['edit_name']);
    $description = trim($_POST['edit_description']);
    
    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $description, $id);
    
    if ($stmt->execute()) {
        $message = "✅ تم تحديث الفئة بنجاح!";
        $message_type = "success";
    } else {
        $message = "❌ حدث خطأ أثناء تحديث الفئة!";
        $message_type = "error";
    }
}

// حذف فئة
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // فحص إذا كان للفئة منتجات
    $products_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $products_stmt->bind_param("i", $id);
    $products_stmt->execute();
    $product_count = $products_stmt->get_result()->fetch_assoc()['count'];
    
    if ($product_count > 0) {
        $message = "❌ لا يمكن حذف هذه الفئة لأن بها $product_count منتج/منتجات!";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "🗑️ تم حذف الفئة بنجاح!";
            $message_type = "success";
        } else {
            $message = "❌ حدث خطأ أثناء حذف الفئة!";
            $message_type = "error";
        }
    }
}

// جلب الفئات مع عدد المنتجات
$categories_query = "SELECT c.*, COUNT(p.id) as product_count 
                     FROM categories c 
                     LEFT JOIN products p ON c.id = p.category_id 
                     GROUP BY c.id 
                     ORDER BY c.name";
$categories_result = $conn->query($categories_query);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏷️ إدارة الفئات - بَهيّ للعطور</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            direction: rtl;
            padding: 20px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        body::-webkit-scrollbar { display: none; }
        
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 { color: #333; font-size: 28px; margin-bottom: 10px; }
        
        .nav-links { 
            margin-top: 15px; 
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .nav-links a {
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

        /* 🆕 قسم الوصول السريع للإدارة */
        .admin-quick-access {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
        }
        .admin-link.customers { 
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); 
        }
        .admin-link.orders { 
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); 
        }
        .admin-link.discounts { 
            background: linear-gradient(135deg, #fd7e14 0%, #e66100 100%); 
        }
        .admin-link.points-settings { 
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); 
        }
        .admin-link.points-reports { 
            background: linear-gradient(135deg, #e83e8c 0%, #d91a72 100%); 
        }
        .admin-link.customers-points { 
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); 
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
            line-height: 1.6;
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
        }
        
        .add-form h3 { color: #333; margin-bottom: 20px; text-align: center; }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            font-family: 'Cairo', sans-serif;
        }
        
        .form-group textarea { height: 80px; resize: vertical; }
        
        .form-group input:focus, .form-group textarea:focus { 
            outline: none; 
            border-color: #667eea; 
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
            max-width: 200px;
            margin: 0 auto;
            display: block;
        }
        
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3); }
        
        .categories-table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .categories-table h3 { background: #333; color: white; padding: 20px; margin: 0; text-align: center; }
        
        .table-container { overflow-x: auto; }
        
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        
        th, td { padding: 15px; text-align: center; border-bottom: 1px solid #eee; }
        
        th { background: #f8f9fa; color: #333; font-weight: bold; }
        
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin: 2px;
            display: inline-block;
        }
        
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        
        .btn-edit:hover { background: #e0a800; }
        .btn-delete:hover { background: #c82333; }
        
        .category-icon {
            font-size: 24px;
            margin-bottom: 5px;
            display: block;
        }
        
        .product-count {
            background: #e3f2fd; color: #1565c0; font-size: 11px; padding: 3px 8px; border-radius: 12px;
            margin-top: 5px; display: inline-block;
        }
        
        .empty-state { text-align: center; padding: 50px; color: #666; font-size: 18px; }
        
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
            margin: 10% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }
        
        .close { 
            color: #aaa; 
            float: left; 
            font-size: 28px; 
            font-weight: bold; 
            cursor: pointer; 
        }
        
        .close:hover { color: #000; }
        
        @media (max-width: 768px) {
            body { padding: 10px; }
            .nav-links { flex-direction: column; align-items: center; }
            .nav-links a { width: 100%; max-width: 200px; text-align: center; }
            .form-grid { grid-template-columns: 1fr; }
            th, td { padding: 8px; font-size: 12px; }
            
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
            <h1>🏷️ إدارة فئات المنتجات</h1>
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
                 <a href="admin_orders.php" class="admin-link orders">
                    <i class="fas fa-shopping-cart"></i>
                    <span>إدارة الطلبات</span>
                </a>
                <a href="admin_customers.php" class="admin-link customers">
                    <i class="fas fa-users"></i>
                    <span>إدارة العملاء</span>
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
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <!-- نموذج إضافة فئة جديدة -->
        <div class="add-form">
            <h3>➕ إضافة فئة جديدة</h3>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم الفئة:</label>
                        <input type="text" name="name" required maxlength="100" placeholder="مثال: عطور رجالي">
                    </div>
                    
                    <div class="form-group">
                        <label>وصف الفئة:</label>
                        <textarea name="description" placeholder="وصف مختصر للفئة (اختياري)"></textarea>
                    </div>
                </div>
                
                <button type="submit" name="add_category" class="btn">➕ إضافة الفئة</button>
            </form>
        </div>
        
        <!-- جدول الفئات -->
        <div class="categories-table">
            <h3>📋 قائمة الفئات</h3>
            <?php if ($categories_result && $categories_result->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>اسم الفئة</th>
                            <th>الوصف</th>
                            <th>عدد المنتجات</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div>
                                    <?php 
                                    // أيقونات الفئات
                                    $icons = [
                                        'عطور رجالي' => '👔',
                                        'عطور نسائي' => '💎', 
                                        'بخور' => '🔮',
                                        'عطور الجسد' => '✨'
                                    ];
                                    $icon = $icons[$category['name']] ?? '🏷️';
                                    ?>
                                    <span class="category-icon"><?= $icon ?></span>
                                    <strong><?= htmlspecialchars($category['name']) ?></strong>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($category['description'] ?? 'لا يوجد وصف') ?></td>
                            <td>
                                <?= $category['product_count'] ?>
                                <div class="product-count"><?= $category['product_count'] ?> منتج</div>
                            </td>
                            <td>
                                <a href="#" onclick="openEditModal(<?= $category['id'] ?>)" class="btn-edit">✏️ تعديل</a>
                                
                                <?php if ($category['product_count'] == 0): ?>
                                    <a href="?delete=<?= $category['id'] ?>" class="btn-delete" 
                                       onclick="return confirm('هل تريد حذف فئة <?= htmlspecialchars($category['name']) ?>؟')">
                                        🗑️ حذف
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">لا يمكن الحذف</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    🏷️ لا توجد فئات حتى الآن<br>
                    <small>ابدأ بإضافة فئة المنتجات الأولى!</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة التعديل -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3>✏️ تعديل الفئة</h3>
            <form method="post">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="form-group">
                    <label>اسم الفئة:</label>
                    <input type="text" name="edit_name" id="edit_name" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label>وصف الفئة:</label>
                    <textarea name="edit_description" id="edit_description"></textarea>
                </div>
                
                <button type="submit" name="edit_category" class="btn">💾 حفظ التعديلات</button>
            </form>
        </div>
    </div>
    
    <script>
        const categories = [
            <?php
            $categories_result = $conn->query($categories_query);
            $categories_js = [];
            while ($category = $categories_result->fetch_assoc()) {
                $categories_js[] = json_encode($category);
            }
            echo implode(',', $categories_js);
            ?>
        ];
        
        function openEditModal(categoryId) {
            const category = categories.find(c => c.id == categoryId);
            if (category) {
                document.getElementById('edit_category_id').value = category.id;
                document.getElementById('edit_name').value = category.name;
                document.getElementById('edit_description').value = category.description || '';
                document.getElementById('editModal').style.display = 'block';
            }
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
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
