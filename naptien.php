<?php
require_once __DIR__ . '/includes/config.php';
Auth::requireLogin();

$pageTitle = 'Nạp tiền';
$user = Auth::user();
$settings = getSettings();

// Webhook check endpoint (for AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_topup'])) {
    $txId  = $_POST['tx_id'] ?? '';
    $tx    = DB::find('transactions', 'id', $txId);
    if ($tx && $tx['user_id'] === Auth::id() && $tx['status'] === 'success') {
        echo json_encode(['confirmed' => true, 'balance' => DB::find('users','id',Auth::id())['balance'] ?? 0]);
    } else {
        echo json_encode(['confirmed' => false]);
    }
    exit;
}

// Create top-up request
$txId = null;
$amount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topup'])) {
    $amount = max(10000, (int)preg_replace('/\D/', '', $_POST['amount'] ?? '0'));
    $txId   = DB::nextId('TOP');
    DB::append('transactions', [
        'id'         => $txId,
        'user_id'    => Auth::id(),
        'amount'     => $amount,
        'type'       => 'topup',
        'status'     => 'pending',
        'created_at' => date('c'),
    ]);
}

$bankCode  = setting('sepay_bank_code', 'MB');
$accountNo = setting('sepay_account_no', '');

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb-bar">
  <div class="container">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Nạp tiền</li>
    </ol>
  </div>
</div>

