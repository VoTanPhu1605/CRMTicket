-- ============================================================
-- cleanup_demo.sql
-- 1. Xóa tin nhắn xúc phạm trong chat
-- 2. Dọn dẹp service_templates trùng lặp, tạo lại chuẩn
-- 3. Tạo dữ liệu demo ticket đa dạng
-- ============================================================

-- 1. Xóa tin nhắn có nội dung xúc phạm trong chat
DELETE FROM chat_messages WHERE message LIKE '%tình cc%' OR message LIKE '%cc%' OR message LIKE '%đm%' OR message LIKE '%đcm%';

-- 2. Dọn service_templates, tạo lại 1 bộ sạch (mỗi loại 1 cái)
DELETE FROM service_templates;

INSERT INTO service_templates (name, category_id, price, description) VALUES
-- Kỹ thuật
('Kiểm tra & vệ sinh máy tính', (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1), 150000, 'Vệ sinh bụi bẩn, kiểm tra phần cứng, test ổn định hệ thống'),
('Cài đặt & cấu hình phần mềm', (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1), 200000, 'Cài đặt hệ điều hành, phần mềm văn phòng, driver'),
('Sửa lỗi hệ điều hành Windows', (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1), 350000, 'Khắc phục lỗi boot, BSOD, virus, phục hồi dữ liệu'),
('Nâng cấp RAM / SSD', (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1), 100000, 'Tư vấn và lắp đặt RAM/SSD, không tính phần cứng'),
-- Tài khoản
('Khôi phục tài khoản / đặt lại mật khẩu', (SELECT id FROM categories WHERE name='Tài khoản' LIMIT 1), 100000, 'Mở khóa và đặt lại mật khẩu tài khoản nội bộ'),
('Thiết lập bảo mật 2 lớp (2FA)', (SELECT id FROM categories WHERE name='Tài khoản' LIMIT 1), 150000, 'Cấu hình xác thực 2 yếu tố cho email, hệ thống'),
-- Mạng & Kết nối (dùng danh mục Kỹ thuật)
('Khắc phục lỗi kết nối mạng / VPN', (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1), 200000, 'Cấu hình mạng LAN/WiFi, VPN, proxy'),
-- Cải tiến
('Tư vấn & cải tiến quy trình IT', (SELECT id FROM categories WHERE name='Cải tiến' LIMIT 1), 500000, 'Phân tích và đề xuất cải tiến hạ tầng IT');

-- 3. Tạo dữ liệu demo ticket (INSERT IGNORE tránh trùng)
-- Lấy ID trạng thái, ưu tiên, danh mục, người dùng bằng subquery

INSERT IGNORE INTO tickets
    (title, description, customer_name, customer_email, customer_phone, customer_address,
     category_id, priority_id, status_id, assigned_to, created_by, due_date, created_at)
VALUES
-- Ticket 1: Mở, Khẩn cấp, Kỹ thuật
('Máy chủ bị treo, không khởi động được',
 'Máy chủ chính của văn phòng đột ngột tắt nguồn và không khởi động lại được. Ảnh hưởng toàn bộ hệ thống nội bộ.',
 'Công ty TNHH Minh Phúc', 'it@minhphuc.vn', '0901234567',
 '15 Nguyễn Huệ, P. Bến Nghé, Q.1, TP.HCM',
 (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id DESC LIMIT 1),
 (SELECT id FROM statuses WHERE name='Mở' LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='IT Helpdesk') LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Admin') LIMIT 1),
 DATE_ADD(CURDATE(), INTERVAL 1 DAY), NOW()),

