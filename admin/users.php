<?php
// admin/users.php
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

// ── Tạo tài khoản khách ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username   = trim($_POST['username'] ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $password   = trim($_POST['password'] ?? '');
    $role       = in_array($_POST['role']??'',['user','admin']) ? $_POST['role'] : 'user';
    $server_ids = array_values(array_filter(array_map('trim', explode("\n", $_POST['server_ids']??''))));
    $balance    = max(0, (int)($_POST['balance'] ?? 0));

    $errs = [];
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username))
        $errs[] = 'Username chỉ gồm chữ, số, gạch dưới (3-20 ký tự)';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errs[] = 'Email không hợp lệ';
    if (strlen($password) < 6)
        $errs[] = 'Mật khẩu phải ít nhất 6 ký tự';
    if (DB::find('users','username',$username))
        $errs[] = 'Username đã tồn tại';
    if (DB::find('users','email',$email))
        $errs[] = 'Email đã được sử dụng';

    if ($errs) {
        flash('error', implode(' | ', $errs));
    } else {
        $uid = DB::nextId('usr_');
        DB::append('users', [
            'id'         => $uid,
            'username'   => $username,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'role'       => $role,
            'balance'    => $balance,
            'server_ids' => $server_ids,
            'active'     => true,
            'created_at' => date('c'),
            'last_login' => null,
        ]);
        flash('success', "✓ Đã tạo tài khoản {$username}");
    }
    redirect('/admin/users.php');
}

// ── Tạo magic link ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_magic'])) {
    $uid = trim($_POST['magic_uid'] ?? '');
    if ($uid && DB::find('users','id',$uid)) {
        $token   = bin2hex(random_bytes(24));
        $expires = time() + 7 * 24 * 3600;
        $links   = DB::read('magic_links', []);
        $links   = array_values(array_filter($links, fn($l) => $l['uid'] !== $uid));
        $links[] = ['token' => $token, 'uid' => $uid, 'expires' => $expires, 'created_at' => date('c')];
        DB::write('magic_links', $links);
        flash('success', "✓ Đã tạo magic link — Token: {$token}");
    }
    redirect('/admin/users.php');
}

// ── Xóa magic link ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_magic'])) {
    $uid   = trim($_POST['magic_uid'] ?? '');
    $links = DB::read('magic_links', []);
    $links = array_values(array_filter($links, fn($l) => $l['uid'] !== $uid));
    DB::write('magic_links', $links);
    flash('success', '✓ Đã xóa magic link');
    redirect('/admin/users.php');
}

$adminPageTitle = 'Người dùng';
require_once __DIR__ . '/header.php';

$users = DB::read('users', []);
usort($users, fn($a,$b) => strcmp($b['created_at'],$a['created_at']));

