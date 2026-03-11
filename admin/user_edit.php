<?php
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

$uid  = $_GET['id'] ?? '';
$user = DB::find('users', 'id', $uid);
if (!$user) { flash('error','Không tìm thấy'); redirect('/admin/users.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $patch = [
        'role'       => in_array($_POST['role']??'',['user','admin']) ? $_POST['role'] : 'user',
        'active'     => isset($_POST['active']),
        'balance'    => max(0, (int)$_POST['balance']),
        'server_ids' => array_values(array_filter(array_map('trim', explode("\n", $_POST['server_ids']??'')))),
        'email'      => strtolower(trim($_POST['email']??$user['email'])),
    ];
    if (!empty($_POST['new_password'])) $patch['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    DB::update('users','id',$uid,$patch);

    flash('success','Đã cập nhật người dùng');
    redirect('/admin/user_edit.php?id='.$uid);
}

$adminPageTitle = 'Sửa người dùng';
require_once __DIR__ . '/header.php';

$user = DB::find('users','id',$uid);
$userOrders = DB::filter('orders', fn($o) => $o['user_id'] === $uid);
usort($userOrders, fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
?>
<div class="d-flex gap-2 mb-3">
  <a href="/admin/users.php" class="btn btn-sm btn-outline-secondary">← Quay lại</a>
</div>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <h6 class="fw-700 mb-3">Thông tin tài khoản</h6>
        <form method="POST">
          <div class="mb-2">
            <label class="form-label small fw-600">Username</label>
            <input class="form-control" value="<?= h($user['username']) ?>" disabled>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-600">Email</label>
            <input type="email" name="email" class="form-control" value="<?= h($user['email']) ?>">
          </div>
          <div class="mb-2">
            <label class="form-label small fw-600">Mật khẩu mới (để trống = không đổi)</label>
            <input type="password" name="new_password" class="form-control" placeholder="••••••">
          </div>
          <div class="mb-2">
            <label class="form-label small fw-600">Role</label>
            <select name="role" class="form-select">
              <option value="user" <?= $user['role']==='user'?'selected':'' ?>>user</option>
              <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>admin</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-600">Số dư (₫)</label>
            <input type="number" name="balance" class="form-control" value="<?= (int)($user['balance']??0) ?>" min="0">
          </div>
          <div class="mb-2">
            <label class="form-label small fw-600">Server IDs (mỗi dòng 1 ID)</label>
            <textarea name="server_ids" class="form-control" rows="4" placeholder="srv-abc&#10;srv-xyz"><?= h(implode("\n", $user['server_ids']??[])) ?></textarea>
            <div class="form-text">Các server này sẽ hiện trong dashboard và client panel của khách</div>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" name="active" id="active" class="form-check-input" <?= !empty($user['active'])?'checked':'' ?>>
              <label class="form-check-label" for="active">Tài khoản đang hoạt động</label>
            </div>
          </div>
          <button type="submit" name="save_user" class="btn btn-primary w-100"
            onclick="return confirm('Lưu thay đổi?')">💾 Lưu</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header bg-white fw-700">Lịch sử đơn hàng</div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>Mã đơn</th><th>Dịch vụ</th><th>Tổng</th><th>TT</th><th>Ngày</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($userOrders as $o): ?>
          <tr>
            <td><code style="font-size:10px"><?= h($o['id']) ?></code></td>
            <td class="small"><?= h($o['product_name']) ?></td>
            <td class="small fw-600"><?= fmtMoney($o['total']) ?></td>
            <td><?= orderStatusLabel($o['status']) ?></td>
            <td class="small text-muted"><?= fmtDate($o['created_at']) ?></td>
            <td><a href="/admin/order_edit.php?id=<?= h($o['id']) ?>" class="btn btn-xs btn-outline-secondary" style="font-size:11px;padding:2px 6px">→</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$userOrders): ?><tr><td colspan="6" class="text-center text-muted py-3">Chưa có đơn</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
