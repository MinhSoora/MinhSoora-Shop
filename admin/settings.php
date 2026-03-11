<?php
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

// AJAX: toggle server maintenance
if (!empty($_GET['ajax']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if ($_GET['ajax'] === 'set_srv_maint') {
        $sid     = trim($_POST['sid']    ?? '');
        $enabled = !empty($_POST['enabled']);
        $reason  = trim($_POST['reason'] ?? '');
        if ($sid) {
            setServerMaintenance($sid, $enabled, $reason);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['error' => 'missing sid']);
        }
    }
    exit;
}

// Save settings — xử lý TRƯỚC khi output HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = DB::read('settings', []);
    foreach (['shop_name','shop_desc','shop_email','shop_phone','shop_address',
              'logo_url','banner_text','footer_text',
              'sepay_token','sepay_account_no','sepay_bank_code','sepay_webhook_secret'] as $k) {
        if (isset($_POST[$k])) $s[$k] = $_POST[$k];
    }
    $s['maintenance']    = isset($_POST['maintenance']);
    $s['allow_register'] = isset($_POST['allow_register']);
    DB::write('settings', $s);
    flash('success','✓ Đã lưu cài đặt');
    redirect('/admin/settings.php');
}

$adminPageTitle = 'Cài đặt hệ thống';
require_once __DIR__ . '/header.php';

$s = getSettings();
?>

<form method="POST">
<div class="row g-4">
  <div class="col-lg-6">
    <!-- Shop info -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-700">🏪 Thông tin cửa hàng</div>
      <div class="card-body">
        <?php foreach ([
          'shop_name'=>'Tên cửa hàng *','shop_desc'=>'Mô tả ngắn',
          'shop_email'=>'Email hỗ trợ','shop_phone'=>'Số điện thoại','shop_address'=>'Địa chỉ',
          'logo_url'=>'URL Logo (để trống = dùng icon mặc định)',
          'footer_text'=>'Text footer',
        ] as $k=>$lbl): ?>
        <div class="mb-2">
          <label class="form-label small fw-600"><?= $lbl ?></label>
          <input type="text" name="<?= $k ?>" class="form-control form-control-sm" value="<?= h($s[$k]??'') ?>">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Hệ thống -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-700">⚙️ Hệ thống</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label small fw-600">Banner thông báo <span class="text-muted">(hiện ở top web, cũng dùng khi bảo trì)</span></label>
          <input type="text" name="banner_text" class="form-control form-control-sm" value="<?= h($s['banner_text']??'') ?>" placeholder="Thông báo bảo trì, khuyến mãi...">
        </div>
        <div class="p-3 rounded-3 mb-2" style="background:<?= !empty($s['maintenance'])?'#fef2f2':'#f8fafc' ?>;border:1px solid <?= !empty($s['maintenance'])?'#fecaca':'#e2e8f0' ?>">
          <div class="form-check">
            <input type="checkbox" name="maintenance" id="maint" class="form-check-input" <?= !empty($s['maintenance'])?'checked':'' ?>>
            <label class="form-check-label fw-700 <?= !empty($s['maintenance'])?'text-danger':'' ?>" for="maint">
              🔧 Bật chế độ bảo trì TOÀN HỆ THỐNG
            </label>
            <div class="text-muted small mt-1">Chặn tất cả trang (trừ login, track-order). Admin vẫn vào bình thường.</div>
          </div>
        </div>
        <div class="form-check">
          <input type="checkbox" name="allow_register" id="areg" class="form-check-input" <?= !empty($s['allow_register'])?'checked':'' ?>>
          <label class="form-check-label small" for="areg">Cho phép đăng ký tài khoản mới</label>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <!-- Sepay -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-700">💳 Sepay — Thanh toán tự động</div>
      <div class="card-body">
        <div class="alert alert-info small py-2 mb-3">
          Đăng ký tại <a href="https://sepay.vn" target="_blank">sepay.vn</a>.
          Webhook URL: <code><?= h(APP_URL) ?>/webhook/sepay.php</code>
        </div>
        <?php foreach ([
          'sepay_token'=>'API Token',
          'sepay_account_no'=>'Số tài khoản ngân hàng',
          'sepay_bank_code'=>'Mã ngân hàng (MB, VCB, TCB...)',
          'sepay_webhook_secret'=>'Webhook Secret',
        ] as $k=>$lbl): ?>
        <div class="mb-2">
          <label class="form-label small fw-600"><?= $lbl ?></label>
          <input type="text" name="<?= $k ?>" class="form-control form-control-sm" value="<?= h($s[$k]??'') ?>">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- NodePanel -->
    <div class="card">
      <div class="card-header bg-white fw-700">🔗 NodePanel Manager</div>
      <div class="card-body">
        <div class="alert alert-success py-2 mb-2 small"><i class="bi bi-shield-lock-fill me-1"></i>Cấu hình cứng trong code — an toàn</div>
        <table class="table table-sm table-borderless mb-0" style="font-size:12px">
          <tr><td class="text-muted" style="width:35%">Manager URL</td><td><code><?= h(MANAGER_URL) ?></code></td></tr>
          <tr><td class="text-muted">Bridge Secret</td><td><code><?= str_repeat('●',8).substr(BRIDGE_SECRET,-4) ?></code> <span class="badge bg-success" style="font-size:10px">✓</span></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>
