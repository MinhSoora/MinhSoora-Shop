<?php
/**
 * Header layout template
 * Usage: include with $pageTitle set
 */
$settings = getSettings();
$user     = Auth::user();
$shopName = $settings['shop_name'] ?? APP_NAME;
$pageTitle = ($pageTitle ?? 'Trang chủ') . ' — ' . $shopName;

$categories = DB::read('categories', []);
usort($categories, fn($a,$b) => ($a['order']??99) <=> ($b['order']??99));

// ── Maintenance mode block ────────────────────────────────────────────────
if (!empty($settings['maintenance']) && !Auth::isAdmin()) {
    $allowed = ['login.php','logout.php','track-order.php'];
    if (!in_array(basename($_SERVER['PHP_SELF']??''), $allowed)) {
        http_response_code(503);
        ?><!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>🔧 Bảo trì — <?= h($shopName) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
        body{background:linear-gradient(135deg,#0f172a,#1e293b);min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;}
        .mc{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:24px;padding:52px 40px;text-align:center;max-width:500px;backdrop-filter:blur(16px);}
        .mi{font-size:5rem;margin-bottom:1.5rem;display:block;animation:spin 5s linear infinite;}
        @keyframes spin{to{transform:rotate(360deg)}}
        h1{font-size:2rem;font-weight:900;color:#fff;margin-bottom:.75rem;}
        p{color:#94a3b8;font-size:.95rem;line-height:1.75;}
        .tbtn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#2563eb,#0ea5e9);color:#fff!important;text-decoration:none;padding:12px 28px;border-radius:12px;font-weight:700;margin-top:1.5rem;transition:.2s;}
        .tbtn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(37,99,235,.4);}
        </style></head><body>
        <div class="mc">
          <span class="mi">🔧</span>
          <h1>Đang bảo trì</h1>
          <p><?= h($settings['banner_text'] ?: 'Hệ thống đang được nâng cấp để phục vụ bạn tốt hơn. Vui lòng quay lại sau ít phút.') ?></p>
          <a href="/track-order.php" class="tbtn">🔍 Tra cứu đơn hàng</a>
          <?php if (!Auth::isLoggedIn()): ?>
          <div class="mt-3"><a href="/login.php" style="color:#64748b;font-size:13px">← Admin đăng nhập</a></div>
          <?php endif; ?>
        </div></body></html><?php
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<meta name="description" content="<?= h($settings['shop_desc'] ?? '') ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root {
  --primary: #2563eb;
  --primary-d: #1d4ed8;
  --accent: #0ea5e9;
  --success: #16a34a;
  --border: #e5e7eb;
  --muted: #6b7280;
  --bg-soft: #f9fafb;
}
body { background: #fff; color: #111827; font-family: 'Segoe UI', system-ui, sans-serif; }

/* Navbar */
.navbar { border-bottom: 1px solid var(--border); background: #fff !important; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.navbar-brand { font-weight: 800; font-size: 1.25rem; color: var(--primary) !important; letter-spacing: -.5px; }
.nav-link { color: #374151 !important; font-weight: 500; padding: .5rem .75rem !important; border-radius: 6px; transition: background .15s; }
.nav-link:hover, .nav-link.active { background: #eff6ff; color: var(--primary) !important; }
.navbar .btn-primary { background: var(--primary); border-color: var(--primary); }
.navbar .btn-primary:hover { background: var(--primary-d); }

/* Category nav */
.cat-nav { background: var(--bg-soft); border-bottom: 1px solid var(--border); padding: 6px 0; }
.cat-nav a { display: inline-flex; align-items: center; gap: 5px; padding: 5px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; color: #374151; text-decoration: none; white-space: nowrap; transition: all .15s; }
.cat-nav a:hover, .cat-nav a.active { background: var(--primary); color: #fff; }

/* Product cards */
.product-card { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; transition: box-shadow .2s, transform .2s; height: 100%; }
.product-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.08); transform: translateY(-2px); }
.product-card .card-img-top { height: 180px; object-fit: cover; background: linear-gradient(135deg, #eff6ff, #dbeafe); }
.product-card .price { font-size: 1.4rem; font-weight: 800; color: var(--primary); }
.product-card .price-type { font-size: 12px; color: var(--muted); }
.product-card .badge-featured { position: absolute; top: 10px; right: 10px; background: #f59e0b; color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 8px; }

/* Sidebar */
.sidebar-menu .nav-link { color: #374151; font-size: 14px; padding: 8px 12px; border-radius: 8px; margin-bottom: 2px; }
.sidebar-menu .nav-link:hover, .sidebar-menu .nav-link.active { background: #eff6ff; color: var(--primary); }
.sidebar-menu .nav-link i { width: 20px; }

/* Stats cards */
.stat-card { border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
.stat-card .stat-val { font-size: 2rem; font-weight: 800; line-height: 1; }
.stat-card .stat-label { font-size: 13px; color: var(--muted); margin-top: 4px; }

/* Tables */
.table th { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); background: var(--bg-soft); }

/* Order status */
.order-row:hover { background: #fafafa; }

/* Footer */
footer { background: #f3f4f6; border-top: 1px solid var(--border); margin-top: 60px; padding: 32px 0 16px; }
footer .footer-brand { font-weight: 800; color: var(--primary); font-size: 1.1rem; }

/* Hero */
.hero { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 50%, #e0f2fe 100%); padding: 60px 0 40px; }
.hero h1 { font-weight: 800; font-size: 2.5rem; }

/* Breadcrumb */
.breadcrumb-bar { background: var(--bg-soft); border-bottom: 1px solid var(--border); padding: 10px 0; font-size: 13px; }

/* Responsive adjustments */
@media (max-width: 768px) {
  .hero h1 { font-size: 1.75rem; }
  .stat-card .stat-val { font-size: 1.5rem; }
}

/* Loading overlay */
#loading { display: none; position: fixed; inset: 0; background: rgba(255,255,255,.7); z-index: 9999; align-items: center; justify-content: center; }

/* Balance chip */
.balance-chip { background: #eff6ff; color: var(--primary); border: 1px solid #bfdbfe; border-radius: 20px; padding: 4px 12px; font-size: 13px; font-weight: 600; }

/* Notification dot */
.notif-dot { position: absolute; top: 2px; right: 2px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 2px solid #fff; }
</style>
</head>
<body>

<div id="loading"><div class="spinner-border text-primary"></div></div>

<!-- ═══ NAVBAR ═══════════════════════════════════════════════════════════════ -->
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand" href="/">
      <?php if (!empty($settings['logo_url'])): ?>
        <img src="<?= h($settings['logo_url']) ?>" height="30" alt="logo" class="me-2">
      <?php else: ?>
        <i class="bi bi-lightning-charge-fill"></i>
      <?php endif; ?>
      <?= h($shopName) ?>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-house me-1"></i>Trang chủ</a></li>
        <li class="nav-item"><a class="nav-link" href="/products.php"><i class="bi bi-grid me-1"></i>Sản phẩm</a></li>
        <li class="nav-item"><a class="nav-link" href="/track-order.php"><i class="bi bi-search me-1"></i>Tra cứu đơn</a></li>
        <?php foreach (array_slice($categories, 0, 4) as $cat): ?>
        <li class="nav-item d-none d-lg-block"><a class="nav-link" href="/products.php?cat=<?= h($cat['id']) ?>"><?= h($cat['icon']??'') ?> <?= h($cat['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <?php if ($user): ?>
          <span class="balance-chip d-none d-md-inline"><i class="bi bi-wallet2 me-1"></i><?= fmtMoney($user['balance'] ?? 0) ?></span>
          <div class="dropdown">
            <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle me-1"></i><?= h($user['username']) ?>
              <?php if (Auth::isAdmin()): ?><span class="badge bg-danger ms-1 py-0" style="font-size:10px">Admin</span><?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width:200px">
              <li><div class="dropdown-item-text small text-muted"><?= h($user['email']) ?></div></li>
              <li><hr class="dropdown-divider my-1"></li>
              <li><a class="dropdown-item" href="/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
              <li><a class="dropdown-item" href="/orders.php"><i class="bi bi-bag me-2"></i>Đơn hàng</a></li>
              <li><a class="dropdown-item" href="/tickets.php"><i class="bi bi-headset me-2"></i>Hỗ trợ</a></li>
              <li><a class="dropdown-item" href="/naptien.php"><i class="bi bi-plus-circle me-2"></i>Nạp tiền</a></li>
              <li><a class="dropdown-item" href="/profile.php"><i class="bi bi-gear me-2"></i>Tài khoản</a></li>
              <?php if (Auth::isAdmin()): ?>
              <li><hr class="dropdown-divider my-1"></li>
              <li><a class="dropdown-item text-danger" href="/admin/"><i class="bi bi-shield-lock me-2"></i>Quản trị</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider my-1"></li>
              <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a href="/login.php" class="btn btn-outline-primary btn-sm">Đăng nhập</a>
          <a href="/register.php" class="btn btn-primary btn-sm">Đăng ký</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- ═══ CATEGORY BAR ═══════════════════════════════════════════════════════ -->
<div class="cat-nav">
  <div class="container d-flex gap-1 overflow-auto" style="scrollbar-width:none">
    <a href="/products.php" class="<?= !isset($_GET['cat']) && basename($_SERVER['PHP_SELF'])==='products.php' ? 'active' : '' ?>">
      <i class="bi bi-grid-3x3-gap"></i> Tất cả
    </a>
    <?php foreach ($categories as $cat): ?>
    <a href="/products.php?cat=<?= h($cat['id']) ?>"
       class="<?= ($_GET['cat'] ?? '') === $cat['id'] ? 'active' : '' ?>">
      <?= h($cat['icon'] ?? '') ?> <?= h($cat['name']) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($settings['banner_text'])): ?>
<div class="alert alert-info alert-dismissible mb-0 rounded-0 border-0 border-bottom text-center py-2" style="font-size:13px">
  <?= h($settings['banner_text']) ?>
  <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Flash messages -->
<div class="container mt-3" id="flash-container">
  <?= renderFlash() ?>
</div>
