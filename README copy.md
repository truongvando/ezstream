# KẾ HOẠCH TRIỂN KHAI DỰ ÁN LIVESTREAM VPS (LARAVEL & LIVEWIRE)

## I. MỤC TIÊU DỰ ÁN

Xây dựng nền tảng quản lý dịch vụ livestream VPS cho phép người dùng cấu hình, điều khiển luồng FFmpeg và quản lý gói dịch vụ. Admin có toàn quyền quản trị hệ thống.

## II. CÔNG NGHỆ CHỦ ĐẠO

*   **Backend**: PHP 8.1+, Laravel 10+.
*   **Frontend (Tích hợp)**: Laravel Livewire, Alpine.js.
*   **Styling**: Tailwind CSS (qua Laravel Vite).
*   **Database**: PostgreSQL (ưu tiên) hoặc MySQL.
*   **ORM**: Eloquent ORM.
*   **Xác thực**: Laravel Breeze (hoặc Jetstream - tùy chọn).
*   **Tương tác VPS**: `phpseclib`.
*   **Mã hóa**: `Crypt` facade của Laravel.
*   **Tác vụ nền**: Laravel Queues (Driver: Redis hoặc Database).
*   **Môi trường phát triển**: Docker với Laravel Sail.

## III. CÁC GIAI ĐOẠN PHÁT TRIỂN (TUẦN TỰ CHO AI)

### GIAI ĐOẠN 1: THIẾT LẬP NỀN TẢNG DỰ ÁN

1.  **Khởi tạo Dự án Laravel**: Sử dụng `composer create-project laravel/laravel .`
2.  **Cài đặt Laravel Sail**: Chạy `php artisan sail:install` (chọn PostgreSQL nếu có thể).
3.  **Cấu hình môi trường**: Thiết lập file `.env` (DB connection, App URL, Mail, Queue Driver là `database` ban đầu).
4.  **Khởi động Sail**: Chạy `./vendor/bin/sail up -d`.
5.  **Tạo Key Ứng Dụng**: Chạy `./vendor/bin/sail artisan key:generate`.
6.  **Cài đặt Laravel Breeze**: Chạy `./vendor/bin/sail composer require laravel/breeze --dev` và `php artisan breeze:install` (chọn Blade với Alpine.js).
7.  **Biên dịch Assets**: Chạy `./vendor/bin/sail npm install && ./vendor/bin/sail npm run dev`.
8.  **Chạy Migration ban đầu**: Chạy `./vendor/bin/sail artisan migrate`.
9.  **Thiết lập Git**: Khởi tạo repository, commit mã nguồn ban đầu.

### GIAI ĐOẠN 2: QUẢN LÝ NGƯỜI DÙNG VÀ PHÂN QUYỀN

1.  **Model `User`**: Mở rộng model User mặc định của Breeze nếu cần (ví dụ: thêm trường `role`).
2.  **Roles & Permissions**: 
    *   Tạo `Enum` cho User Roles (ví dụ: `UserRole::ADMIN`, `UserRole::USER`).
    *   Thêm cột `role` vào table `users` (mặc định là `USER`).
    *   Tạo Middleware `EnsureUserHasRole` để kiểm tra vai trò.
    *   Tạo Gates hoặc Policies cho các hành động của admin và user.
3.  **Admin Seeder**: Tạo một seeder để tạo tài khoản admin ban đầu.

### GIAI ĐOẠN 3: QUẢN LÝ MÁY CHỦ VPS (ADMIN)

1.  **Model `VpsServer`**: Tạo model, migration, factory, seeder.
    *   Thuộc tính: `name` (string), `ip_address` (string, unique), `ssh_user` (string, default 'root'), `ssh_password` (string, encrypted), `ssh_port` (integer, default 22), `is_active` (boolean, default true).
2.  **Mã hóa Mật khẩu SSH**: Sử dụng `Crypt::encryptString()` và `Crypt::decryptString()` khi lưu và lấy mật khẩu.
3.  **Livewire Component `VpsServerManager`**: CRUD cho VPS.
    *   Hiển thị danh sách VPS (table với Tailwind).
    *   Form thêm/sửa VPS (modal hoặc trang riêng).
    *   Nút xóa VPS (có xác nhận).
4.  **Routes & Navigation**: Tạo route và thêm link vào menu admin (sẽ tạo ở Giai đoạn 8).
5.  **Authorization**: Chỉ Admin mới có quyền truy cập.

### GIAI ĐOẠN 4: QUẢN LÝ GÓI DỊCH VỤ (ADMIN)

1.  **Model `ServicePackage`**: Tạo model, migration, factory, seeder.
    *   Thuộc tính: `name` (string), `description` (text, nullable), `price_monthly` (decimal), `price_yearly` (decimal, nullable), `max_streams` (integer), `max_quality` (string, ví dụ: '720p', '1080p'), `is_active` (boolean, default true), `features` (json, nullable, ví dụ: danh sách các tính năng).
