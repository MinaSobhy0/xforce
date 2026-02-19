<?php

namespace XLinic\Framework\Core\Model\Traits;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

trait HasAttachments
{
    use InteractsWithMedia;

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->singleFile();

        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp'
            ]);

        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        $this->addMediaCollection('documents')
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain'
            ]);
    }

    /**
     * Add attachment to specific collection.
     */
    public function addAttachment(UploadedFile|string $file, string $collection = 'attachments', array $properties = []): Media
    {
        $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
        $tenantId = $tenantManager->getCurrentTenantId();

        $mediaAdder = $this->addMedia($file)->toMediaCollection($collection);

        if ($tenantId) {
            $mediaAdder->withCustomProperties(array_merge($properties, ['tenant_id' => $tenantId]));
        }

        return $mediaAdder;
    }

    /**
     * Add image with automatic thumbnail generation.
     */
    public function addImage(UploadedFile|string $file, array $properties = []): Media
    {
        return $this->addAttachment($file, 'images', $properties);
    }

    /**
     * Add document.
     */
    public function addDocument(UploadedFile|string $file, array $properties = []): Media
    {
        return $this->addAttachment($file, 'documents', $properties);
    }

    /**
     * Get attachments by collection.
     */
    public function getAttachments(string $collection = 'attachments'): Collection
    {
        return $this->getMedia($collection);
    }

    /**
     * Get all images.
     */
    public function getImages(): Collection
    {
        return $this->getMedia('images');
    }

    /**
     * Get all documents.
     */
    public function getDocuments(): Collection
    {
        return $this->getMedia('documents');
    }

    /**
     * Get first image or default.
     */
    public function getFirstImage(?string $default = null): ?string
    {
        $media = $this->getFirstMedia('images');
        return $media ? $media->getUrl() : $default;
    }

    /**
     * Get first image thumbnail.
     */
    public function getFirstImageThumbnail(?string $default = null): ?string
    {
        $media = $this->getFirstMedia('images');
        return $media ? $media->getUrl('thumb') : $default;
    }

    /**
     * Check if model has attachments.
     */
    public function hasAttachments(string $collection = 'attachments'): bool
    {
        return $this->getMedia($collection)->isNotEmpty();
    }

    /**
     * Check if model has images.
     */
    public function hasImages(): bool
    {
        return $this->hasAttachments('images');
    }

    /**
     * Check if model has documents.
     */
    public function hasDocuments(): bool
    {
        return $this->hasAttachments('documents');
    }

    /**
     * Get attachment URLs.
     */
    public function getAttachmentUrls(string $collection = 'attachments'): array
    {
        return $this->getMedia($collection)
            ->map(fn(Media $media) => [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getUrl(),
                'created_at' => $media->created_at,
            ])
            ->toArray();
    }

    /**
     * Get image URLs with thumbnails.
     */
    public function getImageUrls(): array
    {
        return $this->getMedia('images')
            ->map(fn(Media $media) => [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'url' => $media->getUrl(),
                'thumb' => $media->getUrl('thumb'),
                'created_at' => $media->created_at,
            ])
            ->toArray();
    }

    /**
     * Remove attachment by ID.
     */
    public function removeAttachment(int $mediaId): bool
    {
        $media = $this->media()->find($mediaId);

        if (!$media) {
            return false;
        }

        $media->delete();
        return true;
    }

    /**
     * Clear all attachments from collection.
     */
    public function clearAttachments(string $collection = 'attachments'): void
    {
        $this->clearMediaCollection($collection);
    }

    /**
     * Get total attachment size in bytes.
     */
    public function getTotalAttachmentSize(string $collection = 'attachments'): int
    {
        return $this->getMedia($collection)->sum('size');
    }

    /**
     * Get total attachment size formatted.
     */
    public function getTotalAttachmentSizeFormatted(string $collection = 'attachments'): string
    {
        $bytes = $this->getTotalAttachmentSize($collection);

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Get attachment statistics.
     */
    public function getAttachmentStats(): array
    {
        $allMedia = $this->media;

        return [
            'total' => $allMedia->count(),
            'images' => $this->getImages()->count(),
            'documents' => $this->getDocuments()->count(),
            'total_size' => $allMedia->sum('size'),
            'total_size_formatted' => $this->formatBytes($allMedia->sum('size')),
            'by_mime_type' => $allMedia->groupBy('mime_type')
                ->map(fn($group) => $group->count())
                ->toArray(),
        ];
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Bulk upload attachments.
     */
    public function bulkUploadAttachments(array $files, string $collection = 'attachments', array $properties = []): Collection
    {
        $uploaded = collect();

        foreach ($files as $file) {
            $uploaded->push($this->addAttachment($file, $collection, $properties));
        }

        return $uploaded;
    }

    /**
     * Get attachments grouped by date.
     */
    public function getAttachmentsGroupedByDate(string $collection = 'attachments', string $format = 'Y-m-d'): Collection
    {
        return $this->getMedia($collection)
            ->groupBy(fn(Media $media) => $media->created_at->format($format));
    }

    /**
     * Search attachments by name.
     */
    public function searchAttachments(string $search, string $collection = 'attachments'): Collection
    {
        return $this->getMedia($collection)
            ->filter(fn(Media $media) =>
                str_contains(strtolower($media->name), strtolower($search)) ||
                str_contains(strtolower($media->file_name), strtolower($search))
            );
    }
}