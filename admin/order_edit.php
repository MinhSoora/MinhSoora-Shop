<?php
/**
 * admin/order_edit.php — Quản lý chi tiết đơn hàng
 * Update trạng thái, ghi note (public/private), xem timeline
 */
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

$oid   = $_GET['id'] ?? '';
$order = DB::find('orders', 'id', $oid);
if (!$order) { flash('error','Không tìm thấy đơn hàng'); redirect('/admin/orders.php'); }

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // Cập nhật trạng thái + ghi update vào timeline
    if ($act === 'update_status') {
        $newStatus  = $_POST['status'] ?? $order['status'];
        $noteMsg    = trim($_POST['note'] ?? '');
        $notePublic = trim($_POST['note_public'] ?? '');

        // Auto message nếu không nhập
        if (!$noteMsg) {
            $noteMsg = match($newStatus) {
                'paid'       => '✅ Xác nhận đã nhận thanh toán thành công.',
                'processing' => '⚙️ Đơn hàng đang được xử lý, vui lòng chờ trong ít phút.',
                'active'     => '🎉 Dịch vụ đã được kích hoạt! Cảm ơn bạn đã tin dùng.',
                'cancelled'  => '❌ Đơn hàng đã bị hủy. Liên hệ hỗ trợ nếu có thắc mắc.',
                'expired'    => '⏰ Đơn hàng đã hết hạn.',
                default      => 'Trạng thái đơn hàng được cập nhật.',
            };
        }

        $updates   = $order['updates'] ?? [];
        $updates[] = [
            'id'      => uniqid('upd_'),
            'time'    => date('c'),
            'actor'   => 'admin',
            'status'  => $newStatus,
            'message' => $noteMsg,
            'public'  => true,
        ];

        $patch = [
            'status'     => $newStatus,
            'updates'    => $updates,
            'updated_at' => date('c'),
        ];
        if ($notePublic !== '') $patch['admin_note_public'] = $notePublic;
        if ($newStatus === 'paid' && empty($order['paid_at'])) $patch['paid_at'] = date('c');

        DB::update('orders','id',$oid,$patch);
        flash('success','✓ Đã cập nhật trạng thái');
        redirect('/admin/order_edit.php?id='.$oid);
    }

    // Thêm ghi chú
    if ($act === 'add_note') {
        $msg      = trim($_POST['message'] ?? '');
        $isPublic = isset($_POST['is_public']);
        if ($msg) {
            $updates   = $order['updates'] ?? [];
            $updates[] = [
                'id'      => uniqid('note_'),
                'time'    => date('c'),
                'actor'   => 'admin',
                'message' => $msg,
                'public'  => $isPublic,
                'private' => !$isPublic,
            ];
            DB::update('orders','id',$oid,['updates'=>$updates,'updated_at'=>date('c')]);
            if ($isPublic) DB::update('orders','id',$oid,['admin_note_public'=>$msg]);
            flash('success','✓ Đã thêm ghi chú');
        }
        redirect('/admin/order_edit.php?id='.$oid);
    }

    // Xóa một update
    if ($act === 'del_update') {
        $uid     = $_POST['uid'] ?? '';
        $updates = array_values(array_filter($order['updates']??[], fn($u)=>$u['id']!==$uid));
        DB::update('orders','id',$oid,['updates'=>$updates]);
        redirect('/admin/order_edit.php?id='.$oid);
    }
}

$order   = DB::find('orders','id',$oid); // reload
$product = DB::find('products','id',$order['product_id']??'');
$user    = $order['user_id'] ? DB::find('users','id',$order['user_id']) : null;
$updates = $order['updates'] ?? [];
usort($updates, fn($a,$b) => strcmp($b['time']??'',$a['time']??''));

$statuses = ['pending','paid','processing','active','cancelled','expired'];
$statusLabels = ['pending'=>'⏳ Chờ thanh toán','paid'=>'💳 Đã thanh toán','processing'=>'⚙️ Đang xử lý','active'=>'✅ Hoàn thành','cancelled'=>'❌ Đã hủy','expired'=>'⏰ Hết hạn'];

$adminPageTitle = 'Chi tiết Đơn hàng';
require_once __DIR__ . '/header.php';

