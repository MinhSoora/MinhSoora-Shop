<?php
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();
$settings = getSettings();
$shopName = $settings['shop_name'] ?? APP_NAME;
$pageTitle = ($adminPageTitle ?? 'Admin') . ' — Admin';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root { --sidebar-w:230px; --primary:#2563eb; }
body { background:#f1f5f9; font-size:14px; }
.admin-layout { display:flex; min-height:100vh; }
.admin-sidebar {
  width:var(--sidebar-w); flex-shrink:0; position:fixed; top:0; left:0; bottom:0;
  background:#1e293b; color:#cbd5e1; overflow-y:auto; z-index:100;
}
.admin-sidebar .brand { padding:18px 16px; border-bottom:1px solid #334155; display:flex; align-items:center; gap:8px; }
.admin-sidebar .brand-name { font-weight:800; color:#fff; font-size:15px; }
.admin-sidebar .brand-badge { background:#dc2626; color:#fff; font-size:10px; padding:1px 6px; border-radius:6px; }
.admin-sidebar .nav { padding:10px 8px; }
.admin-sidebar .nav-section-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b; padding:10px 10px 4px; }
.admin-sidebar .nav-link { color:#94a3b8; font-size:13px; padding:8px 10px; border-radius:6px; margin-bottom:1px; display:flex; align-items:center; gap:8px; transition:.15s; }
.admin-sidebar .nav-link:hover { background:#334155; color:#e2e8f0; }
.admin-sidebar .nav-link.active { background:#2563eb; color:#fff; }
.admin-sidebar .nav-link i { width:16px; text-align:center; }
.admin-main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
.admin-topbar { background:#fff; border-bottom:1px solid #e2e8f0; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
.admin-topbar .page-title { font-weight:800; font-size:16px; }
.admin-content { padding:24px; flex:1; }
.stat-card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,.06); border:1px solid #e2e8f0; }
.stat-card .val { font-size:2rem; font-weight:800; line-height:1; }
.stat-card .label { font-size:12px; color:#64748b; margin-top:4px; }
.card { border-radius:10px; border:1px solid #e2e8f0 !important; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.table th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; background:#f8fafc; }
.badge-admin { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; font-size:10px; }
@media(max-width:768px) {
  .admin-sidebar { transform:translateX(-100%); transition:.3s; }
  .admin-sidebar.show { transform:translateX(0); }
  .admin-main { margin-left:0; }
}
</style>
</head>
<body>
<div class="admin-layout">
<!-- Sidebar -->
<aside class="admin-sidebar">
  <div class="brand">
    <i class="bi bi-shield-lock text-primary"></i>
    <div>
      <div class="brand-name"><?= h($shopName) ?></div>
      <span class="brand-badge">ADMIN</span>
    </div>
  </div>
  <nav class="nav flex-column">
    <div class="nav-section-label">Tổng quan</div>
    <a href="/admin/" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='index.php'?'active':'' ?>"><i class="bi bi-speedometer2"></i>Dashboard</a>

    <div class="nav-section-label">Quản lý</div>
    <a href="/admin/orders.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='orders.php'?'active':'' ?>"><i class="bi bi-bag"></i>Đơn hàng</a>
    <a href="/admin/users.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='users.php'?'active':'' ?>"><i class="bi bi-people"></i>Người dùng</a>
    <a href="/admin/users.php#magic" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='magic_links.php'?'active':'' ?>" style="padding-left:28px;font-size:12px"><i class="bi bi-magic"></i>Magic Links</a>
    <a href="/admin/tickets.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='tickets.php'?'active':'' ?>"><i class="bi bi-headset"></i>Tickets
      <?php $open = DB::count('tickets', fn($t)=>$t['status']==='open'); if($open): ?>
      <span class="badge bg-danger ms-auto"><?= $open ?></span>
      <?php endif; ?>
    </a>
    <a href="/admin/transactions.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='transactions.php'?'active':'' ?>"><i class="bi bi-credit-card"></i>Giao dịch</a>

    <div class="nav-section-label">Nội dung</div>
    <a href="/admin/products.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='products.php'?'active':'' ?>"><i class="bi bi-grid"></i>Sản phẩm</a>
    <a href="/admin/categories.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='categories.php'?'active':'' ?>"><i class="bi bi-tag"></i>Danh mục</a>
    <a href="/admin/reviews.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='reviews.php'?'active':'' ?>"><i class="bi bi-star"></i>Đánh giá</a>

    <div class="nav-section-label">Node.js Manager</div>
    <a href="/admin/servers.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='servers.php'?'active':'' ?>"><i class="bi bi-server"></i>Quản lý Server</a>

    <div class="nav-section-label">Hệ thống</div>
    <a href="/admin/settings.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='settings.php'?'active':'' ?>"><i class="bi bi-gear"></i>Cài đặt</a>
    <a href="/admin/bridge_log.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='bridge_log.php'?'active':'' ?>"><i class="bi bi-plug"></i>Bridge Log</a>
    <a href="/admin/sepay.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])==='sepay.php'?'active':'' ?>"><i class="bi bi-bank"></i>Sepay</a>

    <hr style="border-color:#334155;margin:8px">
    <a href="/" target="_blank" class="nav-link"><i class="bi bi-box-arrow-up-right"></i>Xem website</a>
    <a href="/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i>Đăng xuất</a>
  </nav>
</aside>

<!-- Main -->
<div class="admin-main">
<div class="admin-topbar">
  <div>
    <button class="btn btn-sm btn-light d-md-none me-2" onclick="document.querySelector('.admin-sidebar').classList.toggle('show')">
      <i class="bi bi-list"></i>
    </button>
    <span class="page-title"><?= h($adminPageTitle ?? 'Admin') ?></span>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span class="small text-muted d-none d-md-inline">Admin: <?= h(Auth::user()['username']) ?></span>
    <a href="/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-house"></i></a>
  </div>
</div>
<div class="admin-content">
<?= renderFlash() ?>