2.  **Livewire Component `ServicePackageManager`**: CRUD cho Gói Dịch Vụ.
    *   Giao diện tương tự `VpsServerManager`.
3.  **Routes & Navigation**: Tạo route và thêm link vào menu admin.
4.  **Authorization**: Chỉ Admin mới có quyền truy cập.

### GIAI ĐOẠN 5: QUẢN LÝ CẤU HÌNH STREAM (USER & ADMIN)

1.  **Model `StreamConfiguration`**: Tạo model, migration, factory.
    *   Thuộc tính: `user_id` (FK to users), `vps_server_id` (FK to vps_servers), `title` (string), `description` (text, nullable), `video_source_path` (string, đường dẫn file/stream trên VPS), `rtmp_url` (string), `stream_key` (string), `ffmpeg_options` (text, nullable, các tùy chọn ffmpeg bổ sung), `status` (string, enum: PENDING, ACTIVE, INACTIVE, ERROR, STARTING, STOPPING), `ffmpeg_pid` (integer, nullable), `last_started_at` (timestamp, nullable), `last_stopped_at` (timestamp, nullable), `output_log_path` (string, nullable, đường dẫn log ffmpeg trên VPS).
2.  **Livewire Component `UserStreamManager` (cho User)**:
    *   Hiển thị danh sách stream của user đang đăng nhập.
    *   Form thêm/sửa stream (chỉ cho phép chọn VPS đã được admin gán hoặc VPS 'chung' nếu có).
    *   Nút xóa stream.
    *   Nút Start/Stop stream (sẽ gọi action ở Giai đoạn 6).
    *   Hiển thị trạng thái stream.
3.  **Livewire Component `AdminStreamManager` (cho Admin)**:
    *   Hiển thị TẤT CẢ stream của mọi người dùng.
    *   Chức năng tương tự User, nhưng có thể quản lý stream của bất kỳ ai.
    *   Lọc stream theo user, VPS, trạng thái.
4.  **Routes & Navigation**: Tạo routes cho user và admin.
5.  **Authorization**: User chỉ quản lý stream của mình. Admin quản lý tất cả.

### GIAI ĐOẠN 6: LOGIC ĐIỀU KHIỂN STREAM (SSH & QUEUES)

1.  **Service `SshService`**: Tạo service class để trừu tượng hóa việc tương tác SSH bằng `phpseclib`.
    *   Phương thức: `connect(VpsServer $vps)`, `executeCommand(string $command)`, `getProcessId(string $commandSignature)`, `killProcess(int $pid)`.
2.  **Job `StartStreamJob` (ShouldQueue)**:
    *   Input: `StreamConfiguration $streamConfig`.
    *   Logic: 
        *   Kết nối SSH tới VPS của stream.
        *   Xây dựng lệnh FFmpeg dựa trên `streamConfig`.
        *   Thực thi lệnh FFmpeg trong nền (ví dụ: `nohup your_ffmpeg_command > /path/to/log.txt 2>&1 & echo $!`).
        *   Lấy PID của tiến trình FFmpeg.
        *   Cập nhật `streamConfig->status = 'ACTIVE'`, `ffmpeg_pid`, `last_started_at`.
        *   Xử lý lỗi nếu có, cập nhật `status = 'ERROR'`.
3.  **Job `StopStreamJob` (ShouldQueue)**:
    *   Input: `StreamConfiguration $streamConfig`.
    *   Logic:
        *   Kết nối SSH.
        *   Kill tiến trình FFmpeg bằng `ffmpeg_pid`.
        *   Cập nhật `streamConfig->status = 'INACTIVE'`, `ffmpeg_pid = null`, `last_stopped_at`.
4.  **Controller Actions / Livewire Actions**: Trong `UserStreamManager` và `AdminStreamManager`, khi nhấn Start/Stop, dispatch các Job tương ứng.
5.  **Queue Worker**: Đảm bảo queue worker đang chạy: `./vendor/bin/sail artisan queue:work`.

### GIAI ĐOẠN 7: TÍCH HỢP THANH TOÁN & QUẢN LÝ ĐĂNG KÝ

1.  **Model `Subscription`**: Tạo model, migration.
    *   Thuộc tính: `user_id` (FK), `service_package_id` (FK), `starts_at` (timestamp), `ends_at` (timestamp), `status` (string, enum: ACTIVE, INACTIVE, PENDING_PAYMENT, CANCELED), `payment_transaction_id` (string, nullable).
