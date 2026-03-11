<?php
/**
 * track-order.php — Tra cứu đơn hàng không cần đăng nhập
 * Hiện trạng thái, timeline, note admin cho khách
 */
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Tra cứu đơn hàng';
$order = null;
$error = null;
$code  = strtoupper(trim($_GET['code'] ?? ''));

if ($code) {
    foreach (DB::read('orders', []) as $o) {
        if (strtoupper($o['code'] ?? $o['id'] ?? '') === $code) { $order = $o; break; }
    }
    if (!$order) $error = 'Không tìm thấy đơn hàng <strong>' . h($code) . '</strong>. Kiểm tra lại mã và thử lại.';
}

function tbadge(string $s): string {
    return match($s) {
        'pending'    => '<span class="tbg pending">⏳ Chờ thanh toán</span>',
        'paid'       => '<span class="tbg paid">💳 Đã thanh toán</span>',
        'processing' => '<span class="tbg processing">⚙️ Đang xử lý</span>',
        'active'     => '<span class="tbg active">✅ Hoàn thành</span>',
        'cancelled'  => '<span class="tbg cancelled">❌ Đã hủy</span>',
        'expired'    => '<span class="tbg expired">⏰ Hết hạn</span>',
        default      => '<span class="tbg">' . h($s) . '</span>',
    };
}

