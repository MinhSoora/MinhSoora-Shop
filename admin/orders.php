<?php
$adminPageTitle = 'Đơn hàng';
require_once __DIR__ . '/header.php';

$filter = $_GET['status'] ?? '';
$type   = $_GET['type']   ?? '';
$orders = DB::read('orders', []);
usort($orders, fn($a,$b) => strcmp($b['created_at']??'',$a['created_at']??''));
if ($filter) $orders = array_values(array_filter($orders, fn($o)=>$o['status']===$filter));
if ($type)   $orders = array_values(array_filter($orders, fn($o)=>($o['type']??'server')===$type));

$statuses = ['','pending','paid','processing','active','cancelled','expired'];
$statusNames = [''=>'Tất cả','pending'=>'⏳ Chờ TT','paid'=>'💳 Đã TT','processing'=>'⚙️ Xử lý','active'=>'✅ Hoàn thành','cancelled'=>'❌ Đã hủy','expired'=>'⏰ Hết hạn'];
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="fw-800 mb-0">📦 Đơn hàng</h5>
  <div class="d-flex gap-2 flex-wrap">
    <a href="?type=" class="btn btn-sm <?= !$type?'btn-primary':'btn-outline-secondary' ?>">Tất cả loại</a>
    <a href="?type=server" class="btn btn-sm <?= $type==='server'?'btn-primary':'btn-outline-secondary' ?>">🖥️ Server</a>
    <a href="?type=setup" class="btn btn-sm <?= $type==='setup'?'btn-primary':'btn-outline-secondary' ?>">🔧 Setup</a>
  </div>
</div>
<div class="d-flex gap-2 flex-wrap mb-3">
  <?php foreach ($statuses as $s): ?>
  <a href="?status=<?= h($s) ?>&type=<?= h($type) ?>" class="btn btn-sm <?= $filter===$s?'btn-primary':'btn-outline-secondary' ?>">
    <?= $statusNames[$s] ?>
    <span class="badge bg-light text-dark ms-1"><?= DB::count('orders', $s?fn($o)=>$o['status']===$s:null) ?></span>
  </a>
  <?php endforeach; ?>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr>
        <th>Mã đơn</th><th>Loại</th><th>Khách hàng</th><th>Dịch vụ</th><th>Tổng</th><th>Trạng thái</th><th>Ngày đặt</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
      <?php $u = $o['user_id'] ? DB::find('users','id',$o['user_id']) : null; ?>
      <tr>
        <td><code class="fw-700" style="font-size:12px;letter-spacing:.5px"><?= h($o['code']??$o['id']) ?></code></td>
        <td><span class="badge bg-light text-dark border" style="font-size:11px"><?= ($o['type']??'')==='setup'?'🔧 Setup':'🖥️ Server' ?></span></td>
        <td class="small"><?= $u?'<a href="/admin/user_edit.php?id='.h($u['id']).'">'.h($u['username']).'</a>':'<span class="text-muted">—</span>' ?></td>
        <td class="small"><?= h($o['product_name']??'—') ?></td>
        <td class="fw-700"><?= fmtMoney($o['total']??0) ?></td>
        <td><?= orderStatusLabel($o['status']??'pending') ?></td>
        <td class="small text-muted"><?= fmtDate($o['created_at']??'') ?></td>
        <td>
          <a href="/admin/order_edit.php?id=<?= h($o['id']) ?>" class="btn btn-sm btn-outline-primary">Quản lý</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?>
      <tr><td colspan="8" class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2rem;opacity:.4"></i><br>Không có đơn hàng nào
      </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
