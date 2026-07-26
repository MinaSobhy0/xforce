<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Backup extends Model
{

    protected $connection = 'central';

    protected $table = 'public.backups';

    protected $fillable = [
        'name',
        'type',
        'disk',
        'path',
        'filename',
        'size',
        'status',
        'tenant_id',
        'created_by',
        'notes',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const TYPES = [
        'full' => 'Full Backup',
        'database' => 'Database Only',
        'files' => 'Files Only',
        'tenant' => 'Tenant Backup',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'created_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        $seconds = $this->started_at->diffInSeconds($this->completed_at);

        if ($seconds >= 3600) {
            return gmdate('H:i:s', $seconds);
        } elseif ($seconds >= 60) {
            return gmdate('i:s', $seconds);
        }

        return $seconds . 's';
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->status !== 'completed' || !$this->path) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->temporaryUrl(
                $this->path,
                now()->addMinutes(30)
            );
        } catch (\Exception $e) {
            // Local storage doesn't support temporary URLs
            return route('admin.backups.download', $this->id);
        }
    }

    public function exists(): bool
    {
        if (!$this->path) {
            return false;
        }

        return Storage::disk($this->disk)->exists($this->path);
    }

    public function delete(): bool
    {
        // Delete the file first. System backups point at a directory
        // (backups/system/{id} with dump + manifest) — Storage::delete()
        // fails silently on those, so they need deleteDirectory().
        if ($this->path) {
            $disk = Storage::disk($this->disk);

            if ($disk->directoryExists($this->path)) {
                $disk->deleteDirectory($this->path);
            } elseif ($disk->exists($this->path)) {
                $disk->delete($this->path);
            }

            // Retention applies off-site too: drop the Backblaze mirror of
            // this backup (best-effort, queued — the record is gone next).
            if (\App\Services\BackblazeService::isConfigured()) {
                \App\Jobs\DeleteBackupFromBackblaze::dispatch(
                    \App\Services\BackblazeService::remotePathFor($this->path)
                );
            }
        }

        return parent::delete();
    }

    public function markRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(int $size, string $path): void
    {
        $this->update([
            'status' => 'completed',
            'size' => $size,
            'path' => $path,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }
}
