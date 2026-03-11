<?php
require_once __DIR__ . '/includes/config.php';
Auth::requireLogin();
$pageTitle = 'Đơn hàng của tôi';
$uid = Auth::id();

$orders = DB::filter('orders', fn($o) => $o['user_id'] === $uid);
usort($orders, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));

// Stats
$stats = [
    'total'  => count($orders),
    'active' => count(array_filter($orders, fn($o) => $o['status'] === 'active')),
    'pending'=> count(array_filter($orders, fn($o) => $o['status'] === 'pending')),
    'spent'  => array_sum(array_column(array_filter($orders, fn($o) => in_array($o['status'],['paid','active'])), 'total')),
];

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb-bar">
  <div class="container">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Đơn hàng</li>
    </ol>
  </div>
</div>

<div class="container py-4">
  <h4 class="fw-800 mb-4">🛒 Đơn hàng của tôi</h4>

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-val"><?= $stats['total'] ?></div><div class="stat-label">Tổng đơn</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-val text-success"><?= $stats['active'] ?></div><div class="stat-label">Đang dùng</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-val text-warning"><?= $stats['pending'] ?></div><div class="stat-label">Chờ TT</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-val text-primary" style="font-size:1.2rem"><?= fmtMoney($stats['spent']) ?></div><div class="stat-label">Đã chi</div></div></div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <?php if (empty($orders)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-bag-x" style="font-size:3rem"></i>
        <p class="mt-2">Bạn chưa có đơn hàng nào</p>
        <a href="/products.php" class="btn btn-primary">Xem dịch vụ</a>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th>Mã đơn</th>
            <th>Dịch vụ</th>
            <th style="width:120px">Tổng tiền</th>
            <th style="width:130px">Trạng thái</th>
            <th style="width:130px">Ngày đặt</th>
            <th style="width:100px"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
          <tr class="order-row">
            <td><code style="font-size:12px"><?= h($o['id']) ?></code></td>
            <td><?= h($o['product_name']) ?></td>
            <td class="fw-700"><?= fmtMoney($o['total']) ?></td>
            <td><?= orderStatusLabel($o['status']) ?></td>
            <td class="text-muted small"><?= fmtDate($o['created_at']) ?></td>
            <td>
              <a href="/order.php?id=<?= h($o['id']) ?>" class="btn btn-sm btn-outline-primary">Chi tiết</a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
