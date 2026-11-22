<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// THÔNG TIN KẾT NỐI DATABASE TRÊN MYSQL WORKBENCH
$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'shoes_php_db';
$db_user = 'phpuser';
$db_pass = '123456';

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user,$db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Lỗi kết nối MySQL: " . htmlspecialchars($e->getMessage()));
}

function formatMoney($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

// Hàm lấy ảnh sản phẩm (Hỗ trợ cả link online, ảnh nội bộ và bộ ảnh mẫu theo ID)
function getProductImage($jsonImages, $productId = '') {$shoeImages = [
        '4iKReQ' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?w=600&q=80',
        'u2aJZe' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=600&q=80',
        'Eq1H5L' => 'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?w=600&q=80',
        'eWtkD1' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&q=80',
        'FJWheJ' => 'https://images.unsplash.com/photo-1518002171953-a080ee817e1f?w=600&q=80',
        'kfITXR' => 'https://images.unsplash.com/photo-1560769629-975ec94e6a86?w=600&q=80'
    ];

    if (!empty($jsonImages)) {
        $arr = json_decode($jsonImages, true);
        if (is_array($arr) && count($arr) > 0) {
            $firstImg =$arr[0];
            if (strpos($firstImg, 'http') === 0) {
                return $firstImg;
            }
            $localPath = ltrim($firstImg, '/');
            if (file_exists(__DIR__ . '/' . $localPath)) {
                return $localPath;
            }
        }
    }

    if (!empty($productId) && isset($shoeImages[$productId])) {
        return $shoeImages[$productId];
    }

    return 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=600&q=80';
}

function getSellingPrice($product) {
    return (!empty($product['sale_price']) &&$product['sale_price'] > 0) ? $product['sale_price'] :$product['price'];
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function isAdmin() {
    if (!isLoggedIn()) return false;
    $roles = json_decode($_SESSION['user']['roles'] ?? '[]', true);
    if (is_array($roles)) {
        return in_array('ADMIN', $roles);
    }
    return strpos($_SESSION['user']['roles'] ?? '', 'ADMIN') !== false;
}

function generateProductId($length = 6) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';$res = '';
    for ($i = 0; $i <$length; $i++) {$res .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $res;
}

function createSlug($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/(à\vert{}á\vert{}ạ\vert{}ả\vert{}ã\vert{}â\vert{}ầ\vert{}ấ\vert{}ậ\vert{}ẩ\vert{}ẫ\vert{}ă\vert{}ằ\vert{}ắ\vert{}ặ\vert{}ẳ\vert{}ẵ)/', 'a', $str);
    $str = preg_replace('/(è\vert{}é\vert{}ẹ\vert{}ẻ\vert{}ẽ\vert{}ê\vert{}ề\vert{}ế\vert{}ệ\vert{}ể\vert{}ễ)/', 'e', $str);
    $str = preg_replace('/(ì\vert{}í\vert{}ị\vert{}ỉ\vert{}ĩ)/', 'i', $str);
    $str = preg_replace('/(ò\vert{}ó\vert{}ọ\vert{}ỏ\vert{}õ\vert{}ô\vert{}ồ\vert{}ố\vert{}ộ\vert{}ổ\vert{}ỗ\vert{}ơ\vert{}ờ\vert{}ớ\vert{}ợ\vert{}ở\vert{}ỡ)/', 'o', $str);
    $str = preg_replace('/(ù\vert{}ú\vert{}ụ\vert{}ủ\vert{}ũ\vert{}ư\vert{}ừ\vert{}ứ\vert{}ự\vert{}ử\vert{}ữ)/', 'u', $str);
    $str = preg_replace('/(ỳ\vert{}ý\vert{}ỵ\vert{}ỷ\vert{}ỹ)/', 'y', $str);
    $str = preg_replace('/(đ)/', 'd', $str);
    $str = preg_replace('/[^a-z0-9-\s]/', '',$str);
    $str = preg_replace('/([\s]+)/', '-', trim($str));
    return $str ?: 'item-' . time();
}
?>