<div class="container py-4" style="max-width:700px">
  <h4 class="fw-800 mb-1">💳 Nạp tiền vào tài khoản</h4>
  <p class="text-muted mb-4">Số dư hiện tại: <strong class="text-primary"><?= fmtMoney($user['balance'] ?? 0) ?></strong></p>

  <?php if (!$txId): ?>
  <!-- Form chọn số tiền -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="POST">
        <label class="form-label fw-600">Chọn số tiền nạp</label>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <?php foreach ([50000, 100000, 200000, 500000, 1000000, 2000000] as $preset): ?>
          <button type="button" class="btn btn-outline-primary btn-sm preset-btn" onclick="setAmount(<?= $preset ?>)">
            <?= fmtMoney($preset) ?>
          </button>
          <?php endforeach; ?>
        </div>
        <div class="input-group mb-3">
          <input type="number" class="form-control" name="amount" id="amount-input" min="10000" step="10000" placeholder="Nhập số tiền (tối thiểu 10,000)" required>
          <span class="input-group-text">₫</span>
        </div>
        <button type="submit" name="create_topup" class="btn btn-primary w-100 btn-lg fw-700">
          <i class="bi bi-plus-circle me-2"></i>Tạo lệnh nạp tiền
        </button>
      </form>
    </div>
  </div>

  <!-- Lịch sử nạp tiền -->
  <?php
  $txHistory = DB::filter('transactions', fn($t) => $t['user_id'] === Auth::id() && $t['type'] === 'topup');
  usort($txHistory, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
  $txHistory = array_slice($txHistory, 0, 10);
  ?>
  <?php if ($txHistory): ?>
  <h6 class="fw-700 mt-4 mb-2">Lịch sử nạp tiền</h6>
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Mã GD</th><th>Số tiền</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
        <tbody>
        <?php foreach ($txHistory as $t): ?>
        <tr>
          <td><code style="font-size:11px"><?= h($t['id']) ?></code></td>
          <td class="fw-600"><?= fmtMoney($t['amount']) ?></td>
          <td>
            <?php if ($t['status']==='success'): ?>
            <span class="badge bg-success">✅ Thành công</span>
            <?php elseif ($t['status']==='pending'): ?>
            <span class="badge bg-warning text-dark">⏳ Chờ xử lý</span>
            <?php else: ?>
            <span class="badge bg-secondary"><?= h($t['status']) ?></span>
            <?php endif; ?>
          </td>
          <td class="text-muted small"><?= fmtDate($t['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <!-- QR + hướng dẫn thanh toán -->
  <?php
  $content = 'NAPTIEN ' . strtoupper($txId);
  $qrUrl = $accountNo ? "https://qr.sepay.vn/img?bank={$bankCode}&acc={$accountNo}&template=compact&amount={$amount}&des=" . urlencode($content) : '';
  ?>
  <div class="card border-0 shadow-sm border-primary" style="border-width:2px!important">
    <div class="card-header bg-primary text-white text-center">
      <strong>Chuyển khoản để nạp tiền</strong>
    </div>
    <div class="card-body text-center">
      <?php if ($qrUrl): ?>
      <img src="<?= h($qrUrl) ?>" alt="QR Nạp tiền" class="img-fluid rounded mb-3" style="max-width:220px">
      <?php endif; ?>
      <div class="text-start">
        <div class="row g-2 mb-3">
          <div class="col-6">
            <div class="small text-muted">Ngân hàng</div>
            <div class="fw-700"><?= h($bankCode) ?></div>
          </div>
          <div class="col-6">
            <div class="small text-muted">Số tài khoản</div>
            <div class="fw-700"><?= h($accountNo ?: '—') ?></div>
          </div>
          <div class="col-6">
            <div class="small text-muted">Số tiền</div>
            <div class="fw-800 text-primary fs-5"><?= fmtMoney($amount) ?></div>
          </div>
          <div class="col-12">
            <div class="small text-muted mb-1">Nội dung chuyển khoản</div>
            <div class="d-flex align-items-center gap-2">
              <code class="bg-light rounded px-3 py-2 flex-grow-1 text-center fw-800" style="font-size:15px;letter-spacing:1px"><?= h($content) ?></code>
              <button class="btn btn-outline-secondary btn-sm" onclick="copyText('<?= h($content) ?>', this)"><i class="bi bi-copy"></i></button>
            </div>
          </div>
        </div>
        <div class="alert alert-warning small py-2">
          <i class="bi bi-exclamation-triangle me-1"></i>Nhập <strong>đúng nội dung</strong> để hệ thống tự động cộng tiền
        </div>
      </div>
      <button class="btn btn-success w-100" id="btn-check" onclick="checkTopup('<?= h($txId) ?>')">
        <i class="bi bi-arrow-repeat me-2"></i>Kiểm tra giao dịch
      </button>
      <div id="topup-result" class="mt-2"></div>
      <div class="text-muted mt-2 small">Tự động kiểm tra mỗi 30 giây</div>
      <a href="/naptien.php" class="btn btn-outline-secondary btn-sm mt-2">← Quay lại</a>
    </div>
  </div>
  <script>
  function checkTopup(txId) {
    const btn = document.getElementById('btn-check');
    const res = document.getElementById('topup-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang kiểm tra...';
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'check_topup=1&tx_id='+encodeURIComponent(txId) })
      .then(r=>r.json()).then(d => {
        if (d.confirmed) {
          res.innerHTML = '<div class="alert alert-success py-2 small">✅ Nạp thành công! Số dư mới: <strong>' + d.balance.toLocaleString('vi-VN') + ' ₫</strong></div>';
          clearInterval(autoTimer);
          setTimeout(() => window.location.href = '/dashboard.php', 2000);
        } else {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Kiểm tra giao dịch';
          res.innerHTML = '<div class="alert alert-warning py-2 small">⏳ Chưa nhận được</div>';
          setTimeout(() => res.innerHTML = '', 3000);
        }
      }).catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Kiểm tra giao dịch'; });
  }
  const autoTimer = setInterval(() => checkTopup('<?= h($txId) ?>'), 30000);
  </script>
  <?php endif; ?>
</div>

<script>
function setAmount(v) {
  document.getElementById('amount-input').value = v;
  document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('btn-primary'));
  event.target.classList.add('btn-primary');
  event.target.classList.remove('btn-outline-primary');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