<div class="mt-3">
  <button type="submit" class="btn btn-primary px-5 fw-700">💾 Lưu cài đặt</button>
</div>
</form>

<!-- ═══ SERVER MAINTENANCE PANEL ═══════════════════════════════════════════ -->
<div class="card mt-5">
  <div class="card-header bg-white d-flex align-items-center justify-content-between">
    <span class="fw-700">🔧 Bảo trì Server (từng server)</span>
    <button class="btn btn-sm btn-outline-secondary" onclick="loadSrvList()">
      <i class="bi bi-arrow-clockwise me-1"></i>Làm mới
    </button>
  </div>
  <div class="card-body">
    <div class="alert alert-info small py-2 mb-3">
      Đánh dấu bảo trì từng server riêng lẻ — user sẽ thấy banner cảnh báo khi vào trang quản lý server đó.
      Không ảnh hưởng các server khác hoặc trang web nói chung.
    </div>
    <div id="srv-maint-list">
      <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> <span class="text-muted ms-2 small">Đang tải danh sách server...</span></div>
    </div>
  </div>
</div>

<script>
const MAINT_DATA = <?= json_encode(getServerMaintenanceList()) ?>;

async function loadSrvList() {
  const el = document.getElementById('srv-maint-list');
  el.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
  try {
    const csrf = <?= json_encode(Auth::csrfToken()) ?>;
    const r = await fetch('/admin/servers.php?ajax=list', {headers:{'X-CSRF-Token':csrf}}).then(x=>x.json());
    if (!Array.isArray(r) || !r.length) {
      el.innerHTML = '<div class="text-muted text-center py-4 small">Không có server nào. <a href="/admin/servers.php">Quản lý server</a></div>';
      return;
    }
    el.innerHTML = `<div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:13px">
        <thead><tr>
          <th>Tên server</th><th>Trạng thái</th><th>Bảo trì</th><th>Lý do / Thông báo</th><th></th>
        </tr></thead>
        <tbody>${r.map(s=>{
          const m = MAINT_DATA[s.id];
          const statCls = s.status==='running'?'success':s.status==='crashed'?'danger':s.status==='stopped'?'secondary':'warning';
          return `<tr>
            <td>
              <div class="fw-700">${esc(s.name)}</div>
              <div class="text-muted" style="font-size:11px">${esc(s.id)}</div>
            </td>
            <td><span class="badge bg-${statCls}">${esc(s.status||'?')}</span></td>
            <td>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="sm_${s.id}" ${m?'checked':''}
                  onchange="toggleMaint('${esc(s.id)}', this.checked)">
                <label class="form-check-label small" for="sm_${s.id}">${m?'<span class="text-danger fw-600">Đang BT</span>':'Bình thường'}</label>
              </div>
            </td>
            <td>
              <input type="text" id="sr_${s.id}" class="form-control form-control-sm"
                value="${esc(m?.reason||'')}" placeholder="Lý do bảo trì...">
            </td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="saveMaint('${esc(s.id)}')">Lưu</button>
            </td>
          </tr>`;
        }).join('')}</tbody>
      </table></div>`;
  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger small">Không thể tải server list: ${e.message}</div>`;
  }
}

async function toggleMaint(sid, enabled) {
  const reason = document.getElementById('sr_'+sid)?.value || '';
  const lbl = document.querySelector(`label[for="sm_${sid}"]`);
  if (lbl) lbl.innerHTML = enabled ? '<span class="text-danger fw-600">Đang BT</span>' : 'Bình thường';
  await doSaveMaint(sid, enabled, reason);
}

async function saveMaint(sid) {
  const enabled = document.getElementById('sm_'+sid)?.checked || false;
  const reason  = document.getElementById('sr_'+sid)?.value || '';
  await doSaveMaint(sid, enabled, reason);
  toast('✓ Đã lưu cài đặt bảo trì server');
}

async function doSaveMaint(sid, enabled, reason) {
  const fd = new FormData();
  fd.append('sid', sid); fd.append('enabled', enabled?'1':''); fd.append('reason', reason);
  await fetch('?ajax=set_srv_maint', {method:'POST', body:fd});
}

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function toast(msg) {
  const el = document.createElement('div');
  Object.assign(el.style, {position:'fixed',bottom:'24px',right:'24px',background:'#16a34a',color:'#fff',padding:'10px 20px',borderRadius:'10px',fontSize:'13px',zIndex:'9999',boxShadow:'0 4px 16px rgba(0,0,0,.25)',transition:'.3s'});
  el.textContent = msg; document.body.appendChild(el);
  setTimeout(()=>{ el.style.opacity='0'; setTimeout(()=>el.remove(),300); }, 2200);
}

loadSrvList();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
