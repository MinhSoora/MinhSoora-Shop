<?php
// admin/product_edit.php
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

$pid = $_GET['id'] ?? '';
$product = $pid ? DB::find('products','id',$pid) : null;

// ── Image upload AJAX ───────────────────────────────────────────────────
if (!empty($_GET['upload_img']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $pid2 = $_GET['pid'] ?? $pid;
    if (!$pid2) { echo json_encode(['error' => 'no pid']); exit; }
    $file = $_FILES['image'] ?? null;
    if (!$file || $file['error']) { echo json_encode(['error' => 'no file']); exit; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['error' => 'Chỉ chấp nhận jpg/png/webp/gif']); exit; }
    if ($file['size'] > 5 * 1024 * 1024) { echo json_encode(['error' => 'File quá lớn (tối đa 5MB)']); exit; }
    $fname = $pid2 . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $fname)) {
        echo json_encode(['error' => 'Không thể lưu file — kiểm tra quyền thư mục']); exit;
    }
    $prod2 = DB::find('products','id',$pid2);
    $imgs  = $prod2['images'] ?? [];
    $imgs[] = $fname;
    DB::update('products','id',$pid2,['images' => $imgs]);
    echo json_encode(['success' => true, 'filename' => $fname, 'url' => UPLOAD_URL . '/' . $fname]);
    exit;
}

