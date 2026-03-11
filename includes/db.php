<?php
/**
 * DB — JSON file database helper
 */

class DB {
    private static array $cache = [];

    public static function path(string $name): string {
        return DATA_DIR . '/' . $name . '.json';
    }

    public static function read(string $name, mixed $default = []): mixed {
        $path = self::path($name);
        if (!file_exists($path)) return $default;
        if (isset(self::$cache[$name])) return self::$cache[$name];
        $data = json_decode(file_get_contents($path), true) ?? $default;
        self::$cache[$name] = $data;
        return $data;
    }

    public static function write(string $name, mixed $data): bool {
        self::$cache[$name] = $data;
        $path = self::path($name);
        $dir  = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    }

    public static function append(string $name, array $item): bool {
        $data = self::read($name, []);
        if (!is_array($data)) $data = [];
        $data[] = $item;
        return self::write($name, $data);
    }

    // Find by key=value
    public static function find(string $name, string $key, mixed $value): ?array {
        foreach (self::read($name, []) as $item) {
            if (isset($item[$key]) && $item[$key] == $value) return $item;
        }
        return null;
    }

    // Update item matching key=value
    public static function update(string $name, string $key, mixed $value, array $patch): bool {
        $data = self::read($name, []);
        $found = false;
        foreach ($data as &$item) {
            if (isset($item[$key]) && $item[$key] == $value) {
                $item = array_merge($item, $patch);
                $found = true;
                break;
            }
        }
        unset($item);
        if ($found) return self::write($name, $data);
        return false;
    }

    // Delete item matching key=value
    public static function delete(string $name, string $key, mixed $value): bool {
        $data = self::read($name, []);
        $data = array_values(array_filter($data, fn($item) => !isset($item[$key]) || $item[$key] != $value));
        return self::write($name, $data);
    }

    // Filter list
    public static function filter(string $name, callable $fn): array {
        return array_values(array_filter(self::read($name, []), $fn));
    }

    // Count
    public static function count(string $name, ?callable $fn = null): int {
        $data = self::read($name, []);
        if ($fn) return count(array_filter($data, $fn));
        return count($data);
    }

    // Generate unique ID
    public static function nextId(string $prefix = ''): string {
        return $prefix . base_convert(time(), 10, 36) . base_convert(rand(0, 1295), 10, 36);
    }
}

