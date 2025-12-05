-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 10, 2025 at 01:56 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `myshop`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int NOT NULL,
  `admin_id` int NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`, `description`) VALUES
(1, 'عطور رجالي', '2025-09-04 17:18:33', NULL),
(2, 'عطور نسائي', '2025-09-04 17:18:33', NULL),
(3, 'بخور', '2025-09-04 17:18:33', ''),
(4, 'عطور الجسد', '2025-09-04 17:18:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customer_points`
--

CREATE TABLE `customer_points` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `current_points` int DEFAULT '0',
  `total_earned` int DEFAULT '0',
  `total_spent` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customer_points`
--

INSERT INTO `customer_points` (`id`, `user_id`, `current_points`, `total_earned`, `total_spent`, `created_at`, `updated_at`) VALUES
(1, 12, 10819, 11319, 500, '2025-09-09 11:42:21', '2025-09-09 11:45:38'),
(2, 14, 17820, 17920, 100, '2025-09-09 12:08:01', '2025-09-09 12:15:21');

-- --------------------------------------------------------

--
-- Table structure for table `discount`
--

CREATE TABLE `discount` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `discount_type_id` int DEFAULT NULL,
  `value` decimal(10,2) NOT NULL,
  `min_amount` decimal(10,2) DEFAULT '0.00',
  `max_amount` decimal(10,2) DEFAULT NULL,
  `min_quantity` int DEFAULT NULL,
  `buy_quantity` int DEFAULT NULL,
  `get_quantity` int DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `usage_limit` int DEFAULT NULL,
  `used_count` int DEFAULT '0',
  `status` enum('active','inactive','expired') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discounts`
--

CREATE TABLE `discounts` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `discount_type_id` int DEFAULT NULL,
  `value` decimal(10,2) NOT NULL,
  `min_amount` decimal(10,2) DEFAULT '0.00',
  `max_amount` decimal(10,2) DEFAULT NULL,
  `min_quantity` int DEFAULT '1',
  `buy_quantity` int DEFAULT '0',
  `get_quantity` int DEFAULT '0',
  `coupon_code` varchar(50) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `usage_limit` int DEFAULT NULL,
  `used_count` int DEFAULT '0',
  `status` enum('active','inactive','expired') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `discounts`
--

INSERT INTO `discounts` (`id`, `name`, `discount_type_id`, `value`, `min_amount`, `max_amount`, `min_quantity`, `buy_quantity`, `get_quantity`, `coupon_code`, `category_id`, `product_id`, `start_date`, `end_date`, `usage_limit`, `used_count`, `status`, `created_at`) VALUES
(9, 'mo (500.00 ج.م)', 1, 500.00, 2000.00, NULL, 1, 0, 0, 'MO62', NULL, NULL, '2025-09-07', '2025-09-09', NULL, 2, 'active', '2025-09-07 23:08:57'),
(13, 'ha (500.00 ج.م)', 1, 500.00, 2000.00, NULL, 1, 0, 0, 'HA', NULL, NULL, '2025-09-08', '2025-10-08', 1, 1, 'active', '2025-09-08 15:03:32');

-- --------------------------------------------------------

--
-- Table structure for table `discount_types`
--

CREATE TABLE `discount_types` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `type` enum('percentage','fixed_amount','quantity_based','coupon','first_order','buy_x_get_y') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `discount_types`
--

