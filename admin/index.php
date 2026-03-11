<?php
$adminPageTitle = 'Dashboard';
require_once __DIR__ . '/header.php';

$users    = DB::read('users', []);
$orders   = DB::read('orders', []);
$tickets  = DB::read('tickets', []);
$txs      = DB::read('transactions', []);

$revenue  = array_sum(array_column(array_filter($orders, fn($o) => in_array($o['status'],['paid','active','processing'])), 'total'));
$pending  = count(array_filter($orders, fn($o) => $o['status']==='pending'));
$openTkts = count(array_filter($tickets, fn($t) => $t['status']==='open'));
$recentOrders = array_slice(array_reverse($orders), 0, 10);
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="val text-primary"><?= count($users) ?></div>
      <div class="label"><i class="bi bi-people me-1"></i>Người dùng</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="val text-success"><?= count($orders) ?></div>
      <div class="label"><i class="bi bi-bag me-1"></i>Tổng đơn</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="val text-warning"><?= $pending ?></div>
      <div class="label"><i class="bi bi-clock me-1"></i>Chờ thanh toán</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="val text-danger"><?= $openTkts ?></div>
      <div class="label"><i class="bi bi-headset me-1"></i>Ticket mở</div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <div class="fw-700 text-muted small text-uppercase mb-1">Tổng doanh thu (đơn đã TT)</div>
    <div style="font-size:2.2rem;font-weight:900;color:var(--primary)"><?= fmtMoney($revenue) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-header bg-white fw-700 d-flex justify-content-between">
    <span>Đơn hàng gần đây</span>
    <a href="/admin/orders.php" class="btn btn-sm btn-outline-primary">Tất cả</a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Mã đơn</th><th>Khách</th><th>Dịch vụ</th><th>Tổng</th><th>Trạng thái</th><th>Ngày</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recentOrders as $o): ?>
      <?php $u = DB::find('users','id',$o['user_id']); ?>
      <tr>
        <td><code style="font-size:11px"><?= h($o['id']) ?></code></td>
        <td class="small"><?= h($u['username']??'?') ?></td>
        <td class="small"><?= h($o['product_name']) ?></td>
        <td class="fw-600"><?= fmtMoney($o['total']) ?></td>
        <td><?= orderStatusLabel($o['status']) ?></td>
        <td class="small text-muted"><?= fmtDate($o['created_at']) ?></td>
        <td><a href="/admin/order_edit.php?id=<?= h($o['id']) ?>" class="btn btn-xs btn-outline-secondary" style="font-size:11px;padding:2px 8px">Sửa</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
