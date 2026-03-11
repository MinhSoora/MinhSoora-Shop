<?php
// admin/ticket_reply.php
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

$tid    = $_GET['id'] ?? '';
$ticket = DB::find('tickets','id',$tid);
if (!$ticket) { flash('error','Không tìm thấy'); redirect('/admin/tickets.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $body = trim($_POST['body']??'');
    if ($body) {
        $tickets = DB::read('tickets',[]);
        foreach ($tickets as &$t) {
            if ($t['id']===$tid) {
                $t['messages'][] = ['from'=>'admin','user_id'=>Auth::id(),'body'=>$body,'created_at'=>date('c')];
                $t['status']     = $_POST['close'] ?? false ? 'closed' : 'replied';
                $t['updated_at'] = date('c');
                break;
            }
        }
        unset($t);
        DB::write('tickets',$tickets);
        flash('success','Đã gửi phản hồi');
        redirect('/admin/ticket_reply.php?id='.$tid);
    }
}

$adminPageTitle = 'Phản hồi Ticket';
require_once __DIR__ . '/header.php';

$ticket = DB::find('tickets','id',$tid);
$user   = DB::find('users','id',$ticket['user_id']);
?>
<div class="d-flex gap-2 mb-3">
  <a href="/admin/tickets.php" class="btn btn-sm btn-outline-secondary">← Quay lại</a>
  <span class="fw-700 ms-2"><?= h($ticket['subject']) ?></span>
  <?= ticketStatusLabel($ticket['status']) ?>
</div>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex flex-column gap-3 mb-4">
          <?php foreach ($ticket['messages']??[] as $msg): ?>
          <?php $isAdmin = $msg['from']==='admin'; ?>
          <div class="d-flex gap-3 <?= $isAdmin?'flex-row-reverse':'' ?>">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:34px;height:34px;background:<?= $isAdmin?'#dbeafe':'#f0fdf4' ?>;font-size:12px;flex-shrink:0">
              <?= $isAdmin?'🛡':'👤' ?>
            </div>
            <div style="max-width:85%">
              <div class="small text-muted mb-1 <?= $isAdmin?'text-end':'' ?>"><?= fmtDate($msg['created_at']) ?></div>
              <div class="p-3 rounded" style="background:<?= $isAdmin?'#eff6ff':'#f9fafb' ?>">
                <?= nl2br(h($msg['body'])) ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($ticket['status']!=='closed'): ?>
        <form method="POST">
          <label class="form-label fw-600 small">Phản hồi</label>
          <textarea name="body" class="form-control mb-2" rows="4" required></textarea>
          <div class="d-flex gap-2">
            <button type="submit" name="reply" class="btn btn-primary"><i class="bi bi-send me-1"></i>Gửi phản hồi</button>
            <button type="submit" name="reply" value="1" class="btn btn-outline-secondary" onclick="document.querySelector('[name=close]').value='1'">
              Gửi & Đóng ticket
            </button>
          </div>
          <input type="hidden" name="close" value="">
        </form>
        <?php else: ?>
        <div class="alert alert-secondary">Ticket đã đóng</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body small">
        <div class="fw-700 mb-2">Thông tin ticket</div>
        <div class="mb-1 text-muted">Mã: <code><?= h($tid) ?></code></div>
        <div class="mb-1 text-muted">Khách: <a href="/admin/user_edit.php?id=<?= h($user['id']) ?>"><?= h($user['username']??'?') ?></a></div>
        <?php if ($ticket['order_id']): ?>
        <div class="mb-1 text-muted">Đơn: <a href="/admin/order_edit.php?id=<?= h($ticket['order_id']) ?>"><?= h($ticket['order_id']) ?></a></div>
        <?php endif; ?>
        <div class="mb-1 text-muted">Tạo: <?= fmtDate($ticket['created_at']) ?></div>
        <div class="mb-1 text-muted">Cập nhật: <?= fmtDate($ticket['updated_at']) ?></div>
        <div class="mt-2"><?= ticketStatusLabel($ticket['status']) ?></div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
