<?php
require_once 'config.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$err = '';
$success = '';

// Xử lý Đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Hỗ trợ cả mật khẩu BCrypt ($2a$12$...), password_hash PHP, hoặc mật khẩu thường '123456'
        $hash = str_replace('$2a$', '$2y$', $user['password']);
        if (password_verify($pass, $hash) || $pass === $user['password'] || $pass === '123456') {
            $_SESSION['user'] = $user;
            header("Location: index.php");
            exit;
        }
    }
    $err = "Email hoặc mật khẩu không đúng!";
}

// Xử lý Đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        $err = "Email này đã được sử dụng!";
    } else {
        $rolesJson = json_encode(["USER"]);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, address, roles, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$full_name, $email, $password, $phone, $address, $rolesJson]);
        $success = "Đăng ký thành công! Bạn có thể đăng nhập ngay.";
    }
}

require_once 'header.php';
?>

<?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm p-4 h-100">
            <h4 class="mb-3">Đăng nhập</h4>
            <div class="alert alert-secondary small">
                <b>Tài khoản có sẵn trong DB:</b><br>
                - Admin: <code>admin@gmail.com</code> (Mật khẩu: <code>123456</code>)<br>
                - Khách: <code>user@gmail.com</code> (Mật khẩu: <code>123456</code>)
            </div>
            <form method="POST">
                <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required value="admin@gmail.com"></div>
                <div class="mb-3"><label>Mật khẩu</label><input type="password" name="password" class="form-control" required value="123456"></div>
                <button type="submit" name="do_login" class="btn btn-dark w-100">Đăng nhập</button>
            </form>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card shadow-sm p-4 h-100">
            <h4 class="mb-3">Đăng ký tài khoản mới</h4>
            <form method="POST">
                <div class="mb-2"><label>Họ và tên</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="mb-2"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-2"><label>Số điện thoại</label><input type="text" name="phone" class="form-control" required></div>
                <div class="mb-2"><label>Địa chỉ</label><input type="text" name="address" class="form-control" required></div>
                <div class="mb-3"><label>Mật khẩu</label><input type="password" name="password" class="form-control" required></div>
                <button type="submit" name="do_register" class="btn btn-outline-dark w-100">Đăng ký</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>