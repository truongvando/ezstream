<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FileUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $maxFileSize = 10 * 1024 * 1024 * 1024; // 10GB in bytes

        return [
            'filename' => 'required|string|max:255',
            'content_type' => 'required|in:video/mp4',
            'size' => 'required|integer|min:1|max:' . $maxFileSize,
            'method' => 'sometimes|string|in:server,cdn,stream,hybrid',
            'upload_token' => 'sometimes|string|max:255',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'filename.required' => 'Tên file là bắt buộc.',
            'filename.max' => 'Tên file không được vượt quá 255 ký tự.',
            'content_type.required' => 'Loại file là bắt buộc.',
            'content_type.in' => 'Chỉ hỗ trợ file MP4.',
            'size.required' => 'Kích thước file là bắt buộc.',
            'size.min' => 'File không được rỗng.',
            'size.max' => 'File không được vượt quá 10GB.',
            'method.in' => 'Phương thức upload không hợp lệ.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'filename' => 'tên file',
            'content_type' => 'loại file',
            'size' => 'kích thước file',
            'method' => 'phương thức upload',
            'upload_token' => 'token upload',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional validation logic can be added here
            $this->validateStorageQuota($validator);
            $this->validateFileExtension($validator);
        });
    }

    /**
     * Validate storage quota for the user.
     */
    protected function validateStorageQuota($validator): void
    {
        $user = Auth::user();
        
        // Skip quota check for admins
        if ($user->hasRole('admin')) {
            return;
        }

        $currentUsage = $user->files()->sum('size');
        $package = $user->currentPackage();
        $storageLimit = $package ? $package->storage_limit : 5 * 1024 * 1024 * 1024; // 5GB default

        if (($currentUsage + $this->input('size', 0)) > $storageLimit) {
            $validator->errors()->add('size', 'Bạn đã vượt quá giới hạn dung lượng. Vui lòng nâng cấp gói hoặc xóa bớt file.');
        }
    }

    /**
     * Validate file extension matches content type.
     */
    protected function validateFileExtension($validator): void
    {
        $filename = $this->input('filename');
        $contentType = $this->input('content_type');

        if ($filename && $contentType) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            $allowedExtensions = [
                'video/mp4' => ['mp4'],
            ];

            if (isset($allowedExtensions[$contentType])) {
                if (!in_array($extension, $allowedExtensions[$contentType])) {
                    $validator->errors()->add('filename', 'Phần mở rộng file không khớp với loại file.');
                }
            }
        }
    }
}
