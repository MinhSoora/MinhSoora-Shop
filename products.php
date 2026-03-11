<?php
require_once __DIR__ . '/includes/config.php';

$catId = $_GET['cat'] ?? '';
$search = trim($_GET['q'] ?? '');
$pageTitle = 'Sản phẩm & Dịch vụ';

$all = DB::filter('products', fn($p) => !empty($p['active']));
$products = array_filter($all, function($p) use ($catId, $search) {
    if ($catId && $p['category_id'] !== $catId) return false;
    if ($search && stripos($p['name'].$p['short_desc'], $search) === false) return false;
    return true;
});

$categories = DB::read('categories', []);
usort($categories, fn($a,$b) => ($a['order']??99) <=> ($b['order']??99));

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb-bar">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="/">Trang chủ</a></li>
        <li class="breadcrumb-item active">Sản phẩm</li>
      </ol>
    </nav>
  </div>
</div>

<div class="container py-4">
  <div class="row g-4">

    <!-- Sidebar filter -->
    <div class="col-lg-3">
      <div class="sticky-top" style="top:80px">
        <!-- Search -->
        <form class="mb-3">
          <div class="input-group">
            <input type="search" name="q" class="form-control" placeholder="Tìm kiếm..." value="<?= h($search) ?>">
            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
          </div>
          <?php if ($catId): ?><input type="hidden" name="cat" value="<?= h($catId) ?>"><?php endif; ?>
        </form>

        <!-- Categories -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-600 border-0 pb-0">
            <i class="bi bi-grid me-1"></i> Danh mục
          </div>
          <div class="card-body pt-2">
            <nav class="nav flex-column sidebar-menu">
              <a href="/products.php" class="nav-link <?= !$catId?'active':'' ?>"><i class="bi bi-grid-3x3-gap me-2"></i>Tất cả <span class="badge bg-light text-muted ms-auto border"><?= count($all) ?></span></a>
              <?php foreach ($categories as $cat): ?>
              <?php $cnt = count(array_filter($all, fn($p) => $p['category_id']===$cat['id'])); ?>
              <a href="/products.php?cat=<?= h($cat['id']) ?>" class="nav-link <?= $catId===$cat['id']?'active':'' ?>">
                <span style="width:20px;display:inline-block"><?= h($cat['icon']??'') ?></span><?= h($cat['name']) ?>
                <span class="badge bg-light text-muted ms-auto border"><?= $cnt ?></span>
              </a>
              <?php endforeach; ?>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <!-- Products -->
    <div class="col-lg-9">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h4 class="fw-800 mb-0">
            <?php if ($catId): ?>
              <?= h(DB::find('categories','id',$catId)['icon']??'') ?> <?= h(DB::find('categories','id',$catId)['name']??'Danh mục') ?>
            <?php else: ?>
              Tất cả dịch vụ
            <?php endif; ?>
          </h4>
          <div class="text-muted small"><?= count($products) ?> dịch vụ</div>
        </div>
      </div>

      <div class="row g-4">
        <?php foreach ($products as $p): ?>
        <div class="col-md-6 col-xl-4">
          <div class="product-card card h-100 position-relative" style="border:1px solid var(--border)">
            <?php if (!empty($p['featured'])): ?><div class="badge-featured">⭐</div><?php endif; ?>
            <?php if (!empty($p['images'][0])): ?>
            <img src="/public/uploads/<?= h($p['images'][0]) ?>" class="card-img-top" style="height:150px;object-fit:cover" alt="">
            <?php else: ?>
            <div class="d-flex align-items-center justify-content-center" style="height:120px;background:linear-gradient(135deg,#eff6ff,#dbeafe)">
              <span style="font-size:2.5rem"><?= h(DB::find('categories','id',$p['category_id'])['icon']??'📦') ?></span>
            </div>
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <h6 class="card-title fw-700 mb-1"><?= h($p['name']) ?></h6>
              <p class="card-text text-muted small flex-grow-1" style="font-size:12.5px"><?= h($p['short_desc']??'') ?></p>
              <?php if (!empty($p['options'])): ?>
              <div class="text-muted small mb-2">+ <?= count($p['options']) ?> tùy chọn bổ sung</div>
              <?php endif; ?>
              <div class="d-flex align-items-end justify-content-between">
                <div>
                  <div class="price" style="font-size:1.1rem"><?= fmtMoney($p['price']) ?></div>
                  <div class="price-type"><?= $p['price_type']==='monthly'?'/ tháng':($p['price_type']==='onetime'?'Một lần':'Linh hoạt') ?></div>
                </div>
                <a href="/product.php?slug=<?= h($p['slug']) ?>" class="btn btn-primary btn-sm">Chi tiết</a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
        <div class="col-12 text-center py-5 text-muted">
          <i class="bi bi-search" style="font-size:3rem"></i>
          <p class="mt-2">Không tìm thấy dịch vụ phù hợp</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
