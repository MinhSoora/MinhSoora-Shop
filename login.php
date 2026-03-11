<?php
// login.php
require_once __DIR__ . '/includes/config.php';
if (Auth::isLoggedIn()) redirect('/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = Auth::login($_POST['username']??'', $_POST['password']??'');
    if (!empty($r['success'])) redirect($_GET['redirect'] ?? '/dashboard.php');
    $error = $r['error'] ?? 'Lỗi đăng nhập';
}
$pageTitle = 'Đăng nhập';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:440px">
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <h4 class="fw-800 mb-1 text-center">Đăng nhập</h4>
      <p class="text-muted text-center small mb-4">Chào mừng bạn trở lại!</p>
      <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= h($error) ?></div><?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-600">Tên đăng nhập / Email</label>
          <input type="text" name="username" class="form-control" value="<?= h($_POST['username']??'') ?>" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">Mật khẩu</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 btn-lg fw-700">Đăng nhập</button>
      </form>
      <hr><div class="text-center small">Chưa có tài khoản? <a href="/register.php" class="fw-600">Đăng ký ngay</a></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
