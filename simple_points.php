<?php
class SimplePoints {
    private $conn;
    private $settings = [];
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->loadSettings();
    }
    
    // تحميل إعدادات النقاط من قاعدة البيانات
    private function loadSettings() {
        $result = $this->conn->query("SELECT setting_name, setting_value FROM points_settings");
        while ($row = $result->fetch_assoc()) {
            $this->settings[$row['setting_name']] = $row['setting_value'];
        }
    }
    
    // إنشاء حساب نقاط للعميل الجديد
    public function createCustomerPoints($user_id) {
        $check = $this->conn->prepare("SELECT id FROM customer_points WHERE user_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        
        if ($check->get_result()->num_rows == 0) {
            $welcome_bonus = intval($this->settings['welcome_bonus_points']);
            $stmt = $this->conn->prepare("INSERT INTO customer_points (user_id, current_points, total_earned) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $user_id, $welcome_bonus, $welcome_bonus);
            $stmt->execute();
            
            // تسجيل النقاط الترحيبية
            if ($welcome_bonus > 0) {
                $this->addPointsHistory($user_id, 'earned', $welcome_bonus, 'مكافأة ترحيبية للعضوية الجديدة');
            }
            
            return true;
        }
        return false;
    }
    
    // إضافة نقاط للعميل
    public function addPoints($user_id, $points, $reason, $order_id = null) {
        // تحديث رصيد العميل
        $stmt = $this->conn->prepare("
            UPDATE customer_points 
            SET current_points = current_points + ?, 
                total_earned = total_earned + ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param("iii", $points, $points, $user_id);
        $stmt->execute();
        
        // تسجيل في التاريخ
        $this->addPointsHistory($user_id, 'earned', $points, $reason, $order_id);
        
        return true;
    }
    
    // خصم نقاط من العميل
    public function spendPoints($user_id, $points, $reason, $order_id = null) {
        $current_points = $this->getCustomerPoints($user_id);
        
        if ($current_points < $points) {
            return ['success' => false, 'message' => 'رصيد النقاط غير كافي'];
        }
        
        // خصم النقاط
        $stmt = $this->conn->prepare("
            UPDATE customer_points 
            SET current_points = current_points - ?, 
                total_spent = total_spent + ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param("iii", $points, $points, $user_id);
        $stmt->execute();
        
        // تسجيل في التاريخ
        $this->addPointsHistory($user_id, 'spent', $points, $reason, $order_id);
        
        return ['success' => true, 'message' => 'تم خصم النقاط بنجاح'];
    }
    
    // حساب النقاط من مبلغ الشراء (بناءً على الإعدادات)
    public function calculatePointsFromAmount($amount) {
        $points_per_egp = floatval($this->settings['points_per_egp']);
        return floor($amount * $points_per_egp);
    }
    
    // تحويل النقاط إلى قيمة نقدية (بناءً على الإعدادات)
    public function pointsToMoney($points) {
        $points_to_egp = floatval($this->settings['points_to_egp']);
        return $points * $points_to_egp;
    }
    
    // جلب رصيد العميل
    public function getCustomerPoints($user_id) {
        $stmt = $this->conn->prepare("SELECT current_points FROM customer_points WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return intval($row['current_points']);
        }
        
        // إنشاء حساب جديد إذا لم يكن موجود
        $this->createCustomerPoints($user_id);
        return intval($this->settings['welcome_bonus_points']);
    }
    
    // جلب إعداد معين
    public function getSetting($setting_name) {
        return $this->settings[$setting_name] ?? '';
    }
    
    // تحديث إعداد معين (للأدمن)
    public function updateSetting($setting_name, $setting_value) {
        $stmt = $this->conn->prepare("UPDATE points_settings SET setting_value = ?, updated_at = NOW() WHERE setting_name = ?");
        $stmt->bind_param("ss", $setting_value, $setting_name);
        $stmt->execute();
        
        // إعادة تحميل الإعدادات
        $this->loadSettings();
        
        return true;
    }
    
    // إضافة سجل في تاريخ النقاط
    private function addPointsHistory($user_id, $action_type, $points, $reason, $order_id = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO points_history (user_id, action_type, points, reason, order_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isisi", $user_id, $action_type, $points, $reason, $order_id);
        $stmt->execute();
    }
    
    // جلب تاريخ نقاط العميل
    public function getPointsHistory($user_id, $limit = 20) {
        $stmt = $this->conn->prepare("
            SELECT * FROM points_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
