# 🚀 Hướng Dẫn Setup Google Drive API

## 📋 **Tổng Quan**
Hướng dẫn này sẽ giúp bạn thiết lập Google Drive API cho VPS Live Server Control để có thể:
- Upload/Download files từ Google Drive
- Stream video trực tiếp từ Google Drive
- Quản lý files qua API
- Tiết kiệm chi phí storage

## 🔧 **Bước 1: Setup Google Cloud Console**

### **1.1 Tạo Project**
1. Truy cập: https://console.cloud.google.com/
2. Đăng nhập tài khoản Google
3. Click **"Select a project"** → **"New Project"**
4. Nhập tên: `VPS-Live-Server-Control`
5. Click **"Create"**

### **1.2 Enable Google Drive API**
1. Vào **"APIs & Services"** → **"Library"**
2. Tìm **"Google Drive API"**
3. Click **"Enable"**

### **1.3 Configure OAuth Consent Screen**
1. Vào **"APIs & Services"** → **"OAuth consent screen"**
2. Chọn **"External"** → **"Create"**
3. Điền thông tin:
   ```
   App name: VPS Live Server Control
   User support email: your-email@gmail.com
   Developer contact: your-email@gmail.com
   ```
4. **Scopes**: Click **"Add or Remove Scopes"**
   - Tìm và thêm: `https://www.googleapis.com/auth/drive`
   - Click **"Update"**
5. **Test Users**: Thêm email của bạn
6. Click **"Save and Continue"**

### **1.4 Tạo OAuth 2.0 Client ID**
1. Vào **"APIs & Services"** → **"Credentials"**
2. Click **"Create Credentials"** → **"OAuth client ID"**
3. **Application type**: **"Web application"**
4. **Name**: `VPS Live Server Control Web Client`
5. **Authorized redirect URIs**: Thêm:
   ```
   http://localhost:8000/test-google-drive/callback
   http://your-domain.com/test-google-drive/callback
   ```
6. Click **"Create"** → **Copy Client ID và Client Secret**

### **1.5 Tạo Service Account (Cho production)**
1. Click **"Create Credentials"** → **"Service Account"**
2. Nhập:
   - Name: `vps-drive-service`
   - ID: `vps-drive-service`
   - Description: `Service account for VPS Live Server Control`
3. Click **"Create and Continue"**
4. Bỏ qua phần permissions → Click **"Done"**
5. Click vào service account → Tab **"Keys"** → **"Add Key"** → **"Create new key"**
6. Chọn **"JSON"** → **"Create"**
7. File JSON sẽ download - **LƯU GIỮ AN TOÀN!**

## 📁 **Bước 2: Setup Google Drive**

### **2.1 Tạo Folder**
1. Vào https://drive.google.com/
2. Tạo folder: `VPS-Live-Files`
3. Right-click folder → **"Share"**
4. Thêm email service account (từ JSON file, field `client_email`)
5. Set permission: **"Editor"**
6. Click **"Share"**

### **2.2 Lấy Folder ID**
1. Mở folder vừa tạo
2. Copy URL: `https://drive.google.com/drive/folders/11Zgg2WGNc7oteLiDkZTCmeU6fyBPfVmk`
3. Phần `11Zgg2WGNc7oteLiDkZTCmeU6fyBPfVmk` là **Folder ID**

## ⚙️ **Bước 3: Cấu Hình Laravel**

### **3.1 Copy Service Account File**
```bash
# Copy file JSON đã download vào:
storage/app/credentials/google-service-account.json
```

### **3.2 Cập nhật Environment**
Thêm vào file `env`:
```env
# Google Drive API Configuration
GOOGLE_DRIVE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER_ID=11Zgg2WGNc7oteLiDkZTCmeU6fyBPfVmk
GOOGLE_SERVICE_ACCOUNT_PATH=storage/app/credentials/google-service-account.json
```

### **3.3 Lấy Refresh Token**

#### **Cách 1: Qua Laravel App (Recommended)**
1. Truy cập: `http://localhost:8000/test-google-drive`
2. Click **"Get Authorization URL"** trong phần OAuth Setup
3. Click **"Authorize App"** → Đăng nhập Google → Cho phép quyền
4. Sau khi redirect, copy **refresh_token** từ response
5. Paste vào file `env`: `GOOGLE_DRIVE_REFRESH_TOKEN=your-refresh-token`

