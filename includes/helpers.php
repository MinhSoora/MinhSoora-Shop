<?php
/**
 * Helpers — hàm tiện ích
 */

function h(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES); }
function e(mixed $s): string { return h($s); }

function fmtMoney(int|float $amount): string {
    return number_format($amount, 0, ',', '.') . ' ₫';
}

function fmtDate(string $iso): string {
    if (!$iso) return '—';
    return date('d/m/Y H:i', strtotime($iso));
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $msg): void {
    $_SESSION['flash'][$key] = $msg;
}

function getFlash(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function renderFlash(): string {
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $type => $msg) {
        $cls = match($type) { 'success' => 'alert-success', 'error' => 'alert-danger', 'warning' => 'alert-warning', default => 'alert-info' };
        $html .= "<div class=\"alert {$cls} alert-dismissible fade show\" role=\"alert\">" . h($msg) . "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>";
    }
    $_SESSION['flash'] = [];
    return $html;
}

function slug(string $str): string {
    $str = mb_strtolower(trim($str));
    $map = ['à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','đ'=>'d','è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e','ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o','ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u','ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y'];
    $str = strtr($str, $map);
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', trim($str));
    return $str;
}

function markdownToHtml(string $md): string {
    $md = h($md);
    // Headers
    $md = preg_replace('/^### (.+)/m', '<h5>$1</h5>', $md);
    $md = preg_replace('/^## (.+)/m', '<h4>$1</h4>', $md);
    $md = preg_replace('/^# (.+)/m', '<h3>$1</h3>', $md);
    // Bold
    $md = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md);
    // Italic
    $md = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $md);
    // Lists
    $md = preg_replace('/^- (.+)/m', '<li>$1</li>', $md);
    $md = preg_replace('/(<li>.*?<\/li>)/s', '<ul>$1</ul>', $md);
    // Newlines
    $md = nl2br($md);
    return $md;
}

function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function isAjax(): bool {
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';
}

function getSettings(): array {
    return DB::read('settings', []);
}

function setting(string $key, mixed $default = ''): mixed {
    return DB::read('settings', [])[$key] ?? $default;
}

function orderStatusLabel(string $status): string {
    return match($status) {
        'pending'    => '<span class="badge bg-warning text-dark">⏳ Chờ thanh toán</span>',
        'paid'       => '<span class="badge bg-info">💳 Đã thanh toán</span>',
        'processing' => '<span class="badge bg-primary">⚙ Đang xử lý</span>',
        'active'     => '<span class="badge bg-success">✅ Đang dùng</span>',
        'cancelled'  => '<span class="badge bg-danger">❌ Đã hủy</span>',
        'expired'    => '<span class="badge bg-secondary">⏰ Hết hạn</span>',
        default      => '<span class="badge bg-secondary">' . h($status) . '</span>',
    };
}

function ticketStatusLabel(string $status): string {
    return match($status) {
        'open'       => '<span class="badge bg-danger">🔴 Mở</span>',
        'replied'    => '<span class="badge bg-primary">💬 Đã phản hồi</span>',
        'closed'     => '<span class="badge bg-secondary">✔ Đóng</span>',
        default      => '<span class="badge bg-secondary">' . h($status) . '</span>',
    };
}

// Gọi Sepay API
function sepayRequest(string $endpoint, array $data = [], string $method = 'GET'): array {
    $token = setting('sepay_token');
    if (!$token) return ['error' => 'Chưa cấu hình Sepay token'];
    $url = 'https://my.sepay.vn/userapi' . $endpoint;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error' => $err];
    return json_decode($resp, true) ?? ['error' => 'Invalid response'];
}

// ─── Crash Logger ─────────────────────────────────────────────────────────────
/**
 * Ghi crash event khi server chuyển từ running → crashed
 * Lưu vào CRASH_LOG_JSON (tối đa 200 events) + CRASH_LOG (text)
 */
