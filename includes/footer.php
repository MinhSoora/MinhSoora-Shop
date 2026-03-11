<?php
$settings = getSettings();
$shopName = $settings['shop_name'] ?? APP_NAME;
?>
<footer>
  <div class="container">
    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <div class="footer-brand mb-2"><i class="bi bi-lightning-charge-fill"></i> <?= h($shopName) ?></div>
        <p class="text-muted small mb-2"><?= h($settings['shop_desc'] ?? '') ?></p>
        <?php if (!empty($settings['shop_phone'])): ?>
        <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= h($settings['shop_phone']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['shop_email'])): ?>
        <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= h($settings['shop_email']) ?></div>
        <?php endif; ?>
      </div>
      <div class="col-lg-2">
        <div class="fw-600 mb-2 small text-uppercase text-muted">Dịch vụ</div>
        <?php foreach (DB::read('categories', []) as $c): ?>
        <div><a href="/products.php?cat=<?= h($c['id']) ?>" class="text-muted text-decoration-none small d-block py-1"><?= h($c['icon']??'') ?> <?= h($c['name']) ?></a></div>
        <?php endforeach; ?>
      </div>
      <div class="col-lg-2">
        <div class="fw-600 mb-2 small text-uppercase text-muted">Tài khoản</div>
        <div><a href="/dashboard.php" class="text-muted text-decoration-none small d-block py-1">Dashboard</a></div>
        <div><a href="/orders.php" class="text-muted text-decoration-none small d-block py-1">Đơn hàng</a></div>
        <div><a href="/tickets.php" class="text-muted text-decoration-none small d-block py-1">Hỗ trợ</a></div>
        <div><a href="/naptien.php" class="text-muted text-decoration-none small d-block py-1">Nạp tiền</a></div>
      </div>
      <div class="col-lg-4">
        <div class="fw-600 mb-2 small text-uppercase text-muted">Thanh toán</div>
        <div class="d-flex gap-2 flex-wrap">
          <span class="badge bg-light text-dark border"><i class="bi bi-bank me-1"></i>Chuyển khoản</span>
          <span class="badge bg-light text-dark border"><i class="bi bi-phone me-1"></i>Sepay</span>
        </div>
        <p class="small text-muted mt-2">Thanh toán tự động 24/7 qua hệ thống Sepay. Xác nhận ngay khi nhận tiền.</p>
      </div>
    </div>
    <hr>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="small text-muted"><?= h($settings['footer_text'] ?? '© 2025 ' . $shopName) ?></div>
      <div class="small text-muted">Powered by <strong>NodeShop</strong></div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Loading helper
function showLoading() { document.getElementById('loading').style.display='flex'; }
function hideLoading() { document.getElementById('loading').style.display='none'; }

// Confirm dialog
function confirmAction(msg, formId) {
  if (confirm(msg)) {
    if (formId) document.getElementById(formId).submit();
    return true;
  }
  return false;
}

// Auto-dismiss flash alerts
setTimeout(() => {
  document.querySelectorAll('#flash-container .alert').forEach(el => {
    const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
    bsAlert.close();
  });
}, 5000);

// Copy to clipboard
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check"></i> Đã copy!';
    btn.classList.add('btn-success');
    btn.classList.remove('btn-outline-secondary');
    setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('btn-success'); btn.classList.add('btn-outline-secondary'); }, 2000);
  });
}
</script>
</body>
</html>
