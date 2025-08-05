<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\UserFile;

class FileDeleteRequest extends FormRequest
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
        return [
            'file_id' => 'required|integer|exists:user_files,id',
            'bulk_ids' => 'sometimes|array|min:1',
            'bulk_ids.*' => 'integer|exists:user_files,id',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'file_id.required' => 'ID file là bắt buộc.',
            'file_id.integer' => 'ID file phải là số nguyên.',
            'file_id.exists' => 'File không tồn tại.',
            'bulk_ids.array' => 'Danh sách file phải là mảng.',
            'bulk_ids.min' => 'Phải chọn ít nhất 1 file để xóa.',
            'bulk_ids.*.integer' => 'ID file phải là số nguyên.',
            'bulk_ids.*.exists' => 'Một hoặc nhiều file không tồn tại.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file_id' => 'ID file',
            'bulk_ids' => 'danh sách file',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateFileOwnership($validator);
        });
    }

    /**
     * Validate that user owns the file(s) or is admin.
     */
    protected function validateFileOwnership($validator): void
    {
        $user = Auth::user();
        
        // Admins can delete any file
        if ($user->hasRole('admin')) {
            return;
        }

        // For single file deletion
        if ($this->has('file_id')) {
            $file = UserFile::find($this->input('file_id'));
            if ($file && $file->user_id !== $user->id) {
                $validator->errors()->add('file_id', 'Bạn không có quyền xóa file này.');
            }
        }

        // For bulk deletion
        if ($this->has('bulk_ids')) {
            $fileIds = $this->input('bulk_ids', []);
            $userFiles = UserFile::whereIn('id', $fileIds)
                ->where('user_id', '!=', $user->id)
                ->count();

            if ($userFiles > 0) {
                $validator->errors()->add('bulk_ids', 'Bạn không có quyền xóa một hoặc nhiều file đã chọn.');
            }
        }
    }

    /**
     * Get the file(s) to be deleted.
     */
    public function getFilesToDelete(): \Illuminate\Database\Eloquent\Collection
    {
        $user = Auth::user();

        if ($this->has('bulk_ids')) {
            // Bulk deletion
            $fileIds = $this->input('bulk_ids', []);
            
            if ($user->hasRole('admin')) {
                return UserFile::whereIn('id', $fileIds)->get();
            } else {
                return $user->files()->whereIn('id', $fileIds)->get();
            }
        } else {
            // Single file deletion
            $fileId = $this->input('file_id');
            
            if ($user->hasRole('admin')) {
                $file = UserFile::find($fileId);
                return $file ? collect([$file]) : collect();
            } else {
                $file = $user->files()->find($fileId);
                return $file ? collect([$file]) : collect();
            }
        }
    }

    /**
     * Check if this is a bulk deletion request.
     */
    public function isBulkDeletion(): bool
    {
        return $this->has('bulk_ids') && is_array($this->input('bulk_ids'));
    }
}
