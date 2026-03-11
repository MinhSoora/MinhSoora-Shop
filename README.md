# NodeShop — PHP Website Bán Dịch Vụ

Hệ thống website bán dịch vụ hosting/server với thanh toán tự động qua Sepay.

## Tính năng

### Phía khách hàng
- 🏪 Trang chủ, danh mục, sản phẩm (có ảnh, mô tả chi tiết)
- 🛒 Đặt hàng với tùy chọn bổ sung & fields cần thiết
- 💳 Thanh toán QR Sepay realtime (tự động xác nhận)
- 📋 Lịch sử đơn hàng, hủy đơn
- 💰 Nạp tiền vào ví (tự động qua Sepay webhook)
- 🎧 Ticket hỗ trợ (chat 2 chiều)
- 🖥 Xem & điều khiển server (liên kết với NodePanel)
- 👤 Đăng ký, đăng nhập, đổi mật khẩu

### Phía Admin
- 📊 Dashboard thống kê
- 🛍 Quản lý đơn hàng (sửa trạng thái, gán server, ghi chú)
- 👥 Quản lý người dùng (phân quyền, số dư, server IDs)
- 🎧 Phản hồi ticket hỗ trợ
- 💹 Lịch sử giao dịch
- 📦 Quản lý sản phẩm (tùy chọn bổ sung, fields custom)
- 🏷 Quản lý danh mục
- ⚙ Cài đặt hệ thống & Sepay
- 🔌 Test kết nối Sepay API

## Cài đặt

### Yêu cầu
- PHP >= 8.0 với extension: `curl`, `json`, `session`
- Không cần database (dùng JSON files)

### Chạy nhanh (PHP built-in server)
```bash
cd shopphp
php -S 0.0.0.0:8888 router.php
```

### Biến môi trường (tùy chọn)
```bash
APP_URL=https://yourdomain.com \
APP_PORT=8888 \
php -S 0.0.0.0:8888 router.php
```

## Tài khoản mặc định
- **Admin**: `admin` / `admin123`
- ⚠️ Đổi mật khẩu ngay sau khi đăng nhập!

## Cấu hình Sepay

1. Đăng nhập admin → **Cài đặt** → điền:
   - Sepay API Token
   - Số tài khoản ngân hàng
   - Mã ngân hàng (MB, VCB, TCB,...)
   - Webhook Secret

2. Vào dashboard Sepay → thêm Webhook URL:
   ```
   https://yourdomain.com/webhook/sepay.php
   ```

3. Test tại Admin → **Sepay** → Test kết nối

## Liên kết NodePanel

Admin → **Cài đặt** → nhập:
- Manager URL: `http://your-nodepanel:25745`
- Bridge Secret: (lấy từ NodePanel Admin → Quản lý khách → 🔑 Bridge Secret)

Sau đó, trong **Sửa người dùng**, thêm Server IDs cho khách → khách sẽ thấy nút quản lý server trên dashboard.

## Cấu trúc file
```
shopphp/
├── index.php           — Trang chủ
├── products.php        — Danh sách sản phẩm
├── product.php         — Chi tiết & đặt hàng
├── order.php           — Chi tiết đơn & thanh toán
├── orders.php          — Lịch sử đơn hàng
├── dashboard.php       — Dashboard người dùng
├── tickets.php         — Danh sách ticket
├── ticket.php          — Chi tiết ticket
├── naptien.php         — Nạp tiền
├── profile.php         — Tài khoản cá nhân
├── login.php / register.php / logout.php
├── router.php          — PHP router
├── includes/           — Config, DB, Auth, Helpers, Layout
├── admin/              — Toàn bộ trang quản trị
├── webhook/sepay.php   — Webhook nhận thanh toán tự động
└── .data/              — Dữ liệu JSON (tự tạo)
```

## Data files (.data/)
- `settings.json` — Cài đặt hệ thống
- `users.json` — Danh sách người dùng
- `products.json` — Sản phẩm
- `categories.json` — Danh mục
- `orders.json` — Đơn hàng
- `tickets.json` — Tickets hỗ trợ
- `transactions.json` — Lịch sử giao dịch
