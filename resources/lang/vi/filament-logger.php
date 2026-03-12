<?php

if (! defined('FILAMENT_LOGGER_VI_ACTIVITY_LOG')) {
    define('FILAMENT_LOGGER_VI_ACTIVITY_LOG', 'Nhật ký hoạt động');
}

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    'nav.group' => 'Cài đặt',
    'nav.log.label' => FILAMENT_LOGGER_VI_ACTIVITY_LOG,
    'nav.log.icon' => 'heroicon-o-clipboard-document-list',
    'resource.label.log' => FILAMENT_LOGGER_VI_ACTIVITY_LOG,
    'resource.label.logs' => FILAMENT_LOGGER_VI_ACTIVITY_LOG,
    'resource.label.user' => 'Người dùng',
    'resource.label.subject' => 'Chủ đề',
    'resource.label.subject_type' => 'Loại chủ đề',
    'resource.label.description' => 'Mô tả',
    'resource.label.type' => 'Kiểu',
    'resource.label.event' => 'Sự kiện',
    'resource.label.logged_at' => 'Lúc',
    'resource.label.properties' => 'Thuộc tính',

];
