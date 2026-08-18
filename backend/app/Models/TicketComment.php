<?php

namespace App\Models;

use App\Events\TicketCommentCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketComment extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Single source of truth for allowed attachment MIME types, shared by the
     * API validation rules and the Filament file upload component.
     *
     * @var array<int, string>
     */
    public const ATTACHMENT_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
        'application/zip',
    ];

    protected $fillable = [
        'ticket_id',
        'user_id',
        'content',
        'is_internal',
        'attachments',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'attachments' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Broadcast a comment event to the ticket owner and staff, but never leak
     * internal (staff-only) comments to customers.
     */
    protected static function booted(): void
    {
        static::created(function (self $comment): void {
            if (! $comment->is_internal) {
                event(new TicketCommentCreated($comment));
            }
        });
    }

    /**
     * Absolute URLs for every stored attachment, tolerant of both the API shape
     * (['name', 'path', 'size']) and plain relative paths from Filament.
     *
     * @return array<int, array{name: string, url: string, size: int|null}>
     */
    public function attachmentUrls(): array
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return collect($this->attachments ?? [])
            ->map(function (string|array $attachment) use ($disk): ?array {
                $path = is_string($attachment) ? $attachment : ($attachment['path'] ?? null);

                if ($path === null || $path === '') {
                    return null;
                }

                return [
                    'name' => is_array($attachment) ? ($attachment['name'] ?? basename($path)) : basename($path),
                    'url' => $disk->url($path),
                    'size' => is_array($attachment)
                        ? ($attachment['size'] ?? null)
                        : $disk->size($path),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Sanitize a client-supplied file name before it is stored or displayed.
     */
    public static function sanitizeFilename(string $filename): string
    {
        $name = basename(str_replace('\\', '/', $filename));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = Str::limit($name, 120, '');

        return trim($name) !== '' ? $name : 'attachment';
    }
}