#### **Cách 2: Google OAuth Playground**
1. Truy cập: https://developers.google.com/oauthplayground/
2. **Step 1**: Tìm **"Drive API v3"** → Chọn `https://www.googleapis.com/auth/drive`
3. Click **"Authorize APIs"** → Đăng nhập → Cho phép
4. **Step 2**: Click **"Exchange authorization code for tokens"**
5. Copy **refresh_token**

### **3.4 Cấu trúc thư mục**
```
storage/
├── app/
│   ├── credentials/
│   │   └── google-service-account.json
│   ├── temp/ (tạo tự động)
│   └── downloads/ (tạo tự động)
```

## 🧪 **Bước 4: Test Setup**

### **4.1 Truy cập trang test**
```
http://localhost:8000/test-google-drive
```

### **4.2 Test theo thứ tự:**
1. ✅ **OAuth Setup** → Get Authorization URL (nếu chưa có refresh token)
2. ✅ **Connection Test** → Kiểm tra kết nối API
3. 📤 **Upload Test** → Upload file test
4. 📁 **File Upload** → Upload file thực tế
5. 📋 **List Files** → Xem danh sách files
6. 🎥 **Streaming Test** → Test streaming từ Google Drive

### **4.3 Các test nâng cao:**
- 💰 **Cost Analysis** → Phân tích chi phí
- 🚀 **Performance Benchmark** → Test tốc độ
- 📺 **FFmpeg Command** → Generate streaming command

## 🔐 **Bảo Mật**

### **Quan trọng:**
- ❌ **KHÔNG** commit file `google-service-account.json` lên Git
- ❌ **KHÔNG** share Client Secret hoặc Refresh Token
- ✅ File đã được thêm vào `.gitignore`
- ✅ Chỉ share folder cần thiết với service account
- ✅ Định kỳ rotate service account keys

### **Permissions tối thiểu:**
- Service account chỉ cần quyền **Editor** trên folder cụ thể
- OAuth app chỉ cần scope `https://www.googleapis.com/auth/drive`

## 📊 **Lợi Ích**

### **So với VPS Storage:**
- 💰 **Tiết kiệm 60-80%** chi phí storage
- 🌐 **Bandwidth miễn phí** từ Google
- 🔄 **Auto backup** và redundancy
- 📈 **Scalability** không giới hạn

### **Performance:**
- ⚡ Direct streaming không cần download
- 🚀 CDN global của Google
- 📱 Mobile-friendly APIs

## 🛠️ **Troubleshooting**

### **Lỗi thường gặp:**

**1. "Service account file not found"**
```bash
# Kiểm tra file tồn tại:
ls -la storage/app/credentials/google-service-account.json

# Đảm bảo permissions:
chmod 644 storage/app/credentials/google-service-account.json
```

**2. "Failed to initialize Google Drive service"**
- Kiểm tra `GOOGLE_DRIVE_CLIENT_ID` và `GOOGLE_DRIVE_CLIENT_SECRET` trong file `env`
- Đảm bảo OAuth client đã được tạo đúng
- Kiểm tra redirect URI đã được thêm vào OAuth client

**3. "Folder not accessible"**
- Kiểm tra `GOOGLE_DRIVE_FOLDER_ID` đúng
- Đảm bảo đã share folder với service account email
- Quyền phải là "Editor" không phải "Viewer"

**4. "Invalid refresh token"**
- Refresh token có thể expire sau 7 ngày nếu app chưa verified
- Lấy lại refresh token qua OAuth flow
- Submit app để verify nếu dùng production

**5. "API quota exceeded"**
- Google Drive API có 1 tỷ requests/ngày miễn phí
- Kiểm tra usage tại Google Cloud Console

### **Debug commands:**
```bash
# Test connection qua artisan:
php artisan tinker
>>> app(App\Services\GoogleDriveService::class)->testConnection()

# Check logs:
tail -f storage/logs/laravel.log
```

## 📞 **Support**

Nếu gặp vấn đề:
1. Kiểm tra logs tại `storage/logs/laravel.log`
2. Test từng bước theo hướng dẫn
3. Đảm bảo tất cả credentials được set đúng
4. Liên hệ support team

---

## 🎯 **Next Steps**

Sau khi setup thành công:
1. Tích hợp vào FileManager chính
2. Setup auto-sync với VPS
3. Cấu hình streaming endpoints
4. Monitor usage và costs
5. Scale up production

**🎉 Chúc bạn setup thành công!** 