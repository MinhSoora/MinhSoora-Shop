<?php
require_once __DIR__ . '/includes/config.php';

$slug = $_GET['slug'] ?? '';
$product = null;
foreach (DB::read('products', []) as $p) {
    if ($p['slug'] === $slug && !empty($p['active'])) { $product = $p; break; }
}
if (!$product) { header('Location: /products.php'); exit; }

$pageTitle = $product['name'];
$user = Auth::user();

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    Auth::requireLogin('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));

    $selectedOptions = $_POST['options'] ?? [];
    $fieldValues     = $_POST['fields']  ?? [];

    // Validate required fields
    $errors = [];
    foreach ($product['fields'] ?? [] as $fld) {
        if (!empty($fld['required']) && empty($fieldValues[$fld['id']])) {
            $errors[] = 'Vui lòng điền: ' . $fld['name'];
        }
    }

    if (empty($errors)) {
        // Calculate total
        $total = $product['price'];
        $opts  = [];
        foreach ($product['options'] ?? [] as $opt) {
            if (in_array($opt['id'], $selectedOptions)) {
                $total += $opt['price'];
                $opts[] = $opt;
            }
        }

        $orderId = DB::nextId('ORD');
        $order   = [
            'id'          => $orderId,
            'code'        => generateOrderCode(),
            'type'        => ($product['price_type'] ?? 'monthly') === 'onetime' ? 'setup' : 'server',
            'user_id'     => Auth::id(),
            'product_id'  => $product['id'],
            'product_name'=> $product['name'],
            'price_base'  => $product['price'],
            'options'     => $opts,
            'fields'      => $fieldValues,
            'total'       => $total,
            'status'      => 'pending',
            'server_id'   => null,
            'note'        => '',
            'updates'     => [[
                'id'      => uniqid(),
                'time'    => date('c'),
                'actor'   => 'system',
                'message' => '📋 Đơn hàng đã được tạo thành công. Vui lòng hoàn tất thanh toán.',
                'public'  => true,
            ]],
            'created_at'  => date('c'),
            'updated_at'  => date('c'),
            'paid_at'     => null,
            'expires_at'  => null,
        ];
        DB::append('orders', $order);
        flash('success', 'Đặt hàng thành công! Vui lòng thanh toán để kích hoạt dịch vụ.');
        redirect('/order.php?id=' . $orderId);
    }
}

$cat = DB::find('categories', 'id', $product['category_id']);
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb-bar">
  <div class="container">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="/">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="/products.php">Sản phẩm</a></li>
      <?php if ($cat): ?><li class="breadcrumb-item"><a href="/products.php?cat=<?= h($cat['id']) ?>"><?= h($cat['name']) ?></a></li><?php endif; ?>
      <li class="breadcrumb-item active"><?= h($product['name']) ?></li>
    </ol>
  </div>
</div>

