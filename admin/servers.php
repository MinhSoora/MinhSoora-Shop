<?php
/**
 * admin/servers.php — Quản lý server với Console, File Manager, Crash Log + Groq AI
 * [SECURITY FIX] adminManagerCall undefined → đổi thành adminBridgeCall
 * [SECURITY FIX] CSRF protection trên tất cả POST
 * [SECURITY FIX] Path traversal sanitization cho file manager
 */
require_once dirname(__DIR__) . '/includes/config.php';
Auth::requireAdmin();

// ── Helper bridge với quyền admin (*) ────────────────────────────────────────
function adminBridgeCall(string $path, string $method = 'GET', array $data = []): array {
    $url     = MANAGER_URL . $path;
    $headers = [
        'Content-Type: application/json',
        'x-bridge-token: '      . BRIDGE_SECRET,
        'x-client-uid: admin',
        'x-client-server-ids: *',
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,   CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($method === 'POST')     { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    elseif ($method !== 'GET')  { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    $resp     = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($curlErr)          return ['_err' => $curlErr];
    if ($httpCode === 401) return ['_err' => 'Bridge token không hợp lệ (401)'];
    if ($httpCode === 0)   return ['_err' => 'Không kết nối được tới Node.js Manager (' . MANAGER_URL . ')'];
    if ($httpCode >= 500)  return ['_err' => "Node.js Manager lỗi nội bộ (HTTP {$httpCode})"];
    $decoded = json_decode($resp ?: '{}', true);
    if ($decoded === null) return ['_err' => 'Phản hồi không phải JSON (HTTP ' . $httpCode . '): ' . substr($resp, 0, 100)];
    return $decoded;
}

// ── Phát hiện crash (running → crashed) ──────────────────────────────────────
function detectAndRecordCrash(string $sid, array $newStatus): void {
    $cacheFile = DATA_DIR . '/srv_status_cache.json';
    $cache     = [];
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true) ?? [];
    }
    $prevStatus = $cache[$sid] ?? null;
    $curStatus  = $newStatus['status'] ?? 'unknown';
    if ($prevStatus === 'running' && $curStatus === 'crashed') {
        $logs     = adminBridgeCall('/api/client/server/' . rawurlencode($sid) . '/logs?limit=80');
        $logLines = $logs['logs'] ?? [];
        recordCrash($sid, $newStatus['name'] ?? $sid, $logLines, $prevStatus);
    }
    $cache[$sid] = $curStatus;
    if (count($cache) > 500) $cache = array_slice($cache, -500, null, true);
    file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ── AJAX handler (phải chạy TRƯỚC html output) ───────────────────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    // [SECURITY FIX] CSRF check cho mọi POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!Auth::verifyCsrf($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token không hợp lệ. Hãy tải lại trang.']);
            exit;
        }
    }

    switch ($action) {
        // ─── Danh sách server ──────────────────────────────────────────────────
        case 'list':
            $servers = adminBridgeCall('/api/client/servers');
            if (isset($servers['_err'])) { echo json_encode(['_err' => $servers['_err']]); exit; }
            $users   = DB::read('users', []);
            $crashes = getCrashes();
            foreach ($servers as &$srv) {
                $owners = [];
                foreach ($users as $u) {
                    if (in_array($srv['id'], $u['server_ids'] ?? [])) {
                        $owners[] = ['id' => $u['id'], 'username' => $u['username']];
                    }
                }
                $srv['_owners']      = $owners;
                $srv['_crash_count'] = count(array_filter($crashes, fn($c) => ($c['server_id'] ?? '') === $srv['id']));
                detectAndRecordCrash($srv['id'], $srv);
            }
            echo json_encode($servers);
            break;

        // ─── Status realtime ───────────────────────────────────────────────────
        case 'status':
            $id = $_GET['id'] ?? '';
            if (!$id) { echo json_encode(['error' => 'Thiếu id']); break; }
            $r = adminBridgeCall('/api/client/server/' . rawurlencode($id));
            if (!isset($r['_err'])) detectAndRecordCrash($id, $r);
            echo json_encode($r);
            break;

        // ─── Logs ──────────────────────────────────────────────────────────────
        case 'logs':
            $id    = $_GET['id']    ?? '';
            $limit = min((int)($_GET['limit'] ?? 200), 1000);
            if (!$id) { echo json_encode(['error' => 'Thiếu id']); break; }
            echo json_encode(adminBridgeCall('/api/client/server/' . rawurlencode($id) . '/logs?limit=' . $limit));
            break;

        // ─── Files list ────────────────────────────────────────────────────────
        case 'files':
            $id   = $_GET['id']   ?? '';
            $path = $_GET['path'] ?? '';
            // [SECURITY FIX] Chặn path traversal
            if (strpos($path, '..') !== false) { echo json_encode(['error' => 'Đường dẫn không hợp lệ']); break; }
            if (!$id) { echo json_encode(['error' => 'Thiếu id']); break; }
            echo json_encode(adminBridgeCall('/api/client/server/' . rawurlencode($id) . '/files?path=' . urlencode($path)));
            break;

        // ─── File read ─────────────────────────────────────────────────────────
        case 'file_read':
            $id   = $_GET['id']   ?? '';
            $path = $_GET['path'] ?? '';
            if (!$id || !$path) { echo json_encode(['error' => 'Thiếu tham số']); break; }
            if (strpos($path, '..') !== false) { echo json_encode(['error' => 'Đường dẫn không hợp lệ']); break; }
            echo json_encode(adminBridgeCall('/api/client/server/' . rawurlencode($id) . '/files/read?path=' . urlencode($path)));
            break;

        // ─── File write (admin: ALL file types, không giới hạn .json/.txt) ────
        case 'file_write':
            $id      = $_POST['id']      ?? '';
            $path    = $_POST['path']    ?? '';
            $content = $_POST['content'] ?? '';
            if (!$id || !$path) { echo json_encode(['error' => 'Thiếu tham số']); break; }
            if (strpos($path, '..') !== false) { echo json_encode(['error' => 'Đường dẫn không hợp lệ']); break; }
            echo json_encode(adminBridgeCall('/api/client/server/' . rawurlencode($id) . '/files/write', 'POST', [
                'filePath' => $path, 'content' => $content
            ]));
            break;

        // ─── File delete ───────────────────────────────────────────────────────
        case 'file_delete':
            $id   = $_POST['id']   ?? '';
            $path = $_POST['path'] ?? '';
            if (!$id || !$path) { echo json_encode(['error' => 'Thiếu tham số']); break; }
            if (strpos($path, '..') !== false) { echo json_encode(['error' => 'Đường dẫn không hợp lệ']); break; }
            echo json_encode(adminBridgeCall('/api/client/server/' . rawurlencode($id) . '/files', 'DELETE', ['path' => $path]));
            break;

        // ─── Control ───────────────────────────────────────────────────────────
        case 'control':
            $id  = $_POST['id']  ?? '';
            $act = $_POST['act'] ?? '';
            if (!$id || !in_array($act, ['start','stop','restart','clear_log'])) {
                echo json_encode(['error' => 'Thiếu tham số']); break;
            }
            if ($act === 'clear_log') {
                echo json_encode(adminBridgeCall('/api/client/server/' . rawurlencode($id) . '/logs', 'DELETE'));
            } else {
                echo json_encode(adminBridgeCall("/api/client/server/{$id}/{$act}", 'POST'));
            }
            break;

        // ─── Create ────────────────────────────────────────────────────────────
        case 'create':
            $name      = trim($_POST['name']       ?? '');
            $port      = trim($_POST['port']       ?? '');
            $note      = trim($_POST['note']       ?? '');
            $expiresAt = trim($_POST['expiresAt']  ?? '');
            $assignUid = trim($_POST['assign_uid'] ?? '');
            if (!$name) { echo json_encode(['error' => 'Tên server là bắt buộc']); break; }
            $payload = ['name' => $name];
            if ($port)      $payload['port']      = (int)$port;
            if ($note)      $payload['note']      = $note;
            if ($expiresAt) $payload['expiresAt'] = $expiresAt;
            // [SECURITY FIX] Dùng adminBridgeCall thay vì adminManagerCall (function undefined)
            $result = adminBridgeCall('/api/servers', 'POST', $payload);
            if (!empty($result['success']) && $assignUid && isset($result['server']['id'])) {
                $newId = $result['server']['id'];
                $users = DB::read('users', []);
                foreach ($users as &$u) {
                    if ($u['id'] === $assignUid) {
                        $ids = $u['server_ids'] ?? [];
                        if (!in_array($newId, $ids)) $ids[] = $newId;
                        $u['server_ids'] = $ids;
                    }
                }
                DB::write('users', $users);
                $result['_assigned'] = true;
            }
            echo json_encode($result);
            break;

        // ─── Assign user ───────────────────────────────────────────────────────
        case 'assign':
            $srvId = trim($_POST['srv_id'] ?? '');
            $uid   = trim($_POST['uid']    ?? '');
            $op    = trim($_POST['op']     ?? 'add');
            if (!$srvId || !$uid) { echo json_encode(['error' => 'Thiếu tham số']); break; }
            $users = DB::read('users', []);
            $found = false;
            foreach ($users as &$u) {
                if ($u['id'] !== $uid) continue;
                $found = true;
                $ids = $u['server_ids'] ?? [];
                if ($op === 'add' && !in_array($srvId, $ids)) $ids[] = $srvId;
                if ($op === 'remove') $ids = array_values(array_filter($ids, fn($x) => $x !== $srvId));
                $u['server_ids'] = $ids;
            }
            if (!$found) { echo json_encode(['error' => 'User không tồn tại']); break; }
            DB::write('users', $users);
            echo json_encode(['success' => true]);
            break;

        // ─── Crash log list ────────────────────────────────────────────────────
        case 'crashes':
            $id    = $_GET['id'] ?? null;
            $limit = min((int)($_GET['limit'] ?? 20), 100);
            echo json_encode(getCrashes($id ?: null, $limit));
            break;

        // ─── Clear crashes ─────────────────────────────────────────────────────
        case 'clear_crashes':
            $id = $_POST['id'] ?? null;
            clearCrashes($id ?: null);
            echo json_encode(['success' => true]);
            break;

        // ─── Groq AI summary ───────────────────────────────────────────────────
        case 'groq_summary':
            $logs    = $_POST['logs']     ?? '';
            $srvName = $_POST['srv_name'] ?? 'Server';
            $apiKey  = GROQ_API_KEY;
            if (!$apiKey) {
                echo json_encode(['summary' => '⚠️ Chưa cấu hình GROQ_API_KEY trong biến môi trường. Thêm GROQ_API_KEY=your_key vào .env hoặc environment.']);
                break;
            }
            $prompt = "Bạn là chuyên gia phân tích lỗi server Node.js. Phân tích crash log dưới đây và trả lời bằng tiếng Việt:\n\n"
                    . "Server: {$srvName}\n\nLog:\n```\n" . substr($logs, 0, 4000) . "\n```\n\n"
                    . "Hãy trả lời theo cấu trúc:\n"
                    . "1. **Nguyên nhân crash** (1-2 câu ngắn gọn)\n"
                    . "2. **Lỗi chính** (tên error/exception nếu có)\n"
                    . "3. **Khuyến nghị** (cách fix cụ thể)\n\n"
                    . "Trả lời ngắn gọn, không quá 150 từ.";
            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'model'      => 'llama3-8b-8192',
                    'max_tokens' => 400,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ]),
            ]);
            $resp = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($err) { echo json_encode(['summary' => "❌ Không kết nối được Groq API: {$err}"]); break; }
            $data    = json_decode($resp, true);
            $summary = $data['choices'][0]['message']['content'] ?? 'Không lấy được phân tích từ AI.';
            echo json_encode(['summary' => $summary]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

// ── Render HTML ───────────────────────────────────────────────────────────────
$adminPageTitle = 'Quản lý Server';
$allUsers  = DB::read('users', []);
usort($allUsers, fn($a, $b) => strcmp($a['username'] ?? '', $b['username'] ?? ''));
$csrfToken = Auth::csrfToken();

require_once __DIR__ . '/header.php';
?>
<style>
.srv-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;transition:.15s;}
.srv-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);border-color:#cbd5e1;}
.srv-status{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;}
.srv-status.running{background:#dcfce7;color:#16a34a;}
.srv-status.stopped{background:#f1f5f9;color:#64748b;}
.srv-status.crashed{background:#fee2e2;color:#dc2626;}
.srv-status.unknown{background:#fef9c3;color:#ca8a04;}
.no-owner-badge{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-size:10px;padding:2px 8px;border-radius:20px;}
.code-id{font-family:monospace;font-size:11px;background:#f1f5f9;padding:2px 8px;border-radius:4px;color:#475569;}
#srv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;}
.skeleton{background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:10px;height:160px;}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.owner-pill{display:inline-flex;align-items:center;gap:4px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:20px;font-size:11px;padding:2px 8px;}
.assign-modal .user-row{cursor:pointer;padding:8px 12px;border-radius:6px;transition:.1s;}
.assign-modal .user-row:hover{background:#f1f5f9;}
.assign-modal .user-row.has-server{background:#f0fdf4;}
/* Console */
.log-console{background:#0f172a;color:#e2e8f0;font-family:'Fira Code',monospace;font-size:12px;border-radius:8px;padding:14px;height:420px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;line-height:1.6;}
.log-line-err{color:#f87171;}
.log-line-warn{color:#fbbf24;}
.log-line-info{color:#86efac;}
.log-line-sys{color:#7dd3fc;}
/* File Manager */
.fm-row{cursor:pointer;padding:7px 10px;border-radius:6px;display:flex;align-items:center;gap:8px;transition:.1s;border-bottom:1px solid #f8fafc;}
.fm-row:last-child{border-bottom:none;}
.fm-row:hover{background:#f8fafc;}
.fm-dir{color:#2563eb;font-weight:600;}
.fm-file{color:#1e293b;}
.fm-actions{margin-left:auto;display:flex;gap:4px;opacity:0;transition:.1s;}
.fm-row:hover .fm-actions{opacity:1;}
/* Crash */
.crash-item{border-left:3px solid #dc2626;background:#fff5f5;border-radius:0 8px 8px 0;padding:10px 14px;margin-bottom:8px;cursor:pointer;transition:.1s;}
.crash-item:hover{background:#fee2e2;}
.crash-badge{background:#dc2626;color:#fff;font-size:10px;padding:1px 7px;border-radius:20px;font-weight:700;}
/* Crash popup */
#crash-alert-overlay{display:none;position:fixed;bottom:24px;right:24px;z-index:9999;max-width:380px;width:100%;}
.crash-alert-card{background:#1e293b;border:2px solid #dc2626;border-radius:12px;padding:16px;box-shadow:0 8px 32px rgba(0,0,0,.4);animation:slideIn .3s ease;}
@keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
.crash-alert-title{color:#f87171;font-weight:800;font-size:14px;}
/* Groq */
.groq-panel{background:#f8faff;border:1px solid #c7d7fe;border-radius:8px;padding:14px;margin-top:12px;}
.groq-result{font-size:13px;line-height:1.75;color:#1e293b;}
.groq-result strong{color:#2563eb;}
/* Detail tabs */
.detail-tabs .nav-link{font-size:13px;padding:6px 14px;}
.detail-panel{display:none;}.detail-panel.active{display:block;}
</style>

<!-- Top toolbar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-2">
    <span id="srv-count" class="text-muted small"></span>
    <span class="badge bg-success" id="running-count" style="display:none"></span>
    <span class="badge bg-danger"  id="crashed-count" style="display:none"></span>
    <span class="badge bg-warning text-dark" id="noowner-count" style="display:none"></span>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <input type="text" id="search-box" class="form-control form-control-sm" placeholder="🔍 Tìm server..." style="width:180px">
    <select id="filter-status" class="form-select form-select-sm" style="width:auto">
      <option value="">Tất cả</option>
      <option value="running">🟢 Đang chạy</option>
      <option value="stopped">⚫ Đã dừng</option>
      <option value="crashed">🔴 Crashed</option>
      <option value="no-owner">⚠️ Chưa gán user</option>
    </select>
    <button class="btn btn-sm btn-outline-secondary" onclick="loadServers()"><i class="bi bi-arrow-clockwise me-1"></i>Làm mới</button>
    <button class="btn btn-sm btn-outline-danger" onclick="openCrashLog()"><i class="bi bi-bug me-1"></i>Crash Log</button>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus-lg me-1"></i>Tạo server</button>
  </div>
</div>

<div id="srv-grid"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div>
<div id="empty-msg" class="text-center py-5 text-muted" style="display:none">
  <i class="bi bi-server" style="font-size:2.5rem;opacity:.3"></i><div class="mt-2">Không tìm thấy server nào</div>
</div>

<!-- Crash Alert Popup -->
<div id="crash-alert-overlay">
  <div class="crash-alert-card">
    <div class="d-flex align-items-start justify-content-between">
      <div>
        <div class="crash-alert-title"><i class="bi bi-exclamation-octagon-fill me-2"></i>Server đã bị crash!</div>
        <div style="color:#e2e8f0;font-size:13px;margin-top:4px" id="crash-alert-name">—</div>
        <div style="color:#64748b;font-size:11px" id="crash-alert-time">—</div>
      </div>
      <button class="btn btn-sm" onclick="dismissCrashAlert()" style="color:#94a3b8;border:1px solid #334155;background:transparent">✕</button>
    </div>
    <div class="mt-2 d-flex gap-2">
      <button class="btn btn-sm btn-danger" onclick="openDetailFromAlert()" style="font-size:12px"><i class="bi bi-bug me-1"></i>Xem chi tiết</button>
      <button class="btn btn-sm" onclick="dismissCrashAlert()" style="font-size:12px;color:#94a3b8;border:1px solid #475569;background:transparent">Bỏ qua</button>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Chi tiết server ═══ -->
<div class="modal fade" id="detailModal" tabindex="-1" style="--bs-modal-width:960px">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header py-2">
        <div>
          <h6 class="modal-title fw-800 mb-0" id="detail-title">Chi tiết Server</h6>
          <code class="text-muted" style="font-size:11px" id="detail-subtitle"></code>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <ul class="nav nav-tabs px-3 pt-2 detail-tabs" id="detail-nav">
        <li class="nav-item"><button class="nav-link active"  onclick="switchTab('status')"><i class="bi bi-activity me-1"></i>Status</button></li>
        <li class="nav-item"><button class="nav-link"         onclick="switchTab('console')"><i class="bi bi-terminal me-1"></i>Console</button></li>
        <li class="nav-item"><button class="nav-link"         onclick="switchTab('files')"><i class="bi bi-folder2-open me-1"></i>File Manager</button></li>
        <li class="nav-item"><button class="nav-link"         onclick="switchTab('crashes')">
          <i class="bi bi-bug me-1"></i>Crash Log
          <span id="detail-crash-badge" class="badge bg-danger ms-1" style="display:none"></span>
        </button></li>
      </ul>
      <div class="modal-body p-3">
        <!-- Status -->
        <div id="tab-status" class="detail-panel active">
          <div id="status-content" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>
        </div>
        <!-- Console -->
        <div id="tab-console" class="detail-panel">
          <div class="d-flex align-items-center justify-content-between mb-2 gap-2 flex-wrap">
            <div class="d-flex gap-2">
              <select id="log-limit" class="form-select form-select-sm" style="width:auto">
                <option value="100">100 dòng</option>
                <option value="200" selected>200 dòng</option>
                <option value="500">500 dòng</option>
                <option value="1000">1000 dòng</option>
              </select>
              <button class="btn btn-sm btn-outline-secondary" onclick="loadLogs()"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                <input class="form-check-input" type="checkbox" id="auto-scroll" checked>
                <label class="form-check-label small" for="auto-scroll">Auto scroll</label>
              </div>
              <button class="btn btn-sm btn-outline-danger" onclick="clearLogs()"><i class="bi bi-trash me-1"></i>Xóa log</button>
            </div>
          </div>
          <div class="log-console" id="log-output">Chưa tải log...</div>
          <div class="mt-1 text-muted" style="font-size:11px" id="log-meta"></div>
        </div>
        <!-- Files -->
        <div id="tab-files" class="detail-panel">
          <div id="fm-breadcrumb" class="mb-2 d-flex align-items-center gap-1 flex-wrap" style="font-size:13px"></div>
          <div id="fm-list"></div>
          <div id="fm-editor" style="display:none">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <code style="font-size:12px" id="fm-edit-path"></code>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-success" onclick="saveFile()"><i class="bi bi-check2 me-1"></i>Lưu</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="closeEditor()">Đóng</button>
              </div>
            </div>
            <textarea id="fm-edit-content" class="form-control" rows="20"
              style="font-family:'Fira Code',monospace;font-size:12px;background:#0f172a;color:#e2e8f0;border:none;resize:vertical;border-radius:8px;padding:12px"></textarea>
          </div>
        </div>
        <!-- Crash log server -->
        <div id="tab-crashes" class="detail-panel">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="fw-700">Lịch sử crash của server này</span>
            <button class="btn btn-sm btn-outline-danger" onclick="clearServerCrashes()"><i class="bi bi-trash me-1"></i>Xóa tất cả</button>
          </div>
          <div id="crash-list"><div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div></div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <div class="d-flex gap-2 flex-wrap" id="detail-ctrl-btns"></div>
        <button class="btn btn-sm btn-secondary ms-auto" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Crash Log tổng hợp ═══ -->
<div class="modal fade" id="crashLogModal" tabindex="-1" style="--bs-modal-width:800px">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-800"><i class="bi bi-bug-fill text-danger me-2"></i>Crash Log — Tất cả server</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="text-muted small" id="crash-log-count"></span>
          <button class="btn btn-sm btn-outline-danger" onclick="clearAllCrashes()"><i class="bi bi-trash me-1"></i>Xóa tất cả</button>
        </div>
        <div id="crash-log-list"><div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Chi tiết crash + Groq AI ═══ -->
<div class="modal fade" id="crashDetailModal" tabindex="-1" style="--bs-modal-width:760px">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-800"><i class="bi bi-exclamation-octagon text-danger me-2"></i>Chi tiết Crash</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-auto"><span class="text-muted small">Server:</span> <strong id="cd-name"></strong></div>
          <div class="col-auto"><span class="text-muted small">Thời gian:</span> <span id="cd-time"></span></div>
          <div class="col-auto"><span class="text-muted small">Trước đó:</span> <span id="cd-prev" class="badge bg-secondary"></span></div>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <strong style="font-size:13px"><i class="bi bi-terminal me-1"></i>Log tail (50 dòng cuối)</strong>
          <button class="btn btn-sm btn-primary" onclick="requestGroqSummary()" id="groq-btn">
            <i class="bi bi-stars me-1"></i>Phân tích AI (Groq)
          </button>
        </div>
        <div class="log-console" id="cd-logs" style="height:240px;font-size:11px"></div>
        <div class="groq-panel" id="groq-panel" style="display:none">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-stars text-primary"></i>
            <strong style="font-size:13px">Phân tích từ Groq AI</strong>
            <span class="badge bg-light text-secondary border" style="font-size:10px">llama3-8b-8192</span>
          </div>
          <div id="groq-output" style="color:#6366f1;font-size:13px">Đang phân tích...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Tạo server -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Tạo server mới</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-600 small">Tên server <span class="text-danger">*</span></label>
          <input type="text" id="c-name" class="form-control" placeholder="VD: My Bot Server">
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-600 small">Port (để trống = tự động)</label>
            <input type="number" id="c-port" class="form-control" placeholder="4000">
          </div>
          <div class="col-6">
            <label class="form-label fw-600 small">Hết hạn</label>
            <input type="date" id="c-expires" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-600 small">Ghi chú</label>
          <input type="text" id="c-note" class="form-control" placeholder="Ghi chú tùy chọn">
        </div>
        <div class="mb-3">
          <label class="form-label fw-600 small">Gán ngay cho người dùng</label>
          <select id="c-assign" class="form-select">
            <option value="">— Không gán —</option>
            <?php foreach ($allUsers as $u): ?>
              <?php if (($u['role'] ?? '') !== 'admin'): ?>
              <option value="<?= h($u['id']) ?>"><?= h($u['username']) ?> (<?= h($u['email'] ?? '') ?>)</option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="create-error" class="alert alert-danger py-2 small" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="button" class="btn btn-primary" id="create-btn" onclick="createServer()"><i class="bi bi-plus-circle me-1"></i>Tạo server</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Gán user -->
<div class="modal fade assign-modal" id="assignModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-people me-2 text-primary"></i>Gán người dùng — <span id="assign-srv-name"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 small mb-3"><i class="bi bi-info-circle me-1"></i>Bấm để <strong>thêm / gỡ</strong> quyền truy cập server.</div>
        <input type="text" id="assign-search" class="form-control form-control-sm mb-3" placeholder="🔍 Tìm user...">
        <div id="assign-user-list" style="max-height:350px;overflow-y:auto"></div>
        <div id="assign-msg" class="text-center text-muted small mt-2" style="display:none"></div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Mã server -->
<div class="modal fade" id="codeModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-700"><i class="bi bi-qr-code me-2 text-primary"></i>Mã server</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="mb-1" style="font-size:11px;color:#64748b">Server ID</div>
        <div id="code-id-display" style="font-family:monospace;font-size:18px;font-weight:800;color:#1e293b;letter-spacing:1px;background:#f1f5f9;padding:12px 20px;border-radius:8px;margin-bottom:12px"></div>
        <div class="mb-1" style="font-size:11px;color:#64748b">Tên server</div>
        <div id="code-name-display" style="font-size:14px;font-weight:600;color:#475569" class="mb-3"></div>
        <button class="btn btn-outline-secondary btn-sm" onclick="copyCode()"><i class="bi bi-clipboard me-1"></i>Sao chép ID</button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;
let allServers = [], assignSrvId = '', detailSid = '', detailSrv = null, fmPath = '', fmEditPath = '', currentCrash = null;
let alertedCrashIds = new Set(JSON.parse(sessionStorage.getItem('alertedCrashes')||'[]'));
let logPollTimer = null, detailTab = 'status', detailModal = null;

// ─── CSRF fetch helper ─────────────────────────────────────────────────────
async function apiFetch(url, method='GET', fd=null) {
    const opts = {method};
    if (method === 'POST') {
        if (!fd) fd = new FormData();
        fd.append('_csrf', CSRF);
        opts.body = fd;
    }
    const r = await fetch(url, opts);
    const t = await r.text();
    try { return JSON.parse(t); }
    catch(e) { throw new Error('Phản hồi không phải JSON: ' + t.slice(0,120)); }
}

// ─── Server list ───────────────────────────────────────────────────────────
async function loadServers() {
    document.getElementById('empty-msg').style.display = 'none';
    const grid = document.getElementById('srv-grid');
    grid.innerHTML = '<div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>';
    try {
        const data = await apiFetch('?ajax=list');
        if (data._err) throw new Error(data._err);
        if (!Array.isArray(data)) throw new Error(data.error || 'Phản hồi không hợp lệ');
        allServers = data;
        updateCounts(); renderGrid(); detectNewCrashes(data);
    } catch(e) {
        grid.innerHTML = `<div style="grid-column:1/-1"><div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle me-2"></i><strong>Không thể kết nối:</strong> ${esc(e.message)}
        </div></div>`;
    }
}

function detectNewCrashes(servers) {
    for (const s of servers) {
        if (s.status === 'crashed' && s._crash_count > 0) {
            fetch('?ajax=crashes&id=' + encodeURIComponent(s.id) + '&limit=1')
                .then(r => r.json()).then(cs => {
                    if (cs.length && !alertedCrashIds.has(cs[0].id)) {
                        showCrashAlert(s, cs[0]);
                        alertedCrashIds.add(cs[0].id);
                        sessionStorage.setItem('alertedCrashes', JSON.stringify([...alertedCrashIds].slice(-50)));
                    }
                }).catch(()=>{});
        }
    }
}

function updateCounts() {
    const running = allServers.filter(s=>s.status==='running').length;
    const crashed = allServers.filter(s=>s.status==='crashed').length;
    const noOwner = allServers.filter(s=>!s._owners||!s._owners.length).length;
    document.getElementById('srv-count').textContent = allServers.length + ' server';
    const rc=document.getElementById('running-count'); rc.textContent=`🟢 ${running} chạy`; rc.style.display=running?'':'none';
    const cc=document.getElementById('crashed-count'); cc.textContent=`🔴 ${crashed} crash`; cc.style.display=crashed?'':'none';
    const nc=document.getElementById('noowner-count'); nc.textContent=`⚠️ ${noOwner} chưa gán`; nc.style.display=noOwner?'':'none';
}

function renderGrid() {
    const q=document.getElementById('search-box').value.trim().toLowerCase();
    const fs=document.getElementById('filter-status').value;
    const f=allServers.filter(s=>{
        if(q&&!(s.name?.toLowerCase().includes(q)||s.id?.toLowerCase().includes(q)||s.note?.toLowerCase().includes(q)))return false;
        if(fs==='running'&&s.status!=='running')return false;
        if(fs==='stopped'&&s.status!=='stopped')return false;
        if(fs==='crashed'&&s.status!=='crashed')return false;
        if(fs==='no-owner'&&s._owners?.length>0)return false;
        return true;
    });
    document.getElementById('empty-msg').style.display=f.length?'none':'';
    document.getElementById('srv-grid').innerHTML=f.map(renderCard).join('');
}

function renderCard(s) {
    const sc={running:'running',stopped:'stopped',crashed:'crashed'}[s.status]||'unknown';
    const sl={running:'● Đang chạy',stopped:'■ Đã dừng',crashed:'✕ Crashed'}[s.status]||'? Không rõ';
    const dl=s.daysLeft;
    const expBadge=dl!==null?(dl<0?'<span class="badge bg-danger ms-1" style="font-size:10px">Hết hạn</span>':dl<=7?`<span class="badge bg-warning text-dark ms-1" style="font-size:10px">Còn ${dl} ngày</span>`:`<span class="badge bg-light text-muted border ms-1" style="font-size:10px">Còn ${dl}n</span>`):'';
    const owners=s._owners||[];
    const ownerHtml=owners.length?owners.map(o=>`<span class="owner-pill"><i class="bi bi-person-fill"></i>${esc(o.username)}</span>`).join(' '):'<span class="no-owner-badge"><i class="bi bi-exclamation-triangle me-1"></i>Chưa gán user</span>';
    const crashBadge=s._crash_count>0?`<span class="crash-badge" style="cursor:pointer" onclick="openDetail('${s.id}','crashes')" title="Xem crash log">${s._crash_count}x crash</span>`:'';
    return `<div class="srv-card p-3" id="card-${s.id}">
      <div class="d-flex align-items-start justify-content-between mb-2">
        <div>
          <div class="fw-700" style="font-size:14px">${esc(s.name)}</div>
          <span class="code-id">${esc(s.id)}</span>
          ${s.port?`<span class="code-id ms-1">:${s.port}</span>`:''}
        </div>
        <div class="d-flex gap-1 align-items-center flex-shrink-0">
          <span class="srv-status ${sc}">${sl}</span>${expBadge}
        </div>
      </div>
      ${s.note?`<div class="text-muted small mb-2" style="font-size:11px"><i class="bi bi-sticky me-1"></i>${esc(s.note)}</div>`:''}
      <div class="mb-3 d-flex flex-wrap gap-1 align-items-center">
        <span style="font-size:11px;color:#64748b" class="me-1"><i class="bi bi-people me-1"></i></span>${ownerHtml}
      </div>
      <div class="d-flex align-items-center justify-content-between gap-1 flex-wrap">
        <div class="d-flex gap-1">
          ${s.status==='running'
            ?`<button class="btn btn-sm btn-danger" onclick="control('${s.id}','stop')"><i class="bi bi-stop-fill"></i></button>
              <button class="btn btn-sm btn-warning text-dark" onclick="control('${s.id}','restart')"><i class="bi bi-arrow-counterclockwise"></i></button>`
            :`<button class="btn btn-sm btn-success" onclick="control('${s.id}','start')"><i class="bi bi-play-fill"></i></button>`}
          <button class="btn btn-sm btn-outline-primary" onclick="openDetail('${s.id}')" title="Console/Files/Status"><i class="bi bi-window-desktop"></i></button>
          <button class="btn btn-sm btn-outline-secondary" onclick="openAssign('${s.id}','${esc(s.name)}')" title="Gán user"><i class="bi bi-person-plus"></i></button>
          <button class="btn btn-sm btn-outline-info" onclick="showCode('${s.id}','${esc(s.name)}')" title="Mã server"><i class="bi bi-qr-code"></i></button>
        </div>
        <div class="d-flex align-items-center gap-1" style="font-size:10px">
          ${crashBadge}
          ${s.pid?`<span class="text-success">pid ${s.pid}</span>`:''}
        </div>
      </div>
    </div>`;
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

// ─── Crash Alert ───────────────────────────────────────────────────────────
let crashAlertSid=null;
function showCrashAlert(srv,ce){
    crashAlertSid=srv.id;
    document.getElementById('crash-alert-name').textContent='🔴 '+(srv.name||srv.id);
    document.getElementById('crash-alert-time').textContent=new Date(ce.time).toLocaleString('vi-VN');
    document.getElementById('crash-alert-overlay').style.display='block';
    setTimeout(dismissCrashAlert,30000);
}
function dismissCrashAlert(){document.getElementById('crash-alert-overlay').style.display='none';crashAlertSid=null;}
function openDetailFromAlert(){dismissCrashAlert();if(crashAlertSid)openDetail(crashAlertSid,'crashes');}

// ─── Control ───────────────────────────────────────────────────────────────
async function control(id,act){
    if(act==='stop'&&!confirm('Dừng server này?'))return;
    const card=document.getElementById('card-'+id);
    if(card)card.style.opacity='.5';
    const fd=new FormData();fd.append('id',id);fd.append('act',act);
    try{const r=await apiFetch('?ajax=control','POST',fd);if(r.error||r._err)alert('Lỗi: '+(r.error||r._err));setTimeout(loadServers,1200);}
    catch(e){alert('Lỗi kết nối');}
    if(card)card.style.opacity='';
}

// ─── Detail Modal ──────────────────────────────────────────────────────────
function openDetail(sid,tab='status'){
    detailSid=sid;fmPath='';fmEditPath='';
    const srv=allServers.find(s=>s.id===sid);detailSrv=srv;
    document.getElementById('detail-title').textContent=srv?srv.name:sid;
    document.getElementById('detail-subtitle').textContent=sid;
    updateDetailCtrlBtns(srv);
    if(!detailModal)detailModal=new bootstrap.Modal(document.getElementById('detailModal'));
    detailModal.show();
    switchTab(tab);
}

function switchTab(tab){
    detailTab=tab;
    document.querySelectorAll('.detail-tabs .nav-link').forEach(el=>{
        el.classList.toggle('active',el.getAttribute('onclick')?.includes("'"+tab+"'"));
    });
    document.querySelectorAll('.detail-panel').forEach(el=>el.classList.remove('active'));
    const panel=document.getElementById('tab-'+tab);if(panel)panel.classList.add('active');
    clearInterval(logPollTimer);
    if(tab==='status')  loadDetailStatus();
    if(tab==='console') loadLogs();
    if(tab==='files')   loadFiles('');
    if(tab==='crashes') loadServerCrashes();
}

function updateDetailCtrlBtns(srv){
    const el=document.getElementById('detail-ctrl-btns');
    if(!srv){el.innerHTML='';return;}
    el.innerHTML=srv.status==='running'
        ?`<button class="btn btn-sm btn-danger" onclick="control('${srv.id}','stop')"><i class="bi bi-stop-fill me-1"></i>Dừng</button>
          <button class="btn btn-sm btn-warning text-dark" onclick="control('${srv.id}','restart')"><i class="bi bi-arrow-counterclockwise me-1"></i>Restart</button>`
        :`<button class="btn btn-sm btn-success" onclick="control('${srv.id}','start')"><i class="bi bi-play-fill me-1"></i>Bật</button>`;
}

// ─── Status tab ────────────────────────────────────────────────────────────
async function loadDetailStatus(){
    const el=document.getElementById('status-content');
    el.innerHTML='<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    try{
        const s=await apiFetch('?ajax=status&id='+encodeURIComponent(detailSid));
        const sc={running:'success',stopped:'secondary',crashed:'danger'}[s.status]||'warning';
        const rows=[
            ['Trạng thái',`<span class="badge bg-${sc} fs-6">${esc(s.status||'?')}</span>`],
            ['ID',`<code>${esc(s.id||'—')}</code>`],['Tên',esc(s.name||'—')],
            ['Port',s.port?`:${s.port}`:'—'],['PID',s.pid||'—'],
            ['RAM',s.memoryMB?`${s.memoryMB} MB`:'—'],['CPU',s.cpu?`${s.cpu}%`:'—'],
            ['Uptime',s.uptime?fmtUptime(s.uptime):'—'],['Crashes',s.crashes??'—'],
            ['Hết hạn',s.expiresAt?new Date(s.expiresAt).toLocaleString('vi-VN'):'—'],
            ['Ghi chú',esc(s.note||'—')],
        ];
        el.innerHTML=`<table class="table table-sm table-borderless" style="font-size:13px">${rows.map(([k,v])=>`<tr><td class="text-muted fw-600" style="width:130px">${k}</td><td>${v}</td></tr>`).join('')}</table>`;
        detailSrv=s;updateDetailCtrlBtns(s);
        const cb=document.getElementById('detail-crash-badge');
        if(s.crashes>0){cb.textContent=s.crashes;cb.style.display='';}else cb.style.display='none';
    }catch(e){el.innerHTML=`<div class="alert alert-danger">${esc(e.message)}</div>`;}
}

function fmtUptime(ms){
    const s=Math.floor(ms/1000),m=Math.floor(s/60),h=Math.floor(m/60),d=Math.floor(h/24);
    if(d>0)return`${d}d ${h%24}h`;if(h>0)return`${h}h ${m%60}m`;return`${m}m ${s%60}s`;
}

// ─── Console tab ───────────────────────────────────────────────────────────
async function loadLogs(){
    const out=document.getElementById('log-output');
    const limit=document.getElementById('log-limit').value;
    out.textContent='Đang tải...';clearInterval(logPollTimer);
    try{
        const r=await apiFetch(`?ajax=logs&id=${encodeURIComponent(detailSid)}&limit=${limit}`);
        renderLogLines(r.logs||[],out);
        document.getElementById('log-meta').textContent=`${(r.logs||[]).length} dòng · ${new Date().toLocaleTimeString('vi-VN')}`;
    }catch(e){out.textContent='Lỗi: '+e.message;}
    logPollTimer=setInterval(async()=>{
        if(detailTab!=='console'){clearInterval(logPollTimer);return;}
        try{const r=await apiFetch(`?ajax=logs&id=${encodeURIComponent(detailSid)}&limit=${limit}`);renderLogLines(r.logs||[],out);document.getElementById('log-meta').textContent=`${(r.logs||[]).length} dòng · ${new Date().toLocaleTimeString('vi-VN')}`;}catch(e){}
    },5000);
}

function renderLogLines(lines,el){
    const scroll=document.getElementById('auto-scroll').checked;
    el.innerHTML=lines.map(ln=>{
        const txt=esc(ln.data||ln.text||String(ln)||'');
        let cls='';
        if(/error|err|exception|crash|fatal/i.test(txt))cls='log-line-err';
        else if(/warn/i.test(txt))cls='log-line-warn';
        else if(/info|start|listen|ready|connect/i.test(txt))cls='log-line-info';
        else if(/\[sys\]|\[pm2\]/i.test(txt))cls='log-line-sys';
        const ts=ln.timestamp?`<span style="color:#475569">[${new Date(ln.timestamp).toLocaleTimeString('vi-VN')}] </span>`:'';
        return`<div class="${cls}">${ts}${txt}</div>`;
    }).join('')||'<div class="text-muted">Chưa có log</div>';
    if(scroll)el.scrollTop=el.scrollHeight;
}

async function clearLogs(){
    if(!confirm('Xóa toàn bộ log?'))return;
    const fd=new FormData();fd.append('id',detailSid);fd.append('act','clear_log');
    await apiFetch('?ajax=control','POST',fd);loadLogs();
}

// ─── File Manager ──────────────────────────────────────────────────────────
async function loadFiles(path=''){
    fmPath=path;
    document.getElementById('fm-editor').style.display='none';
    document.getElementById('fm-list').style.display='';
    const el=document.getElementById('fm-list');
    el.innerHTML='<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    renderFmBreadcrumb(path);
    try{
        const r=await apiFetch(`?ajax=files&id=${encodeURIComponent(detailSid)}&path=${encodeURIComponent(path)}`);
        const items=r.items||[];
        if(!items.length){el.innerHTML='<div class="text-muted small py-3 px-2">📭 Thư mục trống</div>';return;}
        const dirs=items.filter(i=>i.type==='directory').sort((a,b)=>a.name.localeCompare(b.name));
        const files=items.filter(i=>i.type!=='directory').sort((a,b)=>a.name.localeCompare(b.name));
        el.innerHTML=`<div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden">${[...dirs,...files].map(item=>{
            const fp=path?path+'/'+item.name:item.name;
            const isDir=item.type==='directory';
            const icon=isDir?'📁':getFileIcon(item.name);
            return`<div class="fm-row">
              <span>${icon}</span>
              <span class="${isDir?'fm-dir':'fm-file'}" style="flex:1;cursor:${isDir?'pointer':'default'}" onclick="${isDir?`loadFiles('${esc(fp)}')`:''}">
                ${esc(item.name)}
              </span>
              <span class="text-muted" style="font-size:11px">${item.size?fmtSize(item.size):''}</span>
              <div class="fm-actions">
                <button class="btn" style="padding:1px 8px;font-size:12px;border:1px solid #e2e8f0;border-radius:4px" onclick="editFile('${esc(fp)}')">
                  <i class="bi bi-pencil text-primary"></i>
                </button>
                <button class="btn" style="padding:1px 8px;font-size:12px;border:1px solid #e2e8f0;border-radius:4px" onclick="deleteFile('${esc(fp)}','${esc(item.name)}')">
                  <i class="bi bi-trash text-danger"></i>
                </button>
              </div>
            </div>`;
        }).join('')}</div>`;
    }catch(e){el.innerHTML=`<div class="alert alert-danger">${esc(e.message)}</div>`;}
}

function renderFmBreadcrumb(path){
    const el=document.getElementById('fm-breadcrumb');
    let html=`<a href="#" onclick="loadFiles('')" class="text-decoration-none text-primary"><i class="bi bi-house-fill"></i> root</a>`;
    if(path){
        const parts=path.split('/').filter(Boolean);let built='';
        for(const pt of parts){
            built=built?built+'/'+pt:pt;
            const b=built;
            html+=` <span class="text-muted">/</span> <a href="#" onclick="loadFiles('${esc(b)}')" class="text-decoration-none text-primary">${esc(pt)}</a>`;
        }
    }
    el.innerHTML=html;
}

async function editFile(path){
    fmEditPath=path;
    document.getElementById('fm-list').style.display='none';
    document.getElementById('fm-editor').style.display='';
    document.getElementById('fm-edit-path').textContent=path;
    const ta=document.getElementById('fm-edit-content');ta.value='Đang tải...';
    try{const r=await apiFetch(`?ajax=file_read&id=${encodeURIComponent(detailSid)}&path=${encodeURIComponent(path)}`);ta.value=r.content??'';}
    catch(e){ta.value='Lỗi: '+e.message;}
}

function closeEditor(){document.getElementById('fm-editor').style.display='none';document.getElementById('fm-list').style.display='';loadFiles(fmPath);}

async function saveFile(){
    const content=document.getElementById('fm-edit-content').value;
    const fd=new FormData();fd.append('id',detailSid);fd.append('path',fmEditPath);fd.append('content',content);
    try{
        const r=await apiFetch('?ajax=file_write','POST',fd);
        if(r.success||r.message)showToast('✓ Đã lưu '+fmEditPath,'success');
        else alert('Lỗi: '+(r.error||r._err||'Không thể lưu'));
    }catch(e){alert('Lỗi: '+e.message);}
}

async function deleteFile(path,name){
    if(!confirm(`Xóa "${name}"?`))return;
    const fd=new FormData();fd.append('id',detailSid);fd.append('path',path);
    try{const r=await apiFetch('?ajax=file_delete','POST',fd);if(r.success){showToast('✓ Đã xóa '+name,'success');loadFiles(fmPath);}else alert('Lỗi: '+(r.error||'Không thể xóa'));}
    catch(e){alert('Lỗi: '+e.message);}
}

function getFileIcon(n){
    if(/\.(js|mjs|cjs|ts|jsx|tsx)$/.test(n))return'📜';
    if(/\.json$/.test(n))return'📋';if(/\.md$/.test(n))return'📝';
    if(/\.log$/.test(n))return'🗒️';if(/\.(env|conf|cfg|yaml|yml)$/.test(n))return'⚙️';
    if(/\.(png|jpg|gif|svg|webp)$/.test(n))return'🖼️';
    return'📄';
}

function fmtSize(b){if(b<1024)return b+'B';if(b<1024*1024)return(b/1024).toFixed(1)+'KB';return(b/1048576).toFixed(2)+'MB';}

// ─── Crash tab (server) ────────────────────────────────────────────────────
async function loadServerCrashes(){
    const el=document.getElementById('crash-list');
    el.innerHTML='<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    try{
        const cs=await apiFetch(`?ajax=crashes&id=${encodeURIComponent(detailSid)}&limit=30`);
        if(!cs.length){el.innerHTML='<div class="text-center text-muted py-3"><i class="bi bi-check-circle me-1 text-success"></i>Không có crash nào</div>';return;}
        el.innerHTML=cs.map(c=>renderCrashItem(c,false)).join('');
        const cb=document.getElementById('detail-crash-badge');cb.textContent=cs.length;cb.style.display='';
    }catch(e){el.innerHTML=`<div class="alert alert-danger">${esc(e.message)}</div>`;}
}

async function clearServerCrashes(){
    if(!confirm('Xóa tất cả crash log của server này?'))return;
    const fd=new FormData();fd.append('id',detailSid);await apiFetch('?ajax=clear_crashes','POST',fd);loadServerCrashes();
}

// ─── Global crash log ──────────────────────────────────────────────────────
async function openCrashLog(){
    const modal=new bootstrap.Modal(document.getElementById('crashLogModal'));modal.show();
    const el=document.getElementById('crash-log-list');
    el.innerHTML='<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    try{
        const cs=await apiFetch('?ajax=crashes&limit=50');
        document.getElementById('crash-log-count').textContent=`${cs.length} sự kiện crash gần nhất`;
        if(!cs.length){el.innerHTML='<div class="text-center text-muted py-4"><i class="bi bi-check-circle me-1 text-success"></i>Không có crash nào</div>';return;}
        el.innerHTML=cs.map(c=>renderCrashItem(c,true)).join('');
    }catch(e){el.innerHTML=`<div class="alert alert-danger">${esc(e.message)}</div>`;}
}

async function clearAllCrashes(){
    if(!confirm('Xóa TẤT CẢ crash log?'))return;
    await apiFetch('?ajax=clear_crashes','POST',new FormData());
    bootstrap.Modal.getInstance(document.getElementById('crashLogModal')).hide();
}

function renderCrashItem(c,showSrv=false){
    const d=new Date(c.time).toLocaleString('vi-VN');
    const safeJson=esc(JSON.stringify(c));
    return`<div class="crash-item" onclick="openCrashDetail('${safeJson.replace(/'/g,"&#39;")}')">
      <div class="d-flex align-items-center justify-content-between">
        <div>${showSrv?`<span class="fw-700 text-danger me-2">${esc(c.server_name||c.server_id)}</span>`:''}<span class="crash-badge">CRASH</span></div>
        <span style="font-size:11px;color:#94a3b8">${d}</span>
      </div>
      <div class="mt-1 text-muted small">
        Trạng thái trước: <strong>${esc(c.prev_status||'?')}</strong> ·
        ${(c.log_tail||[]).length} dòng log ·
        <span class="text-primary">Xem chi tiết →</span>
      </div>
    </div>`;
}

// ─── Crash Detail + Groq ───────────────────────────────────────────────────
function openCrashDetail(jsonStr){
    let c;try{c=JSON.parse(jsonStr.replace(/&#39;/g,"'"));}catch(e){return;}
    currentCrash=c;
    document.getElementById('cd-name').textContent=c.server_name||c.server_id||'—';
    document.getElementById('cd-time').textContent=new Date(c.time).toLocaleString('vi-VN');
    document.getElementById('cd-prev').textContent=c.prev_status||'?';
    const logEl=document.getElementById('cd-logs');
    const lines=c.log_tail||[];
    logEl.innerHTML=lines.map(ln=>{
        const txt=esc(ln.data||ln.text||String(ln)||'');
        let cls='';if(/error|exception|crash|fatal/i.test(txt))cls='log-line-err';else if(/warn/i.test(txt))cls='log-line-warn';
        return`<div class="${cls}">${txt}</div>`;
    }).join('')||'<div class="text-muted">Không có log</div>';
    logEl.scrollTop=logEl.scrollHeight;
    document.getElementById('groq-panel').style.display='none';
    document.getElementById('groq-output').textContent='Đang phân tích...';
    document.getElementById('groq-output').style.color='#6366f1';
    document.getElementById('groq-btn').disabled=false;
    document.getElementById('groq-btn').innerHTML='<i class="bi bi-stars me-1"></i>Phân tích AI (Groq)';
    new bootstrap.Modal(document.getElementById('crashDetailModal')).show();
}

async function requestGroqSummary(){
    if(!currentCrash)return;
    const btn=document.getElementById('groq-btn'),panel=document.getElementById('groq-panel'),out=document.getElementById('groq-output');
    btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Đang phân tích...';
    panel.style.display='';out.style.color='#6366f1';out.className='';out.textContent='Đang gọi Groq AI (llama3-8b-8192)...';
    const logs=(currentCrash.log_tail||[]).map(ln=>ln.data||ln.text||String(ln)||'').join('\n');
    const fd=new FormData();fd.append('logs',logs);fd.append('srv_name',currentCrash.server_name||currentCrash.server_id);
    try{
        const r=await apiFetch('?ajax=groq_summary','POST',fd);
        out.className='groq-result';
        out.innerHTML=esc(r.summary||'Không có kết quả').replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>');
    }catch(e){out.style.color='#dc2626';out.textContent='Lỗi: '+e.message;}
    btn.disabled=false;btn.innerHTML='<i class="bi bi-stars me-1"></i>Phân tích lại';
}

// ─── Create / Assign / Code ────────────────────────────────────────────────
async function createServer(){
    const name=document.getElementById('c-name').value.trim();
    if(!name){showCreateErr('Vui lòng nhập tên server');return;}
    hideCreateErr();
    const btn=document.getElementById('create-btn');btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Đang tạo...';
    const fd=new FormData();
    fd.append('name',name);fd.append('port',document.getElementById('c-port').value.trim());
    fd.append('note',document.getElementById('c-note').value.trim());
    fd.append('expiresAt',document.getElementById('c-expires').value?document.getElementById('c-expires').value+'T00:00:00.000Z':'');
    fd.append('assign_uid',document.getElementById('c-assign').value);
    try{
        const r=await apiFetch('?ajax=create','POST',fd);
        if(r.error||r._err){showCreateErr(r.error||r._err);}
        else{
            bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
            ['c-name','c-port','c-note'].forEach(id=>document.getElementById(id).value='');
            document.getElementById('c-assign').value='';
            if(r.server)showCode(r.server.id,r.server.name);
            loadServers();
        }
    }catch(e){showCreateErr('Lỗi kết nối');}
    btn.disabled=false;btn.innerHTML='<i class="bi bi-plus-circle me-1"></i>Tạo server';
}
function showCreateErr(m){const el=document.getElementById('create-error');el.textContent=m;el.style.display='';}
function hideCreateErr(){document.getElementById('create-error').style.display='none';}

function openAssign(srvId,srvName){
    assignSrvId=srvId;document.getElementById('assign-srv-name').textContent=srvName;
    document.getElementById('assign-search').value='';document.getElementById('assign-msg').style.display='none';
    renderAssignList('');new bootstrap.Modal(document.getElementById('assignModal')).show();
}
function renderAssignList(q){
    const srv=allServers.find(s=>s.id===assignSrvId);const owners=(srv?._owners||[]).map(o=>o.id);
    const allU=<?= json_encode(array_map(fn($u)=>['id'=>$u['id'],'username'=>$u['username'],'email'=>$u['email']??''],$allUsers)) ?>;
    const list=q?allU.filter(u=>u.username.toLowerCase().includes(q.toLowerCase())||(u.email||'').toLowerCase().includes(q.toLowerCase())):allU;
    document.getElementById('assign-user-list').innerHTML=list.map(u=>{
        const has=owners.includes(u.id);
        return`<div class="user-row ${has?'has-server':''} d-flex align-items-center justify-content-between" onclick="toggleAssign('${u.id}','${esc(u.username)}')">
          <div><div class="fw-600 small">${esc(u.username)}</div><div style="font-size:11px;color:#94a3b8">${esc(u.email||'')}</div></div>
          <span class="badge ${has?'bg-success':'bg-light text-secondary border'}">${has?'✓ Có quyền':'+ Thêm'}</span>
        </div>`;
    }).join('')||'<div class="text-center text-muted small py-3">Không tìm thấy</div>';
}
async function toggleAssign(uid,uname){
    const srv=allServers.find(s=>s.id===assignSrvId);const owns=(srv?._owners||[]).map(o=>o.id);const op=owns.includes(uid)?'remove':'add';
    const msg=document.getElementById('assign-msg');
    const fd=new FormData();fd.append('srv_id',assignSrvId);fd.append('uid',uid);fd.append('op',op);
    try{const r=await apiFetch('?ajax=assign','POST',fd);msg.textContent=r.success?(op==='add'?`✓ Đã thêm ${uname}`:`✓ Đã gỡ ${uname}`):'✗ '+(r.error||'Lỗi');msg.style.display='';if(r.success){await loadServers();renderAssignList(document.getElementById('assign-search').value);}}
    catch(e){msg.textContent='✗ Lỗi kết nối';msg.style.display='';}
}

function showCode(id,name){document.getElementById('code-id-display').textContent=id;document.getElementById('code-name-display').textContent=name;new bootstrap.Modal(document.getElementById('codeModal')).show();}
function copyCode(){navigator.clipboard?.writeText(document.getElementById('code-id-display').textContent).then(()=>{const btn=event.target.closest('button');btn.innerHTML='<i class="bi bi-check2 me-1"></i>Đã sao chép!';setTimeout(()=>{btn.innerHTML='<i class="bi bi-clipboard me-1"></i>Sao chép ID';},1500);});}

// ─── Toast ─────────────────────────────────────────────────────────────────
function showToast(msg,type='success'){
    const el=document.createElement('div');
    el.style.cssText=`position:fixed;bottom:80px;right:24px;background:${type==='success'?'#16a34a':'#dc2626'};color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;z-index:9998;box-shadow:0 4px 16px rgba(0,0,0,.3);animation:slideIn .2s ease`;
    el.textContent=msg;document.body.appendChild(el);setTimeout(()=>el.remove(),3000);
}

// ─── Events ────────────────────────────────────────────────────────────────
document.getElementById('search-box').addEventListener('input',renderGrid);
document.getElementById('filter-status').addEventListener('change',renderGrid);
document.getElementById('assign-search').addEventListener('input',e=>renderAssignList(e.target.value));
document.getElementById('detailModal').addEventListener('hidden.bs.modal',()=>{clearInterval(logPollTimer);detailSid='';});

loadServers();
setInterval(loadServers,15000);
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
