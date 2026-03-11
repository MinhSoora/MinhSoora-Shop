<?php
/**
 * Auth — quản lý session người dùng
 */

class Auth {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('nodeshop_sess');
            session_set_cookie_params(['lifetime' => SESSION_TIMEOUT, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
    }

    public static function login(string $username, string $password): array {
        self::start();
        // [SECURITY FIX] Brute-force protection
        $bfErr = self::checkBruteForce($username);
        if ($bfErr) return ['error' => $bfErr];

        $users = DB::read('users', []);
        foreach ($users as $u) {
            if (($u['username'] === $username || $u['email'] === $username)
                && !empty($u['active'])
                && password_verify($password, $u['password'])) {
                // [SECURITY FIX] Regenerate session ID on login to prevent fixation
                session_regenerate_id(true);
                $_SESSION['uid']  = $u['id'];
                $_SESSION['role'] = $u['role'];
                self::clearFailedLogin($username);
                // Update last login
                DB::update('users', 'id', $u['id'], ['last_login' => date('c')]);
                return ['success' => true, 'user' => $u];
            }
        }
        // [SECURITY FIX] Record failed attempt, generic error message
        self::recordFailedLogin($username);
        return ['error' => 'Sai tên đăng nhập hoặc mật khẩu'];
    }

    // ── CSRF Token ─────────────────────────────────────────────────────────────
    public static function csrfToken(): string {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool {
        self::start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    // ── Brute-force login protection ───────────────────────────────────────────
    private static function loginAttemptKey(string $username): string {
        return 'login_fail_' . md5(strtolower(trim($username)));
    }

    private static function checkBruteForce(string $username): ?string {
        self::start();
        $key  = self::loginAttemptKey($username);
        $data = $_SESSION[$key] ?? ['count' => 0, 'until' => 0];
        if ($data['until'] > time()) {
            $secs = $data['until'] - time();
            return "Quá nhiều lần thử. Vui lòng đợi {$secs} giây.";
        }
        return null;
    }

    private static function recordFailedLogin(string $username): void {
        self::start();
        $key  = self::loginAttemptKey($username);
        $data = $_SESSION[$key] ?? ['count' => 0, 'until' => 0];
        $data['count']++;
        if ($data['count'] >= 5) {
            $data['until'] = time() + min(60 * $data['count'], 900); // max 15 phút
        }
        $_SESSION[$key] = $data;
    }

    private static function clearFailedLogin(string $username): void {
        self::start();
        unset($_SESSION[self::loginAttemptKey($username)]);
    }

    public static function register(string $username, string $email, string $password): array {
        self::start();
        $settings = DB::read('settings', []);
        if (empty($settings['allow_register'])) return ['error' => 'Đăng ký hiện tại đã bị tắt'];

        // [SECURITY FIX] Validate username format — prevent injection / admin impersonation
        $username = trim($username);
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            return ['error' => 'Tên đăng nhập chỉ gồm chữ, số, gạch dưới (3-20 ký tự)'];
        }
        // [SECURITY FIX] Validate email
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Email không hợp lệ'];
        }
        if (DB::find('users', 'username', $username)) return ['error' => 'Tên đăng nhập đã tồn tại'];
        if (DB::find('users', 'email', $email))       return ['error' => 'Email đã được đăng ký'];
        // [SECURITY FIX] Stronger password minimum
        if (strlen($password) < 8) return ['error' => 'Mật khẩu phải ít nhất 8 ký tự'];

        $user = [
            'id'         => DB::nextId('usr_'),
            'username'   => trim($username),
            'email'      => strtolower(trim($email)),
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'role'       => 'user',
            'balance'    => 0,
            'server_ids' => [],
            'active'     => true,
            'created_at' => date('c'),
            'last_login' => null,
        ];
        DB::append('users', $user);
        // [SECURITY FIX] Regenerate session on register
        session_regenerate_id(true);
        $_SESSION['uid']  = $user['id'];
        $_SESSION['role'] = 'user';
        return ['success' => true, 'user' => $user];
    }

    public static function logout(): void {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function user(): ?array {
        self::start();
        if (empty($_SESSION['uid'])) return null;
        return DB::find('users', 'id', $_SESSION['uid']);
    }

    public static function id(): ?string { return $_SESSION['uid'] ?? null; }
    public static function role(): string { return $_SESSION['role'] ?? 'guest'; }
    public static function isAdmin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
    public static function isLoggedIn(): bool { return !empty($_SESSION['uid']); }

    public static function requireLogin(string $redirect = '/login.php'): void {
        self::start();
        if (!self::isLoggedIn()) { header('Location: ' . $redirect); exit; }
    }

    public static function requireAdmin(): void {
        self::start();
        if (!self::isAdmin()) { header('Location: /'); exit; }
    }

    public static function changePassword(string $uid, string $oldPw, string $newPw): array {
        $u = DB::find('users', 'id', $uid);
        if (!$u) return ['error' => 'Không tìm thấy tài khoản'];
        if (!password_verify($oldPw, $u['password'])) return ['error' => 'Mật khẩu cũ không đúng'];
        if (strlen($newPw) < 6) return ['error' => 'Mật khẩu mới phải ít nhất 6 ký tự'];
        DB::update('users', 'id', $uid, ['password' => password_hash($newPw, PASSWORD_DEFAULT)]);
        return ['success' => true];
    }
}

Auth::start();
