<?php

return [
    'datetime_format' => 'd/m/Y H:i:s',
    'date_format' => 'd/m/Y',
    'redacted_placeholder' => '[REDACTED]',

    'authorization' => [
        'strict' => true,
        'sensitive_ability' => 'viewSensitiveData',
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
                \MrAdder\FilamentLogger\Support\ActivityEvents::FORCE_DELETED,
                \MrAdder\FilamentLogger\Support\ActivityEvents::FAILED_LOGIN,
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
    ],

    'dashboard' => [
        'enabled' => true,
        'lookback_days' => 30,
        'top_limit' => 5,
    ],

    'activity_playbooks' => \MrAdder\FilamentLogger\Support\ActivityReviewPlaybookManager::DEFAULT_PLAYBOOKS,

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
                'events' => ['Deleted', \MrAdder\FilamentLogger\Support\ActivityEvents::FORCE_DELETED],
            ],
            'auth_issues' => [
                'label' => 'Auth Issues',
                'icon' => 'heroicon-o-lock-closed',
                'log_names' => ['Access'],
                'events' => [\MrAdder\FilamentLogger\Support\ActivityEvents::FAILED_LOGIN, 'Lockout'],
            ],
            'failed_logins' => [
                'label' => 'Failed Logins',
                'icon' => 'heroicon-o-exclamation-triangle',
                'log_names' => ['Access'],
                'events' => [\MrAdder\FilamentLogger\Support\ActivityEvents::FAILED_LOGIN],
                'date_preset' => 'last_7_days',
            ],
            'destructive_recent' => [
                'label' => 'Recent Destructive',
                'icon' => 'heroicon-o-fire',
                'events' => ['Deleted', \MrAdder\FilamentLogger\Support\ActivityEvents::FORCE_DELETED],
                'date_preset' => 'last_7_days',
            ],
            'auth_anomalies' => [
                'label' => 'Auth Anomalies',
                'icon' => 'heroicon-o-finger-print',
                'log_names' => ['Access'],
                'events' => [\MrAdder\FilamentLogger\Support\ActivityEvents::FAILED_LOGIN, 'Lockout', 'Two Factor Recovery'],
                'date_preset' => 'last_30_days',
            ],
        ],
    ],

    'alerts' => [
        'enabled' => false,
        'cache_store' => null,
        'default_channels' => ['mail'],
        'mail' => [
            'to' => [],
        ],
        'slack' => [
            'webhook_url' => null,
        ],
        'discord' => [
            'webhook_url' => null,
        ],
        'rules' => [
            'destructive_activity' => [
                'enabled' => true,
                'label' => 'Destructive activity detected',
                'channels' => ['mail', 'slack', 'discord'],
                'events' => ['Deleted', \MrAdder\FilamentLogger\Support\ActivityEvents::FORCE_DELETED],
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
                'events' => [\MrAdder\FilamentLogger\Support\ActivityEvents::FAILED_LOGIN],
                'threshold' => 5,
                'window_minutes' => 10,
            ],
        ],
    ],

    'custom_events' => [
        'default_log_name' => 'Custom',
        'color' => 'primary',
    ],

    'activity_resource' => \MrAdder\FilamentLogger\Resources\ActivityResource::class,
    'scoped_to_tenant' => true,
    'navigation_sort' => null,

    'resources' => [
        'enabled' => true,
        'log_name' => 'Resource',
        'logger' => \MrAdder\FilamentLogger\Loggers\ResourceLogger::class,
        'color' => 'success',
        
        'exclude' => [
            //App\Filament\Resources\UserResource::class,
        ],
        'ignore' => [
            'updated_at',
            'remember_token',
        ],
        'ignore_for_models' => [
            //App\Models\User::class => ['last_seen_at', 'login_count'],
        ],
        'ignore_for_resources' => [
            //App\Filament\Resources\UserResource::class => ['last_seen_at', 'login_count'],
        ],
        'cluster' => null,
        'navigation_group' =>'Settings',
    ],

    'access' => [
        'enabled' => true,
        'logger' => \MrAdder\FilamentLogger\Loggers\AccessLogger::class,
        'color' => 'danger',
        'log_name' => 'Access',
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
        'logger' => \MrAdder\FilamentLogger\Loggers\NotificationLogger::class,
        'color' => null,
        'log_name' => 'Notification',
        'log_recipient' => false,
        'mask_recipient' => true,
    ],

    'models' => [
        'enabled' => true,
        'log_name' => 'Model',
        'color' => 'warning',
        'logger' => \MrAdder\FilamentLogger\Loggers\ModelLogger::class,
        'ignore' => [
            'updated_at',
            'remember_token',
        ],
        'ignore_for' => [
            //App\Models\User::class => ['last_seen_at', 'login_count'],
        ],
        'register' => [
            //App\Models\User::class,
        ],
    ],

    'custom' => [
        // [
        //     'log_name' => 'Custom',
        //     'color' => 'primary',
        // ]
    ],
];
