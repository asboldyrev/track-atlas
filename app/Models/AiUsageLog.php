<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $fillable = [
        'provider', 'model', 'track_ids', 'prompt_tokens',
        'completion_tokens', 'cost_usd', 'outcome',
        'error_code', 'error_message',
    ];

    protected function casts(): array
    {
        return ['track_ids' => 'array', 'cost_usd' => 'decimal:8'];
    }
}
