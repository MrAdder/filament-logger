<?php

namespace MrAdder\FilamentLogger\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string $label
 * @property string|null $icon
 * @property array<int, string> $columns
 * @property array<string, mixed>|null $filters
 */
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