// Initialize default data files
function initDefaultData(): void {
    // Settings
    if (!file_exists(DB::path('settings'))) {
        DB::write('settings', [
            'shop_name'    => 'NodeShop',
            'shop_desc'    => 'Dịch vụ hosting & server chất lượng cao',
            'shop_email'   => 'admin@nodeshop.vn',
            'shop_phone'   => '0900 000 000',
            'shop_address' => 'Việt Nam',
            'sepay_token'  => '',
            'sepay_account_no' => '',
            'sepay_bank_code'  => 'MB',
            'sepay_webhook_secret' => '',
            'manager_url'  => 'http://localhost:25745',
            'bridge_secret'=> '',
            'maintenance'  => false,
            'allow_register' => true,
            'logo_url'     => '',
            'banner_text'  => '',
            'footer_text'  => '© 2025 NodeShop. All rights reserved.',
            'created_at'   => date('c'),
        ]);
    }

    // Admin account
    if (!file_exists(DB::path('users'))) {
        DB::write('users', [[
            'id'           => 'usr_admin',
            'username'     => 'admin',
            'email'        => 'admin@nodeshop.vn',
            'password'     => password_hash('admin123', PASSWORD_DEFAULT),
            'role'         => 'admin',
            'balance'      => 0,
            'server_ids'   => [],
            'active'       => true,
            'created_at'   => date('c'),
            'last_login'   => null,
        ]]);
    }

    // Default categories
    if (!file_exists(DB::path('categories'))) {
        DB::write('categories', [
            ['id' => 'cat_vps',    'name' => 'VPS / Server',   'icon' => '🖥', 'order' => 1],
            ['id' => 'cat_bot',    'name' => 'Bot Discord',     'icon' => '🤖', 'order' => 2],
            ['id' => 'cat_web',    'name' => 'Web Hosting',     'icon' => '🌐', 'order' => 3],
            ['id' => 'cat_other',  'name' => 'Dịch vụ khác',   'icon' => '📦', 'order' => 4],
        ]);
    }

    // Sample products
    if (!file_exists(DB::path('products'))) {
        DB::write('products', [
            [
                'id'          => 'prd_vps1',
                'category_id' => 'cat_vps',
                'name'        => 'VPS Basic',
                'slug'        => 'vps-basic',
                'short_desc'  => 'VPS cơ bản cho dự án nhỏ',
                'description' => "## VPS Basic\n\n- **RAM**: 1 GB\n- **CPU**: 1 vCore\n- **SSD**: 20 GB\n- **Băng thông**: 100 Mbps\n- **Uptime**: 99.9%\n\nThích hợp cho bot Discord, web nhỏ, API cá nhân.",
                'price'       => 150000,
                'price_type'  => 'monthly', // monthly, onetime, custom
                'images'      => [],
                'options'     => [
                    ['id' => 'opt_ram2', 'name' => 'Nâng RAM lên 2GB', 'price' => 80000, 'type' => 'add'],
                    ['id' => 'opt_ram4', 'name' => 'Nâng RAM lên 4GB', 'price' => 200000, 'type' => 'add'],
                    ['id' => 'opt_ssd40','name' => 'Nâng SSD 40GB',    'price' => 50000,  'type' => 'add'],
                ],
                'fields'      => [
                    ['id' => 'fld_os',   'name' => 'Hệ điều hành', 'type' => 'select', 'options' => ['Ubuntu 22.04','Debian 11','CentOS 7'], 'required' => true],
                    ['id' => 'fld_note', 'name' => 'Ghi chú',      'type' => 'textarea', 'required' => false],
                ],
                'active'      => true,
                'featured'    => true,
                'created_at'  => date('c'),
            ],
            [
                'id'          => 'prd_bot1',
                'category_id' => 'cat_bot',
                'name'        => 'Bot Discord Custom',
                'slug'        => 'bot-discord-custom',
                'short_desc'  => 'Bot Discord custom theo yêu cầu',
                'description' => "## Bot Discord Custom\n\n- Deploy lên server riêng 24/7\n- Hỗ trợ cấu hình theo yêu cầu\n- Uptime monitoring\n- Restart tự động khi crash",
                'price'       => 200000,
                'price_type'  => 'monthly',
                'images'      => [],
                'options'     => [
                    ['id' => 'opt_setup', 'name' => 'Phí setup lần đầu', 'price' => 100000, 'type' => 'add'],
                ],
                'fields'      => [
                    ['id' => 'fld_repo',  'name' => 'Link GitHub repo', 'type' => 'text',     'required' => true],
                    ['id' => 'fld_token', 'name' => 'Bot Token',        'type' => 'password', 'required' => true],
                    ['id' => 'fld_note',  'name' => 'Ghi chú thêm',    'type' => 'textarea', 'required' => false],
                ],
                'active'      => true,
                'featured'    => false,
                'created_at'  => date('c'),
            ],
        ]);
    }

    if (!file_exists(DB::path('orders')))       DB::write('orders', []);
    if (!file_exists(DB::path('tickets')))       DB::write('tickets', []);
    if (!file_exists(DB::path('transactions')))  DB::write('transactions', []);
}

initDefaultData();

// Init reviews & setup_orders
if (!file_exists(DB::path('reviews'))) {
    DB::write('reviews', [
        ['id'=>'rv1','name'=>'Nguyễn Minh','service'=>'VPS Basic','rating'=>5,'text'=>'Dịch vụ tuyệt vời, setup nhanh chóng và hỗ trợ nhiệt tình. Server ổn định, uptime 99.9% như cam kết.','created_at'=>date('c',strtotime('-5 days')),'approved'=>true],
        ['id'=>'rv2','name'=>'Trần Thị Lan','service'=>'Bot Discord Custom','rating'=>5,'text'=>'Team hỗ trợ rất chuyên nghiệp, giải quyết vấn đề kỹ thuật nhanh. Bot chạy ổn định 24/7, rất hài lòng!','created_at'=>date('c',strtotime('-3 days')),'approved'=>true],
        ['id'=>'rv3','name'=>'Lê Văn Hùng','service'=>'VPS Basic','rating'=>4,'text'=>'Giá hợp lý, chất lượng tốt. Tôi đã dùng 3 tháng và chưa có vấn đề gì. Sẽ tiếp tục gia hạn.','created_at'=>date('c',strtotime('-1 day')),'approved'=>true],
    ]);
}
