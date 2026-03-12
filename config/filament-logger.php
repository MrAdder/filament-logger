<?php
return [
    'datetime_format' => 'd/m/Y H:i:s',
    'date_format' => 'd/m/Y',
    'redacted_placeholder' => '[REDACTED]',

    'authorization' => [
        'strict' => true,
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
        'store_user_agent' => true,
        'user_agent_max_length' => 255,
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
