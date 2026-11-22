<?php
require_once 'header.php';

if (!isLoggedIn()) {
    echo "<div class='alert alert-warning'>Vui lòng <a href='login.php' class='fw-bold'>Đăng nhập</a> để quản lý giỏ hàng và đơn hàng của bạn!</div>";
    require_once 'footer.php';
    exit;
}

$user_id = $_SESSION['user']['id'];
$msg = '';
$err = '';

// 1. Lấy hoặc tạo giỏ hàng (`cart`) cho user
$stmtCart = $pdo->prepare("SELECT * FROM cart WHERE user_id = ?");
$stmtCart->execute([$user_id]);
$cart = $stmtCart->fetch();

if (!$cart) {
    $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)")->execute([$user_id]);
    $cart_id = $pdo->lastInsertId();
} else {
    $cart_id = $cart['id'];
}

// 2. Thêm sản phẩm vào giỏ (`cart_item`)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $size = (int)$_POST['size'];
    $qty = max(1, (int)$_POST['quantity']);

    $stmtP = $pdo->prepare("SELECT * FROM product WHERE id = ?");
    $stmtP->execute([$product_id]);
    $prod = $stmtP->fetch();

    if ($prod) {
        $unitPrice = getSellingPrice($prod);
        $stmtCheck = $pdo->prepare("SELECT * FROM cart_item WHERE cart_id = ? AND product_id = ? AND size = ?");
        $stmtCheck->execute([$cart_id, $product_id, $size]);
        $exist = $stmtCheck->fetch();

        if ($exist) {
            $pdo->prepare("UPDATE cart_item SET quantity = quantity + ?, price = ? WHERE id = ?")
                ->execute([$qty, $unitPrice, $exist['id']]);
        } else {
            $pdo->prepare("INSERT INTO cart_item (price, product_id, quantity, size, cart_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([$unitPrice, $product_id, $qty, $size, $cart_id]);
        }
    }
    header("Location: cart.php");
    exit;
}

// 3. Cập nhật số lượng trực tiếp trong giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart_qty'])) {
    $item_id = (int)$_POST['item_id'];
    $new_qty = max(1, (int)$_POST['quantity']);
    $pdo->prepare("UPDATE cart_item SET quantity = ? WHERE id = ? AND cart_id = ?")->execute([$new_qty, $item_id, $cart_id]);
    header("Location: cart.php");
    exit;
}

// 4. Xóa món khỏi giỏ hàng
if (isset($_GET['del'])) {
    $pdo->prepare("DELETE FROM cart_item WHERE id = ? AND cart_id = ?")->execute([$_GET['del'], $cart_id]);
    header("Location: cart.php");
    exit;
}

// 5. User tự Hủy đơn hàng (khi đơn đang ở trạng thái 1 - Chờ lấy hàng)
if (isset($_GET['cancel_order'])) {
    $oid = (int)$_GET['cancel_order'];
    $stmtO = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND buyer = ? AND status = 1");
    $stmtO->execute([$oid, $user_id]);
    $ord = $stmtO->fetch();
    if ($ord) {
        $pdo->prepare("UPDATE orders SET status = 5, modified_at = NOW() WHERE id = ?")->execute([$oid]);
        // Hoàn lại tồn kho vào bảng product_size
        $pdo->prepare("UPDATE product_size SET quantity = quantity + ? WHERE product_id = ? AND size = ?")
            ->execute([$ord['quantity'], $ord['product_id'], $ord['size']]);
        $msg = "Đã hủy đơn hàng #$oid và hoàn lại số lượng tồn kho!";
    }
}

