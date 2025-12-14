<?php
session_start();
require_once 'db_connect.php'; 

// --- 1. LẤY DỮ LIỆU TỪ DB ---
$productList = [];
try {
    // JOIN order_details để tính tổng số lượng đã bán (DaBan)
    // Dùng RAND() để giả lập điểm đánh giá (Rating) từ 3.0 đến 5.0 phục vụ test bộ lọc
    $sql = "SELECT 
                sp.*, 
                l.TenLoai,
                nd.ten AS TenSeller,
                nd.ho_va_tendem AS HoDemSeller,
                COALESCE(SUM(od.Quantity), 0) AS DaBan,
                ROUND(3 + (RAND() * 2), 1) AS RatingAvg, 
                FLOOR(1 + (RAND() * 100)) AS ReviewCount
            FROM sanpham sp 
            LEFT JOIN loaisanpham l ON sp.MaLoai = l.MaLoai 
            LEFT JOIN danglen dl ON sp.ProductID = dl.ProductID
            LEFT JOIN nguoidung nd ON dl.SellerID = nd.id
            LEFT JOIN order_details od ON sp.ProductID = od.ProductID
            GROUP BY sp.ProductID
            ORDER BY sp.ProductID DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $productList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $productList = [];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mua sắm | HCMUT.MALL</title>
  <link href="https://fonts.googleapis.com/css2?family=Afacad:wght@400;500;600;700&family=Alumni+Sans:wght@900&display=swap" rel="stylesheet">
  <style>
    /* --- CSS CƠ BẢN --- */
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Afacad',sans-serif;background:#fff;color:#000;}
    a{text-decoration:none;color:inherit;}
        .hidden {
        display: none !important;
    }


    /* Top Bar & Header */
    .top-bar{height:48px;background:#000;color:#fff;font-size:14px;display:flex;align-items:center;justify-content:space-between;padding:0 100px;}
    .top-bar .left,.top-bar .right{display:flex;gap:32px;align-items:center;}
    header{padding:20px 100px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #eee;}
    .logo{font-family:'Alumni Sans';font-size:48px;font-weight:900;color:#CF0000;}
    .search-bar{flex:1;max-width:600px;margin:0 40px;background:#F0F0F0;border-radius:60px;padding:12px 20px;display:flex;align-items:center;gap:12px;}
    .search-bar input{border:none;background:none;outline:none;width:100%;font-size:16px;}
    .icons{display:flex;gap:16px;align-items:center;}
    nav{padding:16px 100px;display:flex;gap:32px;font-size:14px;border-bottom:1px solid #eee;}

    /* Layout chính */
    main{padding:20px 100px 120px;display:flex;gap:30px;position:relative;}
    aside{width:280px;flex-shrink:0;height:fit-content;}

    /* --- CSS BỘ LỌC (FILTER) --- */
    .filter-header{font-size:20px;font-weight:700;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;}
    .clear-all{color:#CF0000;font-size:14px;cursor:pointer;}
    .filter-section{background:#fff;border:1px solid #eee;border-radius:12px;padding:20px;margin-bottom:16px;}
    .filter-title{font-weight:600;font-size:16px;margin-bottom:15px;}
    
    /* Range Slider */
    .range-slider{position:relative;height:5px;margin:25px 10px;}
    .range-track{position:absolute;top:0;left:0;right:0;height:5px;background:#eee;border-radius:5px;}
    .range-fill{position:absolute;top:0;height:5px;background:#CF0000;border-radius:5px;left:0;right:0;}
    .range-slider input{position:absolute;top:-8px;width:100%;background:none;pointer-events:none;-webkit-appearance:none;}
    .range-slider input::-webkit-slider-thumb{height:20px;width:20px;border-radius:50%;background:#CF0000;pointer-events:auto;-webkit-appearance:none;cursor:pointer;border:2px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,0.2);}
    .price-inputs{display:flex;justify-content:space-between;align-items:center;margin-top:15px;gap:10px;}
    .price-inputs input{width:90px;padding:8px;border:1px solid #ddd;border-radius:6px;text-align:center;font-size:13px;}
    .apply-price-btn{width:100%;margin-top:15px;padding:10px;background:#CF0000;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;}

    /* Đánh giá (Rating Filter) */
    .rating-opt {display:flex;align-items:center;gap:10px;padding:8px 10px;cursor:pointer;border-radius:6px;transition:0.2s;}
    .rating-opt:hover {background:#f5f5f5;}
    .rating-opt.selected {background:#fff0f0; border:1px solid #ffcccc;}
    .stars {color:#FFD700; font-size:18px; letter-spacing: 2px;}
    
    /* Màu sắc & Size (Demo UI) */
    .colors{display:flex;gap:10px;flex-wrap:wrap;}
    .color-opt{width:30px;height:30px;border-radius:50%;cursor:pointer;border:2px solid transparent;}
    .color-opt.selected{border-color:#000;transform:scale(1.15);}
    .sizes{display:flex;gap:8px;flex-wrap:wrap;}
    .size-opt{padding:6px 10px;border:1px solid #ddd;border-radius:6px;cursor:pointer;font-size:13px;}
    .size-opt.selected{background:#000;color:#fff;border-color:#000;}
 
    #miniCart::-webkit-scrollbar {
        width: 4px;
    }
    #miniCart::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 10px;
    }



    /* --- CSS SẢN PHẨM --- */
    section { flex: 1; }
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
    .product-card {
        background: #fff; border: 1px solid #eee; border-radius: 12px;
        overflow: hidden; transition: all .3s; display: flex; flex-direction: column; cursor: pointer;
    }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: #CF0000; }
    .product-img { position: relative; padding-top: 100%; background: #f9f9f9; overflow: hidden; }
    .product-img img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
    .product-card:hover .product-img img { transform: scale(1.08); }
    .add-to-cart {
        position: absolute; top: 10px; right: 10px; background: #CF0000; color: #fff;
        width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 20px; opacity: 0; transition: all .3s; z-index: 10;
    }
    .product-card:hover .add-to-cart { opacity: 1; }
    
    .product-info { padding: 12px; flex: 1; display: flex; flex-direction: column; }
    .product-name { font-weight: 500; margin-bottom: 5px; height: 40px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size:15px; }
    .product-price { color: #CF0000; font-weight: 700; font-size: 17px; margin-bottom: 5px; display:flex; justify-content:space-between; align-items:center;}
    
    /* Stats: Đã bán & Rating */
    .product-meta { display: flex; justify-content:space-between; font-size: 12px; color: #777; margin-bottom: 8px; align-items:center; }
    .meta-rating { color: #FFD700; font-weight: 600; display:flex; align-items:center; gap:3px;}
    .meta-sold { color: #555; }
    
    .seller-row { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #666; margin-top: auto; padding-top: 8px; border-top: 1px dashed #eee; }

    /* Modal & Cart */
    .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;}
    .modal.active{display:flex;}
    .modal-content{background:#fff;padding:30px;border-radius:12px;width:90%;max-width:400px;}
    .form-group input{width:100%;padding:12px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;}
    .btn-full{width:100%;padding:12px;background:#CF0000;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;}
    .toast{position:fixed;bottom:100px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:12px 30px;border-radius:50px;opacity:0;transition:0.4s;z-index:3000;}
    .toast.show{opacity:1;bottom:120px;}
    .cart-float{position:fixed;bottom:30px;right:30px;background:#CF0000;color:#fff;width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;cursor:pointer;box-shadow:0 10px 30px rgba(207,0,0,0.4);z-index:1000;}
    .cart-count{position:absolute;top:-5px;right:-5px;background:#000;width:24px;height:24px;border-radius:50%;font-size:12px;display:flex;align-items:center;justify-content:center;}
  </style>
</head>
<body>

  <div class="top-bar">
    <div class="left"><span>Trang người bán</span><span>Tải ứng dụng</span></div>
    <div class="right">
      <span>Hỗ trợ</span>
      <?php if (isset($_SESSION['user_id'])): ?>
        <span>Xin chào, <strong><?php echo htmlspecialchars($_SESSION['hoten']); ?></strong></span>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'seller'): ?>
          <a href="products_management.html" style="color:#FFD700;font-weight:700;">Quản lý SP</a>
        <?php endif; ?>
        <a href="logout.php" style="color:#fff; text-decoration:underline;">Đăng xuất</a>
      <?php else: ?>
        <span onclick="openLoginModal()" style="cursor:pointer;">Đăng nhập</span>
      <?php endif; ?>
    </div>
  </div>

  <header>
    <div class="logo">HCMUT.MALL</div>
    <div class="search-bar">
      <input type="text" id="searchInput" placeholder="Tìm kiếm sản phẩm..." onkeyup="filterProducts()">
      <button style="background:none;border:none;cursor:pointer;">🔍</button>
    </div>
    <div class="icons">
        <img src="https://img.icons8.com/ios/50/user.png" width="28">
    </div>
  </header>

  <nav><a href="#">Điện thoại</a><a href="#">Laptop</a><a href="#">Quần áo</a><a href="#">Túi</a></nav>

  <main>
    <aside>
      <div class="filter-header">Bộ lọc <span class="clear-all" onclick="resetFilters()">Xóa tất cả</span></div>
      
      <div class="filter-section">
        <div class="filter-title">Khoảng giá</div>
        <div class="range-slider">
            <div class="range-track"></div>
            <div class="range-fill" id="rangeFill"></div>
            <input type="range" min="0" max="50000000" value="0" id="rangeMin" oninput="sliderUpdate()">
            <input type="range" min="0" max="50000000" value="50000000" id="rangeMax" oninput="sliderUpdate()">
        </div>
        <div class="price-inputs">
            <input type="number" id="inputMin" value="0" onchange="inputUpdate()">
            <span>-</span>
            <input type="number" id="inputMax" value="50000000" onchange="inputUpdate()">
        </div>
        <button class="apply-price-btn" onclick="filterProducts()">Áp dụng</button>
      </div>

      <div class="filter-section">
        <div class="filter-title">Đánh giá</div>
        <div class="rating-filter">
            <div class="rating-opt" onclick="toggleRating(5, this)">
                <span class="stars">★★★★★</span> <span style="font-size:14px">từ 5 sao</span>
            </div>
            <div class="rating-opt" onclick="toggleRating(4, this)">
                <span class="stars">★★★★☆</span> <span style="font-size:14px">từ 4 sao</span>
            </div>
            <div class="rating-opt" onclick="toggleRating(3, this)">
                <span class="stars">★★★☆☆</span> <span style="font-size:14px">từ 3 sao</span>
            </div>
        </div>
      </div>
      </div>
    </aside>

    <section>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h2 style="font-size:24px;font-weight:700;">Danh Sách Sản Phẩm</h2>
        <div id="productCount" style="color:#666;">Đang tải...</div>
      </div>
      <div class="product-grid" id="productGrid"></div>
    </section>
  </main>

  <div id="loginModal" class="modal">
    <div class="modal-content">
      <h3 style="margin-bottom:20px;">Đăng Nhập</h3>
      <div id="loginError" style="color:red;display:none;margin-bottom:10px;"></div>
      <form id="loginForm">
        <div class="form-group"><input type="text" name="username" placeholder="Username" required></div>
        <div class="form-group"><input type="password" name="password" placeholder="Password" required></div>
        <button type="submit" class="btn-full">Đăng nhập</button>
        <button type="button" onclick="closeLoginModal()" style="margin-top:10px;background:none;color:#333;border:none;width:100%;cursor:pointer;">Đóng</button>
      </form>
    </div>
  </div>
  <div class="relative">
    <button type="button" onclick="toggleMiniCart()" class="relative">
        🛒
        <div class="cart-count" id="miniCartCount">0</div>
    </button>

    <!-- MINI CART -->
    <div id="miniCart"
         class="hidden absolute right-0 mt-2 w-72 bg-white shadow-lg rounded-lg z-50">
        <div class="p-3 border-b font-semibold">Đơn hàng</div>
        <div id="miniCartItems" class="max-h-60 overflow-y-auto"></div>
        <div class="p-3 border-t text-right">
            <button onclick="openCart()" class="bg-blue-600 text-white px-3 py-1 rounded">
                Xem giỏ
            </button>
        </div>
    </div>
</div>



  <?php if (!isset($_SESSION['role']) || $_SESSION['role'] === 'buyer'): ?>
      <div class="cart-float" onclick="openCart()"><div class="cart-count" id="cartCount">0</div>🛒</div>
  <?php endif; ?>
  <div class="toast" id="toast"></div>

  <script>
    let allProducts = <?php echo json_encode($productList ?: []); ?>;
    let selectedRating = 0; // Biến lưu đánh giá đang chọn

    // ================= SLIDER GIÁ =================
    const rangeMin = document.getElementById('rangeMin');
    const rangeMax = document.getElementById('rangeMax');
    const inputMin = document.getElementById('inputMin');
    const inputMax = document.getElementById('inputMax');
    const rangeFill = document.getElementById('rangeFill');
    const minGap = 100000;

    function sliderUpdate() {
        if (parseInt(rangeMax.value) - parseInt(rangeMin.value) <= minGap) {
            rangeMin.value = parseInt(rangeMax.value) - minGap;
        }
        updateUI();
    }
    function inputUpdate() {
        let min = parseInt(inputMin.value); let max = parseInt(inputMax.value);
        if(min < 0) min = 0; if(max > 50000000) max = 50000000;
        rangeMin.value = min; rangeMax.value = max;
        updateUI();
    }
    function updateUI() {
        const p1 = (rangeMin.value / rangeMax.max) * 100;
        const p2 = (rangeMax.value / rangeMax.max) * 100;
        rangeFill.style.left = p1 + "%"; rangeFill.style.width = (p2 - p1) + "%";
        inputMin.value = rangeMin.value; inputMax.value = rangeMax.value;
    }
    sliderUpdate();

    // ================= BỘ LỌC ĐÁNH GIÁ =================
    function toggleRating(stars, el) {
        // Nếu click lại cái đang chọn thì bỏ chọn
        if (selectedRating === stars) {
            selectedRating = 0;
            el.classList.remove('selected');
        } else {
            selectedRating = stars;
            // Xóa selected cũ
            document.querySelectorAll('.rating-opt').forEach(e => e.classList.remove('selected'));
            // Thêm selected mới
            el.classList.add('selected');
        }
        filterProducts();
    }

    // ================= LOGIC LỌC TỔNG HỢP =================
    function filterProducts() {
        const keyword = document.getElementById('searchInput').value.toLowerCase();
        const minPrice = parseInt(rangeMin.value);
        const maxPrice = parseInt(rangeMax.value);

        const filtered = allProducts.filter(p => {
            const price = parseInt(p.Gia);
            const name = p.TenSanPham.toLowerCase();
            const rating = parseFloat(p.RatingAvg); // Lấy điểm đánh giá

            const priceMatch = (price >= minPrice && price <= maxPrice);
            const nameMatch = name.includes(keyword);
            const ratingMatch = rating >= selectedRating; // Logic: Điểm phải lớn hơn hoặc bằng mức chọn

            return priceMatch && nameMatch && ratingMatch;
        });

        renderProducts(filtered);
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        rangeMin.value = 0; rangeMax.value = 50000000;
        selectedRating = 0;
        sliderUpdate();
        document.querySelectorAll('.selected').forEach(el => el.classList.remove('selected'));
        filterProducts();
    }

    function toggleUI(el) { el.classList.toggle('selected'); }

    // ================= RENDER SẢN PHẨM =================
    function renderProducts(products) {
       const grid = document.getElementById('productGrid');
       const countLabel = document.getElementById('productCount');

       if (!products || products.length === 0) {
           grid.innerHTML = `<p style="grid-column:1/-1;text-align:center;padding:50px;color:#999;">Không tìm thấy sản phẩm phù hợp.</p>`;
           countLabel.textContent = '0 sản phẩm';
           return;
       }

       let html = '';
       products.forEach(p => {
           const price = Number(p.Gia).toLocaleString('vi-VN');
           const imgUrl = p.HinhAnh && p.HinhAnh.trim() ? p.HinhAnh : 'https://via.placeholder.com/300?text=No+Image';
           const sellerName = p.TenSeller ? (p.HoDemSeller + ' ' + p.TenSeller) : 'Shop HCMUT';
           
           // Xử lý dữ liệu
           const daBan = p.DaBan || 0;
           const rating = p.RatingAvg || 5.0;

           html += `
             <div class="product-card" onclick="alert('Chi tiết: ${p.TenSanPham}')">
               <div class="product-img">
                 <img src="${imgUrl}" onerror="this.src='https://via.placeholder.com/300?text=Error'">
                 <div class="add-to-cart" onclick="event.stopPropagation(); addToCart(${p.ProductID}, '${p.TenSanPham.replace(/'/g, "\\'")}', ${p.Gia}, '${imgUrl}')">+</div>
               </div>
               <div class="product-info">
                 <div class="product-name" title="${p.TenSanPham}">${p.TenSanPham}</div>
                 
                 <div style="font-size: 13px; color: #555; margin-bottom: 5px;">
                    Số lượng: <strong>${p.SoLuongTon}</strong>
                 </div>

                 <div class="product-price">
                   <span>${price}đ</span>
                 </div>
                 
                 <div class="product-meta">
                    <span class="meta-rating">★ ${rating}</span>
                    <span class="meta-sold">Đã bán ${daBan}</span>
                 </div>

                 <div class="seller-row">
                    <span>🏬 ${sellerName}</span>
                 </div>
               </div>
             </div>
           `;
       });

       grid.innerHTML = html;
       countLabel.textContent = `Tìm thấy ${products.length} sản phẩm`;
    }
    // ================= CART & LOGIN (GIỮ NGUYÊN) =================
    let cart = JSON.parse(localStorage.getItem('hcmut_cart') || '[]');
    updateCartCount();
    function updateCartCount() {
        const el = document.getElementById('cartCount');
        if(el) el.textContent = cart.reduce((s,i) => s + i.qty, 0);
    }
    function addToCart(id, name, price, img) {
        const role = "<?php echo $_SESSION['role'] ?? ''; ?>";
        if (role === 'seller') { alert('Người bán không thể mua hàng!'); return; }
        const item = cart.find(i => i.id === id);
        if(item) item.qty++; else cart.push({id, name, price, img, qty:1});
        localStorage.setItem('hcmut_cart', JSON.stringify(cart));
        updateCartCount();
        renderMiniCart()
        const t = document.getElementById('toast');
        t.textContent = "Đã thêm vào giỏ!"; t.classList.add('show');
        setTimeout(()=>t.classList.remove('show'), 2000);
    }
    function openCart() {
        toggleMiniCart();
        // if(cart.length === 0) return alert("Giỏ hàng trống!");
        // let msg = cart.map(i => `- ${i.name}: ${i.qty} x ${i.price.toLocaleString()}đ`).join('\n');
        // alert("GIỎ HÀNG CỦA BẠN:\n" + msg);
    }
    function openLoginModal() { document.getElementById('loginModal').classList.add('active'); }
    function closeLoginModal() { document.getElementById('loginModal').classList.remove('active'); }

    function toggleMiniCart() {
        const box = document.getElementById('miniCart');
        box.classList.toggle('hidden');
        renderMiniCart();
    }

    function renderMiniCart() {
        const wrap = document.getElementById('miniCartItems');
        if (!wrap) return;

        if (cart.length === 0) {
            wrap.innerHTML = `<div class="p-3 text-sm text-gray-500">Giỏ hàng trống</div>`;
            return;
        }

        wrap.innerHTML = cart.map(i => `
            <div class="flex gap-2 p-2 border-b text-sm">
                <img src="${i.img}" class="w-10 h-10 object-cover rounded">
                <div class="flex-1">
                    <div class="font-medium">${i.name}</div>
                    <div class="text-gray-500">${i.qty} × ${i.price.toLocaleString()}đ</div>
                </div>
            </div>
        `).join('');
    }


    document.getElementById('loginForm').onsubmit = function(e) {
        e.preventDefault();
        fetch('login.php', {method:'POST', body:new FormData(this)})
        .then(r=>r.json()).then(res => {
            if(res.success) location.reload();
            else { 
                const err = document.getElementById('loginError');
                err.textContent = res.message; err.style.display='block';
            }
        });
    }

    // Init
    renderProducts(allProducts);
  </script>
</body>
</html>