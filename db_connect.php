<?php
// Thông tin cấu hình Database
$host = 'localhost';      // Tên host (thường là localhost)
$dbname = 'test';     // <--- QUAN TRỌNG: Kiểm tra lại tên DB trong phpMyAdmin của bạn (có thể là 'test' hoặc 'database')
$username = 'root';       // Mặc định XAMPP là root
$password = '';           // Mặc định XAMPP là rỗng

try {
    // Tạo kết nối PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Thiết lập chế độ báo lỗi (quan trọng để debug)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Thiết lập chế độ lấy dữ liệu mặc định là mảng kết hợp
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Nếu kết nối thất bại, dừng chương trình và báo lỗi
    die(json_encode([
        "success" => false, 
        "message" => "Lỗi kết nối Database: " . $e->getMessage()
    ]));
}
?>