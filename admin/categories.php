<?php
// admin/categories.php
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['save_cat'])) {
        $id = $_POST['cat_id'] ?: DB::nextId('cat_');
        $item = ['id'=>$id,'name'=>trim($_POST['name']??''),'icon'=>trim($_POST['icon']??''),'order'=>(int)($_POST['order']??99)];
        if ($_POST['cat_id']) DB::update('categories','id',$id,$item);
        else DB::append('categories',$item);
        flash('success','Đã lưu danh mục');
    }
    if (isset($_POST['delete_cat'])) {
        DB::delete('categories','id',$_POST['delete_cat']);
        flash('success','Đã xóa');
    }
    redirect('/admin/categories.php');
}

$adminPageTitle = 'Danh mục';
require_once __DIR__ . '/header.php';

$categories = DB::read('categories',[]);
usort($categories, fn($a,$b)=>($a['order']??99)<=>($b['order']??99));
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header bg-white fw-700">Thêm / Sửa danh mục</div>
      <div class="card-body">
        <form method="POST" id="cat-form">
          <input type="hidden" name="cat_id" id="cat_id">
          <div class="mb-2"><label class="form-label small fw-600">Tên danh mục *</label>
            <input type="text" name="name" id="cat_name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label small fw-600">Icon (emoji)</label>
            <input type="text" name="icon" id="cat_icon" class="form-control" placeholder="🖥"></div>
          <div class="mb-3"><label class="form-label small fw-600">Thứ tự</label>
            <input type="number" name="order" id="cat_order" class="form-control" value="99"></div>
          <button type="submit" name="save_cat" class="btn btn-primary w-100">💾 Lưu</button>
          <button type="button" class="btn btn-outline-secondary w-100 mt-1" onclick="resetForm()">Hủy</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Icon</th><th>Tên</th><th>Thứ tự</th><th>SP</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($categories as $c): ?>
          <tr>
            <td style="font-size:1.2rem"><?= h($c['icon']??'') ?></td>
            <td class="fw-600"><?= h($c['name']) ?></td>
            <td><?= h($c['order']??99) ?></td>
            <td><?= DB::count('products',fn($p)=>$p['category_id']===$c['id']) ?></td>
            <td class="d-flex gap-1">
              <button class="btn btn-sm btn-outline-primary" onclick="editCat('<?= h($c['id']) ?>','<?= h(addslashes($c['name'])) ?>','<?= h($c['icon']??'') ?>',<?= (int)($c['order']??99) ?>)">Sửa</button>
              <form method="POST" class="d-inline">
                <input type="hidden" name="delete_cat" value="<?= h($c['id']) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa danh mục?')">🗑</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
function editCat(id,name,icon,order) {
  document.getElementById('cat_id').value=id;
  document.getElementById('cat_name').value=name;
  document.getElementById('cat_icon').value=icon;
  document.getElementById('cat_order').value=order;
}
function resetForm() {
  ['cat_id','cat_name','cat_icon'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('cat_order').value=99;
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
