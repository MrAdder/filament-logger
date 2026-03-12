@php
    $rows = $getState()['rows'] ?? [];
    $metadata = $getState()['metadata'] ?? [];
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div
        {{
            $attributes
                ->merge($getExtraAttributes(), escape: false)
                ->class(['fi-in-activity-diff space-y-6'])
        }}
    >
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <div class="grid grid-cols-3 gap-px bg-gray-200 dark:bg-white/10">
                <div class="bg-gray-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/10 dark:text-gray-300">
                    {{ __('Field') }}
                </div>

                <div class="bg-amber-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-500/10 dark:text-amber-200">
                    {{ __('filament-logger::filament-logger.resource.label.old') }}
                </div>

                <div class="bg-emerald-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                    {{ __('filament-logger::filament-logger.resource.label.new') }}
                </div>
            </div>

            @forelse ($rows as $row)
                <div class="grid grid-cols-3 gap-px bg-gray-200 dark:bg-white/10">
                    <div class="bg-white px-4 py-3 font-mono text-sm text-gray-700 dark:bg-gray-950/40 dark:text-gray-200">
                        {{ $row['field'] }}
                    </div>

                    @foreach (['old', 'new'] as $column)
                        @php($cell = $row[$column])

                        <div class="bg-white px-4 py-3 dark:bg-gray-950/40">
                            @if ($cell['expandable'])
                                <details class="group">
                                    <summary class="cursor-pointer text-sm text-gray-600 marker:text-gray-400 dark:text-gray-300">
                                        {{ $cell['summary'] }}
                                    </summary>

                                    <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-950 px-4 py-3 text-xs leading-5 text-gray-100">{{ $cell['display'] }}</pre>
                                </details>
                            @else
                                <pre @class([
                                    'whitespace-pre-wrap break-words text-sm leading-6',
                                    'text-gray-400 dark:text-gray-500' => $cell['empty'],
                                    'text-gray-700 dark:text-gray-100' => ! $cell['empty'],
                                ])>{{ $cell['display'] }}</pre>
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-gray-400 dark:text-gray-500">
                    {{ __('No recorded field changes for this activity.') }}
                </div>
            @endforelse
        </div>

        @if (count($metadata) > 0)
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <div class="border-b border-gray-200 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-300">
                    {{ __('filament-logger::filament-logger.resource.label.properties') }}
                </div>

                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($metadata as $item)
                        <div class="grid grid-cols-2 gap-4 px-4 py-3">
                            <div class="font-mono text-sm text-gray-700 dark:text-gray-200">
                                {{ $item['field'] }}
                            </div>

                            <div>
                                @if ($item['value']['expandable'])
                                    <details class="group">
                                        <summary class="cursor-pointer text-sm text-gray-600 marker:text-gray-400 dark:text-gray-300">
                                            {{ $item['value']['summary'] }}
                                        </summary>

                                        <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-950 px-4 py-3 text-xs leading-5 text-gray-100">{{ $item['value']['display'] }}</pre>
                                    </details>
                                @else
                                    <pre class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-700 dark:text-gray-100">{{ $item['value']['display'] }}</pre>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-dynamic-component>
