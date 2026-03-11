<?php
require_once __DIR__ . '/includes/config.php';
Auth::requireLogin();

$orderId = $_GET['id'] ?? '';
$order   = DB::find('orders', 'id', $orderId);
if (!$order || ($order['user_id'] !== Auth::id() && !Auth::isAdmin())) {
    flash('error', 'Không tìm thấy đơn hàng'); redirect('/orders.php');
}

$pageTitle  = 'Đơn hàng #' . $orderId;
$settings   = getSettings();
$product    = DB::find('products', 'id', $order['product_id']);
$user       = Auth::user();

// Tạo nội dung chuyển khoản
$transferContent = 'NODESHOP ' . strtoupper($orderId);

// Kiểm tra giao dịch Sepay
function checkSepayPayment(string $orderId, int $amount): bool {
    $result = sepayRequest('/transactions/list?account_number=' . setting('sepay_account_no') . '&limit=20');
    if (empty($result['transactions'])) return false;
    foreach ($result['transactions'] as $tx) {
        $desc = strtoupper($tx['transaction_content'] ?? '');
        $ref  = strtoupper('NODESHOP ' . $orderId);
        if (str_contains($desc, $ref) && (int)$tx['amount_in'] >= $amount) {
            return true;
        }
    }
    return false;
}

// Auto-check payment (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_payment'])) {
    if ($order['status'] === 'pending') {
        $paid = checkSepayPayment($orderId, $order['total']);
        if ($paid) {
            DB::update('orders', 'id', $orderId, [
                'status'  => 'paid',
                'paid_at' => date('c'),
                'updated_at' => date('c'),
            ]);
            // Log transaction
            DB::append('transactions', [
                'id'         => DB::nextId('TXN'),
                'order_id'   => $orderId,
                'user_id'    => $order['user_id'],
                'amount'     => $order['total'],
                'type'       => 'order_payment',
                'status'     => 'success',
                'created_at' => date('c'),
            ]);
            echo json_encode(['paid' => true]);
        } else {
            echo json_encode(['paid' => false]);
        }
    } else {
        echo json_encode(['paid' => true, 'already' => true]);
    }
    exit;
}

// Cancel order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    if (in_array($order['status'], ['pending'])) {
        DB::update('orders', 'id', $orderId, ['status' => 'cancelled', 'updated_at' => date('c')]);
        flash('success', 'Đã hủy đơn hàng');
    }
    redirect('/orders.php');
}

include __DIR__ . '/includes/header.php';

// QR code URL cho Sepay
$bankCode = setting('sepay_bank_code', 'MB');
$accountNo = setting('sepay_account_no', '');
$qrUrl = '';
if ($accountNo) {
    $qrUrl = "https://qr.sepay.vn/img?bank={$bankCode}&acc={$accountNo}&template=compact&amount={$order['total']}&des=" . urlencode($transferContent);
}
?>

<div class="breadcrumb-bar">
  <div class="container">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="/">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="/orders.php">Đơn hàng</a></li>
      <li class="breadcrumb-item active">#<?= h($orderId) ?></li>
    </ol>
  </div>
</div>