-- Ticket 2: Đang xử lý, Cao, Tài khoản
('Không đăng nhập được Microsoft 365',
 'Nhân viên kế toán báo cáo không thể đăng nhập vào Microsoft 365 từ sáng sớm. Mật khẩu đúng nhưng bị báo lỗi xác thực.',
 'Nguyễn Thị Lan', 'lan.nguyen@company.com', '0912345678',
 '32 Lê Lợi, P. Bến Thành, Q.1, TP.HCM',
 (SELECT id FROM categories WHERE name='Tài khoản' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id DESC LIMIT 1 OFFSET 1),
 (SELECT id FROM statuses WHERE name='Đang xử lý' LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='IT Helpdesk') LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Manager') LIMIT 1),
 DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Ticket 3: Đang chờ, Trung bình, Kỹ thuật
('Máy tính chạy chậm, lag khi làm việc',
 'Máy tính bộ phận kinh doanh khởi động mất 5-10 phút, mở file Excel lag nhiều. Máy đã dùng 4 năm chưa vệ sinh.',
 'Trần Văn Bình', 'binh.tran@sales.com', '0923456789',
 '88 Hai Bà Trưng, P. Đa Kao, Q.1, TP.HCM',
 (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id ASC LIMIT 1 OFFSET 1),
 (SELECT id FROM statuses WHERE name='Đang chờ' LIMIT 1),
 NULL,
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Admin') LIMIT 1),
 DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- Ticket 4: Đã đóng, Thấp, Cải tiến
('Đề xuất nâng cấp phần mềm quản lý kho',
 'Phần mềm quản lý kho hiện tại không hỗ trợ xuất báo cáo tự động. Đề xuất nghiên cứu giải pháp thay thế hoặc bổ sung module.',
 'Lê Hoàng Nam', 'nam.le@warehouse.com', '0934567890',
 '55 Đinh Tiên Hoàng, P. Đa Kao, Q.1, TP.HCM',
 (SELECT id FROM categories WHERE name='Cải tiến' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id ASC LIMIT 1),
 (SELECT id FROM statuses WHERE name='Đã đóng' LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Manager') LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Admin') LIMIT 1),
 DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),

-- Ticket 5: Mở, Khẩn cấp, Kỹ thuật - Quá hạn
('Mất dữ liệu sau khi cúp điện đột ngột',
 'Sau sự cố cúp điện, một số file quan trọng trên server bị corrupt. Cần khôi phục ngay dữ liệu tháng 3.',
 'Phạm Thị Hoa', 'hoa.pham@finance.com', '0945678901',
 '12 Võ Thị Sáu, P. Tân Định, Q.1, TP.HCM',
 (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id DESC LIMIT 1),
 (SELECT id FROM statuses WHERE name='Mở' LIMIT 1),
 NULL,
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Admin') LIMIT 1),
 DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),

-- Ticket 6: Đang xử lý, Cao, Tài khoản
('Cấp quyền truy cập hệ thống CRM cho nhân viên mới',
 'Nhân viên mới Nguyễn Minh Tuấn (phòng kinh doanh) cần được cấp tài khoản và phân quyền truy cập CRM, email nội bộ.',
 'Nguyễn Minh Tuấn', 'tuan.nguyen@sales.com', '0956789012',
 '20 Pasteur, P. Nguyễn Thái Bình, Q.1, TP.HCM',
 (SELECT id FROM categories WHERE name='Tài khoản' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id DESC LIMIT 1 OFFSET 1),
 (SELECT id FROM statuses WHERE name='Đang xử lý' LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='IT Helpdesk') LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Manager') LIMIT 1),
 DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Ticket 7: Mở, Trung bình, Kỹ thuật - Mới hôm nay
('Máy in văn phòng không kết nối được',
 'Máy in Canon MF244dw tầng 3 báo lỗi offline, không in được từ bất kỳ máy tính nào. Đã thử restart nhưng không được.',
 'Vũ Thị Thanh', 'thanh.vu@office.com', '0967890123',
 '45 Nam Kỳ Khởi Nghĩa, P. Phạm Ngũ Lão, Q.1, TP.HCM',
 (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id ASC LIMIT 1 OFFSET 1),
 (SELECT id FROM statuses WHERE name='Mở' LIMIT 1),
 NULL,
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Admin') LIMIT 1),
 DATE_ADD(CURDATE(), INTERVAL 2 DAY), NOW()),

-- Ticket 8: Đã đóng, Trung bình, Tài khoản
('Cài đặt phần mềm diệt virus cho phòng kế toán',
 'Cài đặt và cấu hình Kaspersky Endpoint Security cho 8 máy tính phòng kế toán. Cập nhật definitions và chạy scan toàn bộ.',
 'Đỗ Văn Long', 'long.do@accounting.com', '0978901234',
 '78 Bùi Thị Xuân, P. Phạm Ngũ Lão, Q.1, TP.HCM',
 (SELECT id FROM categories WHERE name='Tài khoản' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id ASC LIMIT 1 OFFSET 1),
 (SELECT id FROM statuses WHERE name='Đã đóng' LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='IT Helpdesk') LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Admin') LIMIT 1),
 DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),

-- Ticket 9: Đang chờ, Cao, Cải tiến
('Triển khai hệ thống backup tự động',
 'Hiện tại backup đang làm thủ công hàng tuần, cần triển khai giải pháp backup tự động hàng ngày cho dữ liệu quan trọng.',
 'Hoàng Minh Châu', 'chau.hoang@director.com', '0989012345',
 '100 Cách Mạng Tháng 8, P.4, Q.3, TP.HCM',
 (SELECT id FROM categories WHERE name='Cải tiến' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id DESC LIMIT 1 OFFSET 1),
 (SELECT id FROM statuses WHERE name='Đang chờ' LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Manager') LIMIT 1),
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Manager') LIMIT 1),
 DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- Ticket 10: Mở, Thấp, Kỹ thuật
('Màn hình máy tính bị nhấp nháy',
 'Màn hình LG 24" bàn nhân viên Hải bị nhấp nháy khi dùng quá 30 phút. Có thể do cáp hoặc driver màn hình.',
 'Ngô Thanh Hải', 'hai.ngo@staff.com', '0990123456',
 '36 Đinh Bộ Lĩnh, P.26, Q. Bình Thạnh, TP.HCM',
 (SELECT id FROM categories WHERE name='Kỹ thuật' LIMIT 1),
 (SELECT id FROM priorities ORDER BY id ASC LIMIT 1),
 (SELECT id FROM statuses WHERE name='Mở' LIMIT 1),
 NULL,
 (SELECT id FROM users WHERE role_id IN (SELECT id FROM roles WHERE name='Admin') LIMIT 1),
 DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 30 MINUTE));
