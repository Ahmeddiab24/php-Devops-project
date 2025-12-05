<?php
/**
 * مدير الخصومات - كوبونات فقط
 * نظام مبسط يدعم الكوبونات من لوحة التحكم بس
 */
class DiscountManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * تطبيق كوبون خصم
     * @param string $coupon_code كود الكوبون
     * @param float $cart_total إجمالي السلة
     * @return float مبلغ الخصم
     */
    public function applyCoupon($coupon_code, $cart_total) {
        // تنظيف كود الكوبون
        $coupon_code = strtoupper(trim($coupon_code));
        
        if (empty($coupon_code)) {
            return 0;
        }
        
        // البحث عن الكوبون في قاعدة البيانات
        $stmt = $this->conn->prepare("
            SELECT d.*, dt.type as discount_type
            FROM discounts d 
            LEFT JOIN discount_types dt ON d.discount_type_id = dt.id
            WHERE d.coupon_code = ? 
            AND d.status = 'active' 
            AND d.start_date <= CURDATE() 
            AND d.end_date >= CURDATE()
            AND (d.usage_limit IS NULL OR d.used_count < d.usage_limit)
        ");
        
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($coupon = $result->fetch_assoc()) {
            // فحص الحد الأدنى للمبلغ
            if ($cart_total < floatval($coupon['min_amount'])) {
                return 0; // المبلغ أقل من الحد الأدنى
            }
            
            $discount_value = floatval($coupon['value']);
            
            // حساب الخصم حسب النوع
            if ($coupon['discount_type_id'] == 1 || $coupon['discount_type'] == 'percentage') {
                // خصم بالنسبة المئوية
                $discount = ($discount_value / 100) * $cart_total;
                return min($discount, $cart_total); // لا يتجاوز إجمالي السلة
            } else {
                // خصم مبلغ ثابت
                return min($discount_value, $cart_total); // لا يتجاوز إجمالي السلة
            }
        }
        
        return 0; // الكوبون غير صالح
    }
    
    /**
     * التحقق من صحة الكوبون (بدون تطبيق)
     * @param string $coupon_code كود الكوبون
     * @param float $cart_total إجمالي السلة
     * @return array معلومات الكوبون أو رسالة خطأ
     */
    public function validateCoupon($coupon_code, $cart_total) {
        $coupon_code = strtoupper(trim($coupon_code));
        
        if (empty($coupon_code)) {
            return ['valid' => false, 'message' => 'يرجى إدخال كود الخصم'];
        }
        
        $stmt = $this->conn->prepare("
            SELECT d.*, dt.type as discount_type, dt.name as type_name
            FROM discounts d 
            LEFT JOIN discount_types dt ON d.discount_type_id = dt.id
            WHERE d.coupon_code = ?
        ");
        
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$coupon = $result->fetch_assoc()) {
            return ['valid' => false, 'message' => 'كود الخصم غير صحيح'];
        }
        
        // فحص الحالة
        if ($coupon['status'] !== 'active') {
            return ['valid' => false, 'message' => 'كود الخصم غير نشط'];
        }
        
        // فحص التاريخ
        $today = date('Y-m-d');
        if ($today < $coupon['start_date']) {
            return ['valid' => false, 'message' => 'كود الخصم لم يبدأ بعد'];
        }
        
        if ($today > $coupon['end_date']) {
            return ['valid' => false, 'message' => 'انتهت صلاحية كود الخصم'];
        }
        
        // فحص حد الاستخدام
        if ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
            return ['valid' => false, 'message' => 'تم استنفاد كود الخصم'];
        }
        
        // فحص الحد الأدنى
        if ($cart_total < floatval($coupon['min_amount'])) {
            return [
                'valid' => false, 
                'message' => 'الحد الأدنى للخصم هو ' . number_format($coupon['min_amount'], 2) . ' ج.م'
            ];
        }
        
        // حساب مبلغ الخصم
        $discount_value = floatval($coupon['value']);
        
        if ($coupon['discount_type_id'] == 1 || $coupon['discount_type'] == 'percentage') {
            $discount_amount = ($discount_value / 100) * $cart_total;
            $discount_text = $discount_value . '%';
        } else {
            $discount_amount = $discount_value;
            $discount_text = number_format($discount_value, 2) . ' ج.م';
        }
        
        $discount_amount = min($discount_amount, $cart_total);
        
        return [
            'valid' => true,
            'message' => 'كود خصم صالح',
            'coupon' => $coupon,
            'discount_amount' => $discount_amount,
            'discount_text' => $discount_text,
            'savings' => number_format($discount_amount, 2) . ' ج.م'
        ];
    }
    
    /**
     * حساب إجمالي الخصومات (كوبونات فقط)
     * @param array $cart_items عناصر السلة
     * @param float $cart_total إجمالي السلة
     * @param int $user_id معرف المستخدم
     * @param string $coupon_code كود الكوبون (اختياري)
     * @return array تفاصيل الخصومات
     */
    public function calculateTotalDiscount($cart_items, $cart_total, $user_id, $coupon_code = null) {
        $discounts = [
            'coupon' => 0,
            'total' => 0
        ];
        
        // خصم الكوبون فقط
        if ($coupon_code) {
            $discounts['coupon'] = $this->applyCoupon($coupon_code, $cart_total);
        }
        
        // الإجمالي = الكوبون فقط
        $discounts['total'] = $discounts['coupon'];
        
        return $discounts;
    }
    
    /**
     * تحديث عداد استخدام الكوبون
     * @param string $coupon_code كود الكوبون
     * @return bool نجح التحديث أم لا
     */
    public function updateCouponUsage($coupon_code) {
        $stmt = $this->conn->prepare("
            UPDATE discounts 
            SET used_count = used_count + 1 
            WHERE coupon_code = ? AND status = 'active'
        ");
        
        $stmt->bind_param("s", $coupon_code);
        return $stmt->execute();
    }
    
    /**
     * الحصول على جميع الكوبونات النشطة (للعرض في الواجهة)
     * @return array قائمة الكوبونات
     */
    public function getActiveCoupons() {
        $stmt = $this->conn->prepare("
            SELECT d.*, dt.name as type_name
            FROM discounts d 
            LEFT JOIN discount_types dt ON d.discount_type_id = dt.id
            WHERE d.status = 'active' 
            AND d.start_date <= CURDATE() 
            AND d.end_date >= CURDATE()
            AND d.coupon_code IS NOT NULL
            AND d.coupon_code != ''
            ORDER BY d.created_at DESC
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $coupons = [];
        while ($row = $result->fetch_assoc()) {
            $coupons[] = $row;
        }
        
        return $coupons;
    }
    
    /**
     * إنشاء الجداول المطلوبة إن لم تكن موجودة
     */
    public function createTablesIfNotExist() {
        // جدول أنواع الخصومات
        $this->conn->query("CREATE TABLE IF NOT EXISTS discount_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            type ENUM('percentage','fixed_amount','coupon') DEFAULT 'coupon',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // جدول الخصومات
        $this->conn->query("CREATE TABLE IF NOT EXISTS discounts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            discount_type_id INT DEFAULT 1,
            value DECIMAL(10,2) NOT NULL,
            min_amount DECIMAL(10,2) DEFAULT 0,
            coupon_code VARCHAR(50) NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            usage_limit INT DEFAULT NULL,
            used_count INT DEFAULT 0,
            status ENUM('active','inactive','expired') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // إدخال أنواع الخصومات الأساسية
        $check = $this->conn->query("SELECT COUNT(*) as count FROM discount_types");
        if ($check->fetch_assoc()['count'] == 0) {
            $this->conn->query("INSERT INTO discount_types (name, description, type) VALUES
                ('خصم النسبة المئوية', 'خصم بالنسبة المئوية', 'percentage'),
                ('خصم مبلغ ثابت', 'خصم مبلغ ثابت بالجنيه', 'fixed_amount'),
                ('كوبون خصم', 'كود خصم يدخله العميل', 'coupon')
            ");
        }
    }
}

// إنشاء الجداول تلقائياً عند تحميل الكلاس
if (isset($conn)) {
    $temp_manager = new DiscountManager($conn);
    $temp_manager->createTablesIfNotExist();
    unset($temp_manager);
}
?>
