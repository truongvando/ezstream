<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\UserRole;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'telegram_bot_token',
        'telegram_chat_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'telegram_bot_token' => 'encrypted',
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->role === UserRole::USER;
    }

    /**
     * Get the stream configurations for the user.
     */
    public function streamConfigurations()
    {
        return $this->hasMany(StreamConfiguration::class);
    }

    /**
     * Get the subscriptions for the user.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the transactions for the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all of the files for the User.
     */
    public function files(): HasMany
    {
        return $this->hasMany(UserFile::class);
    }

    /**
     * Get total allowed streams from all active subscriptions
     */
    public function getTotalAllowedStreams(): int
    {
        if ($this->isAdmin()) {
            return PHP_INT_MAX; // Admin unlimited
        }

        // ✅ Auto-expire subscriptions hết hạn trước khi tính
        $this->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'EXPIRED']);

        return $this->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('ends_at', '>', now()) // Chưa hết hạn
            ->with('servicePackage')
            ->get()
            ->sum(function ($subscription) {
                return $subscription->servicePackage->max_streams ?? 0;
            });
    }

    /**
     * Get active subscriptions with details
     */
    public function getActiveSubscriptionsDetails(): array
    {
        $subscriptions = $this->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('ends_at', '>', now())
            ->with('servicePackage')
            ->orderBy('ends_at')
            ->get();

        return $subscriptions->map(function ($sub) {
            return [
                'package_name' => $sub->servicePackage->name,
                'max_streams' => $sub->servicePackage->max_streams,
                'ends_at' => $sub->ends_at,
                'days_remaining' => now()->diffInDays($sub->ends_at, false)
            ];
        })->toArray();
    }

    /**
     * Get display name for current subscription status
     * Hiển thị tên phù hợp cho dashboard/profile
     */
    public function getSubscriptionDisplayName(): string
    {
        if ($this->isAdmin()) {
            return 'Admin (Không giới hạn)';
        }

        // Auto-expire subscriptions hết hạn
        $this->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'INACTIVE']);

        $activeSubscriptions = $this->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('ends_at', '>', now())
            ->with('servicePackage')
            ->orderBy('ends_at', 'desc') // Gói hết hạn muộn nhất trước
            ->get();

        if ($activeSubscriptions->isEmpty()) {
            return 'Chưa có gói';
        }

        // Nếu chỉ có 1 gói
        if ($activeSubscriptions->count() === 1) {
            return $activeSubscriptions->first()->servicePackage->name;
        }

        // Nếu có nhiều gói - hiển thị gói cao nhất + số lượng
        $totalStreams = $activeSubscriptions->sum(function ($sub) {
            return $sub->servicePackage->max_streams;
        });
        
        $highestPackage = $activeSubscriptions->sortByDesc('servicePackage.price')->first();
        
        return $highestPackage->servicePackage->name . " + " . ($activeSubscriptions->count() - 1) . " gói khác ({$totalStreams} streams)";
    }

    /**
     * Get short display name for compact spaces
     * Hiển thị ngắn gọn cho sidebar/compact areas
     */
    public function getSubscriptionShortName(): string
    {
        if ($this->isAdmin()) {
            return 'Admin';
        }

        $activeSubscriptions = $this->subscriptions()
            ->where('status', 'ACTIVE')
            ->where('ends_at', '>', now())
            ->with('servicePackage')
            ->get();

        if ($activeSubscriptions->isEmpty()) {
            return 'Chưa có';
        }

        if ($activeSubscriptions->count() === 1) {
            return $activeSubscriptions->first()->servicePackage->name;
        }

        $totalStreams = $activeSubscriptions->sum('servicePackage.max_streams');
        return "{$activeSubscriptions->count()} gói ({$totalStreams} streams)";
    }
}