INSERT INTO `discount_types` (`id`, `name`, `description`, `type`, `created_at`) VALUES
(1, 'خصم النسبة المئوية', 'خصم بالنسبة المئوية', 'percentage', '2025-09-07 16:48:08'),
(2, 'خصم مبلغ ثابت', 'خصم مبلغ ثابت بالجنيه', 'fixed_amount', '2025-09-07 16:48:08'),
(3, 'خصم الكمية', 'خصم حسب الكمية المشتراة', 'quantity_based', '2025-09-07 16:48:08'),
(4, 'كوبون خصم', 'كود خصم يدخله العميل', 'coupon', '2025-09-07 16:48:08'),
(5, 'خصم الطلب الأول', 'خصم 15% للعملاء الجدد', 'first_order', '2025-09-07 16:48:08'),
(6, 'اشتري واحصل مجاناً', 'اشتري قطعتين واحصل على الثالثة مجاناً', 'buy_x_get_y', '2025-09-07 16:48:08'),
(7, 'خصم النسبة المئوية', 'خصم بالنسبة المئوية', 'percentage', '2025-09-07 17:54:41'),
(8, 'خصم مبلغ ثابت', 'خصم مبلغ ثابت بالجنيه', 'fixed_amount', '2025-09-07 17:54:41'),
(9, 'كوبون خصم', 'كود خصم يدخله العميل', 'coupon', '2025-09-07 17:54:41'),
(10, 'خصم النسبة المئوية', 'خصم بالنسبة المئوية', 'percentage', '2025-09-07 18:02:40'),
(11, 'خصم مبلغ ثابت', 'خصم مبلغ ثابت بالجنيه', 'fixed_amount', '2025-09-07 18:02:40'),
(12, 'كوبون خصم', 'كود خصم يدخله العميل', 'coupon', '2025-09-07 18:02:40');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_address` text,
  `customer_city` varchar(100) DEFAULT NULL,
  `delivery_notes` text,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `shipping_address` text,
  `payment_method` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `original_total` decimal(10,2) DEFAULT '0.00' COMMENT 'الإجمالي قبل الخصم',
  `discount_percentage` decimal(5,2) DEFAULT '0.00' COMMENT 'نسبة الخصم %',
  `discount_amount` decimal(10,2) DEFAULT '0.00' COMMENT 'مبلغ الخصم',
  `first_order_discount` decimal(10,2) DEFAULT '0.00',
  `buy2get1_discount` decimal(10,2) DEFAULT '0.00',
  `coupon_discount` decimal(10,2) DEFAULT '0.00',
  `coupon_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `customer_phone`, `customer_address`, `customer_city`, `delivery_notes`, `total`, `created_at`, `total_amount`, `status`, `shipping_address`, `payment_method`, `updated_at`, `original_total`, `discount_percentage`, `discount_amount`, `first_order_discount`, `buy2get1_discount`, `coupon_discount`, `coupon_code`) VALUES
