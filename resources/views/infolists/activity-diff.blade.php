@php
    $rows = $getState()['rows'] ?? [];
    $metadata = $getState()['metadata'] ?? [];
    $groupedRows = collect($rows)->groupBy('group');
@endphp

@once
    <style>
        .fi-in-activity-diff {
            display: grid;
            gap: 1rem;
        }

        .fi-in-activity-diff-card {
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.72);
        }

        .dark .fi-in-activity-diff-card {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(17, 24, 39, 0.55);
        }

        .fi-in-activity-diff-grid {
            display: grid;
        }

        .fi-in-activity-diff-row {
            display: grid;
            grid-template-columns: minmax(11rem, 1fr) minmax(0, 1.4fr) minmax(0, 1.4fr);
        }

        .fi-in-activity-diff-group {
            grid-column: 1 / -1;
            padding: 0.65rem 1rem;
            border-top: 1px solid rgba(148, 163, 184, 0.16);
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: rgba(248, 250, 252, 0.75);
        }

        .dark .fi-in-activity-diff-group {
            border-top-color: rgba(255, 255, 255, 0.08);
            color: #cbd5e1;
            background: rgba(15, 23, 42, 0.6);
        }

        .fi-in-activity-diff-row + .fi-in-activity-diff-row {
            border-top: 1px solid rgba(148, 163, 184, 0.16);
        }

        .dark .fi-in-activity-diff-row + .fi-in-activity-diff-row {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fi-in-activity-diff-cell {
            padding: 0.9rem 1rem;
            min-width: 0;
        }

        .fi-in-activity-diff-cell pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.875rem;
            line-height: 1.55;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        .fi-in-activity-diff-head .fi-in-activity-diff-cell {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .fi-in-activity-diff-field-head {
            color: #64748b;
            background: rgba(248, 250, 252, 0.9);
        }

        .fi-in-activity-diff-old-head {
            color: #b45309;
            background: rgba(254, 243, 199, 0.85);
        }

        .fi-in-activity-diff-new-head {
            color: #047857;
            background: rgba(209, 250, 229, 0.88);
        }

        .dark .fi-in-activity-diff-field-head {
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.05);
        }

        .dark .fi-in-activity-diff-old-head {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.14);
        }

        .dark .fi-in-activity-diff-new-head {
            color: #a7f3d0;
            background: rgba(16, 185, 129, 0.14);
        }

        .fi-in-activity-diff-field {
            background: rgba(248, 250, 252, 0.72);
            color: #334155;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        .fi-in-activity-diff-old,
        .fi-in-activity-diff-new,
        .fi-in-activity-diff-metadata-value {
            color: #111827;
        }

        .fi-in-activity-diff-empty {
            color: #94a3b8;
        }

        .dark .fi-in-activity-diff-field {
            background: rgba(2, 6, 23, 0.36);
            color: #e2e8f0;
        }

        .dark .fi-in-activity-diff-old,
        .dark .fi-in-activity-diff-new,
        .dark .fi-in-activity-diff-metadata-value {
            color: #f8fafc;
        }

        .dark .fi-in-activity-diff-empty {
            color: #94a3b8;
        }

        .fi-in-activity-diff-summary {
            cursor: pointer;
            color: #475569;
            font-size: 0.875rem;
        }

        .fi-in-activity-diff-summary-meta {
            margin-left: 0.4rem;
            color: #94a3b8;
            font-size: 0.75rem;
        }

        .dark .fi-in-activity-diff-summary {
            color: #cbd5e1;
        }

        .fi-in-activity-diff-summary::marker {
            color: #94a3b8;
        }

        .fi-in-activity-diff-code {
            margin-top: 0.75rem;
            padding: 0.9rem 1rem;
            overflow-x: auto;
            border-radius: 0.85rem;
            background: #0f172a;
            color: #f8fafc;
        }

        .fi-in-activity-diff-path-parent {
            display: block;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .fi-in-activity-diff-path-leaf {
            display: block;
            font-size: 0.875rem;
            font-weight: 700;
        }

        .dark .fi-in-activity-diff-path-parent {
            color: #94a3b8;
        }

        .fi-in-activity-diff-empty-state {
            padding: 1rem;
            color: #94a3b8;
            font-size: 0.925rem;
        }

        .fi-in-activity-diff-metadata-row {
            display: grid;
            grid-template-columns: minmax(11rem, 1fr) minmax(0, 2fr);
        }

        .fi-in-activity-diff-metadata-row + .fi-in-activity-diff-metadata-row {
            border-top: 1px solid rgba(148, 163, 184, 0.16);
        }

        .dark .fi-in-activity-diff-metadata-row + .fi-in-activity-diff-metadata-row {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fi-in-activity-diff-metadata-key {
            padding: 0.9rem 1rem;
            color: #475569;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        .fi-in-activity-diff-metadata-value {
            padding: 0.9rem 1rem;
        }

        .fi-in-activity-diff-card-header {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .dark .fi-in-activity-diff-card-header {
            border-bottom-color: rgba(255, 255, 255, 0.08);
            color: #cbd5e1;
        }

        @media (max-width: 900px) {
            .fi-in-activity-diff-row,
            .fi-in-activity-diff-metadata-row {
                grid-template-columns: 1fr;
            }

            .fi-in-activity-diff-head {
                display: none;
            }

            .fi-in-activity-diff-cell,
            .fi-in-activity-diff-metadata-key,
            .fi-in-activity-diff-metadata-value {
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }

            .fi-in-activity-diff-cell::before,
            .fi-in-activity-diff-metadata-value::before {
                content: attr(data-label);
                display: block;
                margin-bottom: 0.35rem;
                color: #64748b;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .dark .fi-in-activity-diff-cell::before,
            .dark .fi-in-activity-diff-metadata-value::before {
                color: #cbd5e1;
            }

            .fi-in-activity-diff-metadata-key {
                display: none;
            }
        }
    </style>
@endonce

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div
        {{
            $attributes
                ->merge($getExtraAttributes(), escape: false)
                ->class(['fi-in-activity-diff'])
        }}
    >
        <div class="fi-in-activity-diff-card">
            <div class="fi-in-activity-diff-grid">
                <div class="fi-in-activity-diff-row fi-in-activity-diff-head">
                    <div class="fi-in-activity-diff-cell fi-in-activity-diff-field-head">
                        {{ __('Field') }}
                    </div>

                    <div class="fi-in-activity-diff-cell fi-in-activity-diff-old-head">
                        {{ __('filament-logger::filament-logger.resource.label.old') }}
                    </div>

                    <div class="fi-in-activity-diff-cell fi-in-activity-diff-new-head">
                        {{ __('filament-logger::filament-logger.resource.label.new') }}
                    </div>
                </div>

                @forelse ($groupedRows as $group => $rowsInGroup)
                    @if ($groupedRows->count() > 1)
                        <div class="fi-in-activity-diff-group">
                            {{ \Illuminate\Support\Str::headline((string) $group) }}
                        </div>
                    @endif

                    @foreach ($rowsInGroup as $row)
                        <div class="fi-in-activity-diff-row">
                            <div
                                class="fi-in-activity-diff-cell fi-in-activity-diff-field"
                                data-label="{{ __('Field') }}"
                            >
                                @if ($row['is_nested'] ?? false)
                                    <span class="fi-in-activity-diff-path-parent">{{ \Illuminate\Support\Str::beforeLast($row['field'], '.') }}</span>
                                    <span class="fi-in-activity-diff-path-leaf">{{ \Illuminate\Support\Str::afterLast($row['field'], '.') }}</span>
                                @else
                                    {{ $row['field'] }}
                                @endif
                            </div>

                            @foreach (['old', 'new'] as $column)
                                @php($cell = $row[$column])

                                <div
                                    class="fi-in-activity-diff-cell fi-in-activity-diff-{{ $column }} {{ $cell['empty'] ? 'fi-in-activity-diff-empty' : '' }}"
                                    data-label="{{ __('filament-logger::filament-logger.resource.label.' . $column) }}"
                                >
                                    @if ($cell['expandable'])
                                        <details>
                                            <summary class="fi-in-activity-diff-summary">
                                                {{ $cell['summary'] }}
                                                <span class="fi-in-activity-diff-summary-meta">
                                                    {{ $cell['line_count'] ?? 1 }}L · {{ $cell['char_count'] ?? 0 }}C
                                                </span>
                                            </summary>

                                            <pre class="fi-in-activity-diff-code">{{ $cell['display'] }}</pre>
                                        </details>
                                    @else
                                        <pre>{{ $cell['display'] }}</pre>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @empty
                    <div class="fi-in-activity-diff-empty-state">
                        {{ __('No recorded field changes for this activity.') }}
                    </div>
                @endforelse
            </div>
        </div>

        @if (count($metadata) > 0)
            <div class="fi-in-activity-diff-card">
                <div class="fi-in-activity-diff-card-header">
                    {{ __('filament-logger::filament-logger.resource.label.properties') }}
                </div>

                <div class="fi-in-activity-diff-grid">
                    @foreach ($metadata as $item)
                        <div class="fi-in-activity-diff-metadata-row">
                            <div class="fi-in-activity-diff-metadata-key">
                                {{ $item['field'] }}
                            </div>

                            <div
                                class="fi-in-activity-diff-metadata-value {{ $item['value']['empty'] ? 'fi-in-activity-diff-empty' : '' }}"
                                data-label="{{ $item['field'] }}"
                            >
                                @if ($item['value']['expandable'])
                                    <details>
                                        <summary class="fi-in-activity-diff-summary">
                                            {{ $item['value']['summary'] }}
                                        </summary>

                                        <pre class="fi-in-activity-diff-code">{{ $item['value']['display'] }}</pre>
                                    </details>
                                @else
                                    <pre>{{ $item['value']['display'] }}</pre>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-dynamic-component>
