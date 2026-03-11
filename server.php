<?php
/**
 * server.php — Trang điều khiển server của khách hàng
 * Giao tiếp với Node.js manager qua bridgeCall() nội bộ
 * Khách KHÔNG bao giờ thấy địa chỉ Node.js manager
 */
require_once __DIR__ . '/includes/config.php';
Auth::requireLogin();

$user  = Auth::user();
$uid   = $user['id'];
$sid   = trim($_GET['id'] ?? '');

// Kiểm tra quyền truy cập server này
if (!$sid || !in_array($sid, $user['server_ids'] ?? [])) {
    flash('error', 'Bạn không có quyền truy cập server này');
    redirect('/dashboard.php');
}

// ── Xử lý AJAX (realtime polling) ──────────────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    switch ($action) {
        case 'status':
            $r = bridgeCall('/api/client/server/' . rawurlencode($sid), 'GET', [], $uid, $user['server_ids'] ?? []);
            echo json_encode($r);
            break;
        case 'logs':
            $limit = min((int)($_GET['limit'] ?? 150), 500);
            $r = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/logs?limit=' . $limit, 'GET', [], $uid, $user['server_ids'] ?? []);
            echo json_encode($r);
            break;
        case 'files':
            $path = $_GET['path'] ?? '';
            $r = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/files?path=' . urlencode($path), 'GET', [], $uid, $user['server_ids'] ?? []);
            echo json_encode($r);
            break;
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

// ── Xử lý POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'start') {
        $r = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/start', 'POST', [], $uid, $user['server_ids'] ?? []);
        flash($r['error'] ?? ($r['_err'] ?? null) ? 'error' : 'success',
              $r['message'] ?? ($r['error'] ?? ($r['_err'] ?? 'Đã gửi lệnh khởi động')));
    }
    if ($act === 'stop') {
        $r = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/stop', 'POST', [], $uid, $user['server_ids'] ?? []);
        flash($r['error'] ?? ($r['_err'] ?? null) ? 'error' : 'success',
              $r['message'] ?? ($r['error'] ?? ($r['_err'] ?? 'Đã gửi lệnh dừng')));
    }
    if ($act === 'restart') {
        $r = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/restart', 'POST', [], $uid, $user['server_ids'] ?? []);
        flash($r['error'] ?? ($r['_err'] ?? null) ? 'error' : 'success',
              $r['message'] ?? ($r['error'] ?? ($r['_err'] ?? 'Đang khởi động lại…')));
    }
    if ($act === 'clear_log') {
        bridgeCall('/api/client/server/' . rawurlencode($sid) . '/logs', 'DELETE', [], $uid, $user['server_ids'] ?? []);
        flash('success', '✓ Đã xoá log');
    }
    if ($act === 'save_file') {
        $fp = $_POST['fp'] ?? '';
        if (!preg_match('/\.(json|txt)$/i', $fp)) {
            flash('error', 'Chỉ được sửa file .json hoặc .txt');
        } else {
            $r = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/files/write', 'POST',
                ['filePath' => $fp, 'content' => $_POST['content'] ?? ''], $uid, $user['server_ids'] ?? []);
            flash(empty($r['success']) ? 'error' : 'success',
                  empty($r['success']) ? ($r['error'] ?? 'Lỗi khi lưu') : '✓ Đã lưu file');
        }
    }
    if ($act === 'del_file') {
        $fp = $_POST['fp'] ?? '';
        $r  = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/files', 'DELETE', ['path' => $fp], $uid, $user['server_ids'] ?? []);
        flash(empty($r['success']) ? 'error' : 'success',
              empty($r['success']) ? ($r['error'] ?? 'Lỗi') : '✓ Đã xoá');
    }
    redirect('/server.php?id=' . urlencode($sid) . (!empty($_GET['tab']) ? '&tab=' . h($_GET['tab']) : ''));
}

// ── Lấy thông tin server ────────────────────────────────────
$srv = bridgeCall('/api/client/server/' . rawurlencode($sid), 'GET', [], $uid, $user['server_ids'] ?? []);
if (empty($srv) || !empty($srv['error']) || !empty($srv['_err'])) {
    flash('error', 'Không thể kết nối tới server. Vui lòng thử lại sau.');
    $srv = ['id' => $sid, 'name' => $sid, 'status' => 'unknown', 'port' => null];
}

$tab      = $_GET['tab'] ?? 'control';
$editFile = $_GET['edit'] ?? '';
$cwd      = trim($_GET['path'] ?? '', '/');

// Lấy dữ liệu theo tab
$logs    = [];
$fmItems = [];
$fContent = '';
$fSize    = 0;

