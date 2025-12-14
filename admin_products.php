<?php
include 'db_connect.php';

// Xử lý thêm sản phẩm (Create)
if (isset($_POST['add_product'])) {
    $ten = $_POST['ten'];
    $mota = $_POST['mota'];
    $gia = $_POST['gia'];
    $soluong = $_POST['soluong'];
    $maloai = $_POST['maloai'];
    $hinhanh = $_POST['hinhanh']; // URL hình, hoặc xử lý upload file nếu cần

    $stmt = $pdo->prepare("INSERT INTO sanpham (TenSanPham, MoTa, Gia, SoLuongTon, MaLoai, HinhAnh) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ten, $mota, $gia, $soluong, $maloai, $hinhanh]);
    header("Location: admin_products.php"); // Reload trang
}

// Xử lý sửa sản phẩm (Update)
if (isset($_POST['update_product'])) {
    $id = $_POST['id'];
    $ten = $_POST['ten'];
    $mota = $_POST['mota'];
    $gia = $_POST['gia'];
    $soluong = $_POST['soluong'];
    $maloai = $_POST['maloai'];
    $hinhanh = $_POST['hinhanh'];

    $stmt = $pdo->prepare("UPDATE sanpham SET TenSanPham=?, MoTa=?, Gia=?, SoLuongTon=?, MaLoai=?, HinhAnh=? WHERE ProductID=?");
    $stmt->execute([$ten, $mota, $gia, $soluong, $maloai, $hinhanh, $id]);
    header("Location: admin_products.php");
}

// Xử lý xóa sản phẩm (Delete)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM sanpham WHERE ProductID=?");
    $stmt->execute([$id]);
    header("Location: admin_products.php");
}

// Hiển thị danh sách sản phẩm (Read)
$stmt = $pdo->query("SELECT * FROM sanpham");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <title>Quản Lý Sản Phẩm</title>
    <style> table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; } </style>
</head>
<body>
    <h1>Quản Lý Sản Phẩm</h1>

    <!-- Form thêm sản phẩm -->
    <h2>Thêm Sản Phẩm Mới</h2>
    <form method="POST">
        <input type="text" name="ten" placeholder="Tên sản phẩm" required>
        <input type="text" name="mota" placeholder="Mô tả">
        <input type="number" name="gia" placeholder="Giá" required>
        <input type="number" name="soluong" placeholder="Số lượng tồn" required>
        <input type="number" name="maloai" placeholder="Mã loại" required>
        <input type="text" name="hinhanh" placeholder="URL hình ảnh">
        <button type="submit" name="add_product">Thêm</button>
    </form>

    <!-- Danh sách sản phẩm -->
    <h2>Danh Sách Sản Phẩm</h2>
    <table>
        <tr><th>ID</th><th>Tên</th><th>Giá</th><th>Số Lượng</th><th>Hành Động</th></tr>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><?php echo $product['ProductID']; ?></td>
                <td><?php echo $product['TenSanPham']; ?></td>
                <td><?php echo number_format($product['Gia'], 0, ',', '.'); ?>đ</td>
                <td><?php echo $product['SoLuongTon']; ?></td>
                <td>
                    <a href="?edit=<?php echo $product['ProductID']; ?>">Sửa</a>
                    <a href="?delete=<?php echo $product['ProductID']; ?>" onclick="return confirm('Xác nhận xóa?');">Xóa</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <!-- Form sửa sản phẩm (hiển thị nếu click sửa) -->
    <?php if (isset($_GET['edit'])): 
        $id = $_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM sanpham WHERE ProductID=?");
        $stmt->execute([$id]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>
        <h2>Sửa Sản Phẩm ID: <?php echo $id; ?></h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="text" name="ten" value="<?php echo $editProduct['TenSanPham']; ?>" required>
            <input type="text" name="mota" value="<?php echo $editProduct['MoTa']; ?>">
            <input type="number" name="gia" value="<?php echo $editProduct['Gia']; ?>" required>
            <input type="number" name="soluong" value="<?php echo $editProduct['SoLuongTon']; ?>" required>
            <input type="number" name="maloai" value="<?php echo $editProduct['MaLoai']; ?>" required>
            <input type="text" name="hinhanh" value="<?php echo $editProduct['HinhAnh']; ?>">
            <button type="submit" name="update_product">Cập Nhật</button>
        </form>
    <?php endif; ?>
</body>
</html>