<div class="container py-4">
  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><?= implode('<br>', array_map('h', $errors)) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Left: Product info -->
    <div class="col-lg-7">
      <!-- Images -->
      <?php if (!empty($product['images'])): ?>
      <div id="productImgs" class="carousel slide mb-4 rounded-3 overflow-hidden border" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach ($product['images'] as $i => $img): ?>
          <div class="carousel-item <?= $i===0?'active':'' ?>">
            <img src="/public/uploads/<?= h($img) ?>" class="d-block w-100" style="height:350px;object-fit:cover" alt="">
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($product['images']) > 1): ?>
        <button class="carousel-control-prev" data-bs-target="#productImgs" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
        <button class="carousel-control-next" data-bs-target="#productImgs" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="rounded-3 border mb-4 d-flex align-items-center justify-content-center" style="height:200px;background:linear-gradient(135deg,#eff6ff,#dbeafe)">
        <span style="font-size:4rem"><?= h($cat['icon']??'📦') ?></span>
      </div>
      <?php endif; ?>

      <!-- Description -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <h5 class="fw-700 mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Mô tả chi tiết</h5>
          <div class="product-desc" style="line-height:1.8"><?= markdownToHtml($product['description'] ?? '') ?></div>
        </div>
      </div>
    </div>

    <!-- Right: Order form -->
    <div class="col-lg-5">
      <div class="sticky-top" style="top:80px">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h4 class="fw-800 mb-1"><?= h($product['name']) ?></h4>
            <?php if ($cat): ?>
            <span class="badge bg-light border text-muted mb-2" style="font-size:12px"><?= h($cat['icon']??'') ?> <?= h($cat['name']) ?></span>
            <?php endif; ?>
            <p class="text-muted small"><?= h($product['short_desc']??'') ?></p>

            <div class="d-flex align-items-end gap-2 my-3">
              <div class="price" id="total-price"><?= fmtMoney($product['price']) ?></div>
              <div class="text-muted small"><?= $product['price_type']==='monthly'?'/ tháng':($product['price_type']==='onetime'?'Một lần':'Linh hoạt') ?></div>
            </div>

            <form method="POST" id="order-form">
              <!-- Options -->
              <?php if (!empty($product['options'])): ?>
              <div class="mb-3">
                <label class="form-label fw-600 small">⚙ Tùy chọn bổ sung</label>
                <?php foreach ($product['options'] as $opt): ?>
                <div class="form-check border rounded p-2 mb-2 option-item" style="cursor:pointer"
                     onclick="toggleOption('<?= h($opt['id']) ?>', <?= (int)$opt['price'] ?>)">
                  <input class="form-check-input option-cb" type="checkbox"
                         name="options[]" value="<?= h($opt['id']) ?>" id="opt_<?= h($opt['id']) ?>">
                  <label class="form-check-label d-flex justify-content-between w-100 ms-2" for="opt_<?= h($opt['id']) ?>" style="cursor:pointer">
                    <span><?= h($opt['name']) ?></span>
                    <span class="text-primary fw-600">+<?= fmtMoney($opt['price']) ?></span>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <!-- Fields -->
              <?php if (!empty($product['fields'])): ?>
              <div class="mb-3">
                <label class="form-label fw-600 small">📝 Thông tin cần thiết</label>
                <?php foreach ($product['fields'] as $fld): ?>
                <div class="mb-2">
                  <label class="form-label small" for="fld_<?= h($fld['id']) ?>">
                    <?= h($fld['name']) ?> <?= !empty($fld['required'])?'<span class="text-danger">*</span>':'' ?>
                  </label>
                  <?php if ($fld['type'] === 'select'): ?>
                  <select class="form-select form-select-sm" name="fields[<?= h($fld['id']) ?>]" id="fld_<?= h($fld['id']) ?>" <?= !empty($fld['required'])?'required':'' ?>>
                    <option value="">— Chọn —</option>
                    <?php foreach ($fld['options'] ?? [] as $opt): ?>
                    <option><?= h($opt) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php elseif ($fld['type'] === 'textarea'): ?>
                  <textarea class="form-control form-control-sm" name="fields[<?= h($fld['id']) ?>]" id="fld_<?= h($fld['id']) ?>" rows="3" <?= !empty($fld['required'])?'required':'' ?>></textarea>
                  <?php elseif ($fld['type'] === 'password'): ?>
                  <input type="password" class="form-control form-control-sm" name="fields[<?= h($fld['id']) ?>]" id="fld_<?= h($fld['id']) ?>" autocomplete="new-password" <?= !empty($fld['required'])?'required':'' ?>>
                  <?php else: ?>
                  <input type="text" class="form-control form-control-sm" name="fields[<?= h($fld['id']) ?>]" id="fld_<?= h($fld['id']) ?>" <?= !empty($fld['required'])?'required':'' ?>>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <!-- Total -->
              <div class="bg-light rounded p-3 mb-3">
                <div class="d-flex justify-content-between fw-700">
                  <span>Tổng thanh toán:</span>
                  <span class="text-primary" id="total-price-2"><?= fmtMoney($product['price']) ?></span>
                </div>
              </div>

              <?php if ($user): ?>
              <button type="submit" name="place_order" class="btn btn-primary w-100 btn-lg fw-700"
                onclick="return confirm('Xác nhận đặt hàng?')">
                <i class="bi bi-cart-check me-2"></i>Đặt hàng ngay
              </button>
              <?php else: ?>
              <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập để đặt hàng
              </a>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <!-- Support -->
        <div class="card border-0 shadow-sm mt-3">
          <div class="card-body d-flex align-items-center gap-3">
            <div style="font-size:2rem">🎧</div>
            <div>
              <div class="fw-600 small">Cần hỗ trợ?</div>
              <div class="text-muted" style="font-size:12px">Mở ticket hỗ trợ, chúng tôi phản hồi trong 24h</div>
              <a href="/tickets.php" class="btn btn-outline-primary btn-sm mt-1">Tạo ticket</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const basePrice = <?= (int)$product['price'] ?>;
let totalAdd = 0;
const addMap = {};

function toggleOption(id, price) {
  const cb = document.getElementById('opt_' + id);
  cb.checked = !cb.checked;
  addMap[id] = cb.checked ? price : 0;
  totalAdd = Object.values(addMap).reduce((a,b) => a+b, 0);
  const total = basePrice + totalAdd;
  const fmt = total.toLocaleString('vi-VN') + ' ₫';
  document.getElementById('total-price').textContent = fmt;
  document.getElementById('total-price-2').textContent = fmt;
}
// Prevent label double-toggle
document.querySelectorAll('.option-item input').forEach(cb => {
  cb.addEventListener('click', e => e.stopPropagation());
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