(28, 12, NULL, NULL, NULL, NULL, NULL, 340.00, '2025-09-07 13:47:26', 340.00, 'pending', NULL, NULL, '2025-09-07 13:47:26', 340.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL),
(29, 12, NULL, NULL, NULL, NULL, NULL, 2176.20, '2025-09-07 16:07:29', 2176.20, 'pending', NULL, NULL, '2025-09-07 16:07:29', 2418.00, 10.00, 241.80, 0.00, 0.00, 0.00, NULL),
(30, 12, NULL, NULL, NULL, NULL, NULL, 2168.10, '2025-09-07 16:37:39', 2168.10, 'pending', NULL, NULL, '2025-09-07 16:37:39', 2409.00, 10.00, 240.90, 0.00, 0.00, 0.00, NULL),
(31, 12, NULL, NULL, NULL, NULL, NULL, 1257.00, '2025-09-07 17:01:20', 1257.00, 'pending', NULL, NULL, '2025-09-07 17:01:20', 1620.00, 0.00, 363.00, 243.00, 120.00, 0.00, NULL),
(32, 12, NULL, NULL, NULL, NULL, NULL, 1900.00, '2025-09-07 18:07:16', 0.00, 'pending', NULL, NULL, '2025-09-07 18:07:16', 2400.00, 0.00, 500.00, 0.00, 0.00, 500.00, 'mo622'),
(33, 12, NULL, NULL, NULL, NULL, NULL, 1200.00, '2025-09-07 19:53:51', 0.00, 'pending', NULL, NULL, '2025-09-07 19:53:51', 1200.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL),
(34, 12, NULL, NULL, NULL, NULL, NULL, 2400.00, '2025-09-07 20:02:05', 0.00, 'pending', NULL, NULL, '2025-09-07 20:02:05', 2400.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL),
(35, 12, NULL, NULL, NULL, NULL, NULL, 1200.00, '2025-09-07 20:06:20', 0.00, 'pending', NULL, NULL, '2025-09-07 20:06:20', 1200.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL),
(38, 13, NULL, NULL, NULL, NULL, NULL, 4500.00, '2025-09-08 00:19:15', 0.00, 'pending', 'العنوان الافتراضي', 'cash_on_delivery', '2025-09-08 00:19:15', 5000.00, 10.00, 500.00, 0.00, 0.00, 500.00, 'MO62'),
(39, 14, NULL, NULL, NULL, NULL, NULL, 1500.00, '2025-09-08 00:21:26', 0.00, 'pending', 'العنوان الافتراضي', 'cash_on_delivery', '2025-09-08 00:21:26', 2000.00, 25.00, 500.00, 0.00, 0.00, 500.00, 'MO62'),
(40, 13, NULL, NULL, NULL, NULL, NULL, 6000.00, '2025-09-08 15:09:11', 0.00, 'pending', 'العنوان الافتراضي', 'cash_on_delivery', '2025-09-08 15:09:11', 6500.00, 7.69, 500.00, 0.00, 0.00, 500.00, 'HA'),
(41, 12, NULL, NULL, NULL, NULL, NULL, 17000.00, '2025-09-09 10:16:36', 0.00, 'pending', 'العنوان الافتراضي', 'cash_on_delivery', '2025-09-09 10:16:36', 17000.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL),
(42, 12, 'محمد حازم سيد', '01155351923', 'اسيوط منفلوط', 'أسيوط', '', 1200.00, '2025-09-09 10:30:20', 0.00, 'pending', 'اسيوط منفلوط', 'cash_on_delivery', '2025-09-09 10:30:20', 1200.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL),
(43, 13, 'يوسف عويس', '01737474847', 'فيصل', 'الجيزة', '', 2409.00, '2025-09-09 10:40:07', 0.00, 'pending', 'فيصل', 'cash_on_delivery', '2025-09-09 10:40:07', 2409.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL),
(44, 12, 'محمد حازم سيد', '01155351923', 'اسيوط المدينة', 'أسيوط', '', 10449.00, '2025-09-09 11:43:48', 0.00, 'pending', 'اسيوط المدينة', 'cash_on_delivery', '2025-09-09 11:43:48', 10449.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL),
(45, 12, 'محمد حازم سيد', '01155351923', 'اسيوط', 'أسيوط', '', 820.00, '2025-09-09 11:45:38', 0.00, 'pending', 'اسيوط', 'cash_on_delivery', '2025-09-09 11:45:38', 870.00, 5.75, 50.00, 0.00, 0.00, 0.00, NULL),
(46, 14, 'احمد', '44459695959', 'اسيوط', 'أسيوط', '', 17870.00, '2025-09-09 12:08:30', 0.00, 'pending', 'اسيوط', 'cash_on_delivery', '2025-09-09 12:08:30', 17870.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 28, 59, 1, 340.00),
(2, 29, 68, 2, 1209.00),
(3, 30, 62, 1, 1200.00),
(4, 30, 68, 1, 1209.00),
(5, 31, 78, 1, 1200.00),
(6, 31, 69, 1, 120.00),
(7, 31, 80, 1, 300.00),
(8, 32, 62, 2, 1200.00),
(9, 33, 62, 1, 1200.00),
(10, 34, 62, 2, 1200.00),
(11, 35, 62, 1, 1200.00),
(12, 38, 30, 1, 5000.00),
(13, 39, 78, 1, 1200.00),
(14, 39, 72, 1, 800.00),
(15, 40, 61, 1, 6500.00),
(16, 41, 39, 1, 5000.00),
(17, 41, 51, 1, 12000.00),
(18, 42, 78, 1, 1200.00),
(19, 43, 62, 1, 1200.00),
(20, 43, 68, 1, 1209.00),
(21, 44, 62, 2, 1200.00),
(22, 44, 68, 1, 1209.00),
(23, 44, 59, 1, 340.00),
(24, 44, 61, 1, 6500.00),
(25, 45, 42, 1, 870.00),
(26, 46, 51, 1, 12000.00),
(27, 46, 42, 1, 870.00),
(28, 46, 39, 1, 5000.00);

