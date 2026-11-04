<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\UnprovenSocialVerificationAudit;
use Illuminate\Console\Command;

/**
 * FSA-09 legacy-account follow-up. Read-only: see
 * UnprovenSocialVerificationAudit's docblock for exactly what this finds
 * and why. Lists the affected accounts so the owner can decide, case by
 * case, whether to notify, force re-verification, or leave each one —
 * this command never acts on any of them itself.
 */
class AuditUnprovenSocialAccountsCommand extends Command
{
    protected $signature = 'audit:unproven-social-accounts {--summary : Aggregate counts only — never selects or prints name/email, for a context (e.g. production) where even seeing those must be avoided}';

    protected $description = 'Read-only: list accounts whose only email-verification evidence is a pre-fix Facebook login (FSA-09)';

    public function handle(): int
    {
        if ($this->option('summary')) {
            return $this->handleSummary();
        }

        $users = UnprovenSocialVerificationAudit::find();

        if ($users->isEmpty()) {
            $this->info('No accounts found whose only verification evidence is a Facebook login.');

            return self::SUCCESS;
        }

        $this->warn("{$users->count()} account(s) found — their only email-verification evidence is a pre-fix Facebook login. Review each one manually; this command changed nothing.");

        $this->table(
            ['ID', 'Name', 'Email', 'Verified At', 'Created At'],
            $users->map(fn ($user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->email_verified_at?->toDateTimeString(),
                $user->created_at?->toDateTimeString(),
            ]),
        );

        return self::SUCCESS;
    }

    private function handleSummary(): int
    {
        $summary = UnprovenSocialVerificationAudit::summarize();

        if ($summary['total'] === 0) {
            $this->info('No accounts found whose only verification evidence is a Facebook login.');

            return self::SUCCESS;
        }

        $this->warn("{$summary['total']} account(s) found — their only email-verification evidence is a pre-fix Facebook login. This command changed nothing and printed no name or email.");
        $this->line("Earliest created: {$summary['earliest_created_at']}");
        $this->line("Latest created: {$summary['latest_created_at']}");

        if ($summary['by_month'] !== []) {
            $this->table(
                ['Month', 'Count'],
                array_map(fn ($month, $count) => [$month, $count], array_keys($summary['by_month']), array_values($summary['by_month'])),
            );
        }

        return self::SUCCESS;
    }
}
