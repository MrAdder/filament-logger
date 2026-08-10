<?php

namespace MrAdder\FilamentLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use MrAdder\FilamentLogger\Exceptions\AlertWebhookFailed;

/**
 * Delivers an alert webhook off the request path so an unreachable Slack or
 * Discord endpoint cannot slow down — or fail — the action being audited.
 */
class SendActivityAlertWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $url,
        public array $payload,
        public int $timeout = 5,
        public array $headers = [],
    ) {}

    public function handle(): void
    {
        $response = Http::withHeaders($this->headers)
            ->timeout($this->timeout)
            ->post($this->url, $this->payload);

        if (! $response->successful()) {
            throw AlertWebhookFailed::status($this->url, $response->status());
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }
}