if ($tab === 'logs') {
    $lr   = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/logs?limit=200', 'GET', [], $uid, $user['server_ids'] ?? []);
    $logs = $lr['logs'] ?? [];
}
if ($tab === 'files') {
    if ($editFile) {
        if (preg_match('/\.(json|txt)$/i', $editFile)) {
            $fr = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/files/read?path=' . urlencode($editFile), 'GET', [], $uid, $user['server_ids'] ?? []);
            $fContent = $fr['content'] ?? '';
            $fSize    = $fr['size']    ?? 0;
        }
    } else {
        $fr      = bridgeCall('/api/client/server/' . rawurlencode($sid) . '/files?path=' . urlencode($cwd), 'GET', [], $uid, $user['server_ids'] ?? []);
        $fmItems = $fr['items'] ?? [];
    }
}

$statusColor = match($srv['status'] ?? '') {
    'running'  => 'success',
    'stopped'  => 'secondary',
    'crashed'  => 'danger',
    default    => 'warning',
};
$statusLabel = match($srv['status'] ?? '') {
    'running'  => '● Đang chạy',
    'stopped'  => '■ Đã dừng',
    'crashed'  => '✕ Crashed',
    default    => '? Không rõ',
};

$pageTitle = 'Server — ' . ($srv['name'] ?? $sid);

// ── Server maintenance check ─────────────────────────────────────────────
$srvMaintenance = isServerUnderMaintenance($sid);

include __DIR__ . '/includes/header.php';

// Show maintenance banner if applicable
if ($srvMaintenance && !Auth::isAdmin()): ?>
<div class="alert alert-warning rounded-0 border-0 border-bottom mb-0 py-3" style="background:linear-gradient(90deg,#fef3c7,#fde68a)">
  <div class="container d-flex align-items-center gap-3">
    <span style="font-size:1.8rem">🔧</span>
    <div>
      <div class="fw-800 text-amber-900">Server đang trong thời gian bảo trì</div>
      <?php if (!empty($srvMaintenance['reason'])): ?>
      <div style="font-size:13px"><?= h($srvMaintenance['reason']) ?></div>
      <?php endif; ?>
      <div style="font-size:12px;color:#92400e">Bắt đầu từ: <?= fmtDate($srvMaintenance['since']??'') ?></div>
    </div>
  </div>
</div>
<?php endif; ?><?php
?>

<div class="breadcrumb-bar">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active"><?= h($srv['name'] ?? $sid) ?></li>
      </ol>
    </nav>
  </div>
</div>