// ── Image delete AJAX ───────────────────────────────────────────────────
if (!empty($_GET['del_img']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $pid2  = $_POST['pid']   ?? $pid;
    $fname = $_POST['fname'] ?? '';
    if ($pid2 && $fname) {
        $prod2 = DB::find('products','id',$pid2);
        $imgs  = array_values(array_filter($prod2['images'] ?? [], fn($i) => $i !== $fname));
        DB::update('products','id',$pid2,['images' => $imgs]);
        @unlink(UPLOAD_DIR . '/' . $fname);
        echo json_encode(['success' => true]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_product'])) {
    // Parse options JSON
    $options = [];
    foreach ($_POST['opt_name']??[] as $i=>$name) {
        if (!trim($name)) continue;
        $options[] = [
            'id'    => $_POST['opt_id'][$i] ?: DB::nextId('opt_'),
            'name'  => trim($name),
            'price' => max(0,(int)$_POST['opt_price'][$i]),
            'type'  => 'add',
        ];
    }
    $fields = [];
    foreach ($_POST['fld_name']??[] as $i=>$name) {
        if (!trim($name)) continue;
        $fldOpts = [];
        if (!empty($_POST['fld_opts'][$i])) {
            $fldOpts = array_values(array_filter(array_map('trim', explode("\n", $_POST['fld_opts'][$i]))));
        }
        $fields[] = [
            'id'       => $_POST['fld_id'][$i] ?: DB::nextId('fld_'),
            'name'     => trim($name),
            'type'     => $_POST['fld_type'][$i] ?: 'text',
            'options'  => $fldOpts,
            'required' => isset($_POST['fld_req'][$i]),
        ];
    }
    $data = [
        'id'          => $pid ?: DB::nextId('prd_'),
        'category_id' => $_POST['category_id'] ?? '',
        'name'        => trim($_POST['name'] ?? ''),
        'slug'        => slug(trim($_POST['name'] ?? '')),
        'short_desc'  => trim($_POST['short_desc'] ?? ''),
        'description' => $_POST['description'] ?? '',
        'price'       => max(0,(int)preg_replace('/\D/','',$_POST['price']??0)),
        'price_type'  => in_array($_POST['price_type']??'',['monthly','onetime','custom']) ? $_POST['price_type'] : 'monthly',
        'options'     => $options,
        'fields'      => $fields,
        'active'      => isset($_POST['active']),
        'featured'    => isset($_POST['featured']),
        'images'      => $product['images'] ?? [],
        'created_at'  => $product['created_at'] ?? date('c'),
        'updated_at'  => date('c'),
    ];
    if ($pid) DB::update('products','id',$pid,$data);
    else { DB::append('products',$data); $pid = $data['id']; }
    flash('success','Đã lưu sản phẩm');
    redirect('/admin/product_edit.php?id='.$pid);
}

$adminPageTitle = 'Sửa sản phẩm';
require_once __DIR__ . '/header.php';

$product = $pid ? DB::find('products','id',$pid) : [];
$categories = DB::read('categories',[]);
?>
<div class="d-flex gap-2 mb-3">
  <a href="/admin/products.php" class="btn btn-sm btn-outline-secondary">← Quay lại</a>
  <?php if ($pid): ?><a href="/product.php?slug=<?= h($product['slug']??'') ?>" target="_blank" class="btn btn-sm btn-outline-primary">👁 Xem trang</a><?php endif; ?>
</div>
<form method="POST" id="prod-form">
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header bg-white fw-700">Thông tin cơ bản</div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-8"><label class="form-label small fw-600">Tên sản phẩm *</label>
            <input type="text" name="name" class="form-control" value="<?= h($product['name']??'') ?>" required></div>
          <div class="col-md-4"><label class="form-label small fw-600">Danh mục</label>
            <select name="category_id" class="form-select">
              <?php foreach ($categories as $c): ?>
              <option value="<?= h($c['id']) ?>" <?= ($product['category_id']??'')===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12"><label class="form-label small fw-600">Mô tả ngắn</label>
            <input type="text" name="short_desc" class="form-control" value="<?= h($product['short_desc']??'') ?>"></div>
          <div class="col-md-5"><label class="form-label small fw-600">Giá (₫) *</label>
            <input type="number" name="price" class="form-control" value="<?= (int)($product['price']??0) ?>" min="0" required></div>
          <div class="col-md-4"><label class="form-label small fw-600">Loại giá</label>
            <select name="price_type" class="form-select">
              <option value="monthly" <?= ($product['price_type']??'')!=='onetime'&&($product['price_type']??'')!=='custom'?'selected':'' ?>>Hàng tháng</option>
              <option value="onetime" <?= ($product['price_type']??'')==='onetime'?'selected':'' ?>>Một lần</option>
              <option value="custom" <?= ($product['price_type']??'')==='custom'?'selected':'' ?>>Linh hoạt</option>
            </select>
          </div>
          <div class="col-md-3 d-flex flex-column gap-1 justify-content-end">
            <div class="form-check"><input type="checkbox" name="active" id="act" class="form-check-input" <?= !empty($product['active'])?'checked':'' ?>><label class="form-check-label small" for="act">Kích hoạt</label></div>
            <div class="form-check"><input type="checkbox" name="featured" id="feat" class="form-check-input" <?= !empty($product['featured'])?'checked':'' ?>><label class="form-check-label small" for="feat">⭐ Nổi bật</label></div>
          </div>
          <div class="col-12"><label class="form-label small fw-600">Mô tả chi tiết (Markdown)</label>
            <textarea name="description" class="form-control" rows="8" style="font-family:monospace;font-size:13px"><?= h($product['description']??'') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Options -->
    <div class="card mb-3">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-700">⚙ Tùy chọn bổ sung</span>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addOption()">+ Thêm</button>
      </div>
      <div class="card-body">
        <div id="options-list">
          <?php foreach ($product['options']??[] as $opt): ?>
          <div class="d-flex gap-2 mb-2 option-row">
            <input type="hidden" name="opt_id[]" value="<?= h($opt['id']) ?>">
            <input type="text" name="opt_name[]" class="form-control form-control-sm" placeholder="Tên tùy chọn" value="<?= h($opt['name']) ?>">
            <input type="number" name="opt_price[]" class="form-control form-control-sm" style="width:130px" placeholder="Giá thêm (₫)" value="<?= (int)$opt['price'] ?>">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="text-muted small mt-1">Khách hàng có thể tick chọn để thêm giá</div>
      </div>
    </div>

    <!-- Fields -->
    <div class="card">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-700">📝 Trường thông tin yêu cầu</span>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addField()">+ Thêm</button>
      </div>
      <div class="card-body">
        <div id="fields-list">
          <?php foreach ($product['fields']??[] as $fld): ?>
          <div class="border rounded p-2 mb-2 field-row">
            <input type="hidden" name="fld_id[]" value="<?= h($fld['id']) ?>">
            <div class="d-flex gap-2 mb-2">
              <input type="text" name="fld_name[]" class="form-control form-control-sm" placeholder="Tên trường" value="<?= h($fld['name']) ?>">
              <select name="fld_type[]" class="form-select form-select-sm" style="width:130px" onchange="toggleFieldOpts(this)">
                <?php foreach (['text'=>'Text','password'=>'Password','textarea'=>'Textarea','select'=>'Dropdown'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($fld['type']??'')===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-check d-flex align-items-center">
                <input type="checkbox" name="fld_req[<?= array_search($fld,$product['fields']??[]) ?>]" class="form-check-input" <?= !empty($fld['required'])?'checked':'' ?> title="Bắt buộc">
                <label class="form-check-label small ms-1">*</label>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.field-row').remove()">✕</button>
            </div>
            <div class="field-opts-row" style="<?= ($fld['type']??'')==='select'?'':'display:none' ?>">
              <textarea name="fld_opts[]" class="form-control form-control-sm" rows="3" placeholder="Mỗi dòng một lựa chọn"><?= h(implode("\n",$fld['options']??[])) ?></textarea>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <!-- Images -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-700">🖼️ Ảnh sản phẩm</div>
      <div class="card-body">
        <?php if (!$pid): ?>
        <div class="alert alert-info py-2 small">Lưu sản phẩm trước, sau đó thêm ảnh.</div>
        <?php else: ?>
        <div id="img-grid" class="d-flex flex-wrap gap-2 mb-3">
          <?php foreach ($product['images'] ?? [] as $img): ?>
          <div class="img-thumb" data-fname="<?= h($img) ?>" style="position:relative">
            <img src="<?= h(UPLOAD_URL.'/'.$img) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #e2e8f0">
            <button type="button" onclick="deleteImg('<?= h($img) ?>')" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:20px;height:20px;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center">✕</button>
          </div>
          <?php endforeach; ?>
          <?php if (empty($product['images'])): ?>
          <div class="text-muted small py-2">Chưa có ảnh nào</div>
          <?php endif; ?>
        </div>
        <div class="upload-zone" id="upload-zone"
          style="border:2px dashed #cbd5e1;border-radius:10px;padding:20px;text-align:center;cursor:pointer;transition:.2s"
          ondragover="this.style.borderColor='#2563eb';this.style.background='#eff6ff';event.preventDefault()"
          ondragleave="this.style.borderColor='#cbd5e1';this.style.background=''"
          ondrop="handleDrop(event)">
          <input type="file" id="img-input" accept="image/*" multiple style="display:none" onchange="uploadImages(this.files)">
          <i class="bi bi-cloud-upload" style="font-size:1.8rem;color:#94a3b8"></i>
          <div class="small text-muted mt-1">Kéo thả ảnh vào đây hoặc</div>
          <button type="button" onclick="document.getElementById('img-input').click()" class="btn btn-sm btn-outline-primary mt-2">Chọn ảnh</button>
          <div class="text-muted" style="font-size:11px;margin-top:6px">JPG/PNG/WebP, tối đa 5MB mỗi ảnh</div>
        </div>
        <div id="upload-progress" style="display:none" class="mt-2">
          <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>
          <div class="small text-muted mt-1" id="upload-status">Đang tải...</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card sticky-top" style="top:80px">
      <div class="card-body">
        <button type="submit" name="save_product" class="btn btn-primary w-100 mb-3"
          onclick="return confirm('Lưu sản phẩm?')">💾 Lưu sản phẩm</button>
        <div class="text-muted small">
          <div>Slug: <code><?= h(slug($product['name']??'ten-san-pham')) ?></code></div>
          <?php if ($pid): ?><div class="mt-1">ID: <code style="font-size:11px"><?= h($pid) ?></code></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</form>

<script>
function addOption() {
  const div = document.createElement('div');
  div.className = 'd-flex gap-2 mb-2 option-row';
  div.innerHTML = '<input type="hidden" name="opt_id[]" value="">'
    +'<input type="text" name="opt_name[]" class="form-control form-control-sm" placeholder="Tên tùy chọn">'
    +'<input type="number" name="opt_price[]" class="form-control form-control-sm" style="width:130px" placeholder="Giá thêm (₫)">'
    +'<button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">✕</button>';
  document.getElementById('options-list').appendChild(div);
}

let fldIdx = <?= count($product['fields']??[]) ?>;
function addField() {
  const div = document.createElement('div');
  div.className = 'border rounded p-2 mb-2 field-row';
  div.innerHTML = '<input type="hidden" name="fld_id[]" value="">'
    +'<div class="d-flex gap-2 mb-2">'
    +'<input type="text" name="fld_name[]" class="form-control form-control-sm" placeholder="Tên trường">'
    +'<select name="fld_type[]" class="form-select form-select-sm" style="width:130px" onchange="toggleFieldOpts(this)">'
    +'<option value="text">Text</option><option value="password">Password</option><option value="textarea">Textarea</option><option value="select">Dropdown</option>'
    +'</select>'
    +'<div class="form-check d-flex align-items-center"><input type="checkbox" name="fld_req['+fldIdx+']" class="form-check-input" title="Bắt buộc"><label class="form-check-label small ms-1">*</label></div>'
    +'<button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'.field-row\').remove()">✕</button>'
    +'</div>'
    +'<div class="field-opts-row" style="display:none"><textarea name="fld_opts[]" class="form-control form-control-sm" rows="3" placeholder="Mỗi dòng một lựa chọn"></textarea></div>';
  document.getElementById('fields-list').appendChild(div);
  fldIdx++;
}

function toggleFieldOpts(sel) {
  const row = sel.closest('.field-row').querySelector('.field-opts-row');
  row.style.display = sel.value === 'select' ? '' : 'none';
}

// ── Image upload ─────────────────────────────────────────────────────────
const PROD_ID = '<?= h($pid) ?>';
async function uploadImages(files) {
  if (!PROD_ID) { alert('Lưu sản phẩm trước khi thêm ảnh'); return; }
  const prog = document.getElementById('upload-progress');
  const stat = document.getElementById('upload-status');
  prog.style.display = '';
  for (const file of files) {
    stat.textContent = 'Đang tải: ' + file.name;
    const fd = new FormData(); fd.append('image', file);
    try {
      const r = await fetch(`?upload_img=1&id=<?= h($pid) ?>&pid=<?= h($pid) ?>`, {method:'POST',body:fd}).then(x=>x.json());
      if (r.success) addImgThumb(r.filename, r.url);
      else alert('Lỗi: ' + r.error);
    } catch(e) { alert('Lỗi upload: ' + e.message); }
  }
  prog.style.display = 'none';
}

function addImgThumb(fname, url) {
  const grid = document.getElementById('img-grid');
  const oldEmpty = grid.querySelector('.text-muted');
  if (oldEmpty) oldEmpty.remove();
  const div = document.createElement('div');
  div.className = 'img-thumb';
  div.dataset.fname = fname;
  div.style.position = 'relative';
  div.innerHTML = `<img src="${url}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #e2e8f0">
    <button type="button" onclick="deleteImg('${fname}')" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:20px;height:20px;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center">✕</button>`;
  grid.appendChild(div);
}

async function deleteImg(fname) {
  if (!confirm('Xóa ảnh này?')) return;
  const fd = new FormData(); fd.append('pid', PROD_ID); fd.append('fname', fname);
  const r = await fetch(`?del_img=1&id=<?= h($pid) ?>`, {method:'POST',body:fd}).then(x=>x.json());
  if (r.success) document.querySelector(`.img-thumb[data-fname="${fname}"]`)?.remove();
  else alert('Lỗi xóa ảnh');
}

function handleDrop(e) {
  e.preventDefault();
  document.getElementById('upload-zone').style.borderColor = '#cbd5e1';
  document.getElementById('upload-zone').style.background  = '';
  uploadImages(e.dataTransfer.files);
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
