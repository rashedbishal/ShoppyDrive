<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PAY-RETURN-001: the mother admin's audited operational attestation that a
 * production release is live and old processes have been recycled — an
 * attestation, not a proof. Written only by
 * App\Services\CourierVerificationReadinessService, accepted or refused.
 */
class CourierVerificationReadiness extends Model
{
    use AppendOnly, HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REFUSED = 'refused';

    protected $table = 'courier_verification_readiness';

    protected $fillable = [
        'status',
        'refusal_reasons',
        'fingerprint',
        'local_fingerprint',
        'fingerprint_file_count',
        'deploy_run_id',
        'deploy_sha',
        'deploy_run_started_at',
        'deploy_run_completed_at',
        'config_cache_mtime',
        'route_cache_mtime',
        'web_restarted_at',
        'opcache_probe',
        'scheduler_marker',
        'process_list',
        'checklist',
        'admin_id',
        'admin_name',
        'admin_email',
        'note',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'refusal_reasons' => 'array',
            'opcache_probe' => 'array',
            'scheduler_marker' => 'array',
            'checklist' => 'array',
            'deploy_run_started_at' => 'datetime',
            'deploy_run_completed_at' => 'datetime',
            'web_restarted_at' => 'datetime',
            'config_cache_mtime' => 'integer',
            'route_cache_mtime' => 'integer',
        ];
    }
}
