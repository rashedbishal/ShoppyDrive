<?php

declare(strict_types=1);

namespace App\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Shared Audit Trail wiring for admin-managed models: logs every fillable
 * attribute change under the 'admin' log name, so `Admin\AuditTrailController`
 * has one consistent log to query across every CRUD-managed model. Models
 * only need to add `use Auditable;` and, where the defaults don't fit,
 * override `auditExcept()`/`auditLabel()`.
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('admin')
            ->logFillable()
            ->logOnlyDirty()
            ->logExcept($this->auditExcept())
            ->dontLogIfAttributesChangedOnly($this->auditIgnoreWhenOnlyChanged())
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => sprintf(
                '%s %s: %s',
                class_basename($this),
                $eventName,
                $this->auditLabel(),
            ));
    }

    /**
     * Fillable attributes never written to the audit trail — secrets,
     * hashes, or anything else too sensitive/noisy to log. Timestamps are
     * excluded by default since they're redundant with the log's own
     * created_at. Override per-model for anything else that needs hiding.
     *
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return ['created_at', 'updated_at'];
    }

    /**
     * Fillable attributes that, when they're the ONLY thing that changed,
     * should suppress the entry entirely — for columns a non-admin process
     * updates in the ordinary course of business (e.g. a coupon's
     * `used_count` ticking up at checkout) rather than an admin edit. If an
     * admin edit changes one of these alongside something else, the entry
     * still logs normally with both changes included.
     *
     * @return array<int, string>
     */
    protected function auditIgnoreWhenOnlyChanged(): array
    {
        return [];
    }

    /**
     * Human-readable identifier for this record shown in the audit trail,
     * e.g. "Product updated: Men's Classic Polo". Override when `name`/
     * `title` isn't the right field.
     */
    protected function auditLabel(): string
    {
        return (string) ($this->name ?? $this->title ?? $this->getKey());
    }
}
