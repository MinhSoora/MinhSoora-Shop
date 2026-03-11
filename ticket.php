<?php
require_once __DIR__ . '/includes/config.php';
Auth::requireLogin();

$tid    = $_GET['id'] ?? '';
$ticket = DB::find('tickets', 'id', $tid);
if (!$ticket || ($ticket['user_id'] !== Auth::id() && !Auth::isAdmin())) {
    flash('error', 'Không tìm thấy ticket'); redirect('/tickets.php');
}

$pageTitle = 'Ticket: ' . $ticket['subject'];

// Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $body = trim($_POST['body'] ?? '');
    if ($body) {
        $tickets = DB::read('tickets', []);
        foreach ($tickets as &$t) {
            if ($t['id'] === $tid) {
                $t['messages'][] = [
                    'from'       => Auth::isAdmin() ? 'admin' : 'user',
                    'user_id'    => Auth::id(),
                    'body'       => $body,
                    'created_at' => date('c'),
                ];
                $t['status']     = Auth::isAdmin() ? 'replied' : 'open';
                $t['updated_at'] = date('c');
                break;
            }
        }
        unset($t);
        DB::write('tickets', $tickets);
        redirect('/ticket.php?id=' . $tid);
    }
}

// Close ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_ticket'])) {
    DB::update('tickets', 'id', $tid, ['status' => 'closed', 'updated_at' => date('c')]);
    flash('success', 'Ticket đã đóng');
    redirect('/tickets.php');
}

$ticket = DB::find('tickets', 'id', $tid); // reload
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb-bar">
  <div class="container">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="/tickets.php">Hỗ trợ</a></li>
      <li class="breadcrumb-item active"><?= h($ticket['subject']) ?></li>
    </ol>
  </div>
</div>

<div class="container py-4" style="max-width:760px">
  <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h5 class="fw-800 mb-1"><?= h($ticket['subject']) ?></h5>
      <div class="d-flex gap-2 align-items-center">
        <?= ticketStatusLabel($ticket['status']) ?>
        <span class="text-muted small">Tạo: <?= fmtDate($ticket['created_at']) ?></span>
        <?php if ($ticket['order_id']): ?>
        <a href="/order.php?id=<?= h($ticket['order_id']) ?>" class="badge bg-light border text-muted text-decoration-none small">
          Đơn #<?= h($ticket['order_id']) ?>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($ticket['status'] !== 'closed'): ?>
    <form method="POST">
      <button type="submit" name="close_ticket" class="btn btn-outline-secondary btn-sm"
        onclick="return confirm('Đóng ticket này?')">
        <i class="bi bi-check2-circle me-1"></i>Đóng ticket
      </button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Messages -->
  <div class="d-flex flex-column gap-3 mb-4">
    <?php foreach ($ticket['messages'] ?? [] as $msg): ?>
    <?php $isAdmin = $msg['from'] === 'admin'; ?>
    <div class="d-flex gap-3 <?= $isAdmin ? 'flex-row-reverse' : '' ?>">
      <div class="flex-shrink-0">
        <div class="rounded-circle d-flex align-items-center justify-content-center fw-700"
             style="width:38px;height:38px;background:<?= $isAdmin ? '#dbeafe' : '#f0fdf4' ?>;font-size:14px">
          <?= $isAdmin ? '🛡' : '👤' ?>
        </div>
      </div>
      <div class="flex-grow-1" style="max-width:85%">
        <div class="small text-muted mb-1 <?= $isAdmin ? 'text-end' : '' ?>">
          <?= $isAdmin ? '<span class="badge bg-primary me-1">Admin</span>' : '' ?>
          <?= fmtDate($msg['created_at']) ?>
        </div>
        <div class="card border-0 shadow-sm p-3" style="background:<?= $isAdmin ? '#eff6ff' : '#f9fafb' ?>;border-radius:12px">
          <?= nl2br(h($msg['body'])) ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Reply form -->
  <?php if ($ticket['status'] !== 'closed'): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="POST">
        <label class="form-label fw-600 small">Phản hồi của bạn</label>
        <textarea name="body" class="form-control mb-3" rows="4" placeholder="Nhập nội dung..." required></textarea>
        <div class="d-flex justify-content-end">
          <button type="submit" name="reply" class="btn btn-primary"><i class="bi bi-send me-1"></i>Gửi phản hồi</button>
        </div>
      </form>
    </div>
  </div>
  <?php else: ?>
  <div class="alert alert-secondary text-center">Ticket đã đóng — <a href="/tickets.php?new=1">Tạo ticket mới</a></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