<div class="container py-4">

  <!-- Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
      <div class="rounded-2 bg-primary text-white d-flex align-items-center justify-content-center"
           style="width:44px;height:44px;font-size:1.3rem">
        <i class="bi bi-server"></i>
      </div>
      <div>
        <h4 class="fw-800 mb-0"><?= h($srv['name'] ?? $sid) ?></h4>
        <div class="d-flex align-items-center gap-2 mt-1">
          <span class="badge bg-<?= $statusColor ?>" id="srv-badge"><?= $statusLabel ?></span>
          <?php if ($srv['port'] ?? null): ?>
          <span class="badge bg-light text-dark border" style="font-family:monospace">:<?= h($srv['port']) ?></span>
          <?php endif; ?>
          <?php
            $dl = $srv['daysLeft'] ?? null;
            if ($dl !== null):
              $expCls = $dl < 0 ? 'danger' : ($dl <= 7 ? 'warning' : 'success');
          ?>
          <span class="badge bg-<?= $expCls ?>">
            <?= $dl < 0 ? 'Hết hạn' : ($dl === 0 ? 'Hết hạn hôm nay' : "Còn {$dl} ngày") ?>
          </span>
          <?php endif; ?>
          <span class="text-muted" style="font-size:11px;font-family:monospace" id="live-ping">…</span>
        </div>
      </div>
    </div>
    <!-- Quick control buttons always visible -->
    <div class="d-flex gap-2 flex-wrap" id="ctrl-btns">
      <?php if (($srv['status'] ?? '') === 'running'): ?>
        <form method="POST"><input type="hidden" name="act" value="stop">
          <button class="btn btn-danger btn-sm" onclick="return confirm('Tắt server?')"><i class="bi bi-stop-fill me-1"></i>Tắt</button>
        </form>
        <form method="POST"><input type="hidden" name="act" value="restart">
          <button class="btn btn-warning btn-sm text-dark"><i class="bi bi-arrow-counterclockwise me-1"></i>Restart</button>
        </form>
      <?php else: ?>
        <form method="POST"><input type="hidden" name="act" value="start">
          <button class="btn btn-success btn-sm"><i class="bi bi-play-fill me-1"></i>Bật</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-4" style="border-bottom:2px solid #e5e7eb">
    <li class="nav-item">
      <a class="nav-link <?= $tab==='control'?'active':'' ?>" href="?id=<?= urlencode($sid) ?>&tab=control">
        <i class="bi bi-sliders me-1"></i>Điều khiển
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tab==='logs'?'active':'' ?>" href="?id=<?= urlencode($sid) ?>&tab=logs">
        <i class="bi bi-terminal me-1"></i>Console Log
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tab==='files'?'active':'' ?>" href="?id=<?= urlencode($sid) ?>&tab=files">
        <i class="bi bi-folder2-open me-1"></i>File Manager
      </a>
    </li>
  </ul>

  <?php /* ══════════════════════════════ TAB: CONTROL ══════════════════════════════ */ ?>
  <?php if ($tab === 'control'): ?>
  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-700 border-0 pt-3">
          <i class="bi bi-sliders me-2 text-primary"></i>Điều khiển server
        </div>
        <div class="card-body">
          <!-- Server controls -->
          <div class="d-flex gap-2 flex-wrap mb-4">
            <?php if (($srv['status'] ?? '') === 'running'): ?>
            <form method="POST"><input type="hidden" name="act" value="stop">
              <button class="btn btn-danger" onclick="return confirm('Tắt server này?')">
                <i class="bi bi-stop-fill me-1"></i>Tắt server
              </button>
            </form>
            <form method="POST"><input type="hidden" name="act" value="restart">
              <button class="btn btn-warning text-dark">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Restart
              </button>
            </form>
            <?php else: ?>
            <form method="POST"><input type="hidden" name="act" value="start">
              <button class="btn btn-success btn-lg">
                <i class="bi bi-play-fill me-2"></i>Bật server
              </button>
            </form>
            <?php endif; ?>
          </div>

          <!-- Server info -->
          <table class="table table-sm table-borderless mb-0">
            <tbody>
              <tr>
                <td class="text-muted" style="font-size:12px;width:40%">Server ID</td>
                <td><code style="font-size:11px"><?= h($sid) ?></code></td>
              </tr>
              <tr>
                <td class="text-muted" style="font-size:12px">Trạng thái</td>
                <td><span id="info-status"><?= $statusLabel ?></span></td>
              </tr>
              <tr>
                <td class="text-muted" style="font-size:12px">PID</td>
                <td><code style="font-size:11px" id="info-pid"><?= h($srv['pid'] ?? '—') ?></code></td>
              </tr>
              <?php if ($srv['port'] ?? null): ?>
              <tr>
                <td class="text-muted" style="font-size:12px">Port</td>
                <td><code style="font-size:11px">:<?= h($srv['port']) ?></code></td>
              </tr>
              <?php endif; ?>
              <?php if ($srv['expiresAt'] ?? null): ?>
              <tr>
                <td class="text-muted" style="font-size:12px">Hết hạn</td>
                <td style="font-size:12px"><?= date('d/m/Y', strtotime($srv['expiresAt'])) ?></td>
              </tr>
              <?php endif; ?>
              <?php if ($srv['note'] ?? null): ?>
              <tr>
                <td class="text-muted" style="font-size:12px">Ghi chú</td>
                <td style="font-size:12px"><?= h($srv['note']) ?></td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <!-- Log preview -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between pt-3">
          <span class="fw-700"><i class="bi bi-terminal me-2 text-success"></i>Log gần đây</span>
          <a href="?id=<?= urlencode($sid) ?>&tab=logs" class="btn btn-sm btn-outline-secondary">Xem đầy đủ →</a>
        </div>
        <div class="card-body p-0">
          <div id="logbox-mini" style="
            background:#0d1117;color:#7bb8ff;font-family:monospace;font-size:11.5px;
            padding:14px 16px;min-height:180px;max-height:280px;overflow-y:auto;
            white-space:pre-wrap;word-break:break-all;border-radius:0 0 10px 10px;
            line-height:1.7
          ">
            <span style="color:#4d6690;font-style:italic">Đang tải log…</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php /* ══════════════════════════════ TAB: LOGS ══════════════════════════════ */ ?>
  <?php elseif ($tab === 'logs'): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex flex-wrap align-items-center gap-2 justify-content-between pt-3">
      <span class="fw-700"><i class="bi bi-terminal me-2 text-success"></i>Console Output</span>
      <div class="d-flex gap-2 flex-wrap">
        <select id="log-limit" onchange="fetchLog()" class="form-select form-select-sm" style="width:auto">
          <option value="100">100 dòng</option>
          <option value="200" selected>200 dòng</option>
          <option value="500">500 dòng</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary" id="auto-btn" onclick="toggleAuto()">
          <i class="bi bi-pause-fill me-1"></i>Dừng
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="scrollBot()">
          <i class="bi bi-arrow-down me-1"></i>Cuối
        </button>
        <form method="POST" onsubmit="return confirm('Xoá toàn bộ log?')" style="display:inline">
          <input type="hidden" name="act" value="clear_log">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Xoá log</button>
        </form>
      </div>
    </div>
    <div id="logbox" style="
      background:#0d1117;color:#7bb8ff;font-family:monospace;font-size:12px;
      padding:16px 18px;min-height:500px;max-height:700px;overflow-y:auto;
      white-space:pre-wrap;word-break:break-all;line-height:1.8;
      border-radius:0 0 10px 10px
    ">
      <span style="color:#4d6690;font-style:italic">Đang tải log…</span>
    </div>
    <div class="card-footer bg-white border-0 d-flex align-items-center gap-2" style="font-size:11px;color:#6b7280">
      <span id="log-count"></span>
      <span class="ms-auto" id="log-live-dot">●</span> <span id="log-live-txt">live</span>
    </div>
  </div>

  <script>
  const SID='<?= h($sid) ?>';
  let autoRef=true, logTimer=null;
  function esc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
  function scrollBot(){const b=document.getElementById('logbox');b.scrollTop=b.scrollHeight;}
  function toggleAuto(){
    autoRef=!autoRef;
    document.getElementById('auto-btn').innerHTML=autoRef?
      '<i class="bi bi-pause-fill me-1"></i>Dừng':'<i class="bi bi-play-fill me-1"></i>Resume';
  }
  function colorLine(l){
    if(l.includes('[ERR]')) return `<span style="color:#ff6b6b">${esc(l)}</span>`;
    if(l.includes('[SYS]')) return `<span style="color:#ffc554">${esc(l)}</span>`;
    return `<span style="color:#7bb8ff">${esc(l)}</span>`;
  }
  async function fetchLog(){
    if(!autoRef) return;
    const lim=document.getElementById('log-limit').value;
    try {
      const r=await fetch(`?id=${SID}&ajax=logs&limit=${lim}`).then(x=>x.json());
      const logs=r.logs||[];
      const box=document.getElementById('logbox');
      const atBot=box.scrollHeight-box.scrollTop-box.clientHeight<80;
      document.getElementById('log-count').textContent=logs.length?logs.length+' dòng':'';
      if(!logs.length){box.innerHTML='<span style="color:#4d6690;font-style:italic">— Chưa có log —</span>';return;}
      box.innerHTML=logs.map(colorLine).join('\n');
      if(atBot) box.scrollTop=box.scrollHeight;
      document.getElementById('log-live-dot').style.color='#22c55e';
      document.getElementById('log-live-txt').textContent='live ✓';
    } catch(e){
      document.getElementById('log-live-dot').style.color='#ef4444';
      document.getElementById('log-live-txt').textContent='offline';
    }
  }
  fetchLog(); scrollBot();
  setInterval(fetchLog, 2500);
  </script>

  <?php /* ══════════════════════════════ TAB: FILES ══════════════════════════════ */ ?>
  <?php elseif ($tab === 'files'): ?>

  <?php if ($editFile): ?>
  <?php $isJson = preg_match('/\.json$/i', $editFile); ?>
  <!-- ── EDITOR TOOLBAR ── -->
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="?id=<?= urlencode($sid) ?>&tab=files&path=<?= urlencode($cwd) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <span class="badge bg-primary" style="font-family:monospace;font-size:11px"><?= h($editFile) ?></span>
    <span class="text-muted" style="font-size:11px"><?= number_format($fSize) ?> bytes</span>
    <?php if ($isJson): ?>
    <div class="ms-auto d-flex align-items-center gap-1 bg-light border rounded px-1" style="font-size:12px">
      <button id="btn-simple" onclick="setMode('simple')" class="btn btn-xs px-2 py-1" style="font-size:11px;border-radius:4px;background:#2563eb;color:#fff">
        <i class="bi bi-ui-checks me-1"></i>Simple
      </button>
      <button id="btn-raw" onclick="setMode('raw')" class="btn btn-xs px-2 py-1 btn-light" style="font-size:11px;border-radius:4px">
        <i class="bi bi-code-slash me-1"></i>Raw JSON
      </button>
    </div>
    <?php else: ?>
    <span class="ms-auto text-muted" style="font-size:11px">
      <i class="bi bi-lock me-1 text-warning"></i>Chỉ có thể sửa .json / .txt
    </span>
    <?php endif; ?>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-dark border-0 d-flex align-items-center gap-2 py-2">
      <i class="bi bi-file-earmark-code text-info"></i>
      <span class="text-light" style="font-family:monospace;font-size:12px"><?= h($editFile) ?></span>
      <div class="ms-auto d-flex gap-2">
        <form method="POST" id="save-form">
          <input type="hidden" name="act"     value="save_file">
          <input type="hidden" name="fp"      value="<?= h($editFile) ?>">
          <input type="hidden" name="content" id="save-content">
          <button type="submit" class="btn btn-success btn-sm" id="save-btn" onclick="prepareSubmit()">
            <i class="bi bi-floppy me-1"></i>Lưu <kbd class="bg-success border-0" style="font-size:9px">Ctrl+S</kbd>
          </button>
        </form>
        <a href="?id=<?= urlencode($sid) ?>&tab=files&path=<?= urlencode($cwd) ?>" class="btn btn-secondary btn-sm">Hủy</a>
      </div>
    </div>

    <?php if ($isJson): ?>
    <!-- SIMPLE MODE panel -->
    <div id="panel-simple" class="card-body" style="background:#f8fafc;min-height:200px">
      <div id="simple-fields"></div>
      <div id="simple-error" class="alert alert-warning py-2 small mt-3" style="display:none">
        <i class="bi bi-exclamation-triangle me-1"></i><span id="simple-error-msg"></span>
        <button type="button" class="btn btn-xs btn-outline-warning ms-2" onclick="setMode('raw')" style="font-size:11px">Chuyển sang Raw</button>
      </div>
    </div>
    <?php endif; ?>

    <!-- RAW MODE panel -->
    <div id="panel-raw" class="card-body p-0" <?= $isJson ? 'style="display:none"' : '' ?>>
      <textarea id="editor" spellcheck="false" style="
        width:100%;min-height:500px;background:#0d1117;color:#93c5fd;
        font-family:monospace;font-size:13px;padding:16px 18px;
        border:none;outline:none;resize:vertical;line-height:1.8;tab-size:2;
        border-radius:0 0 10px 10px;display:block
      "><?= htmlspecialchars($fContent, ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>
  </div>

  <style>
  .sf-label { font-family:monospace; font-size:12px; font-weight:600; color:#475569; margin-bottom:3px; display:block; }
  .sf-section { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin-bottom:12px; }
  .sf-section-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
  .sf-array-item { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px; margin-bottom:8px; position:relative; }
  .sf-array-item .del-btn { position:absolute; top:6px; right:8px; }
  .type-badge { font-size:9px; padding:1px 6px; border-radius:10px; font-weight:600; vertical-align:middle; }
  </style>

  <script>
  const IS_JSON   = <?= $isJson ? 'true' : 'false' ?>;
  let   curMode   = IS_JSON ? 'simple' : 'raw';
  let   parsedObj = null;

  // ── Mode switch ────────────────────────────────────────────────────────────
  function setMode(mode) {
    if (mode === 'raw' && curMode === 'simple') {
      const rebuilt = collectSimple();
      if (rebuilt !== null) document.getElementById('editor').value = JSON.stringify(rebuilt, null, 2);
    }
    if (mode === 'simple' && curMode === 'raw') {
      try {
        parsedObj = JSON.parse(document.getElementById('editor').value);
        renderSimple(parsedObj);
        clearErr();
      } catch(e) { showErr('JSON không hợp lệ: ' + e.message); return; }
    }
    curMode = mode;
    document.getElementById('panel-simple').style.display = mode==='simple' ? '' : 'none';
    document.getElementById('panel-raw').style.display    = mode==='raw'    ? '' : 'none';
    const bs = document.getElementById('btn-simple');
    const br = document.getElementById('btn-raw');
    bs.style.cssText = 'font-size:11px;border-radius:4px;' + (mode==='simple' ? 'background:#2563eb;color:#fff;border:1px solid #2563eb' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0');
    br.style.cssText = 'font-size:11px;border-radius:4px;' + (mode==='raw'    ? 'background:#2563eb;color:#fff;border:1px solid #2563eb' : 'background:#f1f5f9;color:#374151;border:1px solid #e2e8f0');
  }

  function prepareSubmit() {
    if (curMode === 'simple') {
      const v = collectSimple();
      if (v === null) { event.preventDefault(); return false; }
      document.getElementById('save-content').value = JSON.stringify(v, null, 2);
    } else {
      document.getElementById('save-content').value = document.getElementById('editor').value;
    }
  }

  // ── Render simple fields ───────────────────────────────────────────────────
  function renderSimple(data) {
    const c = document.getElementById('simple-fields');
    c.innerHTML = '';
    parsedObj   = data;
    clearErr();
    if (Array.isArray(data)) {
      c.appendChild(buildArrayWidget(data, [], 'root'));
    } else if (data && typeof data === 'object') {
      buildObjectFields(c, data, []);
    } else {
      showErr('JSON không phải object/array — chuyển sang Raw Mode.');
      setMode('raw');
    }
  }

  function buildObjectFields(container, obj, path) {
    Object.entries(obj).forEach(([key, val]) => {
      container.appendChild(buildField(key, val, [...path, key]));
    });
  }

  function buildField(label, val, path) {
    if (val === null || val === undefined) return buildInput(label, val ?? '', path, 'text');
    if (Array.isArray(val))  return buildArraySection(label, val, path);
    if (typeof val === 'object') return buildObjectSection(label, val, path);
    if (typeof val === 'boolean') return buildBoolField(label, val, path);
    if (typeof val === 'number')  return buildInput(label, val, path, 'number');
    const isLong = String(val).length > 80 || String(val).includes('\n');
    return buildInput(label, val, path, isLong ? 'textarea' : 'text');
  }

  function buildObjectSection(label, obj, path) {
    const sec = mk('div','sf-section');
    const ttl = mk('div','sf-section-title');
    ttl.innerHTML = `<i class="bi bi-braces" style="color:#94a3b8"></i>${esc(label)} <span class="type-badge" style="background:#eff6ff;color:#2563eb">object</span>`;
    sec.appendChild(ttl);
    buildObjectFields(sec, obj, path);
    return sec;
  }

  function buildArraySection(label, arr, path) {
    const sec = mk('div','sf-section');
    const ttl = mk('div','d-flex align-items-center gap-2 mb-2');
    ttl.innerHTML = `<span class="sf-section-title mb-0"><i class="bi bi-list-ul me-1" style="color:#94a3b8"></i>${esc(label)} <span class="type-badge" style="background:#f0fdf4;color:#16a34a">${arr.length} items</span></span>`;
    const addBtn = mk('button','btn btn-xs ms-auto');
    addBtn.type='button'; addBtn.style.cssText='font-size:10px;padding:2px 8px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:4px';
    addBtn.innerHTML='<i class="bi bi-plus-lg me-1"></i>Thêm';
    addBtn.onclick = () => addArrayItem(path);
    ttl.appendChild(addBtn);
    sec.appendChild(ttl);
    sec.appendChild(buildArrayWidget(arr, path, label));
    return sec;
  }

  function buildArrayWidget(arr, path, label) {
    const list = mk('div','array-list');
    list.dataset.path = JSON.stringify(path);
    arr.forEach((item, idx) => list.appendChild(buildArrayItem(item, idx, path)));
    return list;
  }

  function buildArrayItem(item, idx, path) {
    const wrap = mk('div','sf-array-item');
    const idxLbl = mk('div','mb-2');
    idxLbl.innerHTML = `<span style="font-family:monospace;font-size:10px;color:#94a3b8;font-weight:700">[${idx}]</span>`;
    wrap.appendChild(idxLbl);

    if (item && typeof item === 'object' && !Array.isArray(item)) {
      buildObjectFields(wrap, item, [...path, idx]);
    } else {
      wrap.appendChild(buildField(String(idx), item, [...path, idx]));
    }

    const del = mk('button','btn del-btn');
    del.type='button'; del.innerHTML='<i class="bi bi-trash"></i>';
    del.style.cssText='font-size:10px;padding:2px 6px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px';
    del.onclick = () => { removeArrayItem(path, idx); };
    wrap.appendChild(del);
    return wrap;
  }

  function buildInput(label, val, path, type) {
    const wrap = mk('div','mb-3');
    const lbl  = mk('label','sf-label'); lbl.textContent = label;
    wrap.appendChild(lbl);
    let inp;
    if (type === 'textarea') {
      inp = mk('textarea','form-control form-control-sm');
      inp.rows=3; inp.value=String(val??'');
      inp.style.cssText='font-family:monospace;font-size:12px;resize:vertical';
    } else {
      inp = mk('input','form-control form-control-sm');
      inp.type  = type==='number' ? 'number' : 'text';
      inp.value = val ?? '';
      if (type==='number') inp.step='any';
    }
    inp.dataset.jpath = JSON.stringify(path);
    inp.addEventListener('input', () => {
      const p = JSON.parse(inp.dataset.jpath);
      setAtPath(parsedObj, p, type==='number' ? (inp.value==='' ? null : Number(inp.value)) : inp.value);
    });
    wrap.appendChild(inp);
    return wrap;
  }

  function buildBoolField(label, val, path) {
    const wrap = mk('div','mb-3 d-flex align-items-center gap-3');
    const lbl  = mk('span','sf-label mb-0'); lbl.textContent = label;
    const grp  = mk('div','d-flex gap-1');
    ['true','false'].forEach(opt => {
      const isActive = String(val) === opt;
      const btn = mk('button','btn btn-xs');
      btn.type='button'; btn.textContent=opt;
      btn.style.cssText = 'font-size:11px;padding:2px 10px;border-radius:4px;' + (isActive
        ? (opt==='true' ? 'background:#dcfce7;color:#16a34a;border:1px solid #86efac' : 'background:#fee2e2;color:#dc2626;border:1px solid #fca5a5')
        : 'background:#f1f5f9;color:#6b7280;border:1px solid #e2e8f0');
      btn.onclick = () => { setAtPath(parsedObj, path, opt==='true'); renderSimple(parsedObj); };
      grp.appendChild(btn);
    });
    wrap.appendChild(lbl); wrap.appendChild(grp);
    return wrap;
  }

  // ── Array add/remove ───────────────────────────────────────────────────────
  function addArrayItem(path) {
    const arr = getAtPath(parsedObj, path);
    if (!Array.isArray(arr)) return;
    if (arr.length > 0 && typeof arr[0] === 'object' && !Array.isArray(arr[0])) {
      const tpl = {};
      Object.keys(arr[0]).forEach(k => { tpl[k] = typeof arr[0][k] === 'number' ? 0 : typeof arr[0][k] === 'boolean' ? false : ''; });
      arr.push(tpl);
    } else {
      arr.push(arr.length > 0 ? (typeof arr[0] === 'number' ? 0 : '') : '');
    }
    renderSimple(parsedObj);
  }

  function removeArrayItem(path, idx) {
    const arr = getAtPath(parsedObj, path);
    if (Array.isArray(arr)) { arr.splice(idx, 1); renderSimple(parsedObj); }
  }

  // ── Deep path get/set ──────────────────────────────────────────────────────
  function getAtPath(obj, path) {
    let cur = obj;
    for (const k of path) { if (cur == null) return undefined; cur = cur[k]; }
    return cur;
  }
  function setAtPath(obj, path, value) {
    let cur = obj;
    for (let i = 0; i < path.length - 1; i++) { if (cur == null) return; cur = cur[path[i]]; }
    if (cur != null) cur[path[path.length-1]] = value;
  }

  function collectSimple() {
    try { JSON.parse(JSON.stringify(parsedObj)); return parsedObj; }
    catch(e) { showErr('Lỗi serialize: ' + e.message); return null; }
  }

  function mk(tag, cls, html) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    if (html !== undefined) el.innerHTML = html;
    return el;
  }
  function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function showErr(msg) { const e=document.getElementById('simple-error'); document.getElementById('simple-error-msg').textContent=msg; e.style.display=''; }
  function clearErr()   { document.getElementById('simple-error').style.display='none'; }

  // ── Raw editor keyboard ────────────────────────────────────────────────────
  document.getElementById('editor').addEventListener('keydown', function(e){
    if(e.key==='Tab'){
      e.preventDefault();
      const s=this.selectionStart;
      this.value=this.value.slice(0,s)+'  '+this.value.slice(this.selectionEnd);
      this.selectionStart=this.selectionEnd=s+2;
    }
    if((e.ctrlKey||e.metaKey)&&e.key==='s'){
      e.preventDefault();
      prepareSubmit();
      document.getElementById('save-form').submit();
    }
  });

  // ── Init ───────────────────────────────────────────────────────────────────
  if (IS_JSON) {
    try {
      parsedObj = JSON.parse(<?= json_encode($fContent) ?>);
      renderSimple(parsedObj);
    } catch(e) {
      showErr('Không thể parse JSON: ' + e.message);
      setMode('raw');
    }
  }
  </script>

  <?php else: ?>
  <!-- ── FILE BROWSER ── -->
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <!-- Breadcrumb -->
    <nav style="font-size:12px;font-family:monospace" class="flex-grow-1">
      <a href="?id=<?= urlencode($sid) ?>&tab=files" class="text-primary text-decoration-none">
        <i class="bi bi-house-fill"></i> <?= h($srv['name'] ?? $sid) ?>
      </a>
      <?php if ($cwd):
        $parts = explode('/', $cwd); $built = '';
        foreach ($parts as $pt) {
          if (!$pt) continue;
          $built = $built ? "$built/$pt" : $pt;
          echo ' <span class="text-muted">/</span> <a href="?id='.urlencode($sid).'&tab=files&path='.urlencode($built).'" class="text-primary text-decoration-none">'.h($pt).'</a>';
        }
      endif; ?>
    </nav>
    <?php if ($cwd): ?>
    <a href="?id=<?= urlencode($sid) ?>&tab=files&path=<?= urlencode(dirname($cwd) === '.' ? '' : dirname($cwd)) ?>"
       class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-up me-1"></i>Lên trên
    </a>
    <?php endif; ?>
  </div>

  <!-- Note -->
  <div class="alert alert-warning py-2 mb-3" style="font-size:12px">
    <i class="bi bi-shield-lock me-1"></i>
    <strong>File .js / .ts bị khoá</strong> — Chỉ được chỉnh sửa file <code>.json</code> và <code>.txt</code>.
    Các file khác chỉ xem cấu trúc.
  </div>

  <!-- File list -->
  <div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
      <?php if (empty($fmItems)): ?>
        <div class="list-group-item text-muted text-center py-4" style="font-size:13px">
          <i class="bi bi-folder2-open me-2"></i>Thư mục rỗng
        </div>
      <?php endif; ?>
      <?php foreach ($fmItems as $item):
        $isDir   = $item['type'] === 'dir';
        $name    = $item['name'];
        $isJS    = !$isDir && preg_match('/\.(js|mjs|cjs|ts|jsx|tsx)$/i', $name);
        $canEdit = !$isDir && !$isJS && preg_match('/\.(json|txt)$/i', $name);
        $itemPath = $item['path'];
        $browseUrl = "?id=" . urlencode($sid) . "&tab=files&path=" . urlencode($itemPath);
        $editUrl   = "?id=" . urlencode($sid) . "&tab=files&edit=" . urlencode($itemPath) . "&path=" . urlencode($cwd);
        $icon = $isDir ? 'bi-folder-fill text-warning' :
                ($isJS ? 'bi-file-earmark-lock text-secondary' :
                ($canEdit ? 'bi-file-earmark-text text-primary' : 'bi-file-earmark text-muted'));
      ?>
      <div class="list-group-item d-flex align-items-center gap-3 py-2 px-3
                  <?= $isDir ? 'list-group-item-action' : '' ?>"
           <?= $isDir ? "onclick=\"location.href='".h($browseUrl)."'\" style=\"cursor:pointer\"" : "" ?>>
        <i class="bi <?= $icon ?>" style="font-size:15px;flex-shrink:0"></i>
        <div class="flex-grow-1" style="min-width:0">
          <div class="d-flex align-items-center gap-2">
            <?php if ($isDir): ?>
              <a href="<?= h($browseUrl) ?>" class="fw-600 text-decoration-none"
                 style="font-family:monospace;font-size:13px" onclick="event.stopPropagation()">
                <?= h($name) ?>/
              </a>
            <?php else: ?>
              <span style="font-family:monospace;font-size:13px;<?= $isJS ? 'color:#9ca3af' : '' ?>">
                <?= h($name) ?>
              </span>
              <?php if ($isJS): ?>
                <span class="badge bg-light text-secondary border" style="font-size:9px">
                  <i class="bi bi-lock-fill me-1"></i>khoá
                </span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php if (!$isDir): ?>
          <div style="font-size:10px;color:#9ca3af">
            <?= $item['size'] >= 1048576 ? round($item['size']/1048576,1).'MB' :
               ($item['size'] >= 1024    ? round($item['size']/1024,1).'KB'   :
                                           $item['size'].' B') ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-1 flex-shrink-0" onclick="event.stopPropagation()">
          <?php if ($canEdit): ?>
            <a href="<?= h($editUrl) ?>" class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px">
              <i class="bi bi-pencil me-1"></i>Sửa
            </a>
          <?php endif; ?>
          <?php if (!$isDir): ?>
          <form method="POST" onsubmit="return confirm('Xoá <?= h(addslashes($name)) ?>?')" style="display:inline">
            <input type="hidden" name="act" value="del_file">
            <input type="hidden" name="fp"  value="<?= h($itemPath) ?>">
            <button class="btn btn-xs btn-outline-danger" style="font-size:11px;padding:2px 6px">
              <i class="bi bi-trash"></i>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="mt-3 d-flex flex-wrap gap-3" style="font-size:11px;color:#6b7280">
    <span><i class="bi bi-file-earmark-text text-primary me-1"></i>.json / .txt — <strong class="text-success">có thể sửa</strong></span>
    <span><i class="bi bi-file-earmark-lock text-secondary me-1"></i>.js / .ts — <strong class="text-warning">bị khoá</strong></span>
    <span><i class="bi bi-folder-fill text-warning me-1"></i>Thư mục — <strong>có thể duyệt</strong></span>
  </div>
  <?php endif; // end editFile/browser ?>

  <?php endif; // end tab ?>

</div>

<script>
// ── Realtime status polling ──
const SID = '<?= h($sid) ?>';
async function pollStatus(){
  try {
    const r = await fetch(`?id=${SID}&ajax=status`).then(x=>x.json());
    if(!r.status) return;
    // Update badge
    const badge = document.getElementById('srv-badge');
    if(badge){
      const map = {
        running:  ['bg-success','● Đang chạy'],
        stopped:  ['bg-secondary','■ Đã dừng'],
        crashed:  ['bg-danger','✕ Crashed'],
      };
      const [cls, txt] = map[r.status] || ['bg-warning','? Không rõ'];
      badge.className = 'badge ' + cls;
      badge.textContent = txt;
    }
    // Update PID
    const pid = document.getElementById('info-pid');
    if(pid) pid.textContent = r.pid || '—';
    const st = document.getElementById('info-status');
    if(st) st.textContent = r.status;
    // Live ping
    const ping = document.getElementById('live-ping');
    if(ping) ping.textContent = '● live';
  } catch(e){
    const ping = document.getElementById('live-ping');
    if(ping) ping.textContent = '○ offline';
  }
}

// ── Mini log preview (control tab) ──
async function loadMiniLog(){
  const box = document.getElementById('logbox-mini');
  if(!box) return;
  try {
    const r = await fetch(`?id=${SID}&ajax=logs&limit=40`).then(x=>x.json());
    const logs = r.logs || [];
    if(!logs.length){box.innerHTML='<span style="color:#4d6690;font-style:italic">— Chưa có log —</span>';return;}
    function esc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
    box.innerHTML = logs.map(l=>{
      if(l.includes('[ERR]')) return `<span style="color:#ff6b6b">${esc(l)}</span>`;
      if(l.includes('[SYS]')) return `<span style="color:#ffc554">${esc(l)}</span>`;
      return `<span>${esc(l)}</span>`;
    }).join('\n');
    box.scrollTop = box.scrollHeight;
  } catch(e){}
}

pollStatus();
loadMiniLog();
setInterval(pollStatus, 4000);
setInterval(loadMiniLog, 4000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