$allLinks = DB::read('magic_links', []);
$linksMap = [];
foreach ($allLinks as $l) {
    if ($l['expires'] > time()) $linksMap[$l['uid']] = $l;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <span class="text-muted small"><?= count($users) ?> người dùng</span>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateUser">
    <i class="bi bi-person-plus me-1"></i>Tạo tài khoản khách
  </button>
</div>

<div class="card mb-4">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr>
        <th>Username</th><th>Email</th><th>Role</th>
        <th>Số dư</th><th>Servers</th><th>TT</th>
        <th>Magic Link</th><th>Ngày tạo</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <?php $ml = $linksMap[$u['id']] ?? null; ?>
      <tr>
        <td class="fw-600"><?= h($u['username']) ?></td>
        <td class="small"><?= h($u['email']) ?></td>
        <td><?= $u['role']==='admin'?'<span class="badge bg-danger">Admin</span>':'<span class="badge bg-secondary">User</span>' ?></td>
        <td><?= fmtMoney($u['balance']??0) ?></td>
        <td><span class="badge bg-light text-dark border"><?= count($u['server_ids']??[]) ?></span></td>
        <td><?= !empty($u['active'])?'<span class="badge bg-success">✓</span>':'<span class="badge bg-danger">✗</span>' ?></td>
        <td>
          <?php if ($ml): ?>
            <div class="d-flex align-items-center gap-1">
              <span class="badge bg-success small" title="Hết hạn: <?= date('d/m/Y H:i',$ml['expires']) ?>">
                <i class="bi bi-link-45deg"></i> Có link
              </span>
              <button class="btn btn-outline-secondary" style="font-size:10px;padding:1px 5px"
                onclick="copyMagicLink('<?= h($ml['token']) ?>', this)" title="Copy link">
                <i class="bi bi-clipboard"></i>
              </button>
              <form method="POST" class="d-inline" onsubmit="return confirm('Xóa magic link?')">
                <input type="hidden" name="magic_uid" value="<?= h($u['id']) ?>">
                <button type="submit" name="del_magic" class="btn btn-outline-danger"
                  style="font-size:10px;padding:1px 5px" title="Xóa link">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          <?php else: ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="magic_uid" value="<?= h($u['id']) ?>">
              <button type="submit" name="gen_magic" class="btn btn-outline-primary"
                style="font-size:10px;padding:2px 7px" title="Tạo magic link đăng nhập 1 click">
                <i class="bi bi-magic me-1"></i>Tạo link
              </button>
            </form>
          <?php endif; ?>
        </td>
        <td class="small text-muted"><?= fmtDate($u['created_at']) ?></td>
        <td><a href="/admin/user_edit.php?id=<?= h($u['id']) ?>" class="btn btn-sm btn-outline-primary">Sửa</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Hướng dẫn Magic Link -->
<div class="card mb-4">
  <div class="card-header bg-white fw-700">
    <i class="bi bi-magic me-2 text-primary"></i>Hướng dẫn Magic Link
  </div>
  <div class="card-body">
    <div class="alert alert-info small py-2 mb-3">
      <strong>Magic Link</strong> cho phép khách đăng nhập 1 click mà không cần nhớ mật khẩu.
      Khi khách bấm vào link, sẽ hiện popup xác nhận đăng nhập → sau đó chuyển thẳng vào Dashboard.
      Mỗi link có hiệu lực <strong>7 ngày</strong>.
    </div>
    <div class="row g-3">
      <div class="col-md-7">
        <label class="form-label small fw-600">Dạng URL magic link:</label>
        <div class="input-group input-group-sm">
          <input type="text" class="form-control font-monospace" readonly
            value="<?= h(APP_URL) ?>/magic-login.php?token=TOKEN">
          <button class="btn btn-outline-secondary" onclick="copyText('<?= h(APP_URL) ?>/magic-login.php?token=TOKEN', this)">
            <i class="bi bi-clipboard"></i>
          </button>
        </div>
        <div class="form-text">Thay TOKEN bằng token thực tế của từng user</div>
      </div>
      <div class="col-md-5">
        <label class="form-label small fw-600">Embed trên trang ngoài:</label>
        <code class="d-block p-2 bg-light rounded small">
          &lt;a href="<?= h(APP_URL) ?>/magic-login.php?token=TOKEN"
            target="_blank"&gt;Truy cập Server&lt;/a&gt;
        </code>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tạo tài khoản -->
<div class="modal fade" id="modalCreateUser" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-700"><i class="bi bi-person-plus me-2"></i>Tạo tài khoản khách</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-600">Username *</label>
              <input type="text" name="username" class="form-control" required
                pattern="[a-zA-Z0-9_]{3,20}" placeholder="khach001">
              <div class="form-text">3-20 ký tự, chỉ chữ/số/gạch dưới</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Email *</label>
              <input type="email" name="email" class="form-control" required placeholder="khach@email.com">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Mật khẩu *</label>
              <div class="input-group">
                <input type="text" name="password" id="newPwInput" class="form-control" required
                  placeholder="Tối thiểu 6 ký tự">
                <button type="button" class="btn btn-outline-secondary" onclick="genPw()">
                  <i class="bi bi-arrow-clockwise"></i> Tạo ngẫu nhiên
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-600">Role</label>
              <select name="role" class="form-select">
                <option value="user" selected>👤 User (Khách hàng)</option>
                <option value="admin">🛡️ Admin</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-600">Số dư ban đầu (₫)</label>
              <input type="number" name="balance" class="form-control" value="0" min="0" step="1000">
            </div>
            <div class="col-12">
              <label class="form-label small fw-600">
                Server IDs được gán
                <span class="text-muted fw-400">(mỗi dòng 1 ID)</span>
              </label>
              <textarea name="server_ids" class="form-control font-monospace" rows="4"
                placeholder="srv-abc123&#10;srv-xyz456"></textarea>
              <div class="form-text">
                <i class="bi bi-info-circle me-1"></i>
                Server ID lấy từ <a href="/admin/servers.php" target="_blank">Quản lý Server</a>.
                Sau khi tạo, bạn có thể tạo <strong>Magic Link</strong> để khách đăng nhập 1 click.
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" name="create_user" class="btn btn-primary fw-700">
            <i class="bi bi-person-check me-1"></i>Tạo tài khoản
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function genPw() {
  const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789@#!';
  let pw = '';
  for (let i = 0; i < 12; i++) pw += chars[Math.floor(Math.random() * chars.length)];
  document.getElementById('newPwInput').value = pw;
}

function copyMagicLink(token, btn) {
  const url = '<?= h(APP_URL) ?>/magic-login.php?token=' + token;
  navigator.clipboard.writeText(url).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check"></i>';
    btn.classList.replace('btn-outline-secondary','btn-success');
    setTimeout(() => { btn.innerHTML = orig; btn.classList.replace('btn-success','btn-outline-secondary'); }, 2000);
  });
}

// Generate password on modal open
document.getElementById('modalCreateUser').addEventListener('show.bs.modal', () => genPw());
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
