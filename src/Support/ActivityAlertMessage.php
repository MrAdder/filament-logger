<?php

namespace MrAdder\FilamentLogger\Support;

use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/**
 * Renders the title and body of an alert.
 *
 * A rule may supply `title` and `message` templates containing `:placeholder`
 * tokens. When it does not, the built-in wording is used, so existing configs
 * keep producing exactly the same alerts.
 */
final class ActivityAlertMessage
{
    /**
     * @param  array<string, string>  $replacements
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $replacements,
    ) {}

    /**
     * @param  array<string, mixed>  $rule
     */
    public static function for(
        string $ruleName,
        array $rule,
        ActivityContract $activity,
        ?string $risk = null,
        ?int $count = null,
    ): self {
        $replacements = self::replacements($ruleName, $activity, $risk, $count) + [
            ':window' => (string) data_get($rule, 'digest_minutes', 60),
            ':threshold' => (string) data_get($rule, 'threshold', '-'),
        ];

        $title = self::render(
            data_get($rule, 'title') ?? data_get($rule, 'label') ?? Str::headline($ruleName),
            $replacements,
        );

        $template = data_get($rule, 'message');

        $body = is_string($template) && filled($template)
            ? self::render($template, $replacements)
            : self::defaultBody($replacements);

        return new self($title, $body, $replacements);
    }

    /**
     * Title and body joined, for channels that carry a single text field.
     */
    public function toText(): string
    {
        return implode("\n", array_filter([$this->title, $this->body]));
    }

    /**
     * The body split into lines, for channels that render line by line.
     *
     * @return array<int, string>
     */
    public function lines(): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $this->body))));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected static function render(string $template, array $replacements): string
    {
        return trim(strtr($template, $replacements));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected static function defaultBody(array $replacements): string
    {
        return implode("\n", array_filter([
            $replacements[':description'],
            'Event: '.$replacements[':event'],
            'Log: '.$replacements[':log_name'],
            'Risk: '.$replacements[':risk'],
            'Subject: '.$replacements[':subject'],
            'Causer: '.$replacements[':causer'],
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected static function replacements(
        string $ruleName,
        ActivityContract $activity,
        ?string $risk,
        ?int $count,
    ): array {
        $subjectType = data_get($activity, 'subject_type');
        $subjectId = data_get($activity, 'subject_id');
        $causerType = data_get($activity, 'causer_type');
        $causerId = data_get($activity, 'causer_id');
        $reasons = data_get($activity, 'properties.risk_reasons', []);

        if (is_object($reasons) && method_exists($reasons, 'toArray')) {
            $reasons = $reasons->toArray();
        }

        $loggedAt = data_get($activity, 'created_at');

        return [
            ':rule' => Str::headline($ruleName),
            ':event' => (string) (data_get($activity, 'event') ?? '-'),
            ':log_name' => (string) (data_get($activity, 'log_name') ?? '-'),
            ':description' => (string) (data_get($activity, 'description') ?? ''),
            ':risk' => (string) ($risk ?? '-'),
            ':risk_reasons' => is_array($reasons) && $reasons !== []
                ? implode(', ', array_map(fn (mixed $reason): string => (string) $reason, $reasons))
                : '-',
            ':subject' => $subjectType
                ? class_basename((string) $subjectType).' #'.$subjectId
                : 'None',
            ':subject_type' => (string) ($subjectType ?? '-'),
            ':subject_id' => (string) ($subjectId ?? '-'),
            ':causer' => $causerType
                ? class_basename((string) $causerType).' #'.$causerId
                : 'Anonymous',
            ':causer_type' => (string) ($causerType ?? '-'),
            ':causer_id' => (string) ($causerId ?? '-'),
            ':logged_at' => $loggedAt ? (string) $loggedAt : '-',
            ':count' => (string) ($count ?? 1),
        ];
    }
}
