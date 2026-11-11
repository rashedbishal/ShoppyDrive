<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CronHealth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * OPS-002: Admin > System Monitor — whether cron and the queue are running,
 * readable by anyone who can already see Failed Jobs (the same section the
 * dashboard's queue banner is gated by).
 */
class SystemMonitorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/monitor/index', [
            'monitor' => CronHealth::snapshot(),
        ]);
    }
}
