<?php

use MrAdder\FilamentLogger\Loggers\AccessLogger;
use MrAdder\FilamentLogger\Loggers\ModelLogger;
use MrAdder\FilamentLogger\Loggers\NotificationLogger;
use MrAdder\FilamentLogger\Loggers\ResourceLogger;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use MrAdder\FilamentLogger\Support\ActivityEvents;
use MrAdder\FilamentLogger\Support\ActivityReviewPlaybookManager;

return [
    'datetime_format' => 'd/m/Y H:i:s',
    'date_format' => 'd/m/Y',
    'redacted_placeholder' => '[REDACTED]',

    'authorization' => [
        'strict' => true,
        'sensitive_ability' => 'viewSensitiveData',
    ],

    'performance' => [
        // Cache store used for filter option lists. Null uses the default store.
        'cache_store' => null,

        // Seconds to cache the distinct log name / subject type lists that back
        // the table filters. Each rebuild is a full scan of the activity table,
        // so raise this on large installs. Set to 0 to disable caching.
        'filter_options_cache_ttl' => 300,

        // Upper bound on how many distinct values are pulled for those lists.
        'filter_options_limit' => 200,
    ],

    'search' => [
        // The properties column is JSON and cannot use an index, so a LIKE scan
        // over it is the most expensive part of a broad search. Disable this on
        // large installs to keep search on the indexed columns only.
        'include_properties' => true,
    ],

    'sensitive_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'secret',
        'client_secret',
        'api_key',
        'private_key',
        'token',
        'api_token',
        'access_token',
        'refresh_token',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'recovery_codes',
    ],

    'diff' => [
        'collapse_after' => 120,
        'pretty_print_json' => true,
    ],

    'risk' => [
        'high' => [
            'events' => [
                'Deleted',
                ActivityEvents::FORCE_DELETED,
                ActivityEvents::FAILED_LOGIN,
                'Lockout',
            ],
            'change_keys' => [
                'role',
                'role_id',
                'roles',
                'permission',
                'permissions',
            ],
        ],

        'medium' => [
            'events' => [],
        ],

        // Additional heuristics, each producing a named risk reason that alert
        // rules can filter on. Match on changed attribute keys, on the event
        // name, or both. Set a heuristic to an empty array to disable it.
        'heuristics' => [
            'permission_change' => [
                'level' => 'high',
                'change_keys' => [
                    'ability',
                    'abilities',
                    'scope',
                    'scopes',
                    'is_admin',
                    'is_super_admin',
                ],
            ],

            'credential_change' => [
                'level' => 'high',
                'change_keys' => [
                    'password',
                    'email',
                    'username',
                ],
                'events' => [
                    'Password Reset',
                ],
            ],

            'two_factor_change' => [
                'level' => 'high',
                'change_keys' => [
                    'two_factor_secret',
                    'two_factor_recovery_codes',
                    'two_factor_confirmed_at',
                ],
                'events' => [
                    'Two Factor Recovery',
                ],
            ],

            'account_status_change' => [
                'level' => 'medium',
                'change_keys' => [
                    'status',
                    'active',
                    'is_active',
                    'blocked_at',
                    'banned_at',
                    'suspended_at',
                    'email_verified_at',
                ],
            ],
        ],
    ],

    'pruning' => [
        'days' => 365,
        'only' => [],
        'except' => [],
    ],

    'exports' => [
        'enabled' => true,
        'chunk_size' => 500,
        'columns' => [
            'id',
            'log_name',
            'event',
            'description',
            'subject_type',
            'subject_id',
            'causer_type',
            'causer_id',
            'causer_name',
            'risk',
            'tags',
            'properties',
            'created_at',
        ],
        'presets' => [],
        'db_presets_enabled' => false,
        'embed_metadata' => false,
        'manage_ability' => 'manageExportPresets',

        // Gate ability required to download an export. Exports bypass table
        // pagination, so this is the control that stops any user who can open
        // the resource from pulling the whole audit trail. Set to null to allow
        // every viewer of the resource to export.
        'ability' => 'exportActivity',

        // Queued exports. Large exports are slow and fragile to build inside a
        // request, so above `threshold` rows the export is handed to the queue
        // and the user is notified when the file is ready. Below it, the direct
        // download is used exactly as before.
        'queue' => [
            'enabled' => false,

            // Rows above which an export is queued. 0 queues every export and
            // skips the counting query entirely.
            'threshold' => 5000,

            'connection' => null,
            'name' => null,

            // Where generated files are written.
            'disk' => 'local',
            'path' => 'filament-logger/exports',

            // How the user is told the export is ready, and how a failure is
            // reported back to them.
            //   'mail'     - email containing the download link. The default,
            //                because it needs nothing beyond the mail config
            //                every Laravel app already has.
            //   'database' - Filament in-panel notification with a download
            //                action. Nicer in the panel, but requires the host
            //                app to have Laravel's `notifications` table.
            //   null       - no notification; the path is written to the log.
            'notify' => 'mail',

            // Registers the signed download route. Disable to serve the files
            // yourself from the configured disk.
            'routes' => true,
            'route_prefix' => 'filament-logger',
            'route_middleware' => ['web', 'signed'],

            // How long a download link stays valid.
            'link_minutes' => 1440,

            // Generated files older than this are removed by
            // `filament-logger:prune-exports`.
            'retention_days' => 7,
        ],
    ],

    'dashboard' => [
        'enabled' => true,
        'lookback_days' => 30,
        'top_limit' => 5,
    ],

    'activity_playbooks' => ActivityReviewPlaybookManager::DEFAULT_PLAYBOOKS,

    'activity_filters' => [
        'date_presets' => [
            'today' => 'Today',
            'last_24_hours' => 'Last 24 Hours',
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'this_month' => 'This Month',
        ],
        'saved' => [
            'all' => [
                'label' => 'All Activity',
                'icon' => 'heroicon-o-bars-3-bottom-left',
            ],
            'high_risk' => [
                'label' => 'High Risk',
                'icon' => 'heroicon-o-shield-exclamation',
                'risk' => ['high'],
            ],
            'destructive' => [
                'label' => 'Deletes',
                'icon' => 'heroicon-o-trash',
                'events' => ['Deleted', ActivityEvents::FORCE_DELETED],
            ],
            'auth_issues' => [
                'label' => 'Auth Issues',
                'icon' => 'heroicon-o-lock-closed',
                'log_names' => ['Access'],
                'events' => [ActivityEvents::FAILED_LOGIN, 'Lockout'],
            ],
            'failed_logins' => [
                'label' => 'Failed Logins',
                'icon' => 'heroicon-o-exclamation-triangle',
                'log_names' => ['Access'],
                'events' => [ActivityEvents::FAILED_LOGIN],
                'date_preset' => 'last_7_days',
            ],
            'destructive_recent' => [
                'label' => 'Recent Destructive',
                'icon' => 'heroicon-o-fire',
                'events' => ['Deleted', ActivityEvents::FORCE_DELETED],
                'date_preset' => 'last_7_days',
            ],
            'auth_anomalies' => [
                'label' => 'Auth Anomalies',
                'icon' => 'heroicon-o-finger-print',
                'log_names' => ['Access'],
                'events' => [ActivityEvents::FAILED_LOGIN, 'Lockout', 'Two Factor Recovery'],
                'date_preset' => 'last_30_days',
            ],
        ],
    ],

    'alerts' => [
        'enabled' => false,
        'cache_store' => null,
        'default_channels' => ['mail'],

        // Deliver alerts through the queue instead of inline. Recommended:
        // alerts fire from model observers, so a slow or unreachable webhook
        // otherwise blocks the action being audited.
        'queue' => false,
        'queue_connection' => null,
        'queue_name' => null,

        // Seconds to wait on a webhook before giving up.
        'webhook_timeout' => 5,
        'mail' => [
            'to' => [],
        ],
        'slack' => [
            'webhook_url' => null,
        ],
        'discord' => [
            'webhook_url' => null,
        ],

        // Generic webhook channel for services other than Slack and Discord.
        // Receives a structured JSON payload rather than a service-specific
        // shape. Add the 'webhook' channel to a rule to use it.
        'webhook' => [
            'url' => null,
            'headers' => [
                // 'Authorization' => 'Bearer ...',
            ],
        ],

        // How many activity ids a digest keeps as a sample. The reported count
        // is not capped by this.
        'digest_sample_size' => 25,

        'rules' => [
            'destructive_activity' => [
                'enabled' => true,
                'label' => 'Destructive activity detected',
                'channels' => ['mail', 'slack', 'discord'],
                'events' => ['Deleted', ActivityEvents::FORCE_DELETED],
            ],
            'role_changes' => [
                'enabled' => true,
                'label' => 'Role or permission change detected',
                'channels' => ['mail', 'slack', 'discord'],
                'risk_reasons' => ['role_change'],
            ],
            'failed_login_spike' => [
                'enabled' => true,
                'label' => 'Repeated failed login attempts detected',
                'channels' => ['mail', 'slack', 'discord'],
                'type' => 'threshold',
                'log_names' => ['Access'],
                'events' => [ActivityEvents::FAILED_LOGIN],
                'threshold' => 5,
                'window_minutes' => 10,
            ],

            // Example of the optional extras, disabled by default.
            //
            // 'title' and 'message' are templates. Available placeholders:
            //   :rule :event :log_name :description :risk :risk_reasons
            //   :subject :subject_type :subject_id
            //   :causer :causer_type :causer_id
            //   :logged_at :count :window :threshold
            //
            // 'digest' batches matching activity for :window minutes and sends
            // one alert. Schedule filament-logger:send-alert-digests to release
            // digests on time.
            //
            // 'account_changes' => [
            //     'enabled' => true,
            //     'channels' => ['webhook'],
            //     'risk_reasons' => ['credential_change', 'two_factor_change'],
            //     'title' => '[:risk] :rule on :subject',
            //     'message' => ':causer changed :subject at :logged_at (:risk_reasons)',
            //     'digest' => true,
            //     'digest_minutes' => 60,
            //     'digest_title' => ':count account changes in the last hour',
            // ],
        ],
    ],

    'custom_events' => [
        'default_log_name' => 'Custom',
        'color' => 'primary',
    ],

    'activity_resource' => ActivityResource::class,
    'scoped_to_tenant' => true,
    'navigation_sort' => null,

    'resources' => [
        'enabled' => true,
        'log_name' => 'Resource',
        'logger' => ResourceLogger::class,
        'color' => 'success',

        'exclude' => [
            // App\Filament\Resources\UserResource::class,
        ],
        'ignore' => [
            'updated_at',
            'remember_token',
        ],
        'ignore_for_models' => [
            // App\Models\User::class => ['last_seen_at', 'login_count'],
        ],
        'ignore_for_resources' => [
            // App\Filament\Resources\UserResource::class => ['last_seen_at', 'login_count'],
        ],
        'cluster' => null,
        'navigation_group' => 'Settings',
    ],

    'access' => [
        'enabled' => true,
        'logger' => AccessLogger::class,
        'color' => 'danger',
        'log_name' => 'Access',
        'guards' => null,
        'store_ip' => true,
        'anonymize_ip' => true,
        'redact_ip_for_unauthorized_viewers' => false,
        'store_user_agent' => true,
        'user_agent_max_length' => 255,
        'identifier_keys' => [
            'email',
            'username',
            'login',
        ],
        'events' => [
            'login' => true,
            'logout' => true,
            'failed' => true,
            'lockout' => true,
            'password_reset' => true,
            'two_factor_recovery' => true,
        ],
    ],

    'notifications' => [
        'enabled' => true,
        'logger' => NotificationLogger::class,
        'color' => null,
        'log_name' => 'Notification',
        'log_recipient' => false,
        'mask_recipient' => true,
    ],

    'models' => [
        'enabled' => true,
        'log_name' => 'Model',
        'color' => 'warning',
        'logger' => ModelLogger::class,
        'ignore' => [
            'updated_at',
            'remember_token',
        ],
        'ignore_for' => [
            // App\Models\User::class => ['last_seen_at', 'login_count'],
        ],
        'register' => [
            // App\Models\User::class,
        ],
    ],

    'custom' => [
        // [
        //     'log_name' => 'Custom',
        //     'color' => 'primary',
        // ]
    ],
];
