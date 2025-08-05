<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiResponse
{
    /**
     * Return a successful JSON response.
     */
    public static function success($data = null, string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'code' => $code,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response.
     */
    public static function error(string $message, int $code = 400, $errors = null, $data = null): JsonResponse
    {
        $response = [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a validation error response.
     */
    public static function validationError($errors, string $message = 'Dữ liệu không hợp lệ'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    /**
     * Return a not found error response.
     */
    public static function notFound(string $message = 'Không tìm thấy tài nguyên'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * Return an unauthorized error response.
     */
    public static function unauthorized(string $message = 'Không có quyền truy cập'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * Return a forbidden error response.
     */
    public static function forbidden(string $message = 'Bị cấm truy cập'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * Return a server error response.
     */
    public static function serverError(string $message = 'Lỗi máy chủ nội bộ'): JsonResponse
    {
        return self::error($message, 500);
    }

    /**
     * Return a created response.
     */
    public static function created($data = null, string $message = 'Tạo thành công'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Return a no content response.
     */
    public static function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * Return a paginated response.
     */
    public static function paginated($paginator, string $message = null): JsonResponse
    {
        $data = [
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ];

        return self::success($data, $message);
    }

    /**
     * Return a file upload progress response.
     */
    public static function uploadProgress(int $percentage, string $status, array $data = []): JsonResponse
    {
        return self::success(array_merge([
            'percentage' => $percentage,
            'status' => $status,
        ], $data), 'Upload đang tiến hành');
    }

    /**
     * Return a file upload completed response.
     */
    public static function uploadCompleted($fileData, string $message = 'Upload hoàn thành'): JsonResponse
    {
        return self::success($fileData, $message);
    }

    /**
     * Return a file deletion response.
     */
    public static function fileDeleted(int $count = 1): JsonResponse
    {
        $message = $count === 1 
            ? 'File đã được xóa thành công'
            : "Đã xóa {$count} file thành công";

        return self::success(['deleted_count' => $count], $message);
    }

    /**
     * Return a bulk operation response.
     */
    public static function bulkOperation(int $successful, int $failed, string $operation): JsonResponse
    {
        $message = "Hoàn thành {$operation}: {$successful} thành công";
        
        if ($failed > 0) {
            $message .= ", {$failed} thất bại";
        }

        return self::success([
            'successful' => $successful,
            'failed' => $failed,
            'total' => $successful + $failed,
        ], $message);
    }
}
