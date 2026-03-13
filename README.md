# HelpDesk CRM System

Hệ thống quản lý ticket hỗ trợ khách hàng (HelpDesk CRM) được xây dựng bằng PHP + MySQL.

## Tính năng chính

- **Quản lý Ticket**: Tạo, chỉnh sửa, phân công và theo dõi ticket
- **Hệ thống Phân quyền**: Admin, Manager, Agent, Viewer
- **Dashboard**: Thống kê tổng quan và báo cáo
- **Ghi chú Ticket**: Thêm ghi chú cho từng ticket
- **Báo cáo**: Thống kê chi tiết theo thời gian, danh mục, ưu tiên
- **Xuất dữ liệu**: Xuất danh sách ticket ra file CSV

## Cài đặt

### 1. Yêu cầu hệ thống
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx web server
- XAMPP/WAMP (khuyến nghị cho Windows)

### 2. Cài đặt cơ sở dữ liệu
1. Tạo database mới trong phpMyAdmin (ví dụ: `crmhelpdesk`)
2. Import file `database/crmhelpdesk.sql`
3. Cập nhật thông tin database trong `config/database.php`

### 3. Cấu hình Database
Chỉnh sửa file `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'crmhelpdesk');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Quyền truy cập thư mục
Đảm bảo web server có quyền ghi vào thư mục:
- `assets/`
- Các file log nếu có

## Tài khoản Demo

| Vai trò | Tên đăng nhập | Mật khẩu |
|---------|---------------|----------|
| Admin | admin | admin123 |
| Manager | manager | manager123 |
| Agent | agent | agent123 |
| Viewer | viewer | viewer123 |

## Cấu trúc thư mục

```
c:\CRMTicket\
├── assets\           # CSS, JS, images
├── config\           # Cấu hình database
├── controllers\      # Logic xử lý
├── database\         # File SQL schema
├── includes\         # Header, footer, auth
├── models\           # Models database
├── index.php         # Trang chủ
├── login.php         # Đăng nhập
├── dashboard.php     # Dashboard
├── tickets.php       # Quản lý tickets
├── users.php         # Quản lý users
├── reports.php       # Báo cáo
├── profile.php       # Thông tin cá nhân
└── README.md         # Tài liệu này
```

## Quy trình sử dụng

### 1. Đăng nhập
- Truy cập `login.php`
- Sử dụng tài khoản demo hoặc tài khoản đã tạo

### 2. Dashboard
- Xem thống kê tổng quan
- Truy cập nhanh các chức năng

### 3. Quản lý Tickets
- **Tạo ticket**: Nhấn "Tạo Ticket mới"
- **Xem chi tiết**: Click vào tiêu đề ticket
- **Chỉnh sửa**: Nhấn "Chỉnh sửa" trong trang chi tiết
- **Phân công**: Admin/Manager có thể phân công cho Agent
- **Thêm ghi chú**: Trong trang chi tiết ticket

### 4. Quản lý Users (Admin/Manager)
- Tạo user mới
- Chỉnh sửa thông tin
- Đặt lại mật khẩu
- Khóa/Mở khóa tài khoản

### 5. Báo cáo
- Xem thống kê theo thời gian
- Phân tích hiệu suất Agent
- Xuất dữ liệu CSV

## API Endpoints

### Authentication
- `POST /login.php` - Đăng nhập
- `POST /logout.php` - Đăng xuất

### Tickets
- `GET /tickets.php` - Danh sách tickets
- `POST /tickets.php?action=create` - Tạo ticket
- `GET /tickets.php?action=view&id={id}` - Xem chi tiết
- `POST /tickets.php?action=edit&id={id}` - Chỉnh sửa
- `POST /tickets.php?action=delete&id={id}` - Xóa

### Users (Admin/Manager only)
- `GET /users.php` - Danh sách users
- `POST /users.php?action=create` - Tạo user
- `POST /users.php?action=edit&id={id}` - Chỉnh sửa
- `POST /users.php?action=delete&id={id}` - Xóa

### Reports
- `GET /reports.php` - Báo cáo thống kê
- `GET /export.php?type=tickets` - Xuất CSV

## Bảo mật

- Mật khẩu được hash bằng `password_hash()`
- Sử dụng Prepared Statements chống SQL Injection
- Session-based authentication
- CSRF protection
- Input validation và sanitization

## Phát triển thêm

### Thêm tính năng mới
1. Tạo model mới trong `models/`
2. Tạo controller trong `controllers/`
3. Tạo view PHP tương ứng
4. Cập nhật menu trong `includes/header.php`

### Tùy chỉnh giao diện
- Chỉnh sửa `assets/css/style.css`
- Thêm JavaScript trong `assets/js/main.js`
- Sử dụng Bootstrap 5 classes

### Database Migration
1. Thêm thay đổi vào `database/crmhelpdesk.sql`
2. Chạy migration trong phpMyAdmin
3. Cập nhật models nếu cần

## Hỗ trợ

Nếu gặp vấn đề:
1. Kiểm tra PHP error logs
2. Đảm bảo database connection
3. Kiểm tra quyền file/folder
4. Xem browser console cho JavaScript errors

## License

Dự án này được phát triển cho mục đích học tập và thương mại.