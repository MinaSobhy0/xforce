<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-step AI import wizard session — holds LLM proposals across
 * page loads. When status='materialized' the sample_rows and mapping
 * columns can be pruned by a cleanup command later; kept for now for
 * audit ("what did the AI propose for this import?").
 */
class PlatformAiImportSession extends Model
{
    use HasFactory;

    public const STATUS_PREVIEW = 'preview';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_MATERIALIZED = 'materialized';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'platform_ai_import_sessions';

    protected $fillable = [
        'list_id',
        'uploaded_by_user_id',
        'source_filename',
        'source_row_count',
        'sample_rows',
        'ai_mapping',
        'ai_normalizations',
        'ai_flagged_rows',
        'ai_model',
        'ai_tokens_input',
        'ai_tokens_output',
        'ai_cost_usd_cents',
        'status',
        'materialized_count',
    ];

    protected $casts = [
        'sample_rows' => 'array',
        'ai_mapping' => 'array',
        'ai_normalizations' => 'array',
        'ai_flagged_rows' => 'array',
        'source_row_count' => 'integer',
        'ai_tokens_input' => 'integer',
        'ai_tokens_output' => 'integer',
        'ai_cost_usd_cents' => 'integer',
        'materialized_count' => 'integer',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(PlatformEmailList::class, 'list_id');
    }
}
