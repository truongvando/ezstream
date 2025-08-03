<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    /**
     * Translate text from English to Vietnamese
     */
    public function translateToVietnamese($text)
    {
        if (empty($text)) {
            return $text;
        }

        // Cache key for translation
        $cacheKey = 'translation_' . md5($text);
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Use Google Translate API (free tier)
            $response = Http::timeout(10)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => 'en',
                'tl' => 'vi',
                'dt' => 't',
                'q' => $text
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data[0][0][0])) {
                    $translatedText = $data[0][0][0];
                    
                    // Cache for 24 hours
                    Cache::put($cacheKey, $translatedText, 86400);
                    
                    return $translatedText;
                }
            }
        } catch (\Exception $e) {
            Log::error('Translation failed', [
                'text' => $text,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback: return original text
        return $text;
    }

    /**
     * Get Vietnamese description for service based on keywords
     */
    public function getServiceDescription($serviceName)
    {
        $serviceName = strtolower($serviceName);
        
        // Predefined descriptions for common service types
        $descriptions = [
            'views' => 'Tăng lượt xem YouTube cho video của bạn. Giúp video có thêm độ tin cậy và thu hút nhiều người xem hơn.',
            'subscribers' => 'Tăng số lượng subscriber cho kênh YouTube. Giúp kênh phát triển nhanh chóng và có uy tín hơn.',
            'likes' => 'Tăng lượt thích cho video YouTube. Tạo tương tác tích cực và thu hút nhiều người xem hơn.',
            'comments' => 'Tăng bình luận cho video YouTube. Tạo sự tương tác và thảo luận sôi nổi cho video.',
            'shares' => 'Tăng lượt chia sẻ cho video YouTube. Giúp video lan truyền rộng rãi hơn.',
            'watch time' => 'Tăng thời gian xem cho video YouTube. Cải thiện thuật toán và ranking của video.',
            'watchtime' => 'Tăng thời gian xem cho video YouTube. Cải thiện thuật toán và ranking của video.',
            'live stream' => 'Tăng viewer cho live stream YouTube. Tạo không khí sôi động cho buổi phát trực tiếp.',
            'premiere' => 'Tăng viewer cho video premiere YouTube. Thu hút khán giả xem buổi công chiếu.',
            'dislikes' => 'Tăng lượt dislike cho video YouTube (nếu cần thiết cho chiến lược marketing).',
            'custom comments' => 'Thêm bình luận tùy chỉnh cho video YouTube. Tạo cuộc thảo luận theo ý muốn.',
            'real' => 'Dịch vụ tăng tương tác thật từ người dùng thực. Chất lượng cao và an toàn.',
            'high quality' => 'Dịch vụ chất lượng cao với tương tác từ tài khoản thật. Đảm bảo an toàn cho kênh.',
            'instant' => 'Dịch vụ giao hàng nhanh, hiệu quả ngay lập tức. Phù hợp cho các chiến dịch cần tốc độ.',
            'slow' => 'Dịch vụ giao hàng từ từ, tự nhiên. Mô phỏng sự tăng trưởng organic.',
            'targeted' => 'Dịch vụ nhắm mục tiêu theo địa lý hoặc đối tượng cụ thể. Tăng hiệu quả tiếp cận.',
        ];

        // Find matching keywords
        foreach ($descriptions as $keyword => $description) {
            if (stripos($serviceName, $keyword) !== false) {
                return $description;
            }
        }

        // Default description
        return 'Dịch vụ tăng tương tác YouTube chất lượng cao. Giúp phát triển kênh một cách tự nhiên và hiệu quả.';
    }

    /**
     * Enhance service name with Vietnamese context
     */
    public function enhanceServiceName($serviceName)
    {
        // Add quality indicators in Vietnamese
        $enhancements = [
            'High Quality' => 'Chất Lượng Cao',
            'Real' => 'Thật',
            'Instant' => 'Nhanh',
            'Slow' => 'Từ Từ',
            'Targeted' => 'Nhắm Mục Tiêu',
            'Premium' => 'Cao Cấp',
            'Fast' => 'Nhanh',
            'Cheap' => 'Giá Rẻ',
            'Best' => 'Tốt Nhất',
        ];

        $enhancedName = $serviceName;
        foreach ($enhancements as $english => $vietnamese) {
            $enhancedName = str_ireplace($english, $vietnamese, $enhancedName);
        }

        return $enhancedName;
    }
}