-- --------------------------------------------------------

--
-- Table structure for table `points_history`
--

CREATE TABLE `points_history` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action_type` enum('earned','spent') NOT NULL,
  `points` int NOT NULL,
  `reason` varchar(255) NOT NULL,
  `order_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `points_history`
--

INSERT INTO `points_history` (`id`, `user_id`, `action_type`, `points`, `reason`, `order_id`, `created_at`) VALUES
(1, 12, 'earned', 50, 'مكافأة ترحيبية للعضوية الجديدة', NULL, '2025-09-09 11:42:21'),
(2, 12, 'earned', 10449, 'نقاط من طلب رقم #44', 44, '2025-09-09 11:43:48'),
(3, 12, 'spent', 500, 'استبدال نقاط في طلب رقم #45', 45, '2025-09-09 11:45:38'),
(4, 12, 'earned', 820, 'نقاط من طلب رقم #45', 45, '2025-09-09 11:45:38'),
(5, 14, 'earned', 50, 'مكافأة ترحيبية للعضوية الجديدة', NULL, '2025-09-09 12:08:01'),
(6, 14, 'earned', 17870, 'نقاط من طلب رقم #46', 46, '2025-09-09 12:08:30'),
(7, 14, 'spent', 100, 'خصم نقاط من الإدارة', NULL, '2025-09-09 12:15:21');

-- --------------------------------------------------------

--
-- Table structure for table `points_settings`
--

