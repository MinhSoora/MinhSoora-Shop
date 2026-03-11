<?php
// admin/sepay.php — Test & debug Sepay API
$adminPageTitle = 'Sepay — Kiểm tra kết nối';
require_once __DIR__ . '/header.php';

$result = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['test_api'])) {
    $result = sepayRequest('/transactions/list?account_number='.setting('sepay_account_no').'&limit=10');
}
?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-body">
        <h6 class="fw-700 mb-3">Cấu hình hiện tại</h6>
        <table class="table table-sm">
          <tr><td class="text-muted small">Ngân hàng</td><td class="fw-600"><?= h(setting('sepay_bank_code','—')) ?></td></tr>
          <tr><td class="text-muted small">Số TK</td><td class="fw-600"><?= h(setting('sepay_account_no','—')) ?></td></tr>
          <tr><td class="text-muted small">Token</td><td><?= setting('sepay_token') ? '<span class="badge bg-success">✓ Đã cấu hình</span>' : '<span class="badge bg-danger">Chưa có</span>' ?></td></tr>
          <tr><td class="text-muted small">Webhook</td><td><?= setting('sepay_webhook_secret') ? '<span class="badge bg-success">✓ Đã cấu hình</span>' : '<span class="badge bg-warning text-dark">Chưa có</span>' ?></td></tr>
        </table>
        <div class="alert alert-info small py-2 mb-2">
          <strong>Webhook URL để nhập vào Sepay:</strong><br>
          <code><?= h(APP_URL) ?>/webhook/sepay.php</code>
          <button class="btn btn-xs btn-outline-secondary ms-1" onclick="copyText('<?= h(APP_URL.'/webhook/sepay.php') ?>', this)" style="font-size:10px;padding:1px 6px">Copy</button>
        </div>
        <form method="POST">
          <button type="submit" name="test_api" class="btn btn-primary btn-sm">🔌 Test kết nối API</button>
          <a href="/admin/settings.php" class="btn btn-outline-secondary btn-sm ms-2">⚙ Cài đặt</a>
        </form>
      </div>
    </div>
  </div>
  <?php if ($result !== null): ?>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header bg-white fw-700">Kết quả API</div>
      <div class="card-body">
        <?php if (!empty($result['error'])): ?>
        <div class="alert alert-danger"><?= h($result['error']) ?></div>
        <?php elseif (!empty($result['transactions'])): ?>
        <div class="alert alert-success py-2">✅ Kết nối thành công — <?= count($result['transactions']) ?> giao dịch gần đây</div>
        <div class="table-responsive"><table class="table table-sm">
          <thead><tr><th>Thời gian</th><th>Nội dung</th><th>Số tiền</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($result['transactions'],0,5) as $t): ?>
          <tr><td class="small"><?= h($t['transaction_date']??'') ?></td>
              <td class="small"><?= h($t['transaction_content']??'') ?></td>
              <td class="fw-600 text-success"><?= fmtMoney($t['amount_in']??0) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php else: ?>
        <pre style="font-size:12px;background:#f8f9fa;padding:10px;border-radius:6px"><?= h(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
