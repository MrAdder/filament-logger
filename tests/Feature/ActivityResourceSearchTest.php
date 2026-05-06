<?php

use Spatie\Activitylog\Models\Activity as ActivityModel;

it('finds activities by broad search terms', function () {
    // create two records with distinct searchable content
    $first = ActivityModel::create([
        'log_name' => 'default',
        'description' => 'Updated email address for user',
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'event' => 'updated',
        'properties' => ['tags' => ['email', 'user']],
    ]);

    $second = ActivityModel::create([
        'log_name' => 'default',
        'description' => 'Deleted order #42',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => 42,
        'event' => 'deleted',
        'properties' => ['tags' => ['order', 'delete']],
    ]);

    // replicate the search logic used by the resource filter
    $term = 'email';

    $results = ActivityModel::where(function ($q) use ($term) {
        $q->where('description', 'like', "%{$term}%")
            ->orWhere('subject_type', 'like', "%{$term}%")
            ->orWhere('properties', 'like', "%{$term}%")
            ->orWhereHas('causer', function ($q2) use ($term) {
                $q2->where('name', 'like', "%{$term}%");
            });
    })->get();

    expect($results->pluck('id'))->toContain($first->id)->and->not->toContain($second->id);
});