<div class="container py-4" style="max-width:900px">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h4 class="fw-800 mb-1">Đơn hàng #<?= h($orderId) ?></h4>
      <div class="text-muted small">Đặt ngày <?= fmtDate($order['created_at']) ?></div>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <?= orderStatusLabel($order['status']) ?>
      <?php if ($order['status'] === 'pending'): ?>
      <form method="POST" class="d-inline">
        <button type="submit" name="cancel_order" class="btn btn-outline-danger btn-sm"
          onclick="return confirm('Bạn chắc chắn muốn hủy đơn này?')">
          <i class="bi bi-x-circle me-1"></i>Hủy đơn
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-4">
    <!-- Order info -->
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <h6 class="fw-700 mb-3"><i class="bi bi-receipt me-2 text-primary"></i>Chi tiết đơn hàng</h6>
          <table class="table table-sm">
            <tr><td class="text-muted small">Dịch vụ</td><td class="fw-600"><?= h($order['product_name']) ?></td></tr>
            <tr><td class="text-muted small">Giá gốc</td><td><?= fmtMoney($order['price_base']) ?></td></tr>
            <?php foreach ($order['options'] ?? [] as $opt): ?>
            <tr><td class="text-muted small">+ <?= h($opt['name']) ?></td><td class="text-primary">+<?= fmtMoney($opt['price']) ?></td></tr>
            <?php endforeach; ?>
            <tr class="border-top"><td class="fw-700">Tổng cộng</td><td class="fw-800 text-primary fs-5"><?= fmtMoney($order['total']) ?></td></tr>
          </table>

          <?php if (!empty($order['fields'])): ?>
          <hr>
          <h6 class="fw-700 mb-2 small">📝 Thông tin cung cấp</h6>
          <?php foreach ($order['fields'] as $fldId => $val): ?>
          <?php $fldDef = null; foreach ($product['fields']??[] as $f) if ($f['id']===$fldId) $fldDef=$f; ?>
          <div class="mb-1 small">
            <span class="text-muted"><?= h($fldDef['name'] ?? $fldId) ?>:</span>
            <?php if (($fldDef['type']??'') === 'password'): ?>
            <span class="text-muted fst-italic">[ẩn]</span>
            <?php else: ?>
            <span class="fw-500"><?= h($val) ?></span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>

          <?php if (!empty($order['server_id'])): ?>
          <hr>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-server text-success"></i>
            <div>
              <div class="small text-muted">Server ID</div>
              <code class="small"><?= h($order['server_id']) ?></code>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Payment -->
    <div class="col-md-6">
      <?php if ($order['status'] === 'pending'): ?>
      <div class="card border-0 shadow-sm border-primary" style="border-width:2px!important">
        <div class="card-header bg-primary text-white">
          <i class="bi bi-credit-card me-2"></i><strong>Thanh toán đơn hàng</strong>
        </div>
        <div class="card-body text-center">
          <?php if ($qrUrl): ?>
          <img src="<?= h($qrUrl) ?>" alt="QR Thanh toán" class="img-fluid rounded mb-3" style="max-width:220px">
          <?php endif; ?>
          <div class="mb-3 text-start">
            <div class="mb-2 small">
              <div class="text-muted">Ngân hàng</div>
              <div class="fw-700"><?= h($bankCode) ?> — <?= h($accountNo ?: 'Chưa cấu hình') ?></div>
            </div>
            <div class="mb-2 small">
              <div class="text-muted">Số tiền</div>
              <div class="fw-800 text-primary fs-5"><?= fmtMoney($order['total']) ?></div>
            </div>
            <div class="small">
              <div class="text-muted">Nội dung chuyển khoản</div>
              <div class="d-flex align-items-center gap-2 mt-1">
                <code class="bg-light rounded px-2 py-1 flex-grow-1 text-center fw-700" style="font-size:14px"><?= h($transferContent) ?></code>
                <button class="btn btn-outline-secondary btn-sm" onclick="copyText('<?= h($transferContent) ?>', this)">
                  <i class="bi bi-copy"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="alert alert-warning py-2 text-start small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Lưu ý:</strong> Nhập đúng nội dung chuyển khoản để hệ thống tự động xác nhận
          </div>
          <button class="btn btn-success w-100" id="btn-check-payment" onclick="checkPayment()">
            <i class="bi bi-arrow-repeat me-2"></i>Kiểm tra thanh toán
          </button>
          <div id="payment-result" class="mt-2"></div>
          <div class="text-muted mt-2" style="font-size:11px">Tự động kiểm tra mỗi 30 giây</div>
        </div>
      </div>
      <?php elseif ($order['status'] === 'paid' || $order['status'] === 'processing' || $order['status'] === 'active'): ?>
      <div class="card border-0 shadow-sm border-success" style="border-width:2px!important">
        <div class="card-body text-center py-4">
          <div style="font-size:3rem">✅</div>
          <h5 class="fw-800 mt-2 text-success">Đã thanh toán</h5>
          <p class="text-muted small">Cảm ơn bạn! Đơn hàng đang được xử lý.</p>
          <?php if ($order['paid_at']): ?>
          <div class="text-muted small">Thanh toán lúc: <?= fmtDate($order['paid_at']) ?></div>
          <?php endif; ?>
          <a href="/tickets.php?new=1" class="btn btn-outline-primary btn-sm mt-3">
            <i class="bi bi-headset me-1"></i>Liên hệ hỗ trợ
          </a>
        </div>
      </div>
      <?php elseif ($order['status'] === 'cancelled'): ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-4">
          <div style="font-size:3rem">❌</div>
          <h5 class="fw-800 mt-2 text-danger">Đã hủy</h5>
          <p class="text-muted small">Đơn hàng này đã bị hủy.</p>
          <a href="/products.php" class="btn btn-primary btn-sm mt-2">Đặt hàng mới</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Admin note -->
  <?php if (!empty($order['note'])): ?>
  <div class="card border-0 shadow-sm mt-3 border-start border-4 border-info">
    <div class="card-body">
      <div class="fw-600 small text-info mb-1"><i class="bi bi-info-circle me-1"></i>Ghi chú từ admin</div>
      <div><?= h($order['note']) ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
let autoCheckTimer = null;

function checkPayment() {
  const btn = document.getElementById('btn-check-payment');
  const res = document.getElementById('payment-result');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang kiểm tra...';

  fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'check_payment=1' })
    .then(r => r.json())
    .then(d => {
      if (d.paid) {
        clearInterval(autoCheckTimer);
        res.innerHTML = '<div class="alert alert-success py-2 small">✅ Thanh toán thành công! Đang tải lại...</div>';
        setTimeout(() => location.reload(), 1500);
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Kiểm tra thanh toán';
        res.innerHTML = '<div class="alert alert-warning py-2 small">⏳ Chưa nhận được thanh toán, vui lòng thử lại sau</div>';
        setTimeout(() => res.innerHTML = '', 4000);
      }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Kiểm tra thanh toán'; });
}

// Auto check every 30s
<?php if ($order['status'] === 'pending'): ?>
autoCheckTimer = setInterval(checkPayment, 30000);
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
