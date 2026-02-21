<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class PasswordHistory extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'password_history';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'password_hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::orderedUuid()->toString();
            }

            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a password matches this history entry.
     */
    public function matches(string $password): bool
    {
        return Hash::check($password, $this->password_hash);
    }

    /**
     * Check if a password was used before for a user.
     */
    public static function wasUsedBefore(User $user, string $password, int $limit = 5): bool
    {
        $recentPasswords = static::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($recentPasswords as $history) {
            if ($history->matches($password)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record a password change.
     */
    public static function record(User $user, string $hashedPassword): self
    {
        return static::create([
            'user_id' => $user->id,
            'password_hash' => $hashedPassword,
        ]);
    }

    /**
     * Clean up old password history entries.
     */
    public static function cleanup(User $user, int $keep = 10): int
    {
        $idsToKeep = static::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($keep)
            ->pluck('id');

        return static::where('user_id', $user->id)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
