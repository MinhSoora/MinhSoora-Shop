<?php
// admin/transactions.php
$adminPageTitle = 'Giao dịch';
require_once __DIR__ . '/header.php';
$txs = DB::read('transactions',[]);
usort($txs, fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
$total = array_sum(array_column(array_filter($txs,fn($t)=>$t['status']==='success'), 'amount'));
?>
<div class="card mb-3 border-0 bg-primary text-white" style="border-radius:10px">
  <div class="card-body py-3">
    <div class="small opacity-75">Tổng giao dịch thành công</div>
    <div style="font-size:2rem;font-weight:900"><?= fmtMoney($total) ?></div>
  </div>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Mã GD</th><th>Khách</th><th>Loại</th><th>Số tiền</th><th>TT</th><th>Tham chiếu</th><th>Thời gian</th></tr></thead>
      <tbody>
      <?php foreach ($txs as $t): ?>
      <?php $u = DB::find('users','id',$t['user_id']); ?>
      <tr>
        <td><code style="font-size:11px"><?= h($t['id']) ?></code></td>
        <td class="small"><?= h($u['username']??'?') ?></td>
        <td>
          <?php if ($t['type']==='topup'): ?><span class="badge bg-success">Nạp tiền</span>
          <?php elseif ($t['type']==='order_payment'): ?><span class="badge bg-primary">Thanh toán đơn</span>
          <?php else: ?><span class="badge bg-secondary"><?= h($t['type']) ?></span><?php endif; ?>
        </td>
        <td class="fw-600"><?= fmtMoney($t['amount']) ?></td>
        <td><?= $t['status']==='success'?'<span class="badge bg-success">✅</span>':'<span class="badge bg-warning text-dark">⏳</span>' ?></td>
        <td class="small text-muted"><?= h($t['ref']??'—') ?></td>
        <td class="small text-muted"><?= fmtDate($t['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$txs): ?><tr><td colspan="7" class="text-center py-4 text-muted">Chưa có giao dịch</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
