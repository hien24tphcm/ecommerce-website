<?php
session_start();
require_once 'db_connect.php'; 
header('Content-Type: application/json');

// 1. KIỂM TRA QUYỀN
if (!isset($_SESSION['seller_id']) || $_SESSION['role'] !== 'seller') {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Vui lòng đăng nhập tài khoản người bán!"]);
    exit;
}

$sellerId = $_SESSION['seller_id'];
$action = $_GET['action'] ?? '';

try {
    // --- LẤY DANH SÁCH SẢN PHẨM ---
    if ($action === 'get_all') {
        $sql = "SELECT sp.*, l.TenLoai
                FROM sanpham sp
                JOIN danglen dl ON sp.ProductID = dl.ProductID
                LEFT JOIN loaisanpham l ON sp.MaLoai = l.MaLoai
                WHERE dl.SellerID = ?
                ORDER BY sp.ProductID DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sellerId]);
        echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
        exit;
    }

    // --- LẤY DANH MỤC (LOẠI SP) ---
    if ($action === 'get_categories') {
        $stmt = $pdo->query("SELECT MaLoai, TenLoai FROM loaisanpham ORDER BY TenLoai ASC");
        echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
        exit;
    }

    // --- THÊM SẢN PHẨM ---
    if ($action === 'add') {
        // Kiểm tra dữ liệu đầu vào
        if (empty($_POST['TenSanPham']) || empty($_POST['Gia']) || empty($_POST['MaLoai'])) {
            echo json_encode(["success" => false, "message" => "Vui lòng nhập đủ thông tin!"]);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Thêm SP
            $stmt = $pdo->prepare("INSERT INTO sanpham (TenSanPham, HinhAnh, MoTa, Gia, SoLuongTon, MaLoai) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['TenSanPham'], 
                $_POST['HinhAnh'] ?? '', 
                $_POST['MoTa'] ?? '', 
                $_POST['Gia'], 
                $_POST['SoLuongTon'] ?? 0, 
                $_POST['MaLoai']
            ]);
            $newId = $pdo->lastInsertId();

            // Gắn quyền sở hữu
            $stmt2 = $pdo->prepare("INSERT INTO danglen (ProductID, SellerID, NgayDang) VALUES (?, ?, NOW())");
            $stmt2->execute([$newId, $sellerId]);

            $pdo->commit();
            echo json_encode(["success" => true, "message" => "Thêm thành công!"]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

    // --- SỬA SẢN PHẨM ---
    if ($action === 'update') {
        $id = $_POST['ProductID'];
        
        // Kiểm tra quyền
        $check = $pdo->prepare("SELECT 1 FROM danglen WHERE ProductID = ? AND SellerID = ?");
        $check->execute([$id, $sellerId]);
        if (!$check->fetch()) {
            echo json_encode(["success" => false, "message" => "Không có quyền sửa!"]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE sanpham SET TenSanPham=?, Gia=?, SoLuongTon=?, MaLoai=?, HinhAnh=?, MoTa=? WHERE ProductID=?");
        $stmt->execute([
            $_POST['TenSanPham'], 
            $_POST['Gia'], 
            $_POST['SoLuongTon'], 
            $_POST['MaLoai'], 
            $_POST['HinhAnh'], 
            $_POST['MoTa'], 
            $id
        ]);

        echo json_encode(["success" => true, "message" => "Cập nhật thành công!"]);
        exit;
    }

    // --- XÓA SẢN PHẨM ---
    if ($action === 'delete') {
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input['ProductID'];

        // Kiểm tra quyền
        $check = $pdo->prepare("SELECT 1 FROM danglen WHERE ProductID = ? AND SellerID = ?");
        $check->execute([$id, $sellerId]);
        if (!$check->fetch()) {
            echo json_encode(["success" => false, "message" => "Không có quyền xóa!"]);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM danglen WHERE ProductID = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM sanpham WHERE ProductID = ?")->execute([$id]); // Có thể cần xóa order_details trước nếu có ràng buộc
            $pdo->commit();
            echo json_encode(["success" => true, "message" => "Đã xóa!"]);
        } catch (Exception $e) {
            $pdo->rollBack();
            // Nếu lỗi do ràng buộc khóa ngoại (ví dụ đã có người mua)
            if ($e->getCode() == '23000') {
                 echo json_encode(["success" => false, "message" => "Không thể xóa sản phẩm đã có đơn hàng!"]);
            } else {
                 throw $e;
            }
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi Server: " . $e->getMessage()]);
}
?>