// 6. Xử lý Đặt hàng (Hỗ trợ COD & Chuyển khoản QR Code + Áp dụng Coupon)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $receiver_name = trim($_POST['receiver_name']);
    $receiver_phone = trim($_POST['receiver_phone']);
    $receiver_address = trim($_POST['receiver_address']);
    $payment_method = $_POST['payment_method'] ?? 'COD';
    $user_note = trim($_POST['note']);
    $note = "[" . $payment_method . "] " . $user_note;
    $coupon_code = trim($_POST['coupon_code'] ?? '');

    // Kiểm tra mã giảm giá trong bảng `promotion`
    $promoObj = null;
    if ($coupon_code !== '') {
        $stmtPromo = $pdo->prepare("SELECT * FROM promotion WHERE coupon_code = ? AND is_active = 1");
        $stmtPromo->execute([$coupon_code]);
        $promoObj = $stmtPromo->fetch();
        if (!$promoObj) {
            $coupon_code = null;
        }
    } else {
        $coupon_code = null;
    }

    $stmtItems = $pdo->prepare("SELECT ci.*, p.name AS product_name FROM cart_item ci JOIN product p ON ci.product_id = p.id WHERE ci.cart_id = ?");
    $stmtItems->execute([$cart_id]);
    $items = $stmtItems->fetchAll();

    $createdIds = [];

    foreach ($items as $item) {
        $subTotal = $item['price'] * $item['quantity'];
        $discountAmount = 0;
        if ($promoObj) {
            if ((int)$promoObj['discount_type'] === 1) { // Giảm theo %
                $discountAmount = ($subTotal * (int)$promoObj['discount_value']) / 100;
                if ($promoObj['maximum_discount_value'] > 0 && $discountAmount > $promoObj['maximum_discount_value']) {
                    $discountAmount = $promoObj['maximum_discount_value'];
                }
            } else { // Giảm tiền trực tiếp
                $discountAmount = (int)$promoObj['discount_value'];
            }
        }
        $finalTotal = max(0, $subTotal - $discountAmount);
        $promoJson = $promoObj ? json_encode($promoObj) : null;

        $stmtOrder = $pdo->prepare("INSERT INTO orders 
            (created_at, note, price, promotion, quantity, receiver_address, receiver_name, receiver_phone, size, status, total_price, buyer, created_by, product_id, coupon_code) 
            VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)");
        $stmtOrder->execute([
            $note, $item['price'], $promoJson, $item['quantity'], $receiver_address, $receiver_name, $receiver_phone,
            $item['size'], $finalTotal, $user_id, $user_id, $item['product_id'], $coupon_code
        ]);
        $order_id = $pdo->lastInsertId();
        $createdIds[] = $order_id;

        $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, size, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$order_id, $item['product_id'], $item['product_name'], $item['price'], $item['size'], $item['quantity'], $finalTotal]);

        $pdo->prepare("UPDATE product_size SET quantity = GREATEST(0, quantity - ?) WHERE product_id = ? AND size = ?")
            ->execute([$item['quantity'], $item['product_id'], $item['size']]);
        $pdo->prepare("UPDATE product SET total_sold = IFNULL(total_sold, 0) + ? WHERE id = ?")
            ->execute([$item['quantity'], $item['product_id']]);
    }

    $pdo->prepare("DELETE FROM cart_item WHERE cart_id = ?")->execute([$cart_id]);
    $msg = "Đặt hàng thành công! Mã đơn hàng: #" . implode(', #', $createdIds) . " - Phương thức: " . ($payment_method === 'QR' ? 'Chuyển khoản QR Code (H2P SNEAKER)' : 'Thanh toán khi nhận hàng (COD)');
}

// Lấy danh sách giỏ hàng
$stmtList = $pdo->prepare("SELECT ci.*, p.name, p.images FROM cart_item ci JOIN product p ON ci.product_id = p.id WHERE ci.cart_id = ?");
$stmtList->execute([$cart_id]);
$cartItems = $stmtList->fetchAll();

// Lấy danh sách Mã khuyến mãi công khai đang hoạt động
$activePromos = $pdo->query("SELECT * FROM promotion WHERE is_active = 1 AND is_public = 1")->fetchAll();

// Lọc đơn hàng của User theo trạng thái
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : 0;
if ($statusFilter > 0) {
    $stmtMyOrders = $pdo->prepare("SELECT o.*, p.name AS product_name FROM orders o LEFT JOIN product p ON o.product_id = p.id WHERE o.buyer = ? AND o.status = ? ORDER BY o.id DESC");
    $stmtMyOrders->execute([$user_id, $statusFilter]);
} else {
    $stmtMyOrders = $pdo->prepare("SELECT o.*, p.name AS product_name FROM orders o LEFT JOIN product p ON o.product_id = p.id WHERE o.buyer = ? ORDER BY o.id DESC");
    $stmtMyOrders->execute([$user_id]);
}
$myOrders = $stmtMyOrders->fetchAll();
?>

