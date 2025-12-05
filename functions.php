<?php
// أضف هذه الدوال في نهاية db.php

// دالة للحصول على إحصائيات تقييم منتج
function getProductRating($product_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            ROUND(AVG(rating), 1) as average_rating,
            COUNT(*) as total_ratings
        FROM product_ratings 
        WHERE product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return [
        'average' => $result['average_rating'] ?: 0,
        'total' => $result['total_ratings'] ?: 0
    ];
}

// دالة للحصول على تقييم مستخدم معين لمنتج
function getUserRating($user_id, $product_id, $conn) {
    $stmt = $conn->prepare("SELECT rating FROM product_ratings WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? intval($result['rating']) : 0;
}
?>
<?php