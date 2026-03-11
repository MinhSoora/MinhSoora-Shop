<?php
/**
 * magic-login.php — Đăng nhập 1 click qua magic link
 * Admin tạo link từ /admin/users.php → gửi cho khách
 * Khách bấm vào → hiện popup xác nhận → đăng nhập → đến Dashboard
 */
require_once __DIR__ . '/includes/config.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$user  = null;

if (!$token) {
    $error = 'Link không hợp lệ hoặc đã hết hạn.';
} else {
    $links = DB::read('magic_links', []);
    $link  = null;
    foreach ($links as $l) {
        if ($l['token'] === $token) { $link = $l; break; }
    }

    if (!$link) {
        $error = 'Link không tồn tại hoặc đã bị xóa.';
    } elseif ($link['expires'] < time()) {
        $error = 'Link đã hết hạn. Vui lòng liên hệ admin để lấy link mới.';
    } else {
        $user = DB::find('users', 'id', $link['uid']);
        if (!$user || empty($user['active'])) {
            $error = 'Tài khoản không tồn tại hoặc đã bị vô hiệu hóa.';
            $user  = null;
        }
    }
}

// Xử lý xác nhận đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_login']) && $user) {
    session_regenerate_id(true);
    $_SESSION['uid']  = $user['id'];
    $_SESSION['role'] = $user['role'];
    DB::update('users', 'id', $user['id'], ['last_login' => date('c')]);
    // Redirect về dashboard
    redirect('/dashboard.php');
}

$settings = getSettings();
$shopName = $settings['shop_name'] ?? APP_NAME;
$pageTitle = 'Truy cập hệ thống';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?> — <?= h($shopName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body {
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Segoe UI', system-ui, sans-serif;
}
.magic-card {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 25px 60px rgba(0,0,0,.4);
  padding: 40px;
  max-width: 440px;
  width: 100%;
  margin: 20px;
  position: relative;
  overflow: hidden;
}
.magic-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, #2563eb, #7c3aed, #2563eb);
}
.brand-icon {
  width: 64px; height: 64px;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; color: #fff;
  margin: 0 auto 16px;
  box-shadow: 0 8px 20px rgba(37,99,235,.35);
}
.user-avatar {
  width: 56px; height: 56px;
  background: linear-gradient(135deg, #059669, #0891b2);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; color: #fff;
  margin: 0 auto 12px;
  font-weight: 800;
}
.info-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px;
  background: #f8fafc;
  border-radius: 10px;
  margin-bottom: 8px;
  font-size: 13px;
}
.info-row .label { color: #64748b; min-width: 80px; }
.info-row .value { font-weight: 600; color: #1e293b; }
.btn-login {
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  border: none;
  color: #fff;
  padding: 14px;
  font-size: 15px;
  font-weight: 700;
  border-radius: 12px;
  width: 100%;
  cursor: pointer;
  transition: all .2s;
  box-shadow: 0 4px 15px rgba(37,99,235,.4);
}
.btn-login:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,.5); }
.security-note {
  background: #fefce8;
  border: 1px solid #fde047;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 12px;
  color: #713f12;
  margin-top: 16px;
}
</style>
</head>
<body>

<div class="magic-card">
  <div class="text-center mb-4">
    <div class="brand-icon">🔐</div>
    <h4 class="fw-800 mb-1"><?= h($shopName) ?></h4>
    <p class="text-muted small mb-0">Truy cập hệ thống quản lý server</p>
  </div>

  <?php if ($error): ?>
    <!-- Lỗi -->
    <div class="text-center py-3">
      <div style="font-size:48px">⚠️</div>
      <h5 class="fw-700 text-danger mt-2">Link không hợp lệ</h5>
      <p class="text-muted small"><?= h($error) ?></p>
      <a href="/login.php" class="btn btn-outline-primary btn-sm mt-2">
        <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập thông thường
      </a>
    </div>

  <?php elseif ($user): ?>
    <!-- Xác nhận đăng nhập -->
    <div class="text-center mb-3">
      <div class="user-avatar"><?= strtoupper(substr($user['username'],0,1)) ?></div>
      <h5 class="fw-700 mb-0"><?= h($user['username']) ?></h5>
      <p class="text-muted small"><?= h($user['email']) ?></p>
    </div>

    <div class="mb-3">
      <div class="info-row">
        <i class="bi bi-person-circle text-primary"></i>
        <span class="label">Tài khoản</span>
        <span class="value"><?= h($user['username']) ?></span>
      </div>
      <?php if (!empty($user['server_ids'])): ?>
      <div class="info-row">
        <i class="bi bi-server text-success"></i>
        <span class="label">Servers</span>
        <span class="value"><?= count($user['server_ids']) ?> server được gán</span>
      </div>
      <?php endif; ?>
      <div class="info-row">
        <i class="bi bi-clock text-warning"></i>
        <span class="label">Link hết hạn</span>
        <span class="value"><?php
          $link2 = null;
          foreach (DB::read('magic_links',[]) as $l) if ($l['token']===$token) { $link2=$l; break; }
          echo $link2 ? date('d/m/Y H:i', $link2['expires']) : '—';
        ?></span>
      </div>
    </div>

    <form method="POST">
      <button type="submit" name="confirm_login" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-2"></i>Xác nhận đăng nhập
      </button>
    </form>

    <a href="/login.php" class="btn btn-link btn-sm text-muted d-block text-center mt-3">
      Đăng nhập bằng tài khoản khác
    </a>

    <div class="security-note">
      <i class="bi bi-shield-check me-1"></i>
      <strong>Bảo mật:</strong> Link này chỉ dùng 1 lần và hết hạn sau ngày
      <?= $link2 ? date('d/m/Y',$link2['expires']) : '' ?>.
      Không chia sẻ link này với người khác.
    </div>

  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