2.  **Model `Transaction`**: Tạo model, migration.
    *   Thuộc tính: `user_id` (FK), `subscription_id` (FK, nullable), `amount` (decimal), `currency` (string), `payment_gateway` (string, ví dụ: 'USER_API'), `gateway_transaction_id` (string, nullable), `status` (string, enum: PENDING, COMPLETED, FAILED), `description` (string, nullable).
3.  **Chuẩn bị cho API Thanh Toán (CHỜ USER CUNG CẤP ENDPOINT)**:
    *   **Livewire Component `PackageSelection`**: Hiển thị danh sách các `ServicePackage` cho người dùng chọn.
    *   Khi người dùng chọn gói, tạo một `Subscription` với status `PENDING_PAYMENT` và một `Transaction` với status `PENDING`.
    *   **Nút "Thanh Toán"**: Sẽ gọi đến một phương thức trong Livewire component.
    *   **Phương thức `processPayment` (Livewire)**: 
        *   Chuẩn bị dữ liệu cần thiết (thông tin giao dịch, user, gói).
        *   **GỌI API ENDPOINT CỦA USER CUNG CẤP ĐỂ XỬ LÝ THANH TOÁN.**
        *   Xử lý callback/response từ API (sẽ cần thiết kế luồng này khi có API).
4.  **Service `SubscriptionService`**: Logic quản lý subscription (kích hoạt, gia hạn, hủy).
5.  **Trang Quản Lý Đăng Ký (User)**: Hiển thị gói đang sử dụng, ngày hết hạn, lịch sử giao dịch.

### GIAI ĐOẠN 8: BẢNG ĐIỀU KHIỂN ADMIN

1.  **Layout Admin**: Tạo layout riêng cho admin (có thể kế thừa từ app layout của Breeze).
2.  **Navigation Admin**: Sidebar menu với các link đến các trang quản lý (VPS, Users, Packages, Streams, Transactions).
3.  **Livewire Component `AdminDashboard`**:
    *   Thống kê: Số lượng users, VPS, streams đang chạy, tổng số gói đã bán, doanh thu (đơn giản ban đầu).
4.  **Quản lý Users (Admin)**: Livewire component để xem danh sách users, sửa vai trò, xem chi tiết (gói đang dùng, streams).
5.  **Quản lý Transactions (Admin)**: Livewire component để xem danh sách giao dịch, lọc theo trạng thái, user.

### GIAI ĐOẠN 9: HOÀN THIỆN UI/UX VÀ TÍNH NĂNG PHỤ

1.  **Styling với Tailwind CSS**: Áp dụng theme tối (dark mode) cho toàn bộ ứng dụng.
2.  **Responsive Design**: Đảm bảo các trang hiển thị tốt trên mobile và desktop.
3.  **Thông báo (Flash Messages)**: Sử dụng cho các hành động thành công/thất bại.
4.  **Cải thiện Form Validation và Error Handling**.
5.  **Xem Log Stream (Tùy chọn nâng cao)**: Nếu `output_log_path` được cấu hình, cho phép user/admin xem nội dung log từ VPS.

### GIAI ĐOẠN 10: KIỂM THỬ VÀ TRIỂN KHAI

1.  **Viết Tests**: PHPUnit tests cho các logic quan trọng (Feature tests, Unit tests).
2.  **Kiểm thử thủ công**: Kiểm tra toàn bộ luồng hoạt động của user và admin.
3.  **Tối ưu hóa**: Tối ưu câu lệnh DB, hiệu năng Livewire component.
4.  **Chuẩn bị triển khai**: Cấu hình server (Nginx, PHP-FPM, PostgreSQL, Redis), cài đặt Supervisor cho queue worker.

## IV. LƯU Ý QUAN TRỌNG CHO AI

*   **Tuân thủ tuần tự**: Thực hiện các giai đoạn và bước theo đúng thứ tự được liệt kê.
*   **Sử dụng Laravel Best Practices**: Áp dụng các quy tắc và kiến trúc chuẩn của Laravel.
*   **Mã hóa dữ liệu nhạy cảm**: Luôn mã hóa mật khẩu VPS và các thông tin quan trọng khác.
*   **Validation**: Validate tất cả dữ liệu đầu vào từ người dùng.
*   **Authorization**: Sử dụng Gates/Policies của Laravel để kiểm soát quyền truy cập chặt chẽ.
*   **Tái sử dụng Components**: Tận dụng Livewire components để tránh lặp code.
*   **Ghi chú code**: Thêm comment cho các đoạn code phức tạp.
*   **Tích hợp API Thanh Toán**: Mục này cần thông tin API endpoint từ người dùng để hoàn thiện. Hãy tạo các phần chờ và ghi chú rõ ràng.

---
*Tài liệu này là chỉ dẫn chi tiết cho AI. Các điều chỉnh nhỏ có thể cần thiết trong quá trình phát triển thực tế.*
