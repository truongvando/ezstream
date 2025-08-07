# 💰 EZStream Currency Standards

## 🎯 **NGUYÊN TẮC CHUNG**

**USD là currency chính** - Tất cả pricing, balance, transaction đều lưu bằng USD trong database.
**VND chỉ để hiển thị** - Convert sang VND khi hiển thị cho user Việt Nam.

## 📊 **CHUẨN HỆ THỐNG**

### 1. **Database Storage (USD Only)**
```sql
-- ✅ TẤT CẢ BẢNG SỬ DỤNG USD
users.balance              DECIMAL(10,2)  -- USD
transactions.amount        DECIMAL(10,2)  -- USD  
transactions.currency      VARCHAR(3)     -- 'USD'
api_services.rate          DECIMAL(10,2)  -- USD per 1000 units
mmo_services.price         DECIMAL(10,2)  -- USD
mmo_services.currency      VARCHAR(3)     -- 'USD'
service_packages.price     DECIMAL(10,2)  -- USD
```

### 2. **Display Standards**
```php
// ✅ HIỂN THỊ CHUẨN
Primary:   $12.50 USD
Secondary: ≈ 325,000 VND

// ❌ KHÔNG DÙNG
Primary:   325,000 VND  
Secondary: ≈ $12.50 USD
```

### 3. **Code Standards**

#### ✅ **ĐÚNG - Sử dụng CurrencyService**
```php
$currencyService = new CurrencyService();
$display = $currencyService->getFormattedPriceDisplay($usdAmount);
echo $display['display']; // $12.50 (≈ 325,000 VND)
```

#### ❌ **SAI - Hardcode exchange rate**
```php
$vndAmount = $usdAmount * 24000; // KHÔNG DÙNG!
```

## 🔧 **SERVICES ĐÃ CẬP NHẬT**

### 1. **ExchangeRateService**
- Auto-fetch tỷ giá từ Vietcombank API
- Fallback sang exchangerate-api.com
- Cache 1 giờ
- Update mỗi giờ qua schedule

### 2. **CurrencyService** (Mới)
- `formatUSD()` - Format USD amount
- `formatVND()` - Format VND amount  
- `getFormattedPriceDisplay()` - USD primary, VND secondary
- `convertUsdToVnd()` - Convert using real-time rate

### 3. **PaymentService**
- Tất cả transaction lưu USD
- QR code convert sang VND khi generate
- Balance deduction tính bằng USD

## 📅 **LỊCH TỰ ĐỘNG**

```php
// bootstrap/app.php
$schedule->command('exchange-rate:update')->hourly();           // Cập nhật tỷ giá
$schedule->command('currency:check-consistency')->dailyAt('04:00'); // Kiểm tra consistency
```

## 🧪 **TESTING COMMANDS**

```bash
# Kiểm tra currency consistency
php artisan currency:check-consistency

# Fix currency issues
php artisan currency:check-consistency --fix

# Update exchange rate manually
php artisan exchange-rate:update

# Test currency service
php artisan tinker
>>> $service = new App\Services\CurrencyService(new App\Services\ExchangeRateService());
>>> $service->getFormattedPriceDisplay(12.50);
```

## 🎯 **MIGRATION CHECKLIST**

### ✅ **ĐÃ CHUẨN**
- [x] User balance: USD
- [x] Transaction: USD
- [x] MmoService: USD
- [x] MmoOrder: USD
- [x] ApiService rate: USD
- [x] ServicePackage price: USD

### ✅ **ĐÃ SỬA**
- [x] payment/view-order.blade.php: Dùng ExchangeRateService thay vì hardcode
- [x] .env.example: Sync rate với .env
- [x] TransactionManagement: Thêm currency format helpers

### 🔄 **ĐANG THEO DÕI**
- [ ] Tất cả views hiển thị consistent USD primary, VND secondary
- [ ] Không còn hardcode exchange rate
- [ ] API responses đều return USD

## 🚨 **LƯU Ý QUAN TRỌNG**

1. **KHÔNG BAO GIỜ** lưu VND vào database
2. **LUÔN LUÔN** sử dụng ExchangeRateService để convert
3. **KIỂM TRA** currency consistency định kỳ
4. **TEST** thoroughly khi thay đổi pricing logic

## 📞 **SUPPORT**

Nếu phát hiện inconsistency:
1. Chạy `php artisan currency:check-consistency`
2. Review log files
3. Fix bằng `--fix` flag nếu cần
4. Test lại toàn bộ payment flow
