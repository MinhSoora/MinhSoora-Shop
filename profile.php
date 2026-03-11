<?php
require_once __DIR__ . '/includes/config.php';
Auth::requireLogin();
$pageTitle = 'Tài khoản';
$user = Auth::user();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_password'])) {
        $r = Auth::changePassword(Auth::id(), $_POST['old_pw']??'', $_POST['new_pw']??'');
        if (!empty($r['success'])) $success = 'Đổi mật khẩu thành công!';
        else $error = $r['error'];
    }
    if (isset($_POST['update_profile'])) {
        $email = strtolower(trim($_POST['email']??''));
        $existing = DB::find('users','email',$email);
        if ($existing && $existing['id'] !== Auth::id()) $error = 'Email đã được dùng bởi tài khoản khác';
        else {
            DB::update('users','id',Auth::id(),['email'=>$email]);
            $success = 'Cập nhật thành công!';
            $user = Auth::user();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container py-4" style="max-width:600px">
  <h4 class="fw-800 mb-4"><i class="bi bi-gear me-2"></i>Cài đặt tài khoản</h4>
  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

  <!-- Profile info -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <h6 class="fw-700 mb-3">Thông tin cá nhân</h6>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-600">Tên đăng nhập</label>
          <input type="text" class="form-control" value="<?= h($user['username']) ?>" disabled>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">Email</label>
          <input type="email" name="email" class="form-control" value="<?= h($user['email']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">Số dư ví</label>
          <div class="form-control bg-light fw-700 text-primary"><?= fmtMoney($user['balance']??0) ?></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">Thành viên từ</label>
          <div class="form-control bg-light"><?= fmtDate($user['created_at']) ?></div>
        </div>
        <button type="submit" name="update_profile" class="btn btn-primary">Lưu thay đổi</button>
      </form>
    </div>
  </div>

  <!-- Change password -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h6 class="fw-700 mb-3">Đổi mật khẩu</h6>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-600">Mật khẩu hiện tại</label>
          <input type="password" name="old_pw" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600">Mật khẩu mới</label>
          <input type="password" name="new_pw" class="form-control" minlength="6" required>
        </div>
        <button type="submit" name="change_password" class="btn btn-outline-primary">Đổi mật khẩu</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
