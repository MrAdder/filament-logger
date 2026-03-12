<?php

if (! defined('FILAMENT_LOGGER_KA_ACTIVITY_LOG')) {
    define('FILAMENT_LOGGER_KA_ACTIVITY_LOG', 'საქმიანობის ჟურნალი');
}

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    'nav.group' => 'პარამეტრები',
    'nav.log.label' => FILAMENT_LOGGER_KA_ACTIVITY_LOG,
    'nav.log.icon' => 'heroicon-o-clipboard-document-list',
    'resource.label.log' => FILAMENT_LOGGER_KA_ACTIVITY_LOG,
    'resource.label.logs' => FILAMENT_LOGGER_KA_ACTIVITY_LOG,
    'resource.label.user' => 'მომხმარებელი',
    'resource.label.subject' => 'საგანი',
    'resource.label.subject_type' => 'საგნის ტიპი',
    'resource.label.description' => 'აღწერილობა',
    'resource.label.type' => 'ტიპი',
    'resource.label.event' => 'ქმედება',
    'resource.label.logged_at' => 'ავტორიზირებული როგორც',
    'resource.label.properties' => 'Თვისებები',
    'resource.label.old' => 'ძველი',
    'resource.label.new' => 'ახალი',
    'resource.label.old_value' => 'ძველი მნიშვნელობა',
    'resource.label.new_value' => 'ახალი მნიშვნელობა',
];
