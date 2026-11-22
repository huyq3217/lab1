<?php
require_once 'header.php';

if (!isAdmin()) {
    echo "<div class='alert alert-danger'>Bạn cần đăng nhập bằng tài khoản Admin (<code>admin@gmail.com</code> / mật khẩu <code>123456</code>) để truy cập Quản trị!</div>";
    require_once 'footer.php';
    exit;
}

$tab = $_GET['tab'] ?? 'products';
$alert = '';

// ==================== XỬ LÝ CRUD CHO TẤT CẢ CÁC MODULE ====================
try {
    // --- 1. MODULE SẢN PHẨM (THÊM, SỬA, XÓA) ---
    if (isset($_POST['save_product'])) {
        $isEdit = !empty($_POST['edit_id']);
        $pid = $isEdit ? $_POST['edit_id'] : generateProductId(6);
        $name = trim($_POST['name']);
        $price = (int)$_POST['price'];
        $sale_price = (int)$_POST['sale_price'];
        $brand_id = (int)$_POST['brand_id'];
        $status = (int)$_POST['status'];
        $desc = trim($_POST['description']);
        $img_url = trim($_POST['image_url']);
        $imgJson = json_encode([$img_url ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&q=80']);
        $cat_ids = $_POST['categories'] ?? [];

        if ($isEdit) {
            $pdo->prepare("UPDATE product SET name=?, slug=?, price=?, sale_price=?, brand_id=?, status=?, description=?, images=?, modified_at=NOW() WHERE id=?")
                ->execute([$name, createSlug($name), $price, $sale_price, $brand_id, $status, $desc, $imgJson, $pid]);
            $pdo->prepare("DELETE FROM product_category WHERE product_id=?")->execute([$pid]);
            $alert = "Đã cập nhật sản phẩm #$pid thành công!";
        } else {
            $pdo->prepare("INSERT INTO product (id, name, slug, price, sale_price, brand_id, description, images, status, total_sold, product_view, created_at, modified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW(), NOW())")
                ->execute([$pid, $name, createSlug($name), $price, $sale_price, $brand_id, $desc, $imgJson, $status]);
            // Khởi tạo tồn kho mặc định cho sản phẩm mới
            foreach ([38, 39, 40, 41, 42] as $sz) {
                $pdo->prepare("INSERT IGNORE INTO product_size (product_id, size, quantity) VALUES (?, ?, 10)")->execute([$pid, $sz]);
            }
            $alert = "Đã thêm sản phẩm mới #$pid thành công!";
        }
        // Gắn danh mục
        foreach ($cat_ids as $cid) {
            $pdo->prepare("INSERT INTO product_category (product_id, category_id) VALUES (?, ?)")->execute([$pid, (int)$cid]);
        }
    }

    if (isset($_GET['del_product'])) {
        $pid = $_GET['del_product'];
        // Xóa dữ liệu bảng con trước để không bao giờ bị lỗi khóa ngoại Foreign Key
        $pdo->prepare("DELETE FROM product_category WHERE product_id = ?")->execute([$pid]);
        $pdo->prepare("DELETE FROM product_size WHERE product_id = ?")->execute([$pid]);
        $pdo->prepare("DELETE FROM comment WHERE product_id = ?")->execute([$pid]);
        $pdo->prepare("DELETE FROM cart_item WHERE product_id = ?")->execute([$pid]);
        $pdo->prepare("DELETE FROM order_items WHERE product_id = ?")->execute([$pid]);
        $pdo->prepare("UPDATE orders SET product_id = NULL WHERE product_id = ?")->execute([$pid]);
        $pdo->prepare("DELETE FROM product WHERE id = ?")->execute([$pid]);
        header("Location: admin.php?tab=products&msg=deleted"); exit;
    }

    // --- 2. MODULE QUẢN LÝ TỒN KHO SIZE (`product_size`) ---
    if (isset($_POST['save_inventory'])) {
        $pid = $_POST['product_id'];
        $size = (int)$_POST['size'];
        $qty = max(0, (int)$_POST['quantity']);
        $stmtChk = $pdo->prepare("SELECT * FROM product_size WHERE product_id = ? AND size = ?");
        $stmtChk->execute([$pid, $size]);
        if ($stmtChk->fetch()) {
            $pdo->prepare("UPDATE product_size SET quantity = ? WHERE product_id = ? AND size = ?")->execute([$qty, $pid, $size]);
        } else {
            $pdo->prepare("INSERT INTO product_size (product_id, size, quantity) VALUES (?, ?, ?)")->execute([$pid, $size, $qty]);
        }
        $alert = "Đã cập nhật tồn kho Size $size cho mã SP $pid!";
    }
    if (isset($_GET['del_size']) && isset($_GET['pid'])) {
        $pdo->prepare("DELETE FROM product_size WHERE product_id = ? AND size = ?")->execute([$_GET['pid'], (int)$_GET['del_size']]);
        header("Location: admin.php?tab=inventory"); exit;
    }

    // --- 3. MODULE NHÃN HÀNG (`brand`: THÊM, SỬA, XÓA) ---
    if (isset($_POST['save_brand'])) {
        $bid = (int)($_POST['brand_id'] ?? 0);
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $status = (int)$_POST['status'];
        if ($bid > 0) {
            $pdo->prepare("UPDATE brand SET name=?, description=?, status=?, modified_at=NOW() WHERE id=?")
                ->execute([$name, $desc, $status, $bid]);
            $alert = "Đã sửa nhãn hàng #$bid!";
        } else {
            $pdo->prepare("INSERT INTO brand (name, description, status, created_at, modified_at) VALUES (?, ?, ?, NOW(), NOW())")
                ->execute([$name, $desc, $status]);
            $alert = "Đã thêm nhãn hàng mới!";
        }
    }
    if (isset($_GET['del_brand'])) {
        $bid = (int)$_GET['del_brand'];
        $pdo->prepare("UPDATE product SET brand_id = NULL WHERE brand_id = ?")->execute([$bid]);
        $pdo->prepare("DELETE FROM brand WHERE id = ?")->execute([$bid]);
        header("Location: admin.php?tab=brands"); exit;
    }

    // --- 4. MODULE DANH MỤC (`category`: THÊM, SỬA, XÓA) ---
    if (isset($_POST['save_category'])) {
        $cid = (int)($_POST['cat_id'] ?? 0);
        $name = trim($_POST['name']);
        $status = (int)$_POST['status'];
        if ($cid > 0) {
            $pdo->prepare("UPDATE category SET name=?, slug=?, status=?, modified_at=NOW() WHERE id=?")
                ->execute([$name, createSlug($name), $status, $cid]);
            $alert = "Đã cập nhật danh mục #$cid!";
        } else {
            $pdo->prepare("INSERT INTO category (name, slug, orders, status, created_at) VALUES (?, ?, 0, ?, NOW())")
                ->execute([$name, createSlug($name), $status]);
            $alert = "Đã thêm danh mục mới!";
        }
    }
    if (isset($_GET['del_cat'])) {
        $cid = (int)$_GET['del_cat'];
        $pdo->prepare("DELETE FROM product_category WHERE category_id = ?")->execute([$cid]);
        $pdo->prepare("DELETE FROM category WHERE id = ?")->execute([$cid]);
        header("Location: admin.php?tab=categories"); exit;
    }

    // --- 5. MODULE COUPON KHUYẾN MÃI (`promotion`: THÊM, SỬA, XÓA) ---
    if (isset($_POST['save_coupon'])) {
        $id = (int)($_POST['promo_id'] ?? 0);
        $code = strtoupper(trim($_POST['coupon_code']));
        $name = trim($_POST['name']);
        $type = (int)$_POST['discount_type'];
        $val = (int)$_POST['discount_value'];
        $max = (int)$_POST['maximum_discount_value'];
        $exp = $_POST['expired_at'] ?: '2026-12-31 23:59:59';
        $active = isset($_POST['is_active']) ? 1 : 0;
        $public = isset($_POST['is_public']) ? 1 : 0;

        if ($id > 0) {
            $pdo->prepare("UPDATE promotion SET coupon_code=?, name=?, discount_type=?, discount_value=?, maximum_discount_value=?, expired_at=?, is_active=?, is_public=? WHERE id=?")
                ->execute([$code, $name, $type, $val, $max, $exp, $active, $public, $id]);
            $alert = "Đã cập nhật mã khuyến mãi $code!";
        } else {
            $pdo->prepare("INSERT INTO promotion (coupon_code, name, discount_type, discount_value, maximum_discount_value, expired_at, is_active, is_public, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())")
                ->execute([$code, $name, $type, $val, $max, $exp, $active, $public]);
            $alert = "Đã tạo mã khuyến mãi $code!";
        }
    }
    if (isset($_GET['del_coupon'])) {
        $pdo->prepare("DELETE FROM promotion WHERE id = ?")->execute([(int)$_GET['del_coupon']]);
        header("Location: admin.php?tab=coupons"); exit;
    }

    // --- 6. MODULE QUẢN LÝ TÀI KHOẢN (`users`: THÊM, SỬA QUYỀN/TRẠNG THÁI, XÓA) ---
    if (isset($_POST['save_user'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $role = $_POST['role'] === 'ADMIN' ? json_encode(["ADMIN", "USER"]) : json_encode(["USER"]);
        $status = (int)$_POST['status'];
        $pass = trim($_POST['password']);

        if ($uid > 0) {
            if ($pass !== '') {
                $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, address=?, roles=?, status=?, password=?, modified_at=NOW() WHERE id=?")
                    ->execute([$full_name, $email, $phone, $address, $role, $status, password_hash($pass, PASSWORD_BCRYPT), $uid]);
            } else {
                $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, address=?, roles=?, status=?, modified_at=NOW() WHERE id=?")
                    ->execute([$full_name, $email, $phone, $address, $role, $status, $uid]);
            }
            $alert = "Đã cập nhật tài khoản #$uid!";
        } else {
            $hash = password_hash($pass ?: '123456', PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (full_name, email, password, phone, address, roles, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())")
                ->execute([$full_name, $email, $hash, $phone, $address, $role, $status]);
            $alert = "Đã thêm tài khoản mới!";
        }
    }
    if (isset($_GET['del_user'])) {
        $uid = (int)$_GET['del_user'];
        if ($uid !== (int)$_SESSION['user']['id']) {
            $pdo->prepare("DELETE FROM comment WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("UPDATE orders SET buyer = NULL, created_by = NULL, modified_by = NULL WHERE buyer = ? OR created_by = ? OR modified_by = ?")->execute([$uid, $uid, $uid]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        }
        header("Location: admin.php?tab=users"); exit;
    }

    // --- 7. MODULE QUẢN LÝ ĐƠN HÀNG (`orders` & `statistic`) ---
    if (isset($_POST['update_order'])) {
        $oid = (int)$_POST['order_id'];
        $status = (int)$_POST['status'];
        $pdo->prepare("UPDATE orders SET status = ?, modified_at = NOW(), modified_by = ? WHERE id = ?")
            ->execute([$status, $_SESSION['user']['id'], $oid]);

        if ($status === 3) {
            $chkStat = $pdo->prepare("SELECT id FROM statistic WHERE order_id = ?");
            $chkStat->execute([$oid]);
            if (!$chkStat->fetch()) {
                $stmtO = $pdo->prepare("SELECT o.*, p.price AS cost_price FROM orders o LEFT JOIN product p ON o.product_id = p.id WHERE o.id = ?");
                $stmtO->execute([$oid]);
                $ord = $stmtO->fetch();
                if ($ord) {
                    $sales = (int)$ord['total_price'];
                    $cost = ((int)($ord['cost_price'] ?? 500000)) * (int)$ord['quantity'];
                    $profit = max(0, $sales - $cost);
                    $pdo->prepare("INSERT INTO statistic (created_at, profit, quantity, sales, order_id) VALUES (NOW(), ?, ?, ?, ?)")
                        ->execute([$profit, $ord['quantity'], $sales, $oid]);
                }
            }
        }
        $alert = "Đã cập nhật trạng thái đơn hàng #$oid!";
    }
    if (isset($_GET['del_order'])) {
        $oid = (int)$_GET['del_order'];
        $pdo->prepare("DELETE FROM statistic WHERE order_id = ?")->execute([$oid]);
        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$oid]);
        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$oid]);
        header("Location: admin.php?tab=orders"); exit;
    }
} catch (Exception $ex) {
    $alert = "Lỗi xử lý: " . $ex->getMessage();
}

$stats = $pdo->query("SELECT SUM(sales) AS total_sales, SUM(profit) AS total_profit, SUM(quantity) AS total_qty FROM statistic")->fetch();
?>

<!-- THANH THỐNG KÊ TỔNG QUAN -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-dark text-white p-3 shadow-sm">
            <div class="small text-uppercase text-white-50">Tổng Doanh Thu (Bảng Statistic)</div>
            <h4 class="fw-bold mb-0 text-warning"><?= formatMoney($stats['total_sales'] ?? 0) ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white p-3 shadow-sm">
            <div class="small text-uppercase text-white-50">Tổng Lợi Nhuận Thực Tế</div>
            <h4 class="fw-bold mb-0"><?= formatMoney($stats['total_profit'] ?? 0) ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary text-white p-3 shadow-sm">
            <div class="small text-uppercase text-white-50">Tổng Số Giày Đã Bán Hoàn Tất</div>
            <h4 class="fw-bold mb-0"><?= (int)($stats['total_qty'] ?? 0) ?> đôi</h4>
        </div>
    </div>
</div>

<?php if ($alert): ?><div class="alert alert-info fw-bold"><?= htmlspecialchars($alert) ?></div><?php endif; ?>

<!-- MENU CHUYỂN TAB QUẢN TRỊ RÕ RÀNG, DỄ DÙNG -->
<ul class="nav nav-pills mb-4 bg-white p-2 rounded shadow-sm border">
    <li class="nav-item"><a class="nav-link <?= $tab==='products'?'active':'' ?>" href="admin.php?tab=products">👟 1. Sản phẩm (Thêm/Sửa/Xóa)</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='inventory'?'active':'' ?>" href="admin.php?tab=inventory">📏 2. Quản lý Tồn kho (Size)</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='brands'?'active':'' ?>" href="admin.php?tab=brands">🏷 3. Nhãn hàng (Brand)</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='categories'?'active':'' ?>" href="admin.php?tab=categories">📂 4. Danh mục</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='coupons'?'active':'' ?>" href="admin.php?tab=coupons">🎟 5. Mã giảm giá (Coupon)</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='orders'?'active':'' ?>" href="admin.php?tab=orders">📦 6. Quản lý Đơn hàng</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='users'?'active':'' ?>" href="admin.php?tab=users">👥 7. Quản lý Tài khoản</a></li>
</ul>

<?php
// ==================== TAB 1: QUẢN LÝ SẢN PHẨM ====================
if ($tab === 'products'):
    $brands = $pdo->query("SELECT * FROM brand ORDER BY name ASC")->fetchAll();
    $categories = $pdo->query("SELECT * FROM category ORDER BY name ASC")->fetchAll();
    $products = $pdo->query("SELECT p.*, b.name AS brand_name FROM product p LEFT JOIN brand b ON p.brand_id = b.id ORDER BY p.modified_at DESC")->fetchAll();

    $editProd = null;
    $editCats = [];
    if (isset($_GET['edit_product'])) {
        $stmtE = $pdo->prepare("SELECT * FROM product WHERE id = ?");
        $stmtE->execute([$_GET['edit_product']]);
        $editProd = $stmtE->fetch();
        if ($editProd) {
            $stmtEC = $pdo->prepare("SELECT category_id FROM product_category WHERE product_id = ?");
            $stmtEC->execute([$editProd['id']]);
            $editCats = $stmtEC->fetchAll(PDO::FETCH_COLUMN);
        }
    }
?>
<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm p-3 border-primary">
            <h5 class="fw-bold text-primary"><?= $editProd ? '✏️ Sửa Sản phẩm #'.$editProd['id'] : '➕ Thêm Sản phẩm mới' ?></h5>
            <form method="POST" action="admin.php?tab=products">
                <input type="hidden" name="edit_id" value="<?= htmlspecialchars($editProd['id'] ?? '') ?>">
                <div class="mb-2">
                    <label class="small fw-bold">Tên sản phẩm</label>
                    <input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars($editProd['name'] ?? '') ?>" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="small fw-bold">Giá vốn (price)</label>
                        <input type="number" name="price" class="form-control form-control-sm" value="<?= htmlspecialchars($editProd['price'] ?? 550000) ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold">Giá bán (sale_price)</label>
                        <input type="number" name="sale_price" class="form-control form-control-sm" value="<?= htmlspecialchars($editProd['sale_price'] ?? 1350000) ?>" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="small fw-bold">Nhãn hàng (Brand)</label>
                        <select name="brand_id" class="form-select form-select-sm" required>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ($editProd['brand_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold">Trạng thái</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="1" <?= ($editProd['status'] ?? 1) == 1 ? 'selected' : '' ?>>Đang bán (1)</option>
                            <option value="0" <?= isset($editProd['status']) && $editProd['status'] == 0 ? 'selected' : '' ?>>Ẩn / Ngừng bán (0)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Danh mục (Chọn nhiều)</label>
                    <div class="border rounded p-2 bg-light">
                        <?php foreach ($categories as $c): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="categories[]" value="<?= $c['id'] ?>" <?= in_array($c['id'], $editCats) ? 'checked' : '' ?>>
                                <label class="form-check-label small"><?= htmlspecialchars($c['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Link Ảnh sản phẩm (URL)</label>
                    <input type="text" name="image_url" class="form-control form-control-sm" value="<?= htmlspecialchars($editProd ? getProductImage($editProd['images'], $editProd['id']) : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&q=80') ?>">
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Mô tả chi tiết</label>
                    <textarea name="description" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($editProd['description'] ?? '- Chất lượng chuẩn Rep 1:1 - Bảo hành trọn đời') ?></textarea>
                </div>
                <button type="submit" name="save_product" class="btn btn-primary w-100 fw-bold"><?= $editProd ? 'Cập nhật Sản phẩm' : 'Thêm Sản phẩm' ?></button>
                <?php if ($editProd): ?>
                    <a href="admin.php?tab=products" class="btn btn-outline-secondary btn-sm w-100 mt-2">Hủy sửa</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm p-3">
            <h5 class="fw-bold">Danh sách Sản phẩm (<?= count($products) ?>)</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle table-sm mt-2">
                    <thead class="table-dark">
                        <tr><th>Mã</th><th>Ảnh</th><th>Tên giày</th><th>Nhãn hàng</th><th>Giá vốn</th><th>Giá bán</th><th>TT</th><th>Thao tác</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($p['id']) ?></code></td>
                            <td><img src="<?= htmlspecialchars(getProductImage($p['images'], $p['id'])) ?>" style="width:45px;height:45px;object-fit:cover;" class="rounded"></td>
                            <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($p['brand_name'] ?? '-') ?></span></td>
                            <td><?= formatMoney($p['price']) ?></td>
                            <td class="text-danger fw-bold"><?= formatMoney($p['sale_price']) ?></td>
                            <td><?= $p['status'] == 1 ? '<span class="badge bg-success">Hiện</span>' : '<span class="badge bg-danger">Ẩn</span>' ?></td>
                            <td class="text-nowrap">
                                <a href="admin.php?tab=products&edit_product=<?= urlencode($p['id']) ?>" class="btn btn-sm btn-warning">Sửa</a>
                                <a href="admin.php?tab=products&del_product=<?= urlencode($p['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa sản phẩm này?')">Xóa</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// ==================== TAB 2: QUẢN LÝ TỒN KHO (`product_size`) ====================
elseif ($tab === 'inventory'):
    $products = $pdo->query("SELECT id, name FROM product ORDER BY name ASC")->fetchAll();
    $inventory = $pdo->query("SELECT ps.*, p.name AS product_name FROM product_size ps JOIN product p ON ps.product_id = p.id ORDER BY ps.product_id, ps.size ASC")->fetchAll();
?>
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm p-3 border-primary">
            <h5 class="fw-bold text-primary">📏 Cập nhật / Thêm Size Tồn Kho</h5>
            <form method="POST" action="admin.php?tab=inventory">
                <div class="mb-2">
                    <label class="small fw-bold">Chọn Sản phẩm</label>
                    <select name="product_id" class="form-select form-select-sm" required>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Size giày (35 - 45)</label>
                    <input type="number" name="size" min="35" max="45" value="39" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Số lượng tồn kho</label>
                    <input type="number" name="quantity" min="0" value="15" class="form-control form-control-sm" required>
                </div>
                <button type="submit" name="save_inventory" class="btn btn-primary w-100 fw-bold">Lưu Tồn Kho</button>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm p-3">
            <h5 class="fw-bold">Bảng Tồn Kho Theo Size (`product_size`)</h5>
            <table class="table table-bordered table-sm align-middle mt-2">
                <thead class="table-dark"><tr><th>Mã SP</th><th>Tên Sản Phẩm</th><th>Size</th><th>Số lượng tồn</th><th>Trạng thái</th><th>Xóa</th></tr></thead>
                <tbody>
                    <?php foreach ($inventory as $inv): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($inv['product_id']) ?></code></td>
                        <td><?= htmlspecialchars($inv['product_name']) ?></td>
                        <td class="fw-bold">Size <?= (int)$inv['size'] ?></td>
                        <td class="fw-bold text-primary"><?= (int)$inv['quantity'] ?> đôi</td>
                        <td><?= $inv['quantity'] > 0 ? '<span class="badge bg-success">Còn hàng</span>' : '<span class="badge bg-danger">Hết hàng</span>' ?></td>
                        <td><a href="admin.php?tab=inventory&pid=<?= urlencode($inv['product_id']) ?>&del_size=<?= $inv['size'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa size này?')">Xóa</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// ==================== TAB 3: QUẢN LÝ NHÃN HÀNG (`brand`) ====================
elseif ($tab === 'brands'):
    $brands = $pdo->query("SELECT * FROM brand ORDER BY id DESC")->fetchAll();
    $editBrand = null;
    if (isset($_GET['edit_brand'])) {
        $stmtB = $pdo->prepare("SELECT * FROM brand WHERE id = ?");
        $stmtB->execute([(int)$_GET['edit_brand']]);
        $editBrand = $stmtB->fetch();
    }
?>
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm p-3 border-primary">
            <h5 class="fw-bold text-primary"><?= $editBrand ? '✏️ Sửa Nhãn hàng #'.$editBrand['id'] : '➕ Thêm Nhãn hàng' ?></h5>
            <form method="POST" action="admin.php?tab=brands">
                <input type="hidden" name="brand_id" value="<?= $editBrand['id'] ?? 0 ?>">
                <div class="mb-2">
                    <label class="small fw-bold">Tên Nhãn hàng (Brand)</label>
                    <input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars($editBrand['name'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Mô tả</label>
                    <input type="text" name="description" class="form-control form-control-sm" value="<?= htmlspecialchars($editBrand['description'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Trạng thái</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="1" <?= ($editBrand['status'] ?? 1) == 1 ? 'selected' : '' ?>>Hiển thị (1)</option>
                        <option value="0" <?= isset($editBrand['status']) && $editBrand['status'] == 0 ? 'selected' : '' ?>>Ẩn (0)</option>
                    </select>
                </div>
                <button type="submit" name="save_brand" class="btn btn-primary w-100 fw-bold">Lưu Nhãn Hàng</button>
                <?php if ($editBrand): ?><a href="admin.php?tab=brands" class="btn btn-outline-secondary btn-sm w-100 mt-2">Hủy sửa</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm p-3">
            <h5 class="fw-bold">Danh sách Nhãn hàng (`brand`)</h5>
            <table class="table table-bordered align-middle table-sm mt-2">
                <thead class="table-dark"><tr><th>ID</th><th>Tên Nhãn hàng</th><th>Mô tả</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                    <?php foreach ($brands as $b): ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= htmlspecialchars($b['description'] ?? '-') ?></td>
                        <td><?= $b['status'] ? '<span class="badge bg-success">Hiển thị</span>' : '<span class="badge bg-secondary">Ẩn</span>' ?></td>
                        <td>
                            <a href="admin.php?tab=brands&edit_brand=<?= $b['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                            <a href="admin.php?tab=brands&del_brand=<?= $b['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa nhãn hàng này?')">Xóa</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// ==================== TAB 4: QUẢN LÝ DANH MỤC (`category`) ====================
elseif ($tab === 'categories'):
    $categories = $pdo->query("SELECT * FROM category ORDER BY id DESC")->fetchAll();
    $editCat = null;
    if (isset($_GET['edit_cat'])) {
        $stmtC = $pdo->prepare("SELECT * FROM category WHERE id = ?");
        $stmtC->execute([(int)$_GET['edit_cat']]);
        $editCat = $stmtC->fetch();
    }
?>
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm p-3 border-primary">
            <h5 class="fw-bold text-primary"><?= $editCat ? '✏️ Sửa Danh mục #'.$editCat['id'] : '➕ Thêm Danh mục' ?></h5>
            <form method="POST" action="admin.php?tab=categories">
                <input type="hidden" name="cat_id" value="<?= $editCat['id'] ?? 0 ?>">
                <div class="mb-2">
                    <label class="small fw-bold">Tên Danh mục</label>
                    <input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars($editCat['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">Trạng thái</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="1" <?= ($editCat['status'] ?? 1) == 1 ? 'selected' : '' ?>>Hiển thị (1)</option>
                        <option value="0" <?= isset($editCat['status']) && $editCat['status'] == 0 ? 'selected' : '' ?>>Ẩn (0)</option>
                    </select>
                </div>
                <button type="submit" name="save_category" class="btn btn-primary w-100 fw-bold">Lưu Danh Mục</button>
                <?php if ($editCat): ?><a href="admin.php?tab=categories" class="btn btn-outline-secondary btn-sm w-100 mt-2">Hủy sửa</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm p-3">
            <h5 class="fw-bold">Danh sách Danh mục (`category`)</h5>
            <table class="table table-bordered align-middle table-sm mt-2">
                <thead class="table-dark"><tr><th>ID</th><th>Tên Danh mục</th><th>Slug</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($c['name']) ?></td>
                        <td><code><?= htmlspecialchars($c['slug']) ?></code></td>
                        <td><?= $c['status'] ? '<span class="badge bg-success">Hiển thị</span>' : '<span class="badge bg-secondary">Ẩn</span>' ?></td>
                        <td>
                            <a href="admin.php?tab=categories&edit_cat=<?= $c['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                            <a href="admin.php?tab=categories&del_cat=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa danh mục này?')">Xóa</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// ==================== TAB 5: QUẢN LÝ COUPON (`promotion`) ====================
elseif ($tab === 'coupons'):
    $promos = $pdo->query("SELECT * FROM promotion ORDER BY id DESC")->fetchAll();
    $editPromo = null;
    if (isset($_GET['edit_coupon'])) {
        $stmtP = $pdo->prepare("SELECT * FROM promotion WHERE id = ?");
        $stmtP->execute([(int)$_GET['edit_coupon']]);
        $editPromo = $stmtP->fetch();
    }
?>
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm p-3 border-primary">
            <h5 class="fw-bold text-primary"><?= $editPromo ? '✏️ Sửa Mã Coupon #'.$editPromo['id'] : '➕ Tạo Mã Giảm Giá Mới' ?></h5>
            <form method="POST" action="admin.php?tab=coupons">
                <input type="hidden" name="promo_id" value="<?= $editPromo['id'] ?? 0 ?>">
                <div class="mb-2">
                    <label class="small fw-bold">Mã Code (VD: TET2026, SUMMER50)</label>
                    <input type="text" name="coupon_code" class="form-control form-control-sm" value="<?= htmlspecialchars($editPromo['coupon_code'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Tên chương trình khuyến mãi</label>
                    <input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars($editPromo['name'] ?? '') ?>" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="small fw-bold">Loại giảm</label>
                        <select name="discount_type" class="form-select form-select-sm">
                            <option value="1" <?= ($editPromo['discount_type'] ?? 1) == 1 ? 'selected' : '' ?>>Theo % (1)</option>
                            <option value="2" <?= ($editPromo['discount_type'] ?? 1) == 2 ? 'selected' : '' ?>>Tiền mặt (2)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold">Giá trị giảm</label>
                        <input type="number" name="discount_value" class="form-control form-control-sm" value="<?= htmlspecialchars($editPromo['discount_value'] ?? 30) ?>" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Giảm tối đa (VNĐ)</label>
                    <input type="number" name="maximum_discount_value" class="form-control form-control-sm" value="<?= htmlspecialchars($editPromo['maximum_discount_value'] ?? 200000) ?>" required>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Hạn sử dụng</label>
                    <input type="text" name="expired_at" class="form-control form-control-sm" value="<?= htmlspecialchars(substr($editPromo['expired_at'] ?? '2026-12-31 23:59:59', 0, 19)) ?>">
                </div>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="is_active" id="chkAct" <?= ($editPromo['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="chkAct">Kích hoạt sử dụng (is_active)</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_public" id="chkPub" <?= ($editPromo['is_public'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="chkPub">Hiển thị công khai ở Giỏ hàng (is_public)</label>
                </div>
                <button type="submit" name="save_coupon" class="btn btn-primary w-100 fw-bold">Lưu Mã Giảm Giá</button>
                <?php if ($editPromo): ?><a href="admin.php?tab=coupons" class="btn btn-outline-secondary btn-sm w-100 mt-2">Hủy sửa</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm p-3">
            <h5 class="fw-bold">Danh sách Mã Giảm Giá (`promotion`)</h5>
            <table class="table table-bordered align-middle table-sm mt-2">
                <thead class="table-dark"><tr><th>Mã Code</th><th>Tên chương trình</th><th>Mức giảm</th><th>Tối đa</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                    <?php foreach ($promos as $pr): ?>
                    <tr>
                        <td><code class="fw-bold fs-6"><?= htmlspecialchars($pr['coupon_code']) ?></code></td>
                        <td><?= htmlspecialchars($pr['name']) ?></td>
                        <td class="fw-bold text-danger"><?= $pr['discount_type'] == 1 ? $pr['discount_value'].'%' : formatMoney($pr['discount_value']) ?></td>
                        <td><?= formatMoney($pr['maximum_discount_value']) ?></td>
                        <td>
                            <?= $pr['is_active'] ? '<span class="badge bg-success">Đang chạy</span>' : '<span class="badge bg-secondary">Tắt</span>' ?>
                            <?= $pr['is_public'] ? '<span class="badge bg-info text-dark">Công khai</span>' : '' ?>
                        </td>
                        <td>
                            <a href="admin.php?tab=coupons&edit_coupon=<?= $pr['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                            <a href="admin.php?tab=coupons&del_coupon=<?= $pr['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa mã giảm giá này?')">Xóa</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// ==================== TAB 6: QUẢN LÝ ĐƠN HÀNG (`orders`) ====================
elseif ($tab === 'orders'):
    $orders = $pdo->query("SELECT o.*, p.name AS product_name FROM orders o LEFT JOIN product p ON o.product_id = p.id ORDER BY o.id DESC")->fetchAll();
?>
<div class="card shadow-sm p-3">
    <h5 class="fw-bold">📦 Quản lý Tất cả Đơn hàng (`orders`)</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle table-sm mt-2">
            <thead class="table-dark">
                <tr><th>Mã ĐH</th><th>Người nhận / SĐT</th><th>Địa chỉ</th><th>Sản phẩm</th><th>Size/SL</th><th>Tổng thu</th><th>Ghi chú / TT</th><th>Cập nhật Trạng thái</th><th>Xóa</th></tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="fw-bold">#<?= $o['id'] ?></td>
                    <td><b><?= htmlspecialchars($o['receiver_name']) ?></b><br><small><?= htmlspecialchars($o['receiver_phone']) ?></small></td>
                    <td class="small"><?= htmlspecialchars($o['receiver_address']) ?></td>
                    <td><?= htmlspecialchars($o['product_name'] ?? $o['product_id']) ?></td>
                    <td>Size <?= $o['size'] ?> (x<?= $o['quantity'] ?>)</td>
                    <td class="text-danger fw-bold">
                        <?= formatMoney($o['total_price']) ?>
                        <?php if ($o['coupon_code']): ?><br><span class="badge bg-info text-dark"><?= htmlspecialchars($o['coupon_code']) ?></span><?php endif; ?>
                    </td>
                    <td class="small"><?= htmlspecialchars($o['note'] ?? '') ?></td>
                    <td>
                        <form method="POST" action="admin.php?tab=orders" class="d-flex gap-1">
                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                            <select name="status" class="form-select form-select-sm">
                                <option value="1" <?= $o['status']==1?'selected':'' ?>>1 - Chờ lấy hàng</option>
                                <option value="2" <?= $o['status']==2?'selected':'' ?>>2 - Đang giao</option>
                                <option value="3" <?= $o['status']==3?'selected':'' ?>>3 - Đã giao (+Doanh thu)</option>
                                <option value="4" <?= $o['status']==4?'selected':'' ?>>4 - Đã trả hàng</option>
                                <option value="5" <?= $o['status']==5?'selected':'' ?>>5 - Đã hủy</option>
                            </select>
                            <button type="submit" name="update_order" class="btn btn-sm btn-success">Lưu</button>
                        </form>
                    </td>
                    <td><a href="admin.php?tab=orders&del_order=<?= $o['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa đơn hàng #<?= $o['id'] ?>?')">Xóa</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// ==================== TAB 7: QUẢN LÝ TÀI KHOẢN (`users`) ====================
elseif ($tab === 'users'):
    $users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
    $editUser = null;
    if (isset($_GET['edit_user'])) {
        $stmtU = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmtU->execute([(int)$_GET['edit_user']]);
        $editUser = $stmtU->fetch();
    }
?>
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm p-3 border-primary">
            <h5 class="fw-bold text-primary"><?= $editUser ? '✏️ Sửa Tài khoản #'.$editUser['id'] : '➕ Thêm Tài khoản' ?></h5>
            <form method="POST" action="admin.php?tab=users">
                <input type="hidden" name="user_id" value="<?= $editUser['id'] ?? 0 ?>">
                <div class="mb-2">
                    <label class="small fw-bold">Họ và tên</label>
                    <input type="text" name="full_name" class="form-control form-control-sm" value="<?= htmlspecialchars($editUser['full_name'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Email đăng nhập</label>
                    <input type="email" name="email" class="form-control form-control-sm" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Mật khẩu <?= $editUser ? '(Để trống nếu giữ nguyên)' : '' ?></label>
                    <input type="text" name="password" class="form-control form-control-sm" placeholder="Nhập mật khẩu mới...">
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Số điện thoại</label>
                    <input type="text" name="phone" class="form-control form-control-sm" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label class="small fw-bold">Địa chỉ</label>
                    <input type="text" name="address" class="form-control form-control-sm" value="<?= htmlspecialchars($editUser['address'] ?? '') ?>">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="small fw-bold">Phân quyền (Roles)</label>
                        <select name="role" class="form-select form-select-sm">
                            <option value="USER">USER (Khách)</option>
                            <option value="ADMIN" <?= ($editUser && strpos($editUser['roles'], 'ADMIN') !== false) ? 'selected' : '' ?>>ADMIN (Quản trị)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold">Trạng thái</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="1" <?= ($editUser['status'] ?? 1) == 1 ? 'selected' : '' ?>>Hoạt động (1)</option>
                            <option value="0" <?= isset($editUser['status']) && $editUser['status'] == 0 ? 'selected' : '' ?>>Khóa (0)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="save_user" class="btn btn-primary w-100 fw-bold">Lưu Tài Khoản</button>
                <?php if ($editUser): ?><a href="admin.php?tab=users" class="btn btn-outline-secondary btn-sm w-100 mt-2">Hủy sửa</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm p-3">
            <h5 class="fw-bold">Danh sách Tài khoản (`users`)</h5>
            <table class="table table-bordered align-middle table-sm mt-2">
                <thead class="table-dark"><tr><th>ID</th><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Quyền (Roles)</th><th>TT</th><th>Thao tác</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($u['full_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                        <td><code><?= htmlspecialchars($u['roles']) ?></code></td>
                        <td><?= $u['status'] ? '<span class="badge bg-success">Mở</span>' : '<span class="badge bg-danger">Khóa</span>' ?></td>
                        <td>
                            <a href="admin.php?tab=users&edit_user=<?= $u['id'] ?>" class="btn btn-sm btn-warning">Sửa</a>
                            <?php if ($u['id'] != $_SESSION['user']['id']): ?>
                                <a href="admin.php?tab=users&del_user=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa tài khoản này?')">Xóa</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>