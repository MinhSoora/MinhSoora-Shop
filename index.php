<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Trang chủ';
$settings  = getSettings();
$products  = DB::filter('products', fn($p) => !empty($p['active']));
$featured  = array_filter($products, fn($p) => !empty($p['featured']));
$reviews   = DB::filter('reviews', fn($r) => !empty($r['approved']));
usort($reviews, fn($a,$b) => strcmp($b['created_at']??'',$a['created_at']??''));
$reviews   = array_slice($reviews, 0, 9);
$totalUsers  = max(1, DB::count('users', fn($u) => ($u['role']??'') !== 'admin'));
$totalOrders = max(1, DB::count('orders', fn($o) => in_array($o['status']??'', ['active','processing'])));
include __DIR__ . '/includes/header.php';
?>
<style>
.reveal{opacity:0;transform:translateY(26px);transition:opacity .65s ease,transform .65s ease}
.reveal.visible{opacity:1;transform:none}
#particle-canvas{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0}
.hero-wrap{position:relative;z-index:1;background:linear-gradient(135deg,#020617 0%,#0f172a 45%,#1e1b4b 100%);padding:88px 0 72px;overflow:hidden;min-height:88vh;display:flex;align-items:center}
.hero-wrap::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 18% 55%,rgba(37,99,235,.38),transparent 52%),radial-gradient(ellipse at 82% 35%,rgba(124,58,237,.28),transparent 52%),radial-gradient(ellipse at 50% 100%,rgba(14,165,233,.18),transparent 45%)}
.orb{position:absolute;border-radius:50%;filter:blur(90px);pointer-events:none;animation:forb 9s ease-in-out infinite}
.orb1{width:480px;height:480px;background:rgba(37,99,235,.22);top:-120px;left:-80px}
.orb2{width:360px;height:360px;background:rgba(124,58,237,.18);bottom:-60px;right:-60px;animation-delay:-5s}
.orb3{width:200px;height:200px;background:rgba(14,165,233,.15);top:35%;left:42%;animation-delay:-2.5s}
@keyframes forb{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-26px) scale(1.04)}}
.hero-wrap .container{position:relative;z-index:2}
.pill{display:inline-flex;align-items:center;gap:8px;background:rgba(37,99,235,.18);border:1px solid rgba(37,99,235,.35);border-radius:30px;padding:7px 18px;font-size:13px;color:#93c5fd;margin-bottom:1.75rem;backdrop-filter:blur(6px)}
.pill .dot{width:7px;height:7px;border-radius:50%;background:#38bdf8;animation:blink 1.6s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.hero-title{font-size:clamp(2.4rem,7vw,4rem);font-weight:900;line-height:1.1;margin-bottom:.6rem;background:linear-gradient(135deg,#fff 0%,#93c5fd 45%,#c4b5fd 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hero-sub{color:#94a3b8;font-size:1.1rem;max-width:520px;margin:0 auto 2.2rem;line-height:1.75}
.btn-glow{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff!important;font-weight:700;font-size:1rem;padding:15px 38px;border-radius:14px;text-decoration:none;box-shadow:0 10px 36px rgba(37,99,235,.45);transition:.3s;position:relative;overflow:hidden}
.btn-glow::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,#7c3aed,#2563eb);opacity:0;transition:.3s}
.btn-glow:hover{transform:translateY(-3px);box-shadow:0 18px 48px rgba(37,99,235,.55)}
.btn-glow:hover::before{opacity:1}
.btn-glow span{position:relative;z-index:1}
.btn-ghost{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.18);color:#e2e8f0!important;font-weight:600;font-size:1rem;padding:15px 34px;border-radius:14px;text-decoration:none;backdrop-filter:blur(8px);transition:.3s}
.btn-ghost:hover{background:rgba(255,255,255,.14);color:#fff!important;transform:translateY(-2px)}
.stats-bar{display:flex;justify-content:center;gap:52px;flex-wrap:wrap;margin-top:3.5rem}
.stat-item .num{font-size:2.4rem;font-weight:900;line-height:1;background:linear-gradient(135deg,#38bdf8,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.stat-item .lbl{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:1.5px;margin-top:5px}
.scroll-arr{position:absolute;bottom:30px;left:50%;transform:translateX(-50%);z-index:2;animation:barr 2s ease-in-out infinite}
@keyframes barr{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(8px)}}
.s-eye{font-size:12px;text-transform:uppercase;letter-spacing:2px;color:#2563eb;font-weight:700;margin-bottom:.4rem}
.s-head{font-size:clamp(1.6rem,4vw,2.2rem);font-weight:900;line-height:1.15;color:#0f172a}
.s-head span{background:linear-gradient(135deg,#2563eb,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.pcard{background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;transition:box-shadow .28s,transform .28s;height:100%;position:relative}
.pcard:hover{box-shadow:0 20px 56px rgba(37,99,235,.13);transform:translateY(-5px)}
.pcard .iw{height:190px;overflow:hidden}
.pcard .iw img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.pcard:hover .iw img{transform:scale(1.05)}
.pcard .ip{height:190px;display:flex;align-items:center;justify-content:center;font-size:3.8rem;background:linear-gradient(135deg,#eff6ff,#e0e7ff)}
.pcard .bf{position:absolute;top:13px;right:13px;background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;font-size:11px;padding:4px 10px;border-radius:8px;font-weight:700;z-index:1}
.pcard-price{font-size:1.55rem;font-weight:900;color:#2563eb}
.pcard-ptype{font-size:11px;color:#94a3b8}
.btn-ord{background:linear-gradient(135deg,#2563eb,#0ea5e9);color:#fff;border:none;border-radius:10px;padding:8px 20px;font-weight:700;font-size:13px;transition:.2s;text-decoration:none}
.btn-ord:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(37,99,235,.35);color:#fff}
.how-section{background:linear-gradient(135deg,#f8faff,#f0f4ff);border-top:1px solid #e0e7ff;border-bottom:1px solid #e0e7ff}
.hcard{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:30px 22px;text-align:center;transition:.28s;position:relative;overflow:hidden}
.hcard::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(37,99,235,.03),transparent);opacity:0;transition:.3s}
.hcard:hover{box-shadow:0 12px 40px rgba(37,99,235,.1);transform:translateY(-5px)}
.hcard:hover::before{opacity:1}
.hicon{width:66px;height:66px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:2.2rem;margin:0 auto 16px}
.hnum{position:absolute;top:14px;left:18px;font-size:52px;font-weight:900;opacity:.04;line-height:1;color:#2563eb}
.reviews-wrap{background:linear-gradient(135deg,#0f172a,#1e293b);padding:88px 0}
.rcard{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:18px;padding:26px;transition:.28s;backdrop-filter:blur(10px)}
.rcard:hover{background:rgba(255,255,255,.09);border-color:rgba(59,130,246,.4);transform:translateY(-3px)}
.rcard .stars{font-size:15px;color:#fbbf24;letter-spacing:1px;margin-bottom:10px}
.rv-body{color:#cbd5e1;font-size:14px;line-height:1.8;font-style:italic}
.rv-auth{display:flex;align-items:center;gap:12px;margin-top:16px}
.rv-av{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:15px;flex-shrink:0}
.rv-name{font-weight:700;font-size:14px;color:#e2e8f0}
.rv-meta{font-size:11px;color:#475569;margin-top:1px}
.rv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(285px,1fr));gap:20px}
.cta-wrap{background:linear-gradient(135deg,#1e1b4b,#0f172a);padding:100px 0;text-align:center;position:relative;overflow:hidden}
.cta-wrap::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at center 40%,rgba(37,99,235,.35),transparent 65%)}
.cta-wrap .container{position:relative;z-index:1}
.cta-title{font-size:clamp(2rem,5vw,3rem);font-weight:900;color:#fff;line-height:1.15;margin-bottom:.75rem}
</style>

<canvas id="particle-canvas"></canvas>

<!-- HERO -->
<div class="hero-wrap">
  <div class="orb orb1"></div><div class="orb orb2"></div><div class="orb orb3"></div>
  <div class="container text-center">
    <div class="pill"><span class="dot"></span>Uptime 99.9% — Hỗ trợ 24/7</div>
    <h1 class="hero-title"><?= h($settings['shop_name'] ?? 'NodeShop') ?></h1>
    <p class="hero-sub mx-auto"><?= h($settings['shop_desc'] ?? 'Dịch vụ hosting & server chất lượng cao — tốc độ, ổn định, hỗ trợ tận tâm') ?></p>
    <div class="d-flex gap-3 justify-content-center flex-wrap">
      <a href="/products.php" class="btn-glow"><span><i class="bi bi-grid me-1"></i>Khám phá dịch vụ</span></a>
      <a href="/track-order.php" class="btn-ghost"><i class="bi bi-search me-1"></i>Tra cứu đơn hàng</a>
    </div>
    <div class="stats-bar">
      <div class="stat-item"><div class="num" data-count="<?= count($products) ?>"><?= count($products) ?></div><div class="lbl">Dịch vụ</div></div>
      <div class="stat-item"><div class="num" data-count="<?= $totalUsers ?>"><?= $totalUsers ?></div><div class="lbl">Khách hàng</div></div>
      <div class="stat-item"><div class="num" data-count="<?= $totalOrders ?>"><?= $totalOrders ?></div><div class="lbl">Đang hoạt động</div></div>
      <div class="stat-item"><div class="num">99.9<span style="-webkit-text-fill-color:#818cf8;font-size:.7em">%</span></div><div class="lbl">Uptime</div></div>
    </div>
  </div>
  <div class="scroll-arr">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
  </div>
</div>

<!-- FEATURED -->
<div style="position:relative;z-index:1;background:#fff">
<div class="container py-5">
  <div class="d-flex align-items-end justify-content-between mb-5 flex-wrap gap-3">
    <div class="reveal"><div class="s-eye">Dịch vụ</div><h2 class="s-head mb-0">Nổi bật & Được <span>tin dùng nhất</span></h2></div>
    <a href="/products.php" class="btn btn-outline-primary reveal">Xem tất cả <i class="bi bi-arrow-right ms-1"></i></a>
  </div>
  <div class="row g-4">
    <?php foreach ($featured as $p): ?>
    <div class="col-md-6 col-lg-4 reveal">
      <div class="pcard">
        <?php if (!empty($p['featured'])): ?><div class="bf">⭐ Nổi bật</div><?php endif; ?>
        <?php if (!empty($p['images'][0])): ?>
        <div class="iw"><img src="<?= h(UPLOAD_URL.'/'.$p['images'][0]) ?>" alt="<?= h($p['name']) ?>"></div>
        <?php else: ?>
        <div class="ip"><?= h(DB::find('categories','id',$p['category_id'])['icon']??'📦') ?></div>
        <?php endif; ?>
        <div class="card-body d-flex flex-column p-4">
          <?php $cat = DB::find('categories','id',$p['category_id']); ?>
          <?php if ($cat): ?><span class="badge bg-light text-muted border mb-2" style="font-size:11px"><?= h($cat['icon']??'') ?> <?= h($cat['name']) ?></span><?php endif; ?>
          <h5 class="fw-800 mb-1"><?= h($p['name']) ?></h5>
          <p class="text-muted small flex-grow-1"><?= h($p['short_desc']??'') ?></p>
          <div class="d-flex align-items-end justify-content-between mt-3">
            <div><div class="pcard-price"><?= fmtMoney($p['price']) ?></div><div class="pcard-ptype"><?= $p['price_type']==='monthly'?'/ tháng':($p['price_type']==='onetime'?'Một lần':'Linh hoạt') ?></div></div>
            <a href="/product.php?slug=<?= h($p['slug']) ?>" class="btn-ord">Xem chi tiết</a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($featured)): ?>
    <div class="col-12 text-center py-5 text-muted reveal">
      <i class="bi bi-box-seam" style="font-size:3rem;opacity:.4"></i>
      <p class="mt-2">Chưa có sản phẩm nổi bật</p>
      <a href="/products.php" class="btn btn-primary">Xem tất cả sản phẩm</a>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>

<!-- HOW IT WORKS -->
<div class="how-section" style="position:relative;z-index:1">
<div class="container py-5">
  <div class="text-center mb-5">
    <div class="s-eye reveal">Quy trình</div>
    <h2 class="s-head reveal">Đơn giản — <span>Nhanh chóng</span> — Tự động</h2>
  </div>
  <div class="row g-4">
    <?php foreach ([
      ['📋','Chọn dịch vụ','Duyệt danh sách và chọn gói phù hợp với nhu cầu của bạn','linear-gradient(135deg,#eff6ff,#dbeafe)'],
      ['🛒','Đặt hàng','Nhận ngay mã đơn để tra cứu tiến trình bất cứ lúc nào, không cần đăng nhập','linear-gradient(135deg,#f0fdf4,#dcfce7)'],
      ['💳','Thanh toán','Chuyển khoản ngân hàng — xác nhận tự động qua Sepay 24/7','linear-gradient(135deg,#fef9c3,#fde68a)'],
      ['⚡','Kích hoạt','Nhận thông tin truy cập và bắt đầu sử dụng ngay lập tức','linear-gradient(135deg,#fce7f3,#fbcfe8)'],
    ] as $i=>[$ic,$t,$d,$bg]): ?>
    <div class="col-md-6 col-lg-3 reveal">
      <div class="hcard h-100">
        <span class="hnum"><?= $i+1 ?></span>
        <div class="hicon" style="background:<?= $bg ?>"><?= $ic ?></div>
        <h6 class="fw-800 mb-2"><?= $t ?></h6>
        <p class="text-muted small mb-0"><?= $d ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-4 reveal">
    <a href="/track-order.php" class="btn btn-outline-primary btn-sm px-4">
      <i class="bi bi-search me-1"></i>Tra cứu đơn hàng không cần đăng nhập
    </a>
  </div>
</div>
</div>

<!-- REVIEWS -->
<?php if (!empty($reviews)): ?>
<div class="reviews-wrap" style="position:relative;z-index:1">
  <div class="container">
    <div class="text-center mb-5">
      <div class="reveal" style="font-size:12px;text-transform:uppercase;letter-spacing:2px;color:#38bdf8;font-weight:700;margin-bottom:.5rem">Đánh giá</div>
      <h2 class="fw-900 reveal" style="color:#fff;font-size:clamp(1.6rem,4vw,2.2rem)">💬 Khách hàng nói gì về chúng tôi?</h2>
      <p class="reveal" style="color:#64748b"><?= count($reviews) ?>+ đánh giá từ cộng đồng</p>
    </div>
    <div class="rv-grid">
      <?php
      $grads=['linear-gradient(135deg,#2563eb,#7c3aed)','linear-gradient(135deg,#0ea5e9,#2563eb)','linear-gradient(135deg,#7c3aed,#ec4899)','linear-gradient(135deg,#16a34a,#0ea5e9)','linear-gradient(135deg,#f59e0b,#ef4444)'];
      foreach ($reviews as $i=>$rv):
        $stars=min(5,max(1,(int)($rv['rating']??5)));
        $grad=$grads[$i%count($grads)];
      ?>
      <div class="rcard reveal">
        <div class="stars"><?= str_repeat('★',$stars).str_repeat('☆',5-$stars) ?></div>
        <div class="rv-body">"<?= h($rv['text']??'') ?>"</div>
        <div class="rv-auth">
          <div class="rv-av" style="background:<?= $grad ?>"><?= strtoupper(mb_substr($rv['name']??'A',0,1)) ?></div>
          <div>
            <div class="rv-name"><?= h($rv['name']??'Khách hàng') ?></div>
            <div class="rv-meta"><?= h($rv['service']??'') ?><?= !empty($rv['service'])?' · ':'' ?><?= date('d/m/Y',strtotime($rv['created_at']??'now')) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- CTA -->
<div class="cta-wrap" style="position:relative;z-index:1">
  <div class="container">
    <div class="reveal">
      <h2 class="cta-title">Sẵn sàng bắt đầu?</h2>
      <p style="color:#94a3b8;font-size:1.1rem;margin-bottom:2rem">Hàng trăm khách hàng đang tin dùng dịch vụ mỗi ngày.</p>
      <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="/products.php" class="btn-glow"><span><i class="bi bi-lightning-charge me-1"></i>Bắt đầu ngay</span></a>
        <?php if (!Auth::isLoggedIn()): ?>
        <a href="/register.php" class="btn-ghost"><i class="bi bi-person-plus me-1"></i>Tạo tài khoản miễn phí</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Particles
(function(){
  const c=document.getElementById('particle-canvas');
  if(!c) return;
  const ctx=c.getContext('2d');
  let W,H;
  function resize(){W=c.width=innerWidth;H=c.height=innerHeight;}
  resize();addEventListener('resize',resize);
  const pts=Array.from({length:55},()=>({x:Math.random()*innerWidth,y:Math.random()*innerHeight,r:Math.random()*1.8+.4,vx:(Math.random()-.5)*.25,vy:(Math.random()-.5)*.25,a:Math.random()*.4+.08}));
  function draw(){
    ctx.clearRect(0,0,W,H);
    for(let i=0;i<pts.length;i++) for(let j=i+1;j<pts.length;j++){const d=Math.hypot(pts[i].x-pts[j].x,pts[i].y-pts[j].y);if(d<130){ctx.beginPath();ctx.strokeStyle=`rgba(59,130,246,${.12*(1-d/130)})`;ctx.lineWidth=.8;ctx.moveTo(pts[i].x,pts[i].y);ctx.lineTo(pts[j].x,pts[j].y);ctx.stroke();}}
    pts.forEach(p=>{ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle=`rgba(99,102,241,${p.a})`;ctx.fill();p.x+=p.vx;p.y+=p.vy;if(p.x<0||p.x>W)p.vx*=-1;if(p.y<0||p.y>H)p.vy*=-1;});
    requestAnimationFrame(draw);
  }
  draw();
})();
// Scroll reveal
const ro=new IntersectionObserver(e=>e.forEach(x=>{if(x.isIntersecting)x.target.classList.add('visible');}),{threshold:.12});
document.querySelectorAll('.reveal').forEach(el=>ro.observe(el));
// Counter
const co=new IntersectionObserver(e=>e.forEach(x=>{
  if(!x.isIntersecting)return;
  const el=x.target,tgt=parseInt(el.dataset.count);
  if(isNaN(tgt))return;
  let cur=0;const step=tgt/45;
  const t=setInterval(()=>{cur=Math.min(cur+step,tgt);el.querySelector('span').textContent=Math.round(cur);if(cur>=tgt)clearInterval(t);},35);
  co.unobserve(el);
}),{threshold:.6});
document.querySelectorAll('[data-count]').forEach(el=>{el.innerHTML=`<span>${el.textContent}</span>`;co.observe(el);});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
