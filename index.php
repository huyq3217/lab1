<?php
require_once 'header.php';

$brands = $pdo->query("SELECT * FROM brand WHERE status = 1")->fetchAll();
$categories = $pdo->query("SELECT * FROM category WHERE status = 1")->fetchAll();

$where = ["p.status = 1"];
$params = [];

if (!empty($_GET['brand'])) {
    $where[] = "p.brand_id = ?";
    $params[] = $_GET['brand'];
}
if (!empty($_GET['category'])) {
    $where[] = "p.id IN (SELECT product_id FROM product_category WHERE category_id = ?)";
    $params[] = $_GET['category'];
}
if (!empty($_GET['q'])) {
    $where[] = "p.name LIKE ?";
    $params[] = '%' . trim($_GET['q']) . '%';
}

$sql = "SELECT p.*, b.name AS brand_name 
        FROM product p 
        LEFT JOIN brand b ON p.brand_id = b.id 
        WHERE " . implode(" AND ", $where) . " 
        ORDER BY p.modified_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-dark text-white fw-bold">Tìm kiếm</div>
            <div class="card-body">
                <form method="GET" action="index.php">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Tên giày..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                        <button class="btn btn-primary" type="submit">Tìm</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-dark text-white fw-bold">Danh mục giày</div>
            <div class="list-group list-group-flush">
                <a href="index.php" class="list-group-item list-group-item-action <?= empty($_GET['category']) ? 'active' : '' ?>">Tất cả danh mục</a>
                <?php foreach ($categories as $c): ?>
                    <a href="index.php?category=<?= $c['id'] ?>" class="list-group-item list-group-item-action <?= ($_GET['category'] ?? '') == $c['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($c['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">Thương hiệu</div>
            <div class="list-group list-group-flush">
                <a href="index.php" class="list-group-item list-group-item-action <?= empty($_GET['brand']) ? 'active' : '' ?>">Tất cả thương hiệu</a>
                <?php foreach ($brands as $b): ?>
                    <a href="index.php?brand=<?= $b['id'] ?>" class="list-group-item list-group-item-action <?= ($_GET['brand'] ?? '') == $b['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($b['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <div class="row">
            <?php if (empty($products)): ?>
                <div class="col-12"><div class="alert alert-warning">Không tìm thấy sản phẩm nào phù hợp!</div></div>
            <?php endif; ?>
            <?php foreach ($products as $p): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm product-card">
                        <img src="<?= htmlspecialchars(getProductImage($p['images'])) ?>" class="card-img-top" onerror="this.src='https://cdn.printgo.vn/uploads/media/772948/thiet-ke-logo-shop-giay-19_1584095087.jpg'">
                        <div class="card-body d-flex flex-column">
                            <span class="badge bg-secondary mb-2 align-self-start"><?= htmlspecialchars($p['brand_name'] ?? 'Chính hãng') ?></span>
                            <h6 class="card-title fw-bold"><?= htmlspecialchars($p['name']) ?></h6>
                            <p class="text-danger fw-bold fs-5 mt-auto mb-2"><?= formatMoney(getSellingPrice($p)) ?></p>
                            <div class="d-flex justify-content-between text-muted small mb-3">
                                <span>Đã bán: <?= (int)$p['total_sold'] ?></span>
                                <span>👁 <?= (int)$p['product_view'] ?> lượt xem</span>
                            </div>
                            <a href="product.php?id=<?= urlencode($p['id']) ?>" class="btn btn-dark w-100">Xem & Chọn Size</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>