include __DIR__ . '/includes/header.php';
?>
<style>
.track-hero{background:linear-gradient(135deg,#0f172a,#1e293b);padding:64px 0 52px;position:relative;overflow:hidden}
.track-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 25% 55%,rgba(37,99,235,.38),transparent 55%),radial-gradient(ellipse at 78% 35%,rgba(124,58,237,.22),transparent 55%)}
.track-hero .container{position:relative;z-index:1}
.tbox{background:rgba(255,255,255,.07);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.13);border-radius:18px;padding:30px;max-width:520px;margin:0 auto}
.tbox input{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:11px;font-size:1.2rem;letter-spacing:3px;text-transform:uppercase;text-align:center;font-weight:800;padding:14px}
.tbox input::placeholder{color:rgba(255,255,255,.3);letter-spacing:1px;font-weight:400}
.tbox input:focus{background:rgba(255,255,255,.15);border-color:#3b82f6;color:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.3)}
.btn-track{display:block;width:100%;background:linear-gradient(135deg,#2563eb,#7c3aed);border:none;color:#fff;font-weight:700;border-radius:11px;padding:13px;font-size:15px;transition:.25s;margin-top:12px;cursor:pointer}
.btn-track:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(37,99,235,.45);color:#fff}
.ocard{background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 6px 32px rgba(0,0,0,.08)}
.ocard-head{background:linear-gradient(135deg,#1e293b,#334155);color:#fff;padding:26px 30px}
.ocode{font-family:monospace;font-size:2rem;font-weight:900;letter-spacing:4px;color:#38bdf8}
/* Progress steps */
.ptrack{display:flex;margin:30px 0 24px}
.pstep{flex:1;display:flex;flex-direction:column;align-items:center;position:relative}
.pstep:not(:last-child)::after{content:'';position:absolute;top:21px;left:50%;width:100%;height:3px;background:#e2e8f0;z-index:0}
.pstep.done::after,.pstep.cur::after{background:linear-gradient(90deg,#2563eb,#7c3aed)}
.pcircle{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;background:#f1f5f9;border:3px solid #e2e8f0;position:relative;z-index:1;transition:.3s}
.pstep.done .pcircle{background:#2563eb;border-color:#2563eb;color:#fff}
.pstep.cur  .pcircle{background:#fff;border-color:#2563eb;box-shadow:0 0 0 5px rgba(37,99,235,.13);animation:pring 2s infinite}
.pstep.fut  .pcircle{opacity:.4}
@keyframes pring{0%,100%{box-shadow:0 0 0 5px rgba(37,99,235,.13)}50%{box-shadow:0 0 0 10px rgba(37,99,235,.05)}}
.plbl{margin-top:8px;font-size:11px;font-weight:700;color:#94a3b8;text-align:center}
.pstep.done .plbl{color:#16a34a}.pstep.cur .plbl{color:#2563eb}
/* Badges */
.tbg{font-size:12px;padding:5px 13px;border-radius:20px;font-weight:600;display:inline-block}
.tbg.pending{background:#fef3c7;color:#92400e}.tbg.paid{background:#dbeafe;color:#1e40af}
.tbg.processing{background:#ede9fe;color:#5b21b6}.tbg.active{background:#dcfce7;color:#14532d}
.tbg.cancelled{background:#fee2e2;color:#7f1d1d}.tbg.expired{background:#f3f4f6;color:#374151}
/* Timeline */
.tl-item{display:flex;gap:14px;padding:14px 0;border-bottom:1px solid #f1f5f9}
.tl-item:last-child{border-bottom:none}
.tl-dot{width:10px;height:10px;border-radius:50%;margin-top:6px;flex-shrink:0}
.tl-dot.admin{background:#7c3aed}.tl-dot.sys{background:#2563eb}
.tl-meta{font-size:11px;color:#94a3b8}
.tl-msg{font-size:13.5px;color:#1e293b;margin-top:3px;line-height:1.65}
/* Info box */
.ibox{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px}
.ibox-label{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;font-weight:700;margin-bottom:14px}
.irow{display:flex;gap:8px;font-size:13px;margin-bottom:8px}
.irow:last-child{margin-bottom:0}
.ikey{color:#94a3b8;min-width:120px;flex-shrink:0}
.ival{color:#1e293b;font-weight:500}
/* Admin note */
.note-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px}
</style>

<!-- Track Hero -->
<div class="track-hero">
  <div class="container text-center">
    <h1 class="fw-900 mb-2" style="color:#fff;font-size:clamp(1.8rem,5vw,2.6rem)"><i class="bi bi-search-heart me-2" style="color:#38bdf8"></i>Tra cứu đơn hàng</h1>
    <p class="mb-4" style="color:#94a3b8">Không cần đăng nhập — nhập mã đơn để xem trạng thái & tiến trình</p>
    <form method="GET" action="/track-order.php">
      <div class="tbox">
        <input type="text" name="code" placeholder="VD: NS-2025-ABCDEF"
          value="<?= h($code) ?>" maxlength="30" autocomplete="off" autofocus>
        <div style="color:#475569;font-size:12px;margin-top:8px"><i class="bi bi-info-circle me-1"></i>Mã đơn được gửi qua email sau khi đặt hàng thành công</div>
        <button type="submit" class="btn-track"><i class="bi bi-search me-2"></i>Tra cứu ngay</button>
      </div>
    </form>
  </div>
</div>

<div class="container py-5">
<?php if ($error): ?>
<div class="alert alert-danger rounded-3 shadow-sm"><?= $error ?></div>
<?php endif; ?>

<?php if ($order):
  $product = DB::find('products','id',$order['product_id']??'');
  $smap    = ['pending'=>0,'paid'=>1,'processing'=>2,'active'=>3];
  $cur     = $smap[$order['status']??'pending'] ?? 0;
  $isBad   = in_array($order['status']??'',['cancelled','expired']);
  $updates = $order['updates'] ?? [];
  $pubUpdates = array_values(array_filter($updates, fn($u) => empty($u['private'])));
  usort($pubUpdates, fn($a,$b)=>strcmp($b['time']??'',$a['time']??''));
?>
<div class="ocard">
  <!-- Header -->
  <div class="ocard-head">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div>
        <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Mã đơn hàng</div>
        <div class="ocode"><?= h($order['code'] ?? $order['id']) ?></div>
        <div class="mt-2"><?= tbadge($order['status']??'pending') ?></div>
      </div>
      <div class="text-end">
        <div style="color:#94a3b8;font-size:11px">Đặt lúc</div>
        <div style="color:#e2e8f0;font-weight:700"><?= fmtDate($order['created_at']??'') ?></div>
        <div class="mt-2">
          <?php if (($order['type']??'') === 'setup'): ?>
            <span style="background:rgba(14,165,233,.2);color:#38bdf8;font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600">🔧 Đơn Setup</span>
          <?php else: ?>
            <span style="background:rgba(37,99,235,.2);color:#93c5fd;font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600">🖥️ Đơn Server</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="p-4">
    <!-- Progress bar -->
    <?php if (!$isBad): ?>
    <div class="ptrack">
      <?php foreach ([['⏳','Đặt hàng'],['💳','Thanh toán'],['⚙️','Xử lý'],['✅','Hoàn thành']] as $i=>[$ic,$lb]): ?>
      <?php $cls = $i<$cur?'done':($i===$cur?'cur':'fut'); ?>
      <div class="pstep <?= $cls ?>">
        <div class="pcircle"><?= $cls==='done'?'<i class="bi bi-check-lg fw-bold" style="color:#fff"></i>':$ic ?></div>
        <div class="plbl"><?= $lb ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="alert alert-warning rounded-3 mb-4">
      <?= $order['status']==='cancelled'?'❌ Đơn hàng này đã bị hủy.':'⏰ Đơn hàng đã hết hạn.' ?>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <!-- Thông tin đơn -->
      <div class="col-md-6">
        <div class="ibox h-100">
          <div class="ibox-label"><i class="bi bi-receipt me-1"></i>Thông tin đơn hàng</div>
          <div class="irow"><span class="ikey">Sản phẩm</span><span class="ival fw-700"><?= h($product['name'] ?? ($order['product_name']??'—')) ?></span></div>
          <div class="irow"><span class="ikey">Loại đơn</span><span class="ival"><?= ($order['type']??'')==='setup'?'🔧 Setup một lần':'🖥️ Server định kỳ' ?></span></div>
          <div class="irow"><span class="ikey">Tổng tiền</span><span class="ival fw-900 text-primary" style="font-size:1.1rem"><?= fmtMoney($order['total']??$order['amount']??0) ?></span></div>
          <?php if (!empty($order['note_customer'])): ?>
          <div class="irow"><span class="ikey">Ghi chú</span><span class="ival"><?= h($order['note_customer']) ?></span></div>
          <?php endif; ?>
          <?php if (!empty($order['expires_at'])): ?>
          <div class="irow"><span class="ikey">Hết hạn</span><span class="ival"><?= fmtDate($order['expires_at']) ?></span></div>
          <?php endif; ?>
        </div>
      </div>
      <!-- Admin note công khai -->
      <?php if (!empty($order['admin_note_public'])): ?>
      <div class="col-md-6">
        <div class="note-box h-100">
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#16a34a;font-weight:700;margin-bottom:12px"><i class="bi bi-megaphone me-1"></i>Thông báo từ Admin</div>
          <div style="font-size:13.5px;color:#166534;line-height:1.75"><?= nl2br(h($order['admin_note_public'])) ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Timeline -->
    <?php if (!empty($pubUpdates)): ?>
    <div>
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;font-weight:700;margin-bottom:14px"><i class="bi bi-clock-history me-1"></i>Lịch sử cập nhật</div>
      <div class="ibox">
        <?php foreach ($pubUpdates as $u): ?>
        <?php $isAdmin = ($u['actor']??'') === 'admin'; ?>
        <div class="tl-item">
          <div class="tl-dot <?= $isAdmin?'admin':'sys' ?>"></div>
          <div>
            <div class="tl-meta">
              <?= $isAdmin?'<i class="bi bi-shield-fill me-1" style="color:#7c3aed"></i>Admin':'<i class="bi bi-gear-fill me-1" style="color:#2563eb"></i>Hệ thống' ?>
              · <?= fmtDate($u['time']??'') ?>
              <?php if (!empty($u['status'])): ?> · <?= tbadge($u['status']) ?><?php endif; ?>
            </div>
            <div class="tl-msg"><?= nl2br(h($u['message']??'')) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="mt-4 d-flex flex-wrap gap-2">
      <a href="/track-order.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Tìm đơn khác</a>
      <a href="/products.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-grid me-1"></i>Xem dịch vụ</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!$order && !$code): ?>
<div class="row g-4 text-center mt-2">
  <?php foreach ([['📧','Kiểm tra email','Mã đơn được gửi ngay khi đặt hàng thành công, dạng NS-YYYY-XXXXXX'],['🔍','Nhập mã đơn','Nhập vào ô tìm kiếm phía trên và bấm Tra cứu'],['📊','Xem tiến trình','Trạng thái và cập nhật realtime từ đội ngũ admin']] as [$ic,$t,$d]): ?>
  <div class="col-md-4">
    <div class="p-4 rounded-3 h-100" style="background:#f8fafc;border:1px solid #e2e8f0">
      <div style="font-size:2.5rem"><?= $ic ?></div>
      <div class="fw-700 mt-2"><?= $t ?></div>
      <div class="text-muted small mt-1"><?= $d ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
