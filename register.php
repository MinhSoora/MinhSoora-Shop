<?php
// register.php
require_once __DIR__ . '/includes/config.php';
if (Auth::isLoggedIn()) redirect('/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = Auth::register($_POST['username']??'', $_POST['email']??'', $_POST['password']??'');
    if (!empty($r['success'])) { flash('success','Đăng ký thành công! Chào mừng bạn.'); redirect('/dashboard.php'); }
    $error = $r['error'] ?? 'Lỗi đăng ký';
}
$pageTitle = 'Đăng ký tài khoản';
include __DIR__ . '/includes/header.php';
?>
<div class="container py-5" style="max-width:460px">
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <h4 class="fw-800 mb-1 text-center">Tạo tài khoản</h4>
      <p class="text-muted text-center small mb-4">Miễn phí — không cần thẻ tín dụng</p>
      <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= h($error) ?></div><?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-600">Tên đăng nhập *</label>
          <input type="text" name="username" class="form-control" value="<?= h($_POST['username']??'') ?>" required autofocus pattern="[a-zA-Z0-9_]{3,20}">
          <div class="form-text">3–20 ký tự, chỉ dùng chữ, số và _</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">Email *</label>
          <input type="email" name="email" class="form-control" value="<?= h($_POST['email']??'') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">Mật khẩu *</label>
          <input type="password" name="password" class="form-control" minlength="6" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 btn-lg fw-700">Đăng ký</button>
      </form>
      <hr><div class="text-center small">Đã có tài khoản? <a href="/login.php" class="fw-600">Đăng nhập</a></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
