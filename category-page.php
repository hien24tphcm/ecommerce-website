<?php
session_start();
require_once 'db_connect.php'; // Sử dụng PDO từ file này
header('Content-Type: application/json');

// =================================================================
// 1. KIỂM TRA QUYỀN TRUY CẬP (BẢO MẬT)
// =================================================================
// Kiểm tra đăng nhập và vai trò phải là 'seller'
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Truy cập bị từ chối! Bạn không phải là Người bán."
    ]);
    exit;
}

// Lấy ID người bán từ Session (đã được set trong login.php)
$sellerId = $_SESSION['seller_id']; 
$action = $_GET['action'] ?? '';

try {
    // =================================================================
    // 2. LẤY DANH SÁCH DANH MỤC (Cho Dropdown)
    // =================================================================
    if ($action === 'get_categories') {
        $stmt = $pdo->prepare("SELECT * FROM loaisanpham ORDER BY TenLoai ASC");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $data]);
        exit;
    }

    // =================================================================
    // 3. LẤY DANH SÁCH SẢN PHẨM (Chỉ của Seller đang đăng nhập)
    // =================================================================
    if ($action === 'get_all') {
        // Kết hợp bảng sanpham, loaisanpham và danglen để lọc đúng sản phẩm của ông bán này
        $sql = "
            SELECT sp.*, l.TenLoai
            FROM sanpham sp
            LEFT JOIN loaisanpham l ON sp.MaLoai = l.MaLoai
            JOIN danglen d ON sp.ProductID = d.ProductID
            WHERE d.SellerID = :sellerId
            ORDER BY sp.ProductID DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['sellerId' => $sellerId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $data]);
        exit;
    }

    // =================================================================
    // 4. THÊM SẢN PHẨM MỚI
    // =================================================================
    if ($action === 'add') {
        // Lấy dữ liệu từ Form
        $name  = trim($_POST['TenSanPham'] ?? '');
        $des   = trim($_POST['MoTa'] ?? '');
        $price = $_POST['Gia'] ?? 0;
        $stock = $_POST['SoLuongTon'] ?? 0;
        $type  = $_POST['MaLoai'] ?? null;
        $img   = trim($_POST['HinhAnh'] ?? '');

        // Validate cơ bản
        if (!$name || !$price || !$type) {
            echo json_encode(["success" => false, "message" => "Thiếu thông tin bắt buộc!"]);
            exit;
        }

        $pdo->beginTransaction(); // Bắt đầu giao dịch để đảm bảo toàn vẹn dữ liệu

        try {
            // Thêm vào bảng sanpham
            $stmt = $pdo->prepare("
                INSERT INTO sanpham (TenSanPham, HinhAnh, MoTa, Gia, SoLuongTon, MaLoai)
                VALUES (:name, :img, :des, :price, :stock, :type)
            ");
            $stmt->execute([
                ':name'  => $name,
                ':img'   => $img,
                ':des'   => $des,
                ':price' => $price,
                ':stock' => $stock,
                ':type'  => $type
            ]);
            
            $newProductId = $pdo->lastInsertId();

            // Thêm vào bảng danglen (Gắn sản phẩm này với Seller hiện tại)
            $stmt2 = $pdo->prepare("
                INSERT INTO danglen (ProductID, SellerID, NgayDang)
                VALUES (:pid, :sid, NOW())
            ");
            $stmt2->execute([
                ':pid' => $newProductId,
                ':sid' => $sellerId
            ]);

            $pdo->commit(); // Lưu thay đổi
            echo json_encode(["success" => true, "message" => "Thêm sản phẩm thành công!"]);

        } catch (Exception $e) {
            $pdo->rollBack(); // Hoàn tác nếu lỗi
            throw $e;
        }
        exit;
    }

    // =================================================================
    // 5. CẬP NHẬT SẢN PHẨM
    // =================================================================
    if ($action === 'update') {
        $id = $_POST['ProductID'];

        // Kiểm tra quyền sở hữu trước khi sửa
        $check = $pdo->prepare("SELECT 1 FROM danglen WHERE ProductID = ? AND SellerID = ?");
        $check->execute([$id, $sellerId]);
        
        if (!$check->fetch()) {
            echo json_encode(["success" => false, "message" => "Bạn không có quyền sửa sản phẩm này!"]);
            exit;
        }

        $name  = trim($_POST['TenSanPham']);
        $des   = trim($_POST['MoTa']);
        $price = $_POST['Gia'];
        $stock = $_POST['SoLuongTon'];
        $type  = $_POST['MaLoai'];
        $img   = trim($_POST['HinhAnh']);

        $stmt = $pdo->prepare("
            UPDATE sanpham 
            SET TenSanPham = ?, HinhAnh = ?, MoTa = ?, Gia = ?, SoLuongTon = ?, MaLoai = ?
            WHERE ProductID = ?
        ");
        $stmt->execute([$name, $img, $des, $price, $stock, $type, $id]);

        echo json_encode(["success" => true, "message" => "Cập nhật thành công!"]);
        exit;
    }

    // =================================================================
    // 6. XÓA SẢN PHẨM
    // =================================================================
    if ($action === 'delete') {
        $input = json_decode(file_get_contents("php://input"), true);
        $id = $input["ProductID"] ?? 0;

        // Kiểm tra quyền sở hữu
        $check = $pdo->prepare("SELECT 1 FROM danglen WHERE ProductID = ? AND SellerID = ?");
        $check->execute([$id, $sellerId]);

        if (!$check->fetch()) {
            echo json_encode(["success" => false, "message" => "Bạn không có quyền xóa sản phẩm này!"]);
            exit;
        }

        // Xóa (Có thể cần xóa ràng buộc khóa ngoại trước nếu DB không set ON DELETE CASCADE)
        // Ở đây giả định xóa bảng con 'danglen' trước rồi xóa 'sanpham'
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM danglen WHERE ProductID = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM sanpham WHERE ProductID = ?")->execute([$id]);
            $pdo->commit();
            echo json_encode(["success" => true, "message" => "Xóa thành công!"]);
        } catch (Exception $ex) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "message" => "Lỗi khi xóa: " . $ex->getMessage()]);
        }
        exit;
    }

    echo json_encode(["success" => false, "message" => "Action không hợp lệ"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi Server: " . $e->getMessage()]);
}
?>