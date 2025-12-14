<?php
session_start(); // 1. Khởi động session để biết đang hủy cái gì

// 2. Xóa tất cả các biến trong session
$_SESSION = array();

// 3. Nếu muốn giết sạch session, phải xóa cả cookie của session đó
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Hủy session hoàn toàn
session_destroy();

// 5. Chuyển hướng về trang chủ
header("Location: category-page_new.php");
exit;
?>