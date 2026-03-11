<?php
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'approve') {
        DB::update('reviews','id',$_POST['id'],['approved'=>true]);
    } elseif ($act === 'reject') {
        DB::update('reviews','id',$_POST['id'],['approved'=>false]);
    } elseif ($act === 'delete') {
        DB::delete('reviews','id',$_POST['id']);
    } elseif ($act === 'add') {
        DB::append('reviews',[
            'id'         => DB::nextId('rv_'),
            'name'       => trim($_POST['name']??''),
            'service'    => trim($_POST['service']??''),
            'rating'     => max(1,min(5,(int)($_POST['rating']??5))),
            'text'       => trim($_POST['text']??''),
            'created_at' => date('c'),
            'approved'   => true,
        ]);
        flash('success','✓ Đã thêm đánh giá');
    }
    redirect('/admin/reviews.php');
}

$adminPageTitle = 'Đánh giá khách hàng';
require_once __DIR__ . '/header.php';

$reviews = DB::read('reviews',[]);
usort($reviews, fn($a,$b)=>strcmp($b['created_at']??'',$a['created_at']??''));
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h5 class="fw-800 mb-0">💬 Đánh giá khách hàng</h5>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus me-1"></i>Thêm đánh giá
  </button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr>
        <th>Tên</th><th>Dịch vụ</th><th>⭐ Rating</th><th>Nội dung</th><th>Ngày</th><th>Trạng thái</th><th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($reviews as $rv): ?>
        <tr>
          <td class="fw-600"><?= h($rv['name']??'') ?></td>
          <td><span class="badge bg-light text-dark border"><?= h($rv['service']??'') ?></span></td>
          <td><?= str_repeat('★',min(5,max(1,(int)($rv['rating']??5)))) ?></td>
          <td style="max-width:300px;font-size:13px"><?= h(mb_substr($rv['text']??'',0,80)) ?>...</td>
          <td class="small text-muted"><?= fmtDate($rv['created_at']??'') ?></td>
          <td><?= !empty($rv['approved']) ? '<span class="badge bg-success">✓ Hiển thị</span>' : '<span class="badge bg-secondary">Ẩn</span>' ?></td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="id" value="<?= h($rv['id']) ?>">
              <input type="hidden" name="act" value="<?= !empty($rv['approved'])?'reject':'approve' ?>">
              <button class="btn btn-xs btn-outline-<?= !empty($rv['approved'])?'secondary':'success' ?>" style="padding:2px 8px;font-size:11px">
                <?= !empty($rv['approved'])?'Ẩn':'Hiện' ?>
              </button>
            </form>
            <form method="POST" class="d-inline ms-1">
              <input type="hidden" name="id" value="<?= h($rv['id']) ?>">
              <input type="hidden" name="act" value="delete">
              <button class="btn btn-xs btn-outline-danger" style="padding:2px 8px;font-size:11px" onclick="return confirm('Xóa đánh giá này?')">Xóa</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal thêm đánh giá -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title fw-700">Thêm đánh giá</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <input type="hidden" name="act" value="add">
        <div class="modal-body">
          <div class="mb-2"><label class="form-label small fw-600">Tên khách hàng *</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label small fw-600">Dịch vụ đã dùng</label><input type="text" name="service" class="form-control" placeholder="VPS Basic, Bot Discord..."></div>
          <div class="mb-2"><label class="form-label small fw-600">Rating</label>
            <select name="rating" class="form-select">
              <?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= str_repeat('★',$i) ?></option><?php endfor; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label small fw-600">Nội dung *</label><textarea name="text" class="form-control" rows="4" required></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary">Thêm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
