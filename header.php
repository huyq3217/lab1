<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>H2P SNEAKER - Hệ Thống Bán Giày & Quản Trị</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .product-card img { height: 210px; object-fit: cover; }
        .navbar-brand img { height: 38px; width: 38px; object-fit: cover; border-radius: 50%; margin-right: 10px; }
        .nav-pills .nav-link.active { background-color: #212529; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
            <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=100&q=80" alt="Logo">
            H2P SNEAKER
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">🏠 Cửa hàng</a></li>
                <li class="nav-item"><a class="nav-link" href="posts.php">📰 Tin tức</a></li>
                <li class="nav-item"><a class="nav-link" href="cart.php">📦 Giỏ hàng & Đơn hàng của tôi</a></li>
                <?php if (isAdmin()): ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-warning btn-sm mt-1 fw-bold" href="admin.php">⚙ QUẢN TRỊ HỆ THỐNG (ADMIN)</a>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item"><span class="nav-link text-info">👤 <?= htmlspecialchars($_SESSION['user']['full_name']) ?></span></li>
                    <li class="nav-item"><a class="btn btn-outline-danger btn-sm ms-2" href="login.php?action=logout">Đăng xuất</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="login.php">Đăng nhập / Đăng ký</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container mb-5 flex-grow-1">