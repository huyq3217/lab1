<?php
require_once 'header.php';

$id = $_GET['id'] ?? '';
$stmt = $pdo->prepare("SELECT p.*, b.name AS brand_name FROM product p LEFT JOIN brand b ON p.brand_id = b.id WHERE p.id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='alert alert-danger'>Không tìm thấy sản phẩm!</div>";
    require_once 'footer.php';
    exit;
}

// Tăng lượt xem product_view
$pdo->prepare("UPDATE product SET product_view = IFNULL(product_view, 0) + 1 WHERE id = ?")->execute([$id]);

// Lấy danh sách size còn hàng từ bảng `product_size`
$stmtSizes = $pdo->prepare("SELECT * FROM product_size WHERE product_id = ? AND quantity > 0 ORDER BY size ASC");
$stmtSizes->execute([$id]);
$sizes = $stmtSizes->fetchAll();

// Xử lý gửi bình luận vào bảng `comment`
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_content']) && isLoggedIn()) {
    $content = trim($_POST['comment_content']);
    if ($content !== '') {
        $stmtCmt = $pdo->prepare("INSERT INTO comment (content, created_at, product_id, user_id) VALUES (?, NOW(), ?, ?)");
        $stmtCmt->execute([$content, $id, $_SESSION['user']['id']]);
        header("Location: product.php?id=" . urlencode($id));
        exit;
    }
}

$stmtCmts = $pdo->prepare("SELECT c.*, u.full_name FROM comment c LEFT JOIN users u ON c.user_id = u.id WHERE c.product_id = ? ORDER BY c.created_at DESC");
$stmtCmts->execute([$id]);
$comments = $stmtCmts->fetchAll();
?>

<div class="card shadow-sm p-4 mb-4">
    <div class="row">
        <div class="col-md-5 text-center">
            <img src="<?= htmlspecialchars(getProductImage($product['images'])) ?>" class="img-fluid rounded border" style="max-height:380px;" onerror="this.src='https://cdn.printgo.vn/uploads/media/772948/thiet-ke-logo-shop-giay-19_1584095087.jpg'">
        </div>
        <div class="col-md-7">
            <span class="badge bg-dark mb-2"><?= htmlspecialchars($product['brand_name'] ?? '') ?></span>
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p class="text-muted small">Mã SP: <code><?= htmlspecialchars($product['id']) ?></code> | Lượt xem: <?= (int)$product['product_view'] + 1 ?></p>
            <h3 class="text-danger fw-bold my-3"><?= formatMoney(getSellingPrice($product)) ?></h3>

            <form method="POST" action="cart.php" class="border rounded p-3 bg-light mb-3">
                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6">
                        <label class="form-label fw-bold">Chọn Size giày (Bảng product_size):</label>
                        <select name="size" class="form-select" required>
                            <?php if (empty($sizes)): ?>
                                <option value="39">Size 39 (Mặc định)</option>
                            <?php else: ?>
                                <?php foreach ($sizes as $s): ?>
                                    <option value="<?= $s['size'] ?>">Size <?= $s['size'] ?> (Còn <?= $s['quantity'] ?> đôi)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label fw-bold">Số lượng:</label>
                        <input type="number" name="quantity" value="1" min="1" max="10" class="form-control" required>
                    </div>
                    <div class="col-sm-3">
                        <button type="submit" name="add_to_cart" class="btn btn-danger w-100">Thêm vào giỏ</button>
                    </div>
                </div>
            </form>

            <div class="mt-3">
                <h6>Mô tả sản phẩm:</h6>
                <div class="border rounded p-3 bg-white"><?= $product['description'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm p-4">
    <h5>Đánh giá & Bình luận (<?= count($comments) ?>)</h5>
    <?php if (isLoggedIn()): ?>
        <form method="POST" class="mb-3">
            <div class="input-group">
                <input type="text" name="comment_content" class="form-control" placeholder="Viết nhận xét của bạn về đôi giày này..." required>
                <button class="btn btn-primary" type="submit">Gửi bình luận</button>
            </div>
        </form>
    <?php else: ?>
        <p class="text-muted">Vui lòng <a href="login.php">Đăng nhập</a> để gửi bình luận.</p>
    <?php endif; ?>

    <ul class="list-group list-group-flush">
        <?php foreach ($comments as $c): ?>
            <li class="list-group-item px-0">
                <strong><?= htmlspecialchars($c['full_name'] ?? 'Khách hàng') ?></strong>
                <small class="text-muted ms-2"><?= $c['created_at'] ?></small>
                <p class="mb-0 mt-1"><?= htmlspecialchars($c['content']) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php require_once 'footer.php'; ?>