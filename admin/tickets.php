<?php
// admin/tickets.php
$adminPageTitle = 'Tickets hỗ trợ';
require_once __DIR__ . '/header.php';
$tickets = DB::read('tickets', []);
usort($tickets, fn($a,$b) => strcmp($b['updated_at'],$a['updated_at']));
$filter = $_GET['status'] ?? '';
if ($filter) $tickets = array_values(array_filter($tickets, fn($t) => $t['status'] === $filter));
?>
<div class="d-flex gap-2 flex-wrap mb-3">
  <?php foreach ([''=> 'Tất cả','open'=>'🔴 Mở','replied'=>'💬 Đã phản hồi','closed'=>'✔ Đóng'] as $s=>$lbl): ?>
  <a href="?status=<?= $s ?>" class="btn btn-sm <?= $filter===$s?'btn-primary':'btn-outline-secondary' ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Mã ticket</th><th>Khách</th><th>Tiêu đề</th><th>Đơn</th><th>TT</th><th>Cập nhật</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tickets as $t): ?>
      <?php $u = DB::find('users','id',$t['user_id']); ?>
      <tr>
        <td><code style="font-size:11px"><?= h($t['id']) ?></code></td>
        <td class="small"><?= h($u['username']??'?') ?></td>
        <td><?= h($t['subject']) ?></td>
        <td class="small text-muted"><?= h($t['order_id']??'—') ?></td>
        <td><?= ticketStatusLabel($t['status']) ?></td>
        <td class="small text-muted"><?= fmtDate($t['updated_at']) ?></td>
        <td><a href="/admin/ticket_reply.php?id=<?= h($t['id']) ?>" class="btn btn-sm btn-outline-primary">Phản hồi</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$tickets): ?><tr><td colspan="7" class="text-center py-4 text-muted">Không có ticket</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