CREATE TABLE `points_settings` (
  `id` int NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `points_settings`
--

INSERT INTO `points_settings` (`id`, `setting_name`, `setting_value`, `updated_at`) VALUES
(1, 'points_per_egp', '1', '2025-09-09 12:52:14'),
(2, 'points_to_egp', '0.1', '2025-09-09 12:52:14'),
(3, 'min_points_redeem', '1000', '2025-09-09 12:52:14'),
(4, 'max_points_per_order', '5000', '2025-09-09 12:52:14'),
(5, 'welcome_bonus_points', '50', '2025-09-09 12:52:14');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(100) NOT NULL,
  `description` text,
  `quantity` int DEFAULT '0',
  `product_code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','hidden','discontinued') NOT NULL DEFAULT 'active',
  `category_id` int DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `image`, `description`, `quantity`, `product_code`, `created_at`, `status`, `category_id`) VALUES
(20, 'MY WAY', 750.00, '68bd732a6f2bd_1757246250.jpg', NULL, 50, NULL, '2025-09-07 11:57:30', 'active', 2),
(21, 'PRANDA', 1120.00, '68bd73722f241_1757246322.jpg', NULL, 70, NULL, '2025-09-07 11:58:42', 'active', 2),
(22, 'GOOD GIRL', 3200.00, '68bd73f1726ab_1757246449.jpg', NULL, 23, NULL, '2025-09-07 12:00:49', 'active', 2),
(23, 'Si', 2900.00, '68bd741e34711_1757246494.jpg', NULL, 54, NULL, '2025-09-07 12:01:34', 'active', 2),
(24, 'ZARA', 900.00, '68bd743d0e4ed_1757246525.jpg', NULL, 99, NULL, '2025-09-07 12:02:05', 'active', 2),
(25, 'LAVERNE', 800.00, '68bd747412770_1757246580.jpg', NULL, 13, NULL, '2025-09-07 12:03:00', 'active', 2),
(26, 'VERSACE', 1950.00, '68bd74f72da0a_1757246711.jpg', NULL, 87, NULL, '2025-09-07 12:05:11', 'active', 2),
(27, 'COCO', 6000.00, '68bd7515b8487_1757246741.jpg', NULL, 12, NULL, '2025-09-07 12:05:41', 'active', 2),
(28, 'الانوثه', 350.00, '68bd755329c4f_1757246803.jpg', NULL, 49, NULL, '2025-09-07 12:06:43', 'active', 2),
(29, 'Shalis', 3300.00, '68bd757eee8a9_1757246846.jpg', NULL, 69, NULL, '2025-09-07 12:07:27', 'active', 2),
(30, 'Club De Nuit Maleka', 5000.00, '68bd76959a5dc_1757247125.jpg', NULL, 16, NULL, '2025-09-07 12:12:05', 'active', 2),
(31, 'Chloé', 800.00, '68bd772340faa_1757247267.jpg', NULL, 10, NULL, '2025-09-07 12:14:27', 'active', 2),
(32, 'Roja London', 2300.00, '68bd777db47b4_1757247357.jpg', NULL, 23, NULL, '2025-09-07 12:15:57', 'active', 2),
(33, 'Dolcissimo Sollievo', 3000.00, '68bd77e866adf_1757247464.jpg', NULL, 7, NULL, '2025-09-07 12:17:44', 'active', 2),
(34, 'GUCCI', 5070.00, '68bd78139e91f_1757247507.jpg', NULL, 6, NULL, '2025-09-07 12:18:27', 'active', 2),
(35, 'Reeh Al Flora', 2300.00, '68bd78ac2e97a_1757247660.jpg', NULL, 33, NULL, '2025-09-07 12:21:00', 'active', 2),
(36, 'SAUVAGE', 5600.00, '68bd79787a03a_1757247864.jpg', NULL, 12, NULL, '2025-09-07 12:24:24', 'active', 1),
(37, 'BLACK SEDUCTION', 4999.00, '68bd7a2072cf3_1757248032.jpg', NULL, 23, NULL, '2025-09-07 12:27:12', 'active', 1),
(38, 'BLEU DE CHANEL', 690.00, '68bd7a6c9b170_1757248108.jpg', NULL, 67, NULL, '2025-09-07 12:28:28', 'active', 1),
(39, 'BLACK OPIUM', 5000.00, '68bd7b7b553d4_1757248379.jpg', NULL, 41, NULL, '2025-09-07 12:32:59', 'active', 1),
(40, 'خيال', 800.00, '68bd7bc8cb11c_1757248456.jpg', NULL, 900, NULL, '2025-09-07 12:34:16', 'active', 1),
(41, 'تاج', 980.00, '68bd7be679cf2_1757248486.jpg', NULL, 90, NULL, '2025-09-07 12:34:46', 'active', 1),
(42, 'ALLURE', 870.00, '68bd7c0542edc_1757248517.jpg', NULL, 39, NULL, '2025-09-07 12:35:17', 'active', 1),
(43, 'DARING BLUE', 590.00, '68bd7c4d605a9_1757248589.jpg', NULL, 29, NULL, '2025-09-07 12:36:29', 'active', 1),
(44, 'L\'HOMME LACOSTE', 4900.00, '68bd7c6483010_1757248612.jpg', NULL, 44, NULL, '2025-09-07 12:36:52', 'active', 1),
(45, 'VALENTINO', 900.00, '68bd7c7ec7433_1757248638.jpg', NULL, 12, NULL, '2025-09-07 12:37:18', 'active', 1),
(46, 'Fahrenheit', 3200.00, '68bd7cdb7f037_1757248731.jpg', NULL, 56, NULL, '2025-09-07 12:38:51', 'active', 1),
(47, 'Dior HOMML', 1300.00, '68bd7d0aa324c_1757248778.jpg', NULL, 7, NULL, '2025-09-07 12:39:38', 'active', 1),
(48, 'DOLCE', 9088.00, '68bd7d268fd84_1757248806.jpg', NULL, 8, NULL, '2025-09-07 12:40:06', 'active', 1),
(49, 'BVLGARI', 7000.00, '68bd7dbbe790a_1757248955.jpg', NULL, 12, NULL, '2025-09-07 12:42:36', 'active', 1),
(50, 'BURBERRY', 5400.00, '68bd7dec471be_1757249004.jpg', NULL, 23, NULL, '2025-09-07 12:43:24', 'active', 1),
(51, 'BAD BOY', 12000.00, '68bd7e2b75bf3_1757249067.jpg', NULL, 7, NULL, '2025-09-07 12:44:27', 'active', 1),
(52, 'GIO', 7600.00, '68bd7e46f1417_1757249094.jpg', NULL, 30, NULL, '2025-09-07 12:44:55', 'active', 1),
(53, 'STRONG WITH YOU', 2300.00, '68bd7e5dbdd5d_1757249117.jpg', NULL, 4, NULL, '2025-09-07 12:45:17', 'active', 1),
(54, 'BLEU DE CHANEL', 6000.00, '68bd7e8627ea8_1757249158.jpg', NULL, 4, NULL, '2025-09-07 12:45:58', 'active', 1),
(55, 'عبق الشرق', 17000.00, '68bd7f9f746fe_1757249439.jpg', NULL, 12, NULL, '2025-09-07 12:50:39', 'active', 3),
(56, 'سرّ العنبر', 800.00, '68bd7fdfcb4e3_1757249503.jpg', NULL, 13, NULL, '2025-09-07 12:51:43', 'active', 3),
(57, 'روح المسك', 1200.00, '68bd8003aeca5_1757249539.jpg', NULL, 50, NULL, '2025-09-07 12:52:19', 'active', 3),
(58, 'سحر العود', 600.00, '68bd807d2af7c_1757249661.jpg', NULL, 10, NULL, '2025-09-07 12:54:21', 'active', 3),
(59, 'الكهرمان', 340.00, '68bd8092e0e40_1757249682.jpg', NULL, 43, NULL, '2025-09-07 12:54:43', 'active', 3),
(60, 'عبير الزعفران', 1234.00, '68bd80ba2a32a_1757249722.jpg', NULL, 75, NULL, '2025-09-07 12:55:22', 'active', 3),
(61, 'بخور الياسمين', 6500.00, '68bd80e30f2c8_1757249763.jpg', NULL, 10, NULL, '2025-09-07 12:56:03', 'active', 3),
(62, 'أسرار الطيب', 1200.00, '68bd8148834dd_1757249864.jpg', NULL, 12, NULL, '2025-09-07 12:57:44', 'active', 3),
(63, 'هالة الروح', 150.00, '68bd81a6dca32_1757249958.jpg', NULL, 76, NULL, '2025-09-07 12:59:18', 'active', 3),
(64, 'مسك الغروب', 230.00, '68bd81bf4f659_1757249983.jpg', NULL, 12, NULL, '2025-09-07 12:59:43', 'active', 3),
(65, 'طيف الريحان', 1200.00, '68bd8206bbe55_1757250054.jpg', NULL, 55, NULL, '2025-09-07 13:00:54', 'active', 3),
(66, 'همس الليالي', 2300.00, '68bd8223992b6_1757250083.jpg', NULL, 12, NULL, '2025-09-07 13:01:23', 'active', 3),
(67, 'عطر الصندل', 670.00, '68bd823de52bc_1757250109.jpg', NULL, 120, NULL, '2025-09-07 13:01:50', 'active', 3),
(68, 'اسرار', 1209.00, '68bd825b972e2_1757250139.jpg', NULL, 117, NULL, '2025-09-07 13:02:19', 'active', 3),
(69, 'Body perfume1', 120.00, '68bd835330761_1757250387.jpg', NULL, 99, NULL, '2025-09-07 13:06:27', 'active', 4),
(70, 'Body perfume2', 300.00, '68bd8367480d2_1757250407.jpg', NULL, 120, NULL, '2025-09-07 13:06:47', 'active', 4),
(71, 'Body perfume3', 300.00, '68bd83772943a_1757250423.jpg', NULL, 50, NULL, '2025-09-07 13:07:03', 'active', 4),
(72, 'Body perfume4', 800.00, '68bd838b03026_1757250443.jpg', NULL, 129, NULL, '2025-09-07 13:07:23', 'active', 4),
(73, 'Body perfume5', 280.00, '68bd83a29f18e_1757250466.jpg', NULL, 160, NULL, '2025-09-07 13:07:46', 'active', 4),
(74, 'Body perfume6', 690.00, '68bd83b94ce3c_1757250489.jpg', NULL, 33, NULL, '2025-09-07 13:08:09', 'active', 4),
(75, 'Body perfume7', 147.00, '68bd83cac2485_1757250506.jpg', NULL, 23, NULL, '2025-09-07 13:08:26', 'active', 4),
(76, 'Body perfume8', 290.00, '68bd83e570ee7_1757250533.jpg', NULL, 90, NULL, '2025-09-07 13:08:53', 'active', 4),
(77, 'Body perfume9', 590.00, '68bd840503b91_1757250565.jpg', NULL, 78, NULL, '2025-09-07 13:09:25', 'active', 4),
(78, '212', 1200.00, '68bd841edcfdb_1757250590.jpg', NULL, 53, NULL, '2025-09-07 13:09:51', 'active', 4),
(79, 'ZF', 1700.00, '68bd843eb5080_1757250622.jpg', NULL, 34, NULL, '2025-09-07 13:10:22', 'active', 4),
(80, 'Body perfume10', 300.00, '68bd8452ec289_1757250642.jpg', NULL, 58, NULL, '2025-09-07 13:10:43', 'active', 4);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `first_order` tinyint(1) DEFAULT '1',
  `address` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password`, `is_blocked`, `first_order`, `address`) VALUES
