<?php

namespace MrAdder\FilamentLogger\Commands;

use Illuminate\Console\Command;
use MrAdder\FilamentLogger\Support\ActivityAlertDigest;
use MrAdder\FilamentLogger\Support\ActivityAlertDispatcher;

class SendAlertDigestsCommand extends Command
{
    protected $signature = 'filament-logger:send-alert-digests
        {--force : Send every pending digest immediately, without waiting for its window to close}';

    protected $description = 'Release any activity alert digests whose window has closed.';

    public function handle(ActivityAlertDispatcher $dispatcher): int
    {
        if (! config('filament-logger.alerts.enabled', false)) {
            $this->components->warn('Alerts are disabled. Set filament-logger.alerts.enabled to true.');

            return self::SUCCESS;
        }

        if (! ActivityAlertDigest::hasDigestRules()) {
            $this->components->info('No alert rules are configured as digests. Nothing to send.');

            return self::SUCCESS;
        }

        $sent = $dispatcher->flushDigests(force: (bool) $this->option('force'));

        if ($sent === 0) {
            $this->components->info('No digests were due.');

            return self::SUCCESS;
        }

        $this->components->info($sent === 1
            ? 'Sent 1 alert digest.'
            : "Sent {$sent} alert digests.");

        return self::SUCCESS;
    }
}
