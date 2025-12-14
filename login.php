<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

$username = trim($_POST["username"] ?? '');
$password = $_POST["password"] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(["success" => false, "message" => "Vui lòng nhập tên đăng nhập và mật khẩu"]);
    exit;
}

try {
    // 1. Tìm thông tin cơ bản trong bảng nguoidung
    $stmt = $pdo->prepare("SELECT * FROM nguoidung WHERE ten_dang_nhap = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Kiểm tra mật khẩu (Lưu ý: Trong thực tế nên dùng password_verify nếu mật khẩu đã mã hóa)
    if ($user && $user['mat_khau'] === $password) {
        
        // Reset session cũ
        session_unset();

        // Lưu thông tin chung
        $_SESSION["user_id"]  = $user["id"];
        $_SESSION["username"] = $user["ten_dang_nhap"];
        $_SESSION["hoten"]    = $user["ho_va_tendem"] . ' ' . $user["ten"];
        
        // 3. XÁC ĐỊNH VAI TRÒ (Role)
        // Kiểm tra xem ID này có trong bảng nguoiban không
        $stmtSeller = $pdo->prepare("SELECT * FROM nguoiban WHERE id = ?");
        $stmtSeller->execute([$user['id']]);
        $isSeller = $stmtSeller->fetch(PDO::FETCH_ASSOC);

        // Kiểm tra xem ID này có trong bảng nguoimua không
        $stmtBuyer = $pdo->prepare("SELECT * FROM nguoimua WHERE id = ?");
        $stmtBuyer->execute([$user['id']]);
        $isBuyer = $stmtBuyer->fetch(PDO::FETCH_ASSOC);

        // Kiểm tra xem ID này có trong bảng admin không
        $stmtAdmin = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
        $stmtAdmin->execute([$user['id']]);
        $isAdmin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        if ($isSeller) {
            $_SESSION['role'] = 'seller';
            $_SESSION['seller_id'] = $user['id']; // Dùng cho các query của người bán
        } elseif ($isBuyer) {
            $_SESSION['role'] = 'buyer';
            $_SESSION['buyer_id'] = $user['id']; // Dùng cho giỏ hàng, đặt hàng
        } elseif ($isAdmin) {
            $_SESSION['role'] = 'admin';
        } else {
            // Trường hợp tài khoản tạo ở bảng nguoidung nhưng chưa phân quyền
            $_SESSION['role'] = 'unknown';
        }

        echo json_encode(["success" => true, "role" => $_SESSION['role']]);
    } else {
        echo json_encode(["success" => false, "message" => "Sai tên đăng nhập hoặc mật khẩu!"]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Lỗi hệ thống: " . $e->getMessage()]);
}
?>