<?php if ($msg): ?><div class="alert alert-success fw-bold"><?= $msg ?></div><?php endif; ?>

<div class="row">
    <!-- CỘT TRÁI: GIỎ HÀNG -->
    <div class="col-lg-7">
        <div class="card shadow-sm p-3 mb-4">
            <h5 class="fw-bold mb-3">🛒 Giỏ hàng của bạn</h5>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-dark">
                        <tr><th>Sản phẩm</th><th>Size</th><th>Đơn giá</th><th style="width:130px;">Số lượng</th><th>Thành tiền</th><th>Xóa</th></tr>
                    </thead>
                    <tbody>
                        <?php $grandTotal = 0; foreach ($cartItems as $ci): 
                            $sub = $ci['price'] * $ci['quantity'];
                            $grandTotal += $sub;
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?= htmlspecialchars(getProductImage($ci['images'], $ci['product_id'])) ?>" style="width:45px;height:45px;object-fit:cover;" class="rounded me-2">
                                    <strong><?= htmlspecialchars($ci['name']) ?></strong>
                                </div>
                            </td>
                            <td class="text-center"><span class="badge bg-secondary"><?= (int)$ci['size'] ?></span></td>
                            <td><?= formatMoney($ci['price']) ?></td>
                            <td>
                                <form method="POST" class="d-flex">
                                    <input type="hidden" name="item_id" value="<?= $ci['id'] ?>">
                                    <input type="number" name="quantity" value="<?= (int)$ci['quantity'] ?>" min="1" max="20" class="form-control form-control-sm me-1">
                                    <button type="submit" name="update_cart_qty" class="btn btn-sm btn-outline-primary">Lưu</button>
                                </form>
                            </td>
                            <td class="text-danger fw-bold"><?= formatMoney($sub) ?></td>
                            <td class="text-center"><a href="cart.php?del=<?= $ci['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa sản phẩm này khỏi giỏ?')">×</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cartItems)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Giỏ hàng đang trống. <a href="index.php">Mua sắm ngay!</a></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <span class="text-muted small">Mẹo: Bạn có thể đổi số lượng rồi bấm "Lưu".</span>
                <h5 class="mb-0">Tổng tạm tính: <span class="text-danger fw-bold" id="rawTotal" data-total="<?= $grandTotal ?>"><?= formatMoney($grandTotal) ?></span></h5>
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI: THANH TOÁN COD / QR CODE -->
    <div class="col-lg-5">
        <div class="card shadow-sm p-3 mb-4 border-dark">
            <h5 class="fw-bold mb-3">💳 Thanh toán & Đặt hàng</h5>
            <form method="POST" id="checkoutForm">
                <div class="mb-2">
                    <label class="form-label small fw-bold">Họ tên người nhận</label>
                    <input type="text" name="receiver_name" class="form-control form-control-sm" value="<?= htmlspecialchars($_SESSION['user']['full_name'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Số điện thoại</label>
                    <input type="text" name="receiver_phone" class="form-control form-control-sm" value="<?= htmlspecialchars($_SESSION['user']['phone'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Địa chỉ giao hàng</label>
                    <input type="text" name="receiver_address" class="form-control form-control-sm" value="<?= htmlspecialchars($_SESSION['user']['address'] ?? '') ?>" required>
                </div>

                <!-- Chọn mã Coupon -->
                <div class="mb-2">
                    <label class="form-label small fw-bold">Mã giảm giá (Coupon)</label>
                    <select name="coupon_code" id="couponSelect" class="form-select form-select-sm" onchange="updateFinalAmount()">
                        <option value="" data-type="1" data-val="0" data-max="0">-- Không dùng mã giảm giá --</option>
                        <?php foreach ($activePromos as $pr): ?>
                            <option value="<?= htmlspecialchars($pr['coupon_code']) ?>" 
                                    data-type="<?= (int)$pr['discount_type'] ?>" 
                                    data-val="<?= (int)$pr['discount_value'] ?>" 
                                    data-max="<?= (int)$pr['maximum_discount_value'] ?>">
                                <?= htmlspecialchars($pr['coupon_code']) ?> - <?= htmlspecialchars($pr['name']) ?> 
                                (Giảm <?= $pr['discount_type'] == 1 ? $pr['discount_value'].'%' : formatMoney($pr['discount_value']) ?>, tối đa <?= formatMoney($pr['maximum_discount_value']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Ghi chú giao hàng</label>
                    <input type="text" name="note" class="form-control form-control-sm" placeholder="Giao giờ hành chính, gọi trước khi giao...">
                </div>

                <!-- PHƯƠNG THỨC THANH TOÁN: COD HOẶC QR CODE -->
                <div class="mb-3 p-2 bg-white border rounded">
                    <label class="form-label small fw-bold d-block mb-2">Chọn phương thức thanh toán:</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment_method" id="payCOD" value="COD" checked onchange="toggleQR()">
                        <label class="form-check-label fw-semibold" for="payCOD">
                            🚚 Thanh toán khi nhận hàng (Ship COD)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" id="payQR" value="QR" onchange="toggleQR()">
                        <label class="form-check-label fw-semibold text-primary" for="payQR">
                            📱 Chuyển khoản Ngân hàng qua Mã QR (VietQR)
                        </label>
                    </div>

                    <!-- Khung hiển thị mã QR tự động tính tiền -->
                    <div id="qrBox" class="text-center mt-3 p-3 bg-light border rounded d-none">
                        <p class="small fw-bold text-danger mb-1">Quét mã VietQR bên dưới để thanh toán:</p>
                        <img id="vietqrImg" src="https://img.vietqr.io/image/MB-0337059295-compact2.png?accountName=CUA%20HANG%20GIAY%20H2P%20SNEAKER" alt="Mã QR Thanh Toán" class="img-fluid rounded shadow-sm my-2" style="max-width: 220px;">
                        <div class="small text-start bg-white p-2 rounded border mt-1">
                            <div>🏦 <b>Ngân hàng:</b> MB Bank (Ngân hàng Quân Đội)</div>
                            <div>💳 <b>Số TK:</b> <code>0337059295</code></div>
                            <div>👤 <b>Chủ TK:</b> CUA HANG GIAY H2P SNEAKER</div>
                            <div>💰 <b>Số tiền cần chuyển:</b> <span id="qrAmountText" class="text-danger fw-bold"></span></div>
                            <div>📝 <b>Nội dung CK:</b> <code id="qrContentText">H2P SNEAKER KH<?= $_SESSION['user']['id'] ?></code></div>
                        </div>
                        <small class="text-muted d-block mt-2">Sau khi chuyển khoản thành công, hãy bấm nút "Hoàn tất Đặt hàng" bên dưới.</small>
                    </div>
                </div>

                <div class="alert alert-info py-2 d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold">Tổng thanh toán:</span>
                    <span class="fs-5 fw-bold text-danger" id="finalPayDisplay"><?= formatMoney($grandTotal) ?></span>
                </div>

                <button type="submit" name="checkout" class="btn btn-dark w-100 fw-bold py-2" <?= empty($cartItems) ? 'disabled' : '' ?>>
                    ✅ HOÀN TẤT ĐẶT HÀNG
                </button>
            </form>
        </div>
    </div>
</div>

<!-- QUẢN LÝ ĐƠN HÀNG CỦA USER -->
<div class="card shadow-sm p-3 mt-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">📦 Quản lý đơn hàng của tôi</h5>
        <div class="btn-group btn-group-sm mt-2 mt-sm-0">
            <a href="cart.php?status=0" class="btn <?= $statusFilter===0?'btn-dark':'btn-outline-dark' ?>">Tất cả</a>
            <a href="cart.php?status=1" class="btn <?= $statusFilter===1?'btn-dark':'btn-outline-dark' ?>">Chờ lấy hàng</a>
            <a href="cart.php?status=2" class="btn <?= $statusFilter===2?'btn-dark':'btn-outline-dark' ?>">Đang giao</a>
            <a href="cart.php?status=3" class="btn <?= $statusFilter===3?'btn-dark':'btn-outline-dark' ?>">Đã giao</a>
            <a href="cart.php?status=4" class="btn <?= $statusFilter===4?'btn-dark':'btn-outline-dark' ?>">Trả hàng</a>
            <a href="cart.php?status=5" class="btn <?= $statusFilter===5?'btn-dark':'btn-outline-dark' ?>">Đã hủy</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Mã ĐH</th><th>Sản phẩm</th><th>Size / SL</th><th>Tổng thanh toán</th>
                    <th>Phương thức & Ghi chú</th><th>Trạng thái</th><th>Ngày đặt</th><th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($myOrders)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">Không có đơn hàng nào trong mục này.</td></tr>
                <?php endif; ?>
                <?php foreach ($myOrders as $o): ?>
                <tr>
                    <td class="fw-bold">#<?= $o['id'] ?></td>
                    <td><?= htmlspecialchars($o['product_name'] ?? $o['product_id']) ?></td>
                    <td>Size <b><?= $o['size'] ?></b> (x<?= $o['quantity'] ?>)</td>
                    <td class="text-danger fw-bold">
                        <?= formatMoney($o['total_price']) ?>
                        <?php if (!empty($o['coupon_code'])): ?>
                            <br><span class="badge bg-success">Mã: <?= htmlspecialchars($o['coupon_code']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= htmlspecialchars($o['note'] ?? 'COD') ?></td>
                    <td>
                        <?php
                        $badges = [
                            1 => '<span class="badge bg-warning text-dark">Chờ lấy hàng</span>',
                            2 => '<span class="badge bg-primary">Đang giao hàng</span>',
                            3 => '<span class="badge bg-success">Đã giao thành công</span>',
                            4 => '<span class="badge bg-secondary">Đã trả hàng</span>',
                            5 => '<span class="badge bg-danger">Đã hủy</span>'
                        ];
                        echo $badges[$o['status']] ?? '<span class="badge bg-info">Mới</span>';
                        ?>
                    </td>
                    <td class="small"><?= substr($o['created_at'], 0, 16) ?></td>
                    <td>
                        <?php if ((int)$o['status'] === 1): ?>
                            <a href="cart.php?cancel_order=<?= $o['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn có chắc muốn hủy đơn hàng #<?= $o['id'] ?> không?')">Hủy đơn</a>
                        <?php else: ?>
                            <span class="text-muted small">Không thể hủy</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function formatVND(num) {
    return new Intl.NumberFormat('vi-VN').format(num) + ' ₫';
}

function updateFinalAmount() {
    let rawEl = document.getElementById('rawTotal');
    let rawTotal = rawEl ? (parseInt(rawEl.getAttribute('data-total')) || 0) : 0;
    let sel = document.getElementById('couponSelect');
    let opt = sel.options[sel.selectedIndex];
    let type = parseInt(opt.getAttribute('data-type')) || 1;
    let val = parseInt(opt.getAttribute('data-val')) || 0;
    let max = parseInt(opt.getAttribute('data-max')) || 0;

    let discount = 0;
    if (val > 0) {
        if (type === 1) {
            discount = (rawTotal * val) / 100;
            if (max > 0 && discount > max) discount = max;
        } else {
            discount = val;
        }
    }
    let finalAmount = Math.max(0, rawTotal - discount);
    document.getElementById('finalPayDisplay').innerText = formatVND(finalAmount);
    document.getElementById('qrAmountText').innerText = formatVND(finalAmount);

    // Tự động tạo mã VietQR chuẩn cho số TK 0337059295 và nội dung H2P SNEAKER
    let content = encodeURIComponent("H2P SNEAKER KH<?= $_SESSION['user']['id'] ?>");
    let accName = encodeURIComponent("CUA HANG GIAY H2P SNEAKER");
    let qrUrl = "https://img.vietqr.io/image/MB-0337059295-compact2.png?amount=" + finalAmount + "&addInfo=" + content + "&accountName=" + accName;
    document.getElementById('vietqrImg').src = qrUrl;
}

function toggleQR() {
    let isQR = document.getElementById('payQR').checked;
    let qrBox = document.getElementById('qrBox');
    if (isQR) {
        qrBox.classList.remove('d-none');
        updateFinalAmount();
    } else {
        qrBox.classList.add('d-none');
    }
}

updateFinalAmount();
</script>

<?php require_once 'footer.php'; ?>