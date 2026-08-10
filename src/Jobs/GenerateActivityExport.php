<?php

namespace MrAdder\FilamentLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use MrAdder\FilamentLogger\Support\ActivityExportCriteria;
use MrAdder\FilamentLogger\Support\ActivityExporter;
use MrAdder\FilamentLogger\Support\ActivityExportNotifier;
use RuntimeException;
use Spatie\Activitylog\ActivitylogServiceProvider;

/**
 * Builds an activity export on the queue and writes it to the configured disk,
 * then notifies the user who asked for it.
 *
 * The query is rebuilt from serialized criteria rather than carried through the
 * queue, because an Eloquent builder cannot be serialized.
 */
class GenerateActivityExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<int, string>|null  $columns
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $format,
        public array $criteria = [],
        public ?array $columns = null,
        public array $metadata = [],
        public mixed $userId = null,
        public ?string $userClass = null,
    ) {}

    public function handle(ActivityExporter $exporter, ActivityExportNotifier $notifier): void
    {
        $disk = (string) config('filament-logger.exports.queue.disk', 'local');
        $directory = trim((string) config('filament-logger.exports.queue.path', 'filament-logger/exports'), '/');

        // Scoping by user keeps one person's export out of another's reach even
        // if a signed URL is shared.
        $path = $directory.'/'.$this->ownerSegment().'/'.$this->fileName();

        $handle = fopen('php://temp', 'w+b');

        if (! is_resource($handle)) {
            throw new RuntimeException('Unable to open a temporary stream for the activity export.');
        }

        try {
            $rows = $exporter->writeTo(
                $handle,
                $this->format,
                ActivityExportCriteria::fromArray($this->criteria)->apply($this->baseQuery()),
                $this->columns,
                $this->metadata,
            );

            rewind($handle);

            Storage::disk($disk)->writeStream($path, $handle);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $notifier->exportReady(
            userId: $this->userId,
            userClass: $this->userClass,
            disk: $disk,
            path: $path,
            rows: $rows,
        );
    }

    public function failed(?\Throwable $exception): void
    {
        app(ActivityExportNotifier::class)->exportFailed(
            userId: $this->userId,
            userClass: $this->userClass,
            reason: $exception?->getMessage(),
        );
    }

    /**
     * @return Builder<Model>
     */
    protected function baseQuery()
    {
        $model = ActivitylogServiceProvider::determineActivityModel();

        return $model::query()->latest('created_at');
    }

    protected function ownerSegment(): string
    {
        return filled($this->userId) ? (string) $this->userId : 'shared';
    }

    protected function fileName(): string
    {
        return 'activity-export-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.'.$this->format;
    }
}
