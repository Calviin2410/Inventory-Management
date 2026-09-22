<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'actor_name',
        'actor_email',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(
        Request $request,
        string $action,
        string $subjectType,
        ?int $subjectId,
        ?string $subjectLabel,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): self {
        $actor = $request->user();

        return self::create([
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            // Do not rely on the database server's local CURRENT_TIMESTAMP.
            // API dates are serialized as UTC and converted to Malaysia time by the UI.
            'created_at' => now(),
        ]);
    }
}