(12, 'محمد حازم سيد', 'mo123@gmail.com', NULL, '$2y$10$C7Ss1.Y2bRudwrR2f6ZRI.sOOCY/3xQGGxhvQBfSPbbp8D529RIcO', 0, 1, NULL),
(13, 'يوسف عويس', 'jo12@gmail.com', NULL, '$2y$10$7E9E/irKdMkgfwFvBaPoCOv/5RJk.OiCFLoXGwCKRZkHnfnXAChfa', 0, 1, NULL),
(14, 'احمد ايهاب', 'ahmed12@gmail.com', NULL, '$2y$10$vwemz.izfgkEFqjlaCY1G..cbNw7TSC06eo3DA1bjU.BLOc6GNIDe', 0, 1, NULL),
(15, 'عبدالله', 'abdallah23@gmail.com', NULL, '$2y$10$FjQbY68N3DsKCsGJXqYzpeHV6vNYmuefY/5jSAuOBpSkBwSwwFSzq', 0, 1, NULL),
(16, 'احمد', 'ahmed56@gmail.com', NULL, '$2y$10$HBB5lTVlyiY9Oy32CV7PO.VOJnxyvwZKhU2T9RAsZr3JGMCZVaoPC', 0, 1, NULL),
(17, 'حازم', 'hazem@gmail.com', '01020625895', '$2y$10$iiKpuQdAMmFErZYKFOEdJ.Psz0u0TxJWcwC2OxBUWN9KwLB3AZJae', 0, 0, 'اسيوط منفلوط');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(9, 13, 62, '2025-09-09 10:38:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_points`
--
ALTER TABLE `customer_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `discount`
--
ALTER TABLE `discount`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupon_code` (`coupon_code`);

--
-- Indexes for table `discounts`
--
ALTER TABLE `discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `discount_type_id` (`discount_type_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `discount_types`
--
ALTER TABLE `discount_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_orders_discount` (`discount_percentage`),
  ADD KEY `idx_orders_total` (`original_total`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `points_history`
--
ALTER TABLE `points_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `points_settings`
--
ALTER TABLE `points_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer_points`
--
ALTER TABLE `customer_points`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `discount`
--
ALTER TABLE `discount`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discounts`
--
ALTER TABLE `discounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `discount_types`
--
ALTER TABLE `discount_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `points_history`
--
ALTER TABLE `points_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `points_settings`
--
ALTER TABLE `points_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_points`
--
ALTER TABLE `customer_points`
  ADD CONSTRAINT `customer_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discounts`
--
ALTER TABLE `discounts`
  ADD CONSTRAINT `discounts_ibfk_1` FOREIGN KEY (`discount_type_id`) REFERENCES `discount_types` (`id`),
  ADD CONSTRAINT `discounts_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `discounts_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `points_history`
--
ALTER TABLE `points_history`
  ADD CONSTRAINT `points_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `points_history_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
