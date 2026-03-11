<?php
/**
 * admin/bridge_log.php — Xem log kết nối PHP ↔ Node.js Bridge
 */
$adminPageTitle = 'Bridge Log — Kết nối Node.js';
require_once __DIR__ . '/header.php';

$logFile = BRIDGE_LOG;
$lines   = [];
$stats   = ['info' => 0, 'warn' => 0, 'error' => 0];

// Action: xoá log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'clear') {
    file_put_contents($logFile, '');
    flash('success', 'Đã xoá log bridge');
    redirect('/admin/bridge_log.php');
}

// Action: test kết nối ngay
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'test') {
    $r = bridgeCall('/health');
    if (isset($r['_err'])) {
        flash('error', '❌ Kết nối thất bại: ' . $r['_err']);
    } elseif (isset($r['ok'])) {
        flash('success', '✅ Kết nối thành công! Node.js uptime: ' . round($r['uptime']) . 's, version: ' . ($r['node'] ?? '?'));
    } else {
        flash('warning', '⚠ Phản hồi bất thường: ' . json_encode($r));
    }
    redirect('/admin/bridge_log.php');
}

// Đọc log
if (file_exists($logFile)) {
    $raw = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse(array_slice($raw, -300)); // Hiện 300 dòng gần nhất, mới nhất lên trên
    foreach ($raw as $l) {
        if (str_contains($l, '][INFO]'))  $stats['info']++;
        if (str_contains($l, '][WARN]'))  $stats['warn']++;
        if (str_contains($l, '][ERROR]')) $stats['error']++;
    }
}

$filter = $_GET['filter'] ?? 'all';
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h5 class="fw-700 mb-0"><i class="bi bi-plug me-2 text-primary"></i>Bridge Log — PHP ↔ Node.js</h5>
  <div class="ms-auto d-flex flex-wrap gap-2">
    <!-- Test connection -->
    <form method="POST" style="display:inline">
      <input type="hidden" name="act" value="test">
      <button class="btn btn-success btn-sm"><i class="bi bi-wifi me-1"></i>Test kết nối ngay</button>
    </form>
    <!-- Clear log -->
    <form method="POST" style="display:inline" onsubmit="return confirm('Xoá toàn bộ log?')">
      <input type="hidden" name="act" value="clear">
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Xoá log</button>
    </form>
  </div>
</div>

<?= renderFlash() ?>

<!-- Thông tin cấu hình -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="row g-3 align-items-center">
      <div class="col-auto">
        <span class="text-muted small">Manager URL:</span>
        <code class="ms-1"><?= h(MANAGER_URL) ?></code>
      </div>
      <div class="col-auto">
        <span class="text-muted small">Bridge Secret:</span>
        <code class="ms-1"><?= str_repeat('●', 8) . substr(BRIDGE_SECRET, -4) ?></code>
        <span class="badge bg-success ms-1" style="font-size:10px">✓ Set</span>
      </div>
      <div class="col-auto">
        <span class="text-muted small">Log file:</span>
        <code class="ms-1" style="font-size:11px"><?= h($logFile) ?></code>
      </div>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="row g-2 mb-3">
  <div class="col-4">
    <div class="card border-0 shadow-sm text-center py-2">
      <div style="font-size:1.4rem;font-weight:800;color:#22c55e"><?= $stats['info'] ?></div>
      <div class="text-muted" style="font-size:11px">INFO (thành công)</div>
    </div>
  </div>
  <div class="col-4">
    <div class="card border-0 shadow-sm text-center py-2">
      <div style="font-size:1.4rem;font-weight:800;color:#f59e0b"><?= $stats['warn'] ?></div>
      <div class="text-muted" style="font-size:11px">WARN (cảnh báo)</div>
    </div>
  </div>
  <div class="col-4">
    <div class="card border-0 shadow-sm text-center py-2">
      <div style="font-size:1.4rem;font-weight:800;color:#ef4444"><?= $stats['error'] ?></div>
      <div class="text-muted" style="font-size:11px">ERROR (lỗi)</div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="d-flex gap-2 mb-2" style="font-size:12px">
  <span class="text-muted">Lọc:</span>
  <?php foreach (['all'=>'Tất cả','error'=>'Lỗi','warn'=>'Cảnh báo','info'=>'Thành công'] as $v=>$lbl): ?>
  <a href="?filter=<?= $v ?>" class="badge <?= $filter===$v ? 'bg-primary' : 'bg-light text-dark border' ?> text-decoration-none"><?= $lbl ?></a>
  <?php endforeach; ?>
  <span class="ms-auto text-muted"><?= count($lines) ?> dòng gần nhất (mới nhất lên trên)</span>
</div>

<!-- Log output -->
<div class="card border-0 shadow-sm">
  <div style="background:#0d1117;border-radius:10px;padding:14px 16px;min-height:400px;max-height:700px;overflow-y:auto;font-family:monospace;font-size:12px;line-height:1.9">
    <?php if (empty($lines)): ?>
      <span style="color:#4d6690;font-style:italic">— Chưa có log. Nhấn "Test kết nối ngay" để kiểm tra. —</span>
    <?php else: ?>
      <?php foreach ($lines as $line):
        $isError = str_contains($line, '][ERROR]');
        $isWarn  = str_contains($line, '][WARN]');
        $isInfo  = str_contains($line, '][INFO]');
        if ($filter === 'error' && !$isError) continue;
        if ($filter === 'warn'  && !$isWarn)  continue;
        if ($filter === 'info'  && !$isInfo)  continue;
        $color = $isError ? '#ff6b6b' : ($isWarn ? '#ffc554' : '#4ade80');
        echo '<div style="color:' . $color . ';border-bottom:1px solid rgba(255,255,255,0.04);padding:1px 0">' . htmlspecialchars($line, ENT_QUOTES) . '</div>';
      endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="mt-2 text-muted" style="font-size:11px">
  <i class="bi bi-info-circle me-1"></i>
  Log được ghi tự động mỗi khi PHP gọi Node.js. File: <code><?= h($logFile) ?></code>
  &nbsp;·&nbsp; Tự động reload:
  <button class="btn btn-xs btn-outline-secondary ms-1" style="font-size:10px;padding:1px 6px" onclick="location.reload()">↻ Reload</button>
  <button class="btn btn-xs btn-outline-secondary ms-1" id="auto-reload-btn" style="font-size:10px;padding:1px 6px">⏱ Auto (5s)</button>
</div>

<script>
let autoTimer = null;
document.getElementById('auto-reload-btn').addEventListener('click', function() {
  if (autoTimer) {
    clearInterval(autoTimer);
    autoTimer = null;
    this.textContent = '⏱ Auto (5s)';
    this.classList.remove('btn-primary');
    this.classList.add('btn-outline-secondary');
  } else {
    autoTimer = setInterval(() => location.reload(), 5000);
    this.textContent = '⏹ Stop auto';
    this.classList.remove('btn-outline-secondary');
    this.classList.add('btn-primary');
  }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
