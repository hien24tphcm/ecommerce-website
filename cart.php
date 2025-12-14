<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// ============================
// 1. KIỂM TRA ĐĂNG NHẬP
// ============================
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Bạn chưa đăng nhập"
    ]);
    exit;
}

$userId = $_SESSION['user_id'];   // map với cột `id`
$cartName = 'mac_dinh';           // hoặc $_SESSION['ten_gio_hang']
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {

    // ============================
    // 2. LẤY GIỎ HÀNG
    // ============================
    if ($action === 'get_all') {
        $stmt = $pdo->prepare("
            SELECT 
                gh.ProductID,
                sp.TenSanPham,
                sp.Gia,
                sp.HinhAnh,
                gh.SoLuong,
                (gh.SoLuong * sp.Gia) AS ThanhTien
            FROM giohang_sanpham gh
            JOIN sanpham sp ON gh.ProductID = sp.ProductID
            WHERE gh.id = ? AND gh.ten_gio_hang = ?
        ");
        $stmt->execute([$userId, $cartName]);

        echo json_encode([
            "success" => true,
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
        exit;
    }

    // ============================
    // 3. THÊM VÀO GIỎ
    // ============================
    if ($action === 'add') {
        $pid = $_POST['ProductID'] ?? 0;
        $qty = $_POST['SoLuong'] ?? 1;

        if ($pid <= 0 || $qty <= 0) {
            echo json_encode(["success"=>false,"message"=>"Dữ liệu không hợp lệ"]);
            exit;
        }

        $check = $pdo->prepare("
            SELECT SoLuong FROM gio_hang_sanpham
            WHERE id=? AND ProductID=? AND ten_gio_hang=?
        ");
        $check->execute([$userId, $pid, $cartName]);

        if ($row = $check->fetch()) {
            $pdo->prepare("
                UPDATE gio_hang_sanpham
                SET SoLuong = SoLuong + ?
                WHERE id=? AND ProductID=? AND ten_gio_hang=?
            ")->execute([$qty, $userId, $pid, $cartName]);
        } else {
            $pdo->prepare("
                INSERT INTO gio_hang_sanpham(id, ProductID, ten_gio_hang, SoLuong)
                VALUES (?,?,?,?)
            ")->execute([$userId, $pid, $cartName, $qty]);
        }

        echo json_encode(["success"=>true,"message"=>"Đã thêm vào giỏ"]);
        exit;
    }

    // ============================
    // 4. CẬP NHẬT SỐ LƯỢNG
    // ============================
    if ($action === 'update') {
        $pid = $_POST['ProductID'];
        $qty = $_POST['SoLuong'];

        if ($qty <= 0) {
            $pdo->prepare("
                DELETE FROM gio_hang_sanpham
                WHERE id=? AND ProductID=? AND ten_gio_hang=?
            ")->execute([$userId, $pid, $cartName]);
        } else {
            $pdo->prepare("
                UPDATE gio_hang_sanpham
                SET SoLuong=?
                WHERE id=? AND ProductID=? AND ten_gio_hang=?
            ")->execute([$qty, $userId, $pid, $cartName]);
        }

        echo json_encode(["success"=>true]);
        exit;
    }

    // ============================
    // 5. XÓA 1 SP
    // ============================
    if ($action === 'delete') {
        $data = json_decode(file_get_contents("php://input"), true);
        $pid = $data['ProductID'] ?? 0;

        $pdo->prepare("
            DELETE FROM gio_hang_sanpham
            WHERE id=? AND ProductID=? AND ten_gio_hang=?
        ")->execute([$userId, $pid, $cartName]);

        echo json_encode(["success"=>true]);
        exit;
    }

    echo json_encode(["success"=>false,"message"=>"Action không hợp lệ"]);

} catch (Exception $e) {
    echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
}