// Breadcrumb
?>
<div class="d-flex gap-2 mb-4 flex-wrap align-items-center">
  <a href="/admin/orders.php" class="btn btn-sm btn-outline-secondary">← Danh sách đơn</a>
  <span class="text-muted">|</span>
  <span class="fw-700">Đơn: <code><?= h($order['code'] ?? $order['id']) ?></code></span>
  <?= orderStatusLabel($order['status']??'pending') ?>
  <a href="/track-order.php?code=<?= h($order['code']??$order['id']) ?>" target="_blank" class="btn btn-sm btn-outline-info ms-auto">
    <i class="bi bi-box-arrow-up-right me-1"></i>Trang tra cứu của khách
  </a>
</div>

<div class="row g-4">
  <!-- LEFT: Info + Timeline -->
  <div class="col-lg-7">
    <!-- Thông tin đơn -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-700">📋 Thông tin đơn hàng</div>
      <div class="card-body">
        <div class="row g-2" style="font-size:13px">
          <div class="col-sm-6">
            <table class="table table-sm table-borderless mb-0">
              <tr><td class="text-muted" style="width:45%">Mã đơn</td>
                  <td><code class="fw-900" style="font-size:1rem;letter-spacing:1px"><?= h($order['code']??$order['id']) ?></code></td></tr>
              <tr><td class="text-muted">Loại đơn</td>
                  <td><?= ($order['type']??'')==='setup'?'🔧 Setup một lần':'🖥️ Server định kỳ' ?></td></tr>
              <tr><td class="text-muted">Sản phẩm</td>
                  <td class="fw-600"><?= h($product['name']??($order['product_name']??'—')) ?></td></tr>
              <tr><td class="text-muted">Tổng tiền</td>
                  <td class="fw-900 text-primary" style="font-size:1.1rem"><?= fmtMoney($order['total']??0) ?></td></tr>
            </table>
          </div>
          <div class="col-sm-6">
            <table class="table table-sm table-borderless mb-0">
              <tr><td class="text-muted" style="width:40%">Khách hàng</td>
                  <td><?= $user ? '<a href="/admin/user_edit.php?id='.h($user['id']).'">'.h($user['username']).'</a>' : '<span class="text-muted">Khách</span>' ?></td></tr>
              <tr><td class="text-muted">Email</td>
                  <td><?= h($user['email']??$order['guest_email']??'—') ?></td></tr>
              <tr><td class="text-muted">Đặt lúc</td>
                  <td><?= fmtDate($order['created_at']??'') ?></td></tr>
              <tr><td class="text-muted">Cập nhật</td>
                  <td><?= fmtDate($order['updated_at']??'') ?></td></tr>
            </table>
          </div>
        </div>
        <?php if (!empty($order['fields'])): ?>
        <hr class="my-2">
        <div style="font-size:12px;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Thông tin khách điền</div>
        <table class="table table-sm table-borderless mb-0" style="font-size:13px">
          <?php foreach ($order['fields'] as $k=>$v): ?>
          <tr><td class="text-muted" style="width:40%"><?= h($k) ?></td>
              <td><?= h(is_array($v)?implode(', ',$v):$v) ?></td></tr>
          <?php endforeach; ?>
        </table>
        <?php endif; ?>
        <?php if (!empty($order['note_customer'])): ?>
        <div class="alert alert-light border py-2 mt-2 small fst-italic">
          <i class="bi bi-chat-quote me-1"></i>Ghi chú khách: <?= nl2br(h($order['note_customer'])) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Timeline -->
    <div class="card">
      <div class="card-header bg-white fw-700">🕐 Lịch sử cập nhật (Admin view)</div>
      <div class="card-body p-0" style="max-height:500px;overflow-y:auto">
        <?php if (empty($updates)): ?>
        <div class="text-muted text-center py-4 small">Chưa có cập nhật nào</div>
        <?php else: foreach ($updates as $u): ?>
        <?php $isPrivate = !empty($u['private']); ?>
        <div class="d-flex gap-3 p-3 border-bottom" style="<?= $isPrivate?'background:#fffbeb':'' ?>">
          <div style="width:10px;height:10px;border-radius:50%;margin-top:6px;flex-shrink:0;background:<?= $isPrivate?'#f59e0b':($u['actor']==='admin'?'#7c3aed':'#2563eb') ?>"></div>
          <div class="flex-grow-1 min-w-0">
            <div style="font-size:11px;color:#94a3b8">
              <?= $u['actor']==='admin'?'<i class="bi bi-shield-fill me-1" style="color:#7c3aed"></i>Admin':'<i class="bi bi-gear-fill me-1" style="color:#2563eb"></i>Hệ thống' ?>
              · <?= fmtDate($u['time']??'') ?>
              <?php if (!empty($u['status'])): ?><?= orderStatusLabel($u['status']) ?><?php endif; ?>
              <?php if ($isPrivate): ?><span class="badge bg-warning text-dark ms-1" style="font-size:10px">🔒 Nội bộ</span><?php endif; ?>
            </div>
            <div style="font-size:13px;color:#1e293b;margin-top:3px;line-height:1.65;white-space:pre-wrap"><?= h($u['message']??'') ?></div>
          </div>
          <form method="POST" class="flex-shrink-0">
            <input type="hidden" name="act" value="del_update">
            <input type="hidden" name="uid" value="<?= h($u['id']??'') ?>">
            <button type="submit" class="btn btn-link btn-sm text-danger p-0" style="font-size:13px" onclick="return confirm('Xóa?')">
              <i class="bi bi-trash3"></i>
            </button>
          </form>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- RIGHT: Actions -->
  <div class="col-lg-5">
    <!-- Cập nhật trạng thái -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-700">⚡ Cập nhật trạng thái</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="act" value="update_status">
          <div class="mb-3">
            <label class="form-label small fw-600">Trạng thái mới</label>
            <select name="status" class="form-select">
              <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= ($order['status']??'')===$s?'selected':'' ?>><?= $statusLabels[$s] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-600">Thông điệp cho khách <span class="text-muted fw-400">(để trống = tự động)</span></label>
            <textarea name="note" class="form-control" rows="3" placeholder="VD: Server của bạn đã sẵn sàng, thông tin kết nối đã được gửi qua email..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-600">📌 Ghim thông báo nổi bật <span class="text-muted fw-400">(hiển thị ô xanh trên trang tra cứu)</span></label>
            <textarea name="note_public" class="form-control" rows="2" placeholder="Thông tin kết nối, credentials, hướng dẫn sử dụng..."><?= h($order['admin_note_public']??'') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-700">
            <i class="bi bi-check2-circle me-1"></i>Cập nhật
          </button>
        </form>
      </div>
    </div>

    <!-- Thêm ghi chú -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-700">📝 Thêm ghi chú</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="act" value="add_note">
          <div class="mb-2">
            <textarea name="message" class="form-control" rows="4"
              placeholder="Ghi chú nội bộ hoặc thông báo cho khách hàng..." required></textarea>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" name="is_public" id="note_pub" class="form-check-input" checked>
            <label class="form-check-label small" for="note_pub">
              <i class="bi bi-eye me-1 text-success"></i>Hiển thị cho khách
              <span class="text-muted">(bỏ chọn = chỉ admin xem)</span>
            </label>
          </div>
          <button type="submit" class="btn btn-outline-primary w-100 btn-sm fw-600">
            <i class="bi bi-plus-circle me-1"></i>Thêm ghi chú
          </button>
        </form>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="card">
      <div class="card-header bg-white fw-700">🔗 Thao tác nhanh</div>
      <div class="card-body d-flex flex-column gap-2">
        <?php $copyCode = $order['code'] ?? $order['id']; ?>
        <button onclick="navigator.clipboard?.writeText('<?= h($copyCode) ?>').then(()=>{this.innerHTML='<i class=\'bi bi-check2 me-2\'></i>Đã copy mã đơn!';setTimeout(()=>this.innerHTML='<i class=\'bi bi-clipboard me-2\'></i>Copy mã: <?= h($copyCode) ?>',2000)})" class="btn btn-outline-secondary btn-sm text-start">
          <i class="bi bi-clipboard me-2"></i>Copy mã: <?= h($copyCode) ?>
        </button>
        <a href="/track-order.php?code=<?= h($copyCode) ?>" target="_blank" class="btn btn-outline-info btn-sm text-start">
          <i class="bi bi-eye me-2"></i>Xem như khách hàng
        </a>
        <?php if ($user): ?>
        <a href="/admin/user_edit.php?id=<?= h($user['id']) ?>" class="btn btn-outline-secondary btn-sm text-start">
          <i class="bi bi-person me-2"></i>Xem tài khoản khách
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
