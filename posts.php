<?php
require_once 'header.php';
$posts = $pdo->query("SELECT * FROM post WHERE status = 1 ORDER BY id DESC")->fetchAll();
?>
<h3 class="mb-4">📰 Tin tức & Bài viết chia sẻ</h3>
<?php foreach ($posts as $post): ?>
    <div class="card shadow-sm mb-4 p-4">
        <h4 class="text-primary"><?= htmlspecialchars($post['title']) ?></h4>
        <small class="text-muted mb-3 d-block">Ngày đăng: <?= $post['published_at'] ?? $post['created_at'] ?></small>
        <div><?= $post['description'] ?></div>
        <?php if (!empty($post['content']) && trim(strip_tags($post['content'])) !== ''): ?>
            <div class="mt-2"><?= $post['content'] ?></div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php require_once 'footer.php'; ?>