function recordCrash(string $serverId, string $serverName, array $logLines = [], ?string $previousStatus = null): void {
    $event = [
        'id'         => uniqid('crash_', true),
        'server_id'  => $serverId,
        'server_name'=> $serverName,
        'time'       => date('c'),
        'timestamp'  => time(),
        'log_tail'   => array_slice($logLines, -50), // 50 dòng log cuối
        'prev_status'=> $previousStatus ?? 'unknown',
    ];

    // Lưu vào JSON
    $jsonFile = defined('CRASH_LOG_JSON') ? CRASH_LOG_JSON : (DATA_DIR . '/crashes.json');
    $crashes  = [];
    if (file_exists($jsonFile)) {
        $crashes = json_decode(file_get_contents($jsonFile), true) ?? [];
    }
    array_unshift($crashes, $event); // newest first
    $crashes = array_slice($crashes, 0, 200); // giữ tối đa 200
    file_put_contents($jsonFile, json_encode($crashes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    // Ghi text log
    $logFile = defined('CRASH_LOG') ? CRASH_LOG : (DATA_DIR . '/crash.log');
    $line    = sprintf("[%s] CRASH server_id=%s name=\"%s\" prev=%s\n", date('Y-m-d H:i:s'), $serverId, $serverName, $previousStatus ?? 'unknown');
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Lấy danh sách crash events, có thể lọc theo server_id
 */
function getCrashes(?string $serverId = null, int $limit = 50): array {
    $jsonFile = defined('CRASH_LOG_JSON') ? CRASH_LOG_JSON : (DATA_DIR . '/crashes.json');
    if (!file_exists($jsonFile)) return [];
    $crashes = json_decode(file_get_contents($jsonFile), true) ?? [];
    if ($serverId) {
        $crashes = array_values(array_filter($crashes, fn($c) => ($c['server_id'] ?? '') === $serverId));
    }
    return array_slice($crashes, 0, $limit);
}

/**
 * Xoá crash events của một server (hoặc tất cả)
 */
function clearCrashes(?string $serverId = null): void {
    $jsonFile = defined('CRASH_LOG_JSON') ? CRASH_LOG_JSON : (DATA_DIR . '/crashes.json');
    if (!file_exists($jsonFile)) return;
    if ($serverId === null) {
        file_put_contents($jsonFile, '[]', LOCK_EX);
        return;
    }
    $crashes = json_decode(file_get_contents($jsonFile), true) ?? [];
    $crashes = array_values(array_filter($crashes, fn($c) => ($c['server_id'] ?? '') !== $serverId));
    file_put_contents($jsonFile, json_encode($crashes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

// ─── Bridge logger ────────────────────────────────────────────────────────────
function bridgeLog(string $level, string $msg, array $ctx = []): void {
    $logFile = defined('BRIDGE_LOG') ? BRIDGE_LOG : (DATA_DIR . '/bridge.log');
    $line = sprintf(
        "[%s][%s] %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $msg,
        $ctx ? ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) : ''
    );
    // Giữ log tối đa 500 dòng
    if (file_exists($logFile) && filesize($logFile) > 500 * 200) {
        $lines = file($logFile);
        file_put_contents($logFile, implode('', array_slice($lines, -400)));
    }
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// ─── Gọi bridge Node.js ───────────────────────────────────────────────────────
// PHP gọi qua internet tới manager, client không thấy URL Node.js
// Xác thực DUY NHẤT qua x-bridge-token header (BRIDGE_SECRET phải khớp 2 bên)
// PHP truyền x-client-server-ids trực tiếp — Node.js không lưu gì cả
function bridgeCall(string $path, string $method = 'GET', array $data = [], string $clientUid = '', array $clientServerIds = []): array {
    $url     = MANAGER_URL . $path;
    $headers = [
        'Content-Type: application/json',
        'x-bridge-token: ' . BRIDGE_SECRET,
    ];
    if ($clientUid)       $headers[] = 'x-client-uid: '        . $clientUid;
    if ($clientServerIds) $headers[] = 'x-client-server-ids: ' . implode(',', $clientServerIds);
    elseif ($clientUid)   $headers[] = 'x-client-server-ids: '; // uid có nhưng không có ids = không có quyền gì

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $resp     = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $curlErrNo= curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime= round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000) . 'ms';
    curl_close($ch);

    $logCtx = ['method' => $method, 'url' => $url, 'http' => $httpCode, 'time' => $totalTime];
    if ($clientUid) $logCtx['uid'] = $clientUid;

    // Lỗi curl (không kết nối được)
    if ($curlErr) {
        $errMap = [
            CURLE_COULDNT_CONNECT => 'Không kết nối được tới Node.js manager — kiểm tra server có đang chạy không',
            CURLE_OPERATION_TIMEDOUT => 'Timeout khi kết nối tới Node.js manager',
            CURLE_COULDNT_RESOLVE_HOST => 'Không phân giải được hostname: ' . MANAGER_URL,
        ];
        $friendlyErr = $errMap[$curlErrNo] ?? $curlErr;
        bridgeLog('error', "CURL #{$curlErrNo}: {$friendlyErr}", $logCtx);
        return ['_err' => $friendlyErr, '_curl_errno' => $curlErrNo, '_url' => $url];
    }

    // HTTP 401 = sai Bridge Secret
    if ($httpCode === 401) {
        bridgeLog('error', 'HTTP 401 — Bridge token không hợp lệ, kiểm tra BRIDGE_SECRET khớp 2 bên', $logCtx);
        return ['_err' => 'Bridge token không hợp lệ (401) — kiểm tra BRIDGE_SECRET', '_http' => 401];
    }

    // HTTP 0 = không nhận được response
    if ($httpCode === 0) {
        bridgeLog('error', 'HTTP 0 — Không nhận được phản hồi từ Node.js manager', $logCtx);
        return ['_err' => 'Không nhận được phản hồi từ Node.js manager', '_http' => 0];
    }

    // HTTP lỗi khác
    if ($httpCode >= 500) {
        bridgeLog('error', "HTTP {$httpCode} — Server Node.js lỗi nội bộ", array_merge($logCtx, ['body' => substr($resp, 0, 200)]));
        return ['_err' => "Node.js manager lỗi (HTTP {$httpCode})", '_http' => $httpCode];
    }

    // Parse JSON
    $decoded = json_decode($resp ?: '{}', true);
    if ($decoded === null) {
        bridgeLog('error', 'JSON parse failed', array_merge($logCtx, ['raw' => substr($resp, 0, 300)]));
        return ['_err' => 'Phản hồi không hợp lệ từ Node.js manager', '_http' => $httpCode];
    }

    // Log thành công (chỉ log nếu có lỗi trong response)
    if (isset($decoded['error'])) {
        bridgeLog('warn', "Node.js trả về lỗi: {$decoded['error']}", $logCtx);
    } else {
        bridgeLog('info', "OK {$method} {$path}", $logCtx);
    }

    return $decoded;
}

/**
 * adminManagerCall — Gọi Node.js Manager với quyền admin (X-API-Secret)
 * Dùng cho các tác vụ admin: tạo server, xem tất cả server, v.v.
 */
function adminManagerCall(string $path, string $method = 'GET', array $data = []): array {
    $url     = MANAGER_URL . $path;
    $headers = [
        'Content-Type: application/json',
        'X-API-Secret: ' . MANAGER_API_SECRET,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $resp    = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr)         return ['_err' => 'CURL: ' . $curlErr];
    if ($httpCode === 401) return ['_err' => 'API Secret không hợp lệ (401) — kiểm tra MANAGER_API_SECRET'];
    if ($httpCode >= 500) return ['_err' => "Node.js manager lỗi (HTTP {$httpCode})"];
    $decoded = json_decode($resp ?: '{}', true);
    return $decoded ?? ['_err' => 'Phản hồi không hợp lệ'];
}


// ─── Order code generator ─────────────────────────────────────────────────────
/**
 * Tạo mã đơn hàng dạng NS-YYYY-XXXX (dễ nhớ, dễ tra)
 */
function generateOrderCode(): string {
    $year  = date('Y');
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // loại bỏ 0/O, 1/I nhầm lẫn
    $code  = '';
    for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars)-1)];
    // Đảm bảo unique
    $tries = 0;
    while ($tries++ < 20) {
        $full = "NS-{$year}-{$code}";
        $orders = DB::read('orders', []);
        $exists = false;
        foreach ($orders as $o) { if (($o['code']??'') === $full) { $exists = true; break; } }
        if (!$exists) return $full;
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars)-1)];
    }
    return "NS-{$year}-{$code}";
}

// ─── Server maintenance status helper ─────────────────────────────────────────
/**
 * Lấy danh sách server đang bảo trì/lỗi (lưu trong .data/srv_maintenance.json)
 */
function getServerMaintenanceList(): array {
    $f = DATA_DIR . '/srv_maintenance.json';
    return file_exists($f) ? (json_decode(file_get_contents($f), true) ?? []) : [];
}

function setServerMaintenance(string $serverId, bool $enabled, string $reason = ''): void {
    $list = getServerMaintenanceList();
    if ($enabled) {
        $list[$serverId] = ['enabled' => true, 'reason' => $reason, 'since' => date('c')];
    } else {
        unset($list[$serverId]);
    }
    file_put_contents(DATA_DIR . '/srv_maintenance.json', json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function isServerUnderMaintenance(string $serverId): ?array {
    $list = getServerMaintenanceList();
    return $list[$serverId] ?? null;
}
