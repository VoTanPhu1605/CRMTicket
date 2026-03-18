# 📘 Hướng Dẫn Hệ Thống HelpDesk CRM

> **Phiên bản:** 1.0 | **Cập nhật:** 2026-03 | **Nền tảng:** PHP + MySQL + Railway

---

## MỤC LỤC

1. [Tổng quan hệ thống](#1-tổng-quan-hệ-thống)
2. [Cấu trúc thư mục](#2-cấu-trúc-thư-mục)
3. [Cơ sở dữ liệu](#3-cơ-sở-dữ-liệu)
4. [Tài khoản & Phân quyền](#4-tài-khoản--phân-quyền)
5. [Hướng dẫn sử dụng từng module](#5-hướng-dẫn-sử-dụng-từng-module)
6. [Quy trình xử lý Ticket](#6-quy-trình-xử-lý-ticket)
7. [Thanh toán & Bảo hành](#7-thanh-toán--bảo-hành)
8. [AI Assistant](#8-ai-assistant)
9. [Chat nội bộ](#9-chat-nội-bộ)
10. [Báo cáo & Thống kê](#10-báo-cáo--thống-kê)
11. [Bảo trì & Cập nhật code](#11-bảo-trì--cập-nhật-code)
12. [Triển khai Railway](#12-triển-khai-railway)
13. [Xử lý sự cố thường gặp](#13-xử-lý-sự-cố-thường-gặp)

---

## 1. Tổng quan hệ thống

Hệ thống **HelpDesk CRM** là phần mềm quản lý yêu cầu hỗ trợ kỹ thuật nội bộ, gồm các tính năng:

| Module | Mô tả |
|--------|-------|
| **Ticket** | Tạo, quản lý và theo dõi yêu cầu hỗ trợ |
| **Thanh toán** | Quản lý phí dịch vụ, xác nhận thanh toán |
| **Bảo hành** | Theo dõi thời hạn bảo hành sau khi đóng ticket |
| **Chat nội bộ** | Nhắn tin nhóm/cá nhân giữa nhân viên |
| **Báo cáo** | Thống kê hiệu suất, doanh thu, hiệu quả làm việc |
| **AI Assistant** | Phân tích ticket, đề xuất giải pháp tự động |
| **Người dùng** | Quản lý tài khoản và phân quyền |

### Công nghệ sử dụng

- **Backend:** PHP 8.x (kiến trúc MVC)
- **Database:** MySQL 8.x / MariaDB
- **Frontend:** Bootstrap 5 + Bootstrap Icons
- **AI:** Groq API (Llama 3.1 8B Instant — miễn phí)
- **Hosting:** Railway.app (tự động deploy từ GitHub)

---

## 2. Cấu trúc thư mục

```
CRMTicket/
│
├── api/
│   └── ai.php                  # Endpoint gọi Groq AI API
│
├── config/
│   ├── database.php            # Kết nối PDO database
│   └── ai_config.php           # Cấu hình Groq API key
│
├── controllers/                # Xử lý logic nghiệp vụ
│   ├── authController.php      # Đăng nhập / phân quyền
│   ├── ticketController.php    # Quản lý ticket
│   ├── billingController.php   # Thanh toán
│   ├── userController.php      # Quản lý user
│   └── reportController.php    # Báo cáo, thống kê
│
├── models/                     # Tương tác database
│   ├── User.php
│   ├── Ticket.php
│   ├── Billing.php
│   ├── Chat.php
│   ├── Note.php
│   └── ActivityLog.php
│
├── includes/                   # Dùng chung
│   ├── auth.php                # Hàm xác thực
│   ├── functions.php           # Hàm tiện ích
│   ├── header.php              # Layout header + nav
│   └── footer.php              # Layout footer + scripts
│
├── database/                   # SQL files
│   ├── railway_full_setup.sql  # Schema đầy đủ (dùng khi setup mới)
│   └── *.sql                   # Các migration bổ sung
│
├── assets/                     # CSS, JS, icon, ảnh
│
├── dashboard.php               # Trang chính
├── tickets.php                 # Quản lý ticket
├── billing.php                 # Thanh toán
├── warranty.php                # Bảo hành
├── chat.php                    # Chat nội bộ
├── reports.php                 # Báo cáo
├── users.php                   # Quản lý người dùng
├── profile.php                 # Hồ sơ cá nhân
├── login.php                   # Đăng nhập
├── migrate.php                 # Chạy migration DB (web)
└── .gitignore
```

### Nguyên tắc kiến trúc MVC

```
Người dùng → Page (*.php) → Controller → Model → Database
                ↑                              ↓
           HTML Response  ←──────────────── Data
```

- **Page** (`tickets.php`): Nhận request, gọi controller, hiển thị HTML
- **Controller** (`ticketController.php`): Kiểm tra dữ liệu, áp dụng logic nghiệp vụ
- **Model** (`Ticket.php`): Thực thi câu SQL, trả về data

---

## 3. Cơ sở dữ liệu

### Danh sách bảng

| Bảng | Mô tả |
|------|-------|
| `roles` | Vai trò (Admin, Manager, IT Helpdesk, IT Support, IT Intern) |
| `users` | Tài khoản người dùng |
| `categories` | Danh mục ticket (Kỹ thuật, Tài khoản, Thanh toán, Cải tiến) |
| `priorities` | Mức ưu tiên (Cao, Trung bình, Thấp) kèm màu sắc |
| `statuses` | Trạng thái ticket (Mở, Đang xử lý, Đã đóng) |
| `tickets` | Dữ liệu chính của ticket |
| `ticket_notes` | Ghi chú nội bộ của ticket |
| `ticket_activity_log` | Lịch sử thao tác đầy đủ |
| `ticket_billing` | Thông tin thanh toán của ticket |
| `ticket_payments` | Giao dịch thanh toán |
| `service_templates` | Mẫu dịch vụ có sẵn với giá chuẩn |
| `chat_rooms` | Phòng chat (general/direct/group) |
| `chat_members` | Thành viên trong phòng chat |
| `chat_messages` | Tin nhắn chat |

### Sơ đồ quan hệ chính

```
users ──── roles
  │
  ├──── tickets ──── categories
  │         │   ──── priorities
  │         │   ──── statuses
  │         │
  │         ├──── ticket_notes
  │         ├──── ticket_activity_log
  │         ├──── ticket_billing ──── ticket_payments
  │         └──── (warranty_end_date, warranty_claim_id)
  │
  └──── chat_members ──── chat_rooms ──── chat_messages
```

### Luồng dữ liệu ticket

```sql
-- Tạo ticket
INSERT INTO tickets (title, customer_name, category_id, priority_id, status_id=1, ...)

-- Chuyển trạng thái
UPDATE tickets SET status_id=2 WHERE id=?   -- Mở → Đang xử lý
UPDATE tickets SET status_id=3,             -- Đang xử lý → Đã đóng
    warranty_end_date = DATE_ADD(NOW(), INTERVAL 12 MONTH)
WHERE id=?

-- Lịch sử được ghi tự động
INSERT INTO ticket_activity_log (ticket_id, user_id, action, description)
```

---

## 4. Tài khoản & Phân quyền

### Cấp bậc vai trò (số nhỏ = quyền cao hơn)

| Cấp | Vai trò | Quyền |
|-----|---------|-------|
| 1 | **Admin** | Toàn quyền: quản lý user, xem tất cả ticket, báo cáo, hệ thống |
| 2 | **Manager** | Quản lý ticket, phân công, xem báo cáo |
| 3 | **IT Helpdesk** | Xử lý ticket được phân công, cập nhật trạng thái |
| 4 | **IT Support** | Tương tự IT Helpdesk |
| 5 | **IT Intern** | Hạn chế — chỉ xử lý ticket được giao |

### Quy tắc phân công

- **Admin/Manager** có thể phân công cho bất kỳ ai
- **IT Helpdesk/Support** không thể phân công lại
- Chỉ có **1 tài khoản Admin** — hệ thống chặn tạo thêm

### Tài khoản mặc định

```
Username: admin
Password: admin123
Role: Administrator
```

> ⚠️ Nên đổi mật khẩu sau khi cài đặt!

### Quản lý user (chỉ Admin)

1. Vào **Người dùng** → **Tạo User mới**
2. Điền: Tên đăng nhập, Mật khẩu (≥6 ký tự), Họ tên, Email, Vai trò
3. Trạng thái: **Active** (đăng nhập được) / **Inactive** (bị khóa)

---

## 5. Hướng dẫn sử dụng từng module

### 5.1 Dashboard

Trang tổng quan hiển thị:
- **4 thẻ thống kê**: Tổng ticket / Đang mở / Đang xử lý / Đã đóng
- **Biểu đồ tròn**: Phân bổ theo trạng thái, ưu tiên, danh mục
- **Thao tác nhanh**: Tạo ticket mới, xem theo trạng thái
- **Hoạt động gần đây**: 5 ticket được cập nhật mới nhất

### 5.2 Tickets

#### Tạo ticket mới
1. Click **"+ Tạo Ticket mới"**
2. Điền thông tin:
   - **Tiêu đề** *(bắt buộc)*
   - **Danh mục**: Kỹ thuật / Tài khoản / Thanh toán / Cải tiến
   - **Ưu tiên**: Cao / Trung bình / Thấp
   - **Thông tin khách hàng**: Tên, Email, SĐT (10 số, đầu 0)
   - **Mô tả** chi tiết vấn đề
   - **Hạn xử lý** *(không được chọn ngày quá khứ)*
   - **Phân công**: Chọn nhân viên xử lý
3. Click **"Tạo Ticket"**

#### Xem danh sách ticket

Bộ lọc có sẵn:
- **Trạng thái**: Mở / Đang xử lý / Đã đóng / Tất cả
- **Ưu tiên**: Cao / Trung bình / Thấp
- **Danh mục**: theo loại
- **Tìm kiếm**: tiêu đề hoặc tên khách hàng

Ticket **Đã đóng** chỉ có nút **Xem** (không sửa được).

#### Xem chi tiết & cập nhật ticket

Từ danh sách → click **biểu tượng mắt** hoặc **tiêu đề** → trang chi tiết:

- **Tab Thông tin**: Xem/sửa thông tin cơ bản (nếu chưa đóng)
- **Ghi chú nội bộ**: Thêm ghi chú không hiển thị cho khách
- **Lịch sử**: Toàn bộ thao tác đã thực hiện
- **Thanh toán** (sidebar phải): Chọn phương thức, thanh toán
- **Thao tác nhanh** (sidebar phải): Đổi trạng thái, chuyển giao

#### Quy tắc chuyển trạng thái

```
Mở (1) ──→ Đang xử lý (2) ──→ Đã đóng (3)
```

- **Bắt buộc có người phân công** trước khi chuyển từ Mở
- **Bắt buộc thanh toán xong** trước khi Đóng ticket
- **Không thể quay ngược** trạng thái
- **Không thể sửa** ticket đã đóng

### 5.3 Người dùng

| Thao tác | Mô tả |
|----------|-------|
| Tạo mới | Điền form, chọn vai trò (Admin slot duy nhất) |
| Chỉnh sửa | Cập nhật thông tin, đặt lại mật khẩu |
| Khóa tài khoản | Chuyển status sang Inactive |
| Xóa | Chỉ xóa được nếu không có ticket liên quan |

---

## 6. Quy trình xử lý Ticket

Dưới đây là luồng chuẩn từ khi tiếp nhận đến hoàn thành:

```
┌─────────────────────────────────────────────────────────────┐
│  BƯỚC 1: TIẾP NHẬN (Trạng thái: Mở)                        │
│  ├─ Khách hàng báo sự cố                                    │
│  ├─ Admin/Manager tạo ticket                                │
│  └─ Phân công nhân viên xử lý                               │
└──────────────────────────┬──────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  BƯỚC 2: XỬ LÝ (Trạng thái: Đang xử lý)                    │
│  ├─ Nhân viên nhận ticket, bắt đầu xử lý                   │
│  ├─ Thêm ghi chú nội bộ theo tiến độ                        │
│  ├─ AI Assistant hỗ trợ phân tích & đề xuất giải pháp       │
│  └─ Cập nhật trạng thái khi hoàn thành                      │
└──────────────────────────┬──────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  BƯỚC 3: THANH TOÁN (Bắt buộc trước khi đóng)              │
│  ├─ Chọn phương thức: Tiền mặt / MoMo / Ngân hàng          │
│  │                   Visa / ZaloPay / VNPay                 │
│  ├─ Admin/Manager xác nhận đã nhận tiền                     │
│  └─ Hoặc chọn "Miễn phí" nếu không thu phí                  │
└──────────────────────────┬──────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  BƯỚC 4: ĐÓNG TICKET (Trạng thái: Đã đóng)                 │
│  ├─ Ngày bảo hành tự động: +12 tháng (hoặc theo danh mục)  │
│  ├─ Ticket xuất hiện trong mục Bảo hành                     │
│  └─ Không thể sửa sau khi đóng                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 7. Thanh toán & Bảo hành

### Thanh toán

#### Phương thức thanh toán hỗ trợ

| Phương thức | Mô tả |
|-------------|-------|
| Tiền mặt | Thu trực tiếp, xác nhận thủ công |
| MoMo | Ví điện tử, tạo mã tham chiếu |
| Chuyển khoản ngân hàng | Chuyển qua số tài khoản |
| Visa / Mastercard | Thẻ quốc tế |
| ZaloPay | Ví Zalo Pay |
| VNPay | Cổng thanh toán VNPay |
| Miễn phí | Không thu phí dịch vụ |

#### Trạng thái thanh toán

```
Chưa thanh toán → Đang chờ xác nhận → Đã thanh toán
                                     → Đã hủy
```

#### Mẫu dịch vụ (Service Templates)

Vào **Thanh toán** → **Mẫu dịch vụ** để:
- Tạo danh sách dịch vụ với giá chuẩn
- Gán theo danh mục ticket
- Tự động điền khi tạo billing cho ticket

### Bảo hành

#### Cách hoạt động

- Khi ticket được **Đóng**, ngày bảo hành tự động tính:
  - Dùng `warranty_months` của danh mục
  - Nếu không có → mặc định **12 tháng**
- Ticket đóng cũ (trước khi có tính năng này) → tự fix khi vào trang Bảo hành

#### Các tab trong trang Bảo hành

| Tab | Hiển thị |
|-----|---------|
| Còn bảo hành | `warranty_end_date >= Hôm nay` |
| Hết bảo hành | `warranty_end_date < Hôm nay` |
| Tất cả | Toàn bộ ticket đã đóng |

#### Tạo yêu cầu bảo hành mới

Ticket còn bảo hành → **"Tạo Ticket Bảo Hành"** → Hệ thống tạo ticket mới liên kết với ticket gốc.

---

## 8. AI Assistant

### Cách sử dụng

Click icon **robot** góc phải màn hình để mở chat AI.

#### Các chức năng

| Nút nhanh | Thực hiện |
|-----------|-----------|
| Reset mật khẩu | Hướng dẫn xử lý vấn đề reset mật khẩu |
| Lỗi mạng | Các bước khắc phục lỗi kết nối mạng |
| Máy chậm | Tối ưu hiệu suất máy tính |

#### Tích hợp với Ticket

Khi xem chi tiết ticket → Click **"Phân tích với AI"**:
- AI đọc tiêu đề + mô tả + danh mục
- Đề xuất: ưu tiên, nhân viên xử lý, thời gian ước tính, các bước giải quyết

### Cấu hình API Key

**Model đang dùng:** `llama-3.1-8b-instant` (Groq — miễn phí, 14,400 req/ngày)

**Để thay API Key:**
1. Vào Railway → **Variables**
2. Sửa giá trị `GROQ_API_KEY`
3. Deploy lại

**Để đổi model AI:**
- Sửa `GROQ_MODEL` trong `config/ai_config.php`
- Các model miễn phí của Groq: `llama-3.1-8b-instant`, `llama-3.3-70b-versatile`, `mixtral-8x7b-32768`

---

## 9. Chat nội bộ

### Tính năng

- **Nhóm IT chung**: Tất cả nhân viên đều trong nhóm này
- **Tạo nhóm mới**: Tạo phòng chat riêng theo dự án/team
- **Chat trực tiếp**: Nhắn tin 1-1 với đồng nghiệp
- **Emoji picker**: Click 😊 để chọn emoji, chèn vào tin nhắn
- **Tự động cập nhật**: Tin nhắn mới hiện mỗi 3 giây (polling)

### Cách dùng

| Thao tác | Cách làm |
|----------|----------|
| Gửi tin nhắn | Nhập → Enter hoặc click nút Gửi |
| Thêm emoji | Click 😊 → chọn emoji → tự động chèn vào ô nhập |
| Chat trực tiếp | Click **"Chat mới"** → chọn người |
| Tạo nhóm | Click **"Tạo nhóm"** → thêm thành viên |

---

## 10. Báo cáo & Thống kê

### Các loại báo cáo

| Báo cáo | Nội dung |
|---------|---------|
| Thống kê tổng quan | Ticket theo trạng thái, ưu tiên, danh mục |
| Hiệu suất nhân viên | Số ticket xử lý, tỷ lệ hoàn thành, thời gian TB |
| Doanh thu | Theo ngày/tháng/năm, biểu đồ xu hướng |
| So sánh năm | Doanh thu năm nay vs năm trước |

### Xuất Excel

Vào **Báo cáo** → **Xuất Excel** → File CSV tải về (hỗ trợ tiếng Việt UTF-8).

---

## 11. Bảo trì & Cập nhật code

### Nguyên tắc quan trọng

> Mọi thay đổi đều phải **commit → push → Railway tự deploy**. Không sửa trực tiếp file trên server.

### Quy trình thay đổi code

```bash
# 1. Chỉnh sửa file local (XAMPP)
# 2. Test trên localhost trước
# 3. Commit và push
git add <file_đã_sửa>
git commit -m "mô tả ngắn gọn thay đổi"
git push origin main
# 4. Railway tự động deploy (~2 phút)
```

### Thêm tính năng mới — Ví dụ thêm cột vào DB

**Bước 1:** Viết file SQL migration

```sql
-- database/ten_migration.sql
ALTER TABLE tickets ADD COLUMN ten_cot VARCHAR(255) NULL;
```

**Bước 2:** Thêm vào `migrate.php`

```php
$files = [
    'database/railway_full_setup.sql',
    'database/payment_methods_migration.sql',
    'database/ten_migration.sql',   // ← thêm vào đây
];
```

**Bước 3:** Commit, push, vào `migrate.php` trên Railway để chạy

**Bước 4:** Cập nhật Model/Controller tương ứng

### Thêm phương thức thanh toán mới

1. Sửa ENUM trong `database/payment_methods_migration.sql`:
```sql
ALTER TABLE ticket_billing MODIFY COLUMN payment_method
    ENUM('cash','momo','bank_transfer','visa','zalopay','vnpay','TEN_MOI') DEFAULT NULL;
```
2. Thêm button trong `tickets.php` (phần payment UI)
3. Thêm case trong `billing.php` để hiển thị
4. Chạy migration

### Thêm vai trò mới

```sql
INSERT INTO roles (name, level) VALUES ('Tên vai trò', 6);
```
Sau đó cập nhật `includes/functions.php` → hàm `getRoleLevel()`.

### Thêm danh mục ticket

```sql
INSERT INTO categories (name, warranty_months, category_type)
VALUES ('Tên danh mục', 12, 'hardware');
-- category_type: 'hardware' | 'software' | 'other'
```

### Cấu trúc file để sửa từng tính năng

| Muốn sửa | File cần mở |
|----------|-------------|
| Giao diện trang ticket | `tickets.php` |
| Logic xử lý ticket | `controllers/ticketController.php` + `models/Ticket.php` |
| Logic thanh toán | `controllers/billingController.php` + `models/Billing.php` |
| Chat | `chat.php` + `chat-api.php` + `models/Chat.php` |
| Bảo hành | `warranty.php` + `models/Ticket.php` (getWarrantyList) |
| Dashboard số liệu | `controllers/reportController.php` |
| Phân quyền | `includes/functions.php` + `controllers/authController.php` |
| Giao diện chung (nav, layout) | `includes/header.php` |
| AI | `api/ai.php` + `config/ai_config.php` |

### Đổi mật khẩu admin

```sql
-- Lấy hash bcrypt của mật khẩu mới:
-- Dùng: https://bcrypt-generator.com (rounds=10)
UPDATE users SET password='$2y$10$HASH_MOI' WHERE username='admin';
```

Hoặc vào **Hồ sơ cá nhân** → Đổi mật khẩu (nếu đang đăng nhập).

---

## 12. Triển khai Railway

### Biến môi trường (Variables)

Vào Railway → project → service CRMTicket → **Variables**:

| Biến | Giá trị | Bắt buộc |
|------|---------|----------|
| `MYSQL_URL` | Tự động từ Railway MySQL | ✅ |
| `GROQ_API_KEY` | Key từ console.groq.com | ✅ (AI) |

### Kết nối Database từ ngoài

Railway không cho phép kết nối MySQL từ bên ngoài bằng client thông thường (do SSL/auth). Thay vào đó dùng:

```
https://your-app.railway.app/migrate.php?key=run_migrate_2024
```

Trang `migrate.php` sẽ chạy tất cả SQL files trong `database/`.

### Quy trình setup Railway từ đầu

1. Tạo project Railway mới
2. Add **MySQL** service → copy `MYSQL_URL`
3. Add **GitHub** service → chọn repo `CRMTicket`
4. Set biến `GROQ_API_KEY`
5. Deploy → vào `migrate.php` để tạo bảng
6. Đăng nhập `admin / admin123`

### Dockerfile (đã có sẵn)

```dockerfile
FROM php:8.2-cli
RUN docker-php-ext-install pdo pdo_mysql
WORKDIR /var/www/html
COPY . .
EXPOSE 8080
CMD php -S 0.0.0.0:${PORT:-8080}
```

---

## 13. Xử lý sự cố thường gặp

### ❌ Trang trắng / lỗi 500

**Nguyên nhân:** Lỗi PHP, thường do thiếu bảng DB hoặc cột.

**Cách fix:**
1. Vào `migrate.php` chạy lại migration
2. Xem Railway Logs → Deployments → xem chi tiết lỗi

---

### ❌ "Table doesn't exist"

**Nguyên nhân:** Bảng chưa được tạo trên Railway.

**Cách fix:**
```
Vào: https://your-app.railway.app/migrate.php?key=run_migrate_2024
```

---

### ❌ AI trả lời "Groq lỗi: ..."

**Nguyên nhân:**
- `GROQ_API_KEY` chưa set hoặc sai
- Vượt quota (14,400 req/ngày)

**Cách fix:**
- Kiểm tra Railway Variables có `GROQ_API_KEY` chưa
- Lấy key mới tại [console.groq.com](https://console.groq.com)

---

### ❌ Thanh toán lỗi JSON

**Nguyên nhân:** ENUM trong DB chưa có phương thức mới.

**Cách fix:** Chạy `migrate.php` — sẽ chạy `payment_methods_migration.sql`.

---

### ❌ Bảo hành không hiện data

**Nguyên nhân:** Ticket đóng cũ chưa có `warranty_end_date`.

**Cách fix:** Chỉ cần vào trang **Bảo hành** — code tự động UPDATE các ticket cũ.

---

### ❌ Không đăng nhập được

**Kiểm tra:**
1. Username/password đúng chưa? (mặc định: `admin` / `admin123`)
2. Tài khoản có bị Inactive không? (Admin check trong DB)
3. Kết nối DB có ok không? (xem Railway logs)

```sql
-- Reset mật khẩu admin qua SQL
UPDATE users SET password='$2y$10$62mge3TdsGN/DxDe0oAYB.VquDHY9e0jLO6BrTI6tZjLHLMBeJzum'
WHERE username='admin';
-- Password sau reset: admin123
```

---

### ❌ Chat emoji không gửi được

**Nguyên nhân:** `chat_messages` chưa dùng charset `utf8mb4`.

**Cách fix:** Chạy `migrate.php` (đã có migration fix charset).

---

## 📞 Liên hệ & Tài liệu

| Tài nguyên | Link |
|------------|------|
| GitHub Repo | https://github.com/VoTanPhu1605/CRMTicket |
| Railway App | https://crmticket-production-cf66.up.railway.app |
| Groq Console | https://console.groq.com |
| Bootstrap 5 Docs | https://getbootstrap.com/docs/5.3 |

---

*Tài liệu này được cập nhật lần cuối: 03/2026*
