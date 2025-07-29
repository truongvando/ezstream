# Test Upload Flow - Server Mode

## Luồng upload hiện tại:

### 1. Generate Upload URL
**Request**: `POST /api/generate-upload-url`
```json
{
    "filename": "test.mp4",
    "size": 1000000,
    "content_type": "video/mp4"
}
```

**Response** (server mode):
```json
{
    "status": "success",
    "upload_url": "http://localhost/api/server-upload/{token}",
    "upload_token": "abc123...",
    "path": "users/1/2025/01/28/1738051200_1_test.mp4",
    "method": "POST",
    "storage_mode": "server"
}
```

### 2. Upload File
**Request**: `POST /api/server-upload/{token}`
- FormData với file

**Response** (FIXED):
- Status: 200
- Body: Empty (giống Bunny)

### 3. Confirm Upload
**Request**: `POST /api/confirm-upload`
```json
{
    "upload_token": "abc123...",
    "size": 1000000,
    "content_type": "video/mp4"
}
```

**Response**:
```json
{
    "status": "success",
    "message": "File uploaded successfully",
    "file": {
        "id": 123,
        "name": "test.mp4",
        "size": 1000000,
        "url": "http://localhost/storage/files/1738051200_1_test.mp4"
    }
}
```

## Fixes đã thực hiện:

1. ✅ **Server upload response format**: Trả về empty response với status 200 (giống Bunny)
2. ✅ **File URL generation**: Tạo đúng URL cho server mode `/storage/files/{filename}`
3. ✅ **UserFile model**: Thêm missing fields `is_locked`, `upload_session_url`
4. ✅ **Route serving files**: Đã có route `/storage/files/{path}` để serve files

## Cần test:

1. Upload file nhỏ để test luồng
2. Kiểm tra file có được lưu vào `storage/app/files/`
3. Kiểm tra database record được tạo đúng
4. Kiểm tra URL file có accessible không
