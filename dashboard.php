<?php
require_once __DIR__ . '/includes/config.php';
Auth::requireLogin();
$pageTitle = 'Dashboard';

$user   = Auth::user();
$uid    = $user['id'];

// ── Dữ liệu đơn hàng ─────────────────────────────────────────────────────────
$orders  = DB::filter('orders',  fn($o) => $o['user_id'] === $uid);
usort($orders, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
$recentOrders = array_slice($orders, 0, 5);

$stats = [
    'orders'  => count($orders),
    'active'  => count(array_filter($orders, fn($o) => $o['status'] === 'active')),
    'pending' => count(array_filter($orders, fn($o) => $o['status'] === 'pending')),
    'spent'   => array_sum(array_column(array_filter($orders, fn($o) => in_array($o['status'],['paid','active'])), 'total')),
];

// ── Tickets chưa đọc ──────────────────────────────────────────────────────────
$tickets     = DB::filter('tickets', fn($t) => $t['user_id'] === $uid);
$openTickets = count(array_filter($tickets, fn($t) => $t['status'] === 'open'));

// ── Servers của user ──────────────────────────────────────────────────────────
$serverIds  = $user['server_ids'] ?? [];
$serverList = [];
if (!empty($serverIds)) {
    $raw = bridgeCall('/api/client/servers', 'GET', [], $uid, $serverIds);
    if (is_array($raw) && !isset($raw['_err'])) {
        $serverList = $raw;
    }
}
$runningCount = count(array_filter($serverList, fn($s) => ($s['status'] ?? '') === 'running'));

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb-bar">
  <div class="container">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item active">Dashboard</li>
    </ol>
  </div>
</div>

<div class="container py-4">

  <!-- ── Welcome ── -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h4 class="fw-800 mb-1">👋 Xin chào, <?= h($user['username']) ?>!</h4>
      <div class="text-muted small">Thành viên từ <?= fmtDate($user['created_at']) ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="/naptien.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Nạp tiền</a>
      <a href="/products.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-grid me-1"></i>Dịch vụ</a>
    </div>
  </div>

  <!-- ── Stat cards ── -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-val text-primary"><?= fmtMoney($user['balance'] ?? 0) ?></div>
        <div class="stat-label"><i class="bi bi-wallet2 me-1"></i>Số dư ví</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-val text-success"><?= $stats['active'] ?></div>
        <div class="stat-label"><i class="bi bi-bag-check me-1"></i>Dịch vụ đang dùng</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-val <?= $stats['pending'] ? 'text-warning' : 'text-muted' ?>"><?= $stats['pending'] ?></div>
        <div class="stat-label"><i class="bi bi-clock me-1"></i>Chờ thanh toán</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-val <?= $runningCount ? 'text-success' : 'text-muted' ?>"><?= $runningCount ?> / <?= count($serverIds) ?></div>
        <div class="stat-label"><i class="bi bi-server me-1"></i>Server đang chạy</div>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- ── Left col ── -->
    <div class="col-lg-8">

      <!-- Servers -->
      <?php if (!empty($serverIds)): ?>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex align-items-center justify-content-between border-0 pt-3">
          <span class="fw-700"><i class="bi bi-server me-2 text-primary"></i>Server của tôi</span>
          <span class="badge bg-light text-muted border"><?= count($serverIds) ?></span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($serverList)): ?>
          <div class="text-center py-4 text-muted small">
            <div class="spinner-border spinner-border-sm me-2"></div>Đang kết nối Node.js Manager…
          </div>
          <?php else: ?>
          <div class="list-group list-group-flush" id="server-list">
            <?php foreach ($serverList as $srv):
              $st   = $srv['status'] ?? 'unknown';
              $stCls = ['running' => 'success', 'stopped' => 'secondary', 'crashed' => 'danger'][$st] ?? 'warning';
              $stLbl = ['running' => '● Đang chạy', 'stopped' => '■ Đã dừng', 'crashed' => '✕ Crashed'][$st] ?? '? Không rõ';
              $dl    = $srv['daysLeft'] ?? null;
            ?>
            <a href="/server.php?id=<?= h($srv['id']) ?>"
               class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-4">
              <div class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:36px;height:36px;background:<?= $st==='running'?'#dcfce7':($st==='crashed'?'#fee2e2':'#f1f5f9') ?>">
                <i class="bi bi-server" style="color:<?= $st==='running'?'#16a34a':($st==='crashed'?'#dc2626':'#94a3b8') ?>"></i>
              </div>
              <div class="flex-grow-1" style="min-width:0">
                <div class="fw-600" style="font-size:14px"><?= h($srv['name']) ?></div>
                <div class="d-flex gap-2 mt-1 flex-wrap">
                  <span class="badge bg-<?= $stCls ?>" style="font-size:10px"><?= $stLbl ?></span>
                  <?php if ($srv['port'] ?? null): ?>
                  <span class="badge bg-light text-dark border" style="font-size:10px;font-family:monospace">:<?= h($srv['port']) ?></span>
                  <?php endif; ?>
                  <?php if ($dl !== null): ?>
                  <span class="badge <?= $dl < 0 ? 'bg-danger' : ($dl <= 7 ? 'bg-warning text-dark' : 'bg-light text-muted border') ?>" style="font-size:10px">
                    <?= $dl < 0 ? 'Hết hạn' : ($dl === 0 ? 'Hết hạn hôm nay' : "Còn {$dl} ngày") ?>
                  </span>
                  <?php endif; ?>
                </div>
              </div>
              <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Recent orders -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between border-0 pt-3">
          <span class="fw-700"><i class="bi bi-bag me-2 text-primary"></i>Đơn hàng gần đây</span>
          <a href="/orders.php" class="btn btn-sm btn-outline-secondary">Tất cả</a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($recentOrders)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-bag-x" style="font-size:2.5rem;opacity:.4"></i>
            <p class="mt-2 mb-3">Bạn chưa có đơn hàng nào</p>
            <a href="/products.php" class="btn btn-primary btn-sm">Xem dịch vụ</a>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Dịch vụ</th>
                  <th>Tổng</th>
                  <th>Trạng thái</th>
                  <th>Ngày</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($recentOrders as $o): ?>
              <tr class="order-row">
                <td class="small fw-600"><?= h($o['product_name']) ?></td>
                <td class="fw-700"><?= fmtMoney($o['total']) ?></td>
                <td><?= orderStatusLabel($o['status']) ?></td>
                <td class="small text-muted"><?= fmtDate($o['created_at']) ?></td>
                <td>
                  <a href="/order.php?id=<?= h($o['id']) ?>" class="btn btn-xs btn-outline-secondary"
                     style="font-size:11px;padding:2px 8px">Chi tiết</a>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /col-lg-8 -->

    <!-- ── Right col ── -->
    <div class="col-lg-4">

      <!-- Account summary -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-700 border-0 pt-3">
          <i class="bi bi-person-circle me-2 text-primary"></i>Tài khoản
        </div>
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-800"
                 style="width:48px;height:48px;font-size:1.2rem;flex-shrink:0">
              <?= strtoupper(substr($user['username'],0,1)) ?>
            </div>
            <div>
              <div class="fw-700"><?= h($user['username']) ?></div>
              <div class="small text-muted"><?= h($user['email'] ?? '') ?></div>
            </div>
          </div>
          <div class="d-flex align-items-center justify-content-between py-2 border-top">
            <span class="small text-muted">Số dư</span>
            <span class="fw-700 text-primary"><?= fmtMoney($user['balance'] ?? 0) ?></span>
          </div>
          <div class="d-flex align-items-center justify-content-between py-2 border-top">
            <span class="small text-muted">Tổng đã chi</span>
            <span class="fw-600"><?= fmtMoney($stats['spent']) ?></span>
          </div>
          <div class="d-flex align-items-center justify-content-between py-2 border-top">
            <span class="small text-muted">Số đơn</span>
            <span class="fw-600"><?= $stats['orders'] ?></span>
          </div>
          <div class="mt-3 d-flex flex-column gap-2">
            <a href="/naptien.php" class="btn btn-primary btn-sm w-100">
              <i class="bi bi-plus-circle me-1"></i>Nạp tiền vào ví
            </a>
            <a href="/profile.php" class="btn btn-outline-secondary btn-sm w-100">
              <i class="bi bi-gear me-1"></i>Cài đặt tài khoản
            </a>
          </div>
        </div>
      </div>

      <!-- Quick links -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-700 border-0 pt-3">
          <i class="bi bi-lightning me-2 text-warning"></i>Truy cập nhanh
        </div>
        <div class="list-group list-group-flush">
          <a href="/orders.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-3" style="font-size:13px">
            <i class="bi bi-bag text-primary"></i> Đơn hàng của tôi
            <?php if ($stats['pending']): ?>
            <span class="badge bg-warning text-dark ms-auto"><?= $stats['pending'] ?> chờ TT</span>
            <?php endif; ?>
          </a>
          <a href="/tickets.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-3" style="font-size:13px">
            <i class="bi bi-headset text-primary"></i> Hỗ trợ / Tickets
            <?php if ($openTickets): ?>
            <span class="badge bg-danger ms-auto"><?= $openTickets ?> mở</span>
            <?php endif; ?>
          </a>
          <a href="/products.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-3" style="font-size:13px">
            <i class="bi bi-grid text-primary"></i> Xem dịch vụ
          </a>
          <a href="/naptien.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-3" style="font-size:13px">
            <i class="bi bi-credit-card text-primary"></i> Nạp tiền
          </a>
        </div>
      </div>

      <!-- Open tickets -->
      <?php
      $openTkList = array_filter($tickets, fn($t) => $t['status'] === 'open');
      usort($openTkList, fn($a,$b) => strcmp($b['created_at'],$a['created_at']));
      $openTkList = array_slice(array_values($openTkList), 0, 3);
      if (!empty($openTkList)):
      ?>
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-700 border-0 pt-3 d-flex justify-content-between align-items-center">
          <span><i class="bi bi-headset me-2 text-danger"></i>Tickets đang mở</span>
          <a href="/tickets.php" class="btn btn-xs btn-outline-secondary" style="font-size:11px;padding:2px 8px">Tất cả</a>
        </div>
        <div class="list-group list-group-flush">
          <?php foreach ($openTkList as $tk): ?>
          <a href="/ticket.php?id=<?= h($tk['id']) ?>"
             class="list-group-item list-group-item-action py-2 px-3" style="font-size:13px">
            <div class="fw-600 text-truncate"><?= h($tk['subject'] ?? '—') ?></div>
            <div class="small text-muted"><?= fmtDate($tk['created_at']) ?></div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /col-lg-4 -->

  </div><!-- /row -->
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
