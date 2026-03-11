<?php
require_once __DIR__ . '/includes/config.php';
Auth::requireLogin();

$pageTitle = 'Hỗ trợ kỹ thuật';
$uid = Auth::id();

// Create new ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject  = trim($_POST['subject'] ?? '');
    $message  = trim($_POST['message'] ?? '');
    $orderId  = $_POST['order_id'] ?? '';
    if ($subject && $message) {
        $tid = DB::nextId('TKT');
        DB::append('tickets', [
            'id'         => $tid,
            'user_id'    => $uid,
            'subject'    => $subject,
            'order_id'   => $orderId,
            'status'     => 'open',
            'messages'   => [[
                'from'    => 'user',
                'user_id' => $uid,
                'body'    => $message,
                'created_at' => date('c'),
            ]],
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ]);
        flash('success', 'Ticket đã được tạo! Chúng tôi sẽ phản hồi sớm nhất.');
        redirect('/ticket.php?id=' . $tid);
    }
}

$tickets = DB::filter('tickets', fn($t) => $t['user_id'] === $uid);
usort($tickets, fn($a,$b) => strcmp($b['updated_at'], $a['updated_at']));

$myOrders = DB::filter('orders', fn($o) => $o['user_id'] === $uid);
$showNew  = isset($_GET['new']);

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb-bar">
  <div class="container">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Hỗ trợ</li>
    </ol>
  </div>
</div>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-800 mb-0">🎧 Hỗ trợ kỹ thuật</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNewTicket">
      <i class="bi bi-plus me-1"></i>Tạo ticket mới
    </button>
  </div>

  <?php if (empty($tickets)): ?>
  <div class="text-center py-5 text-muted">
    <div style="font-size:3rem">🎧</div>
    <p class="mt-2">Chưa có ticket hỗ trợ nào</p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNewTicket">Tạo ticket đầu tiên</button>
  </div>
  <?php else: ?>
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Mã ticket</th>
          <th>Tiêu đề</th>
          <th style="width:90px">Đơn hàng</th>
          <th style="width:130px">Trạng thái</th>
          <th style="width:130px">Cập nhật</th>
          <th style="width:80px"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr>
          <td><code style="font-size:12px"><?= h($t['id']) ?></code></td>
          <td><?= h($t['subject']) ?></td>
          <td><span class="small text-muted"><?= h($t['order_id'] ?? '—') ?></span></td>
          <td><?= ticketStatusLabel($t['status']) ?></td>
          <td class="text-muted small"><?= fmtDate($t['updated_at']) ?></td>
          <td><a href="/ticket.php?id=<?= h($t['id']) ?>" class="btn btn-sm btn-outline-primary">Xem</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Modal create ticket -->
<div class="modal fade" id="modalNewTicket" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-700"><i class="bi bi-plus-circle me-2 text-primary"></i>Tạo ticket hỗ trợ mới</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-600">Tiêu đề *</label>
            <input type="text" name="subject" class="form-control" placeholder="Mô tả ngắn vấn đề của bạn" required>
          </div>
          <?php if ($myOrders): ?>
          <div class="mb-3">
            <label class="form-label fw-600">Liên quan đến đơn hàng (nếu có)</label>
            <select name="order_id" class="form-select">
              <option value="">— Không liên quan —</option>
              <?php foreach ($myOrders as $o): ?>
              <option value="<?= h($o['id']) ?>"><?= h($o['id']) ?> — <?= h($o['product_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label fw-600">Nội dung chi tiết *</label>
            <textarea name="message" class="form-control" rows="5" placeholder="Mô tả chi tiết vấn đề bạn gặp phải..." required></textarea>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" name="create_ticket" class="btn btn-primary"><i class="bi bi-send me-1"></i>Gửi ticket</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($showNew): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  new bootstrap.Modal(document.getElementById('modalNewTicket')).show();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
