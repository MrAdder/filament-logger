<?php

namespace MrAdder\FilamentLogger\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportPreset extends Model
{
    use HasFactory;

    protected $table = 'export_presets';

    protected $casts = [
        'columns' => 'array',
        'filters' => 'array',
    ];

    protected $fillable = [
        'key',
        'label',
        'icon',
        'columns',
        'filters',
        'visibility',
        'created_by',
    ];
}
