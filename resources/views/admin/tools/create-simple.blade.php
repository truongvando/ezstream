<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Tool Mới - EzStream Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white dark:bg-gray-800 shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">🛠️ Thêm Tool Mới</h1>
                    <a href="{{ route('admin.tools.index') }}" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                        ← Quay lại
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Flash Messages -->
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Form -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <form action="{{ route('admin.tools.store') }}" method="POST" class="space-y-6">
                    @csrf
                    
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Tên Tool <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name') }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                   required>
                            @error('name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Slug <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="slug" 
                                   name="slug" 
                                   value="{{ old('slug') }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                   required>
                            @error('slug')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- License Type -->
                    <div class="mb-6">
                        <label for="license_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Loại License <span class="text-red-500">*</span>
                        </label>
                        <select id="license_type"
                                name="license_type"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                onchange="toggleLicenseFields()"
                                required>
                            <option value="LIFETIME" {{ old('license_type', 'LIFETIME') == 'LIFETIME' ? 'selected' : '' }}>Lifetime (Vĩnh viễn)</option>
                            <option value="FREE" {{ old('license_type') == 'FREE' ? 'selected' : '' }}>Free (Miễn phí)</option>
                            <option value="DEMO" {{ old('license_type') == 'DEMO' ? 'selected' : '' }}>Demo (Dùng thử)</option>
                            <option value="MONTHLY" {{ old('license_type') == 'MONTHLY' ? 'selected' : '' }}>Monthly (Hàng tháng)</option>
                            <option value="YEARLY" {{ old('license_type') == 'YEARLY' ? 'selected' : '' }}>Yearly (Hàng năm)</option>
                            <option value="CONSIGNMENT" {{ old('license_type') == 'CONSIGNMENT' ? 'selected' : '' }}>Consignment (Ký gửi)</option>
                        </select>
                        @error('license_type')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Pricing -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div id="price_field">
                            <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Giá Lifetime (USD) <span class="text-red-500">*</span>
                            </label>
                            <input type="number"
                                   step="0.01"
                                   id="price"
                                   name="price"
                                   value="{{ old('price', '0') }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            @error('price')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="monthly_price_field" style="display: none;">
                            <label for="monthly_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Giá Monthly (USD)
                            </label>
                            <input type="number"
                                   step="0.01"
                                   id="monthly_price"
                                   name="monthly_price"
                                   value="{{ old('monthly_price') }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            @error('monthly_price')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="yearly_price_field" style="display: none;">
                            <label for="yearly_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Giá Yearly (USD)
                            </label>
                            <input type="number"
                                   step="0.01"
                                   id="yearly_price"
                                   name="yearly_price"
                                   value="{{ old('yearly_price') }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            @error('yearly_price')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Demo Days (for DEMO license) -->
                    <div id="demo_days_field" style="display: none;" class="mb-6">
                        <label for="demo_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Số ngày Demo
                        </label>
                        <select id="demo_days"
                                name="demo_days"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="7" {{ old('demo_days', '7') == '7' ? 'selected' : '' }}>7 ngày</option>
                            <option value="14" {{ old('demo_days') == '14' ? 'selected' : '' }}>14 ngày</option>
                            <option value="30" {{ old('demo_days') == '30' ? 'selected' : '' }}>30 ngày</option>
                        </select>
                        @error('demo_days')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Ownership (for CONSIGNMENT) -->
                    <div id="ownership_fields" style="display: none;" class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">🤝 Thông tin chủ sở hữu (Consignment)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="owner_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Tên chủ sở hữu
                                </label>
                                <input type="text"
                                       id="owner_name"
                                       name="owner_name"
                                       value="{{ old('owner_name') }}"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                @error('owner_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="owner_contact" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Email/Contact
                                </label>
                                <input type="email"
                                       id="owner_contact"
                                       name="owner_contact"
                                       value="{{ old('owner_contact') }}"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                @error('owner_contact')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="commission_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Tỷ lệ hoa hồng (%)
                            </label>
                            <input type="number"
                                   step="0.01"
                                   id="commission_rate"
                                   name="commission_rate"
                                   value="{{ old('commission_rate', '30') }}"
                                   min="0"
                                   max="100"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            @error('commission_rate')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Descriptions -->
                    <div>
                        <label for="short_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Mô tả ngắn <span class="text-red-500">*</span>
                        </label>
                        <textarea id="short_description" 
                                  name="short_description" 
                                  rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                  required>{{ old('short_description') }}</textarea>
                        @error('short_description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Mô tả chi tiết <span class="text-red-500">*</span>
                        </label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                  required>{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Tool Metadata -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">📦 Thông tin Tool</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="version" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Version
                                </label>
                                <input type="text"
                                       id="version"
                                       name="version"
                                       value="{{ old('version', '1.0.0') }}"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                @error('version')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="max_devices" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Số device tối đa
                                </label>
                                <select id="max_devices"
                                        name="max_devices"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                    <option value="1" {{ old('max_devices', '1') == '1' ? 'selected' : '' }}>1 device</option>
                                    <option value="3" {{ old('max_devices') == '3' ? 'selected' : '' }}>3 devices</option>
                                    <option value="5" {{ old('max_devices') == '5' ? 'selected' : '' }}>5 devices</option>
                                    <option value="10" {{ old('max_devices') == '10' ? 'selected' : '' }}>10 devices</option>
                                </select>
                                @error('max_devices')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- System Requirements -->
                    <div class="mb-6">
                        <label for="system_requirements" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Yêu cầu hệ thống
                        </label>
                        <textarea id="system_requirements"
                                  name="system_requirements"
                                  rows="3"
                                  placeholder="VD: Python 3.8+, Windows 10/11, 4GB RAM, GPU support..."
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">{{ old('system_requirements') }}</textarea>
                        @error('system_requirements')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Features -->
                    <div class="mb-6">
                        <label for="features" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Tính năng (mỗi dòng 1 tính năng)
                        </label>
                        <textarea id="features"
                                  name="features"
                                  rows="4"
                                  placeholder="H.264/H.265 encoding&#10;Batch processing&#10;GPU acceleration&#10;Custom presets"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">{{ old('features') }}</textarea>
                        @error('features')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- URLs -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">🔗 URLs</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    URL Hình ảnh <span class="text-red-500">*</span>
                                </label>
                                <input type="url"
                                       id="image"
                                       name="image"
                                       value="{{ old('image', 'https://via.placeholder.com/400x300?text=Tool+Image') }}"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                       required>
                                @error('image')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="download_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    URL Download <span class="text-red-500">*</span>
                                </label>
                                <input type="url"
                                       id="download_url"
                                       name="download_url"
                                       value="{{ old('download_url') }}"
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                       required>
                                @error('download_url')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="demo_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                URL Demo (Optional)
                            </label>
                            <input type="url"
                                   id="demo_url"
                                   name="demo_url"
                                   value="{{ old('demo_url') }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            @error('demo_url')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">⚙️ Tùy chọn</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="is_active"
                                       name="is_active"
                                       value="1"
                                       {{ old('is_active', true) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <label for="is_active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    Kích hoạt tool
                                </label>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="is_featured"
                                       name="is_featured"
                                       value="1"
                                       {{ old('is_featured') ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <label for="is_featured" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    Tool nổi bật
                                </label>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox"
                                       id="allow_transfer"
                                       name="allow_transfer"
                                       value="1"
                                       {{ old('allow_transfer', true) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <label for="allow_transfer" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    Cho phép chuyển license
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200 dark:border-gray-600">
                        <a href="{{ route('admin.tools.index') }}" 
                           class="px-6 py-2 text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-600 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                            Hủy
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            🛠️ Tạo Tool
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    // Auto-generate slug from name
    document.getElementById('name').addEventListener('input', function() {
        const name = this.value;
        const slug = name.toLowerCase()
                         .replace(/[^a-z0-9\s-]/g, '')
                         .replace(/\s+/g, '-')
                         .replace(/-+/g, '-')
                         .trim('-');
        document.getElementById('slug').value = slug;
    });

    // Toggle license fields based on license type
    function toggleLicenseFields() {
        const licenseType = document.getElementById('license_type').value;

        // Hide all conditional fields
        document.getElementById('demo_days_field').style.display = 'none';
        document.getElementById('ownership_fields').style.display = 'none';
        document.getElementById('monthly_price_field').style.display = 'none';
        document.getElementById('yearly_price_field').style.display = 'none';

        // Update price field label
        const priceLabel = document.querySelector('label[for="price"]');

        switch(licenseType) {
            case 'FREE':
                priceLabel.innerHTML = 'Giá (USD) <span class="text-gray-400">(Free tool)</span>';
                document.getElementById('price').value = '0';
                document.getElementById('price').readOnly = true;
                break;

            case 'DEMO':
                priceLabel.innerHTML = 'Giá sau Demo (USD) <span class="text-red-500">*</span>';
                document.getElementById('demo_days_field').style.display = 'block';
                document.getElementById('price').readOnly = false;
                break;

            case 'MONTHLY':
                priceLabel.innerHTML = 'Giá Lifetime (USD)';
                document.getElementById('monthly_price_field').style.display = 'block';
                document.getElementById('price').readOnly = false;
                break;

            case 'YEARLY':
                priceLabel.innerHTML = 'Giá Lifetime (USD)';
                document.getElementById('monthly_price_field').style.display = 'block';
                document.getElementById('yearly_price_field').style.display = 'block';
                document.getElementById('price').readOnly = false;
                break;

            case 'CONSIGNMENT':
                priceLabel.innerHTML = 'Giá (USD) <span class="text-red-500">*</span>';
                document.getElementById('ownership_fields').style.display = 'block';
                document.getElementById('price').readOnly = false;
                break;

            default: // LIFETIME
                priceLabel.innerHTML = 'Giá Lifetime (USD) <span class="text-red-500">*</span>';
                document.getElementById('price').readOnly = false;
                break;
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleLicenseFields();
    });
    </script>
</body>
</html>
