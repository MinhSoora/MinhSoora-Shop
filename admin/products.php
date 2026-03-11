<?php
// admin/products.php
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

// Delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_product'])) {
    DB::delete('products','id',$_POST['delete_product']);
    flash('success','Đã xóa sản phẩm');
    redirect('/admin/products.php');
}

$adminPageTitle = 'Quản lý sản phẩm';
require_once __DIR__ . '/header.php';

$products = DB::read('products',[]);
usort($products, fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
?>
<div class="d-flex justify-content-between mb-3">
  <span class="text-muted small"><?= count($products) ?> sản phẩm</span>
  <a href="/admin/product_edit.php" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i>Thêm sản phẩm</a>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Tên</th><th>Danh mục</th><th>Giá</th><th>Loại</th><th>Nổi bật</th><th>TT</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): ?>
      <?php $cat = DB::find('categories','id',$p['category_id']); ?>
      <tr>
        <td class="fw-600"><?= h($p['name']) ?></td>
        <td class="small"><?= h($cat['icon']??'') ?> <?= h($cat['name']??'—') ?></td>
        <td class="fw-600"><?= fmtMoney($p['price']) ?></td>
        <td class="small"><?= h($p['price_type']) ?></td>
        <td><?= !empty($p['featured'])?'⭐':'' ?></td>
        <td><?= !empty($p['active'])?'<span class="badge bg-success">✓</span>':'<span class="badge bg-danger">✗</span>' ?></td>
        <td class="d-flex gap-1">
          <a href="/admin/product_edit.php?id=<?= h($p['id']) ?>" class="btn btn-sm btn-outline-primary">Sửa</a>
          <form method="POST" class="d-inline">
            <input type="hidden" name="delete_product" value="<?= h($p['id']) ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa sản phẩm này?')">🗑</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?><tr><td colspan="7" class="text-center py-4 text-muted">Chưa có sản phẩm</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
