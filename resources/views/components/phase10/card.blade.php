@props([
    'phases' => [],
    'title' => 'Phase 10',
    'subtitle' => null,
    'showBands' => false,
    'size' => 'md',
])

@php
    $maxW      = match($size) { 'xl' => 'max-w-4xl', 'lg' => 'max-w-2xl', default => 'max-w-md' };
    $padding   = match($size) { 'xl' => 'px-5 pt-6 pb-5 sm:px-10 sm:pt-10 sm:pb-8', 'lg' => 'px-8 pt-8 pb-7', default => 'px-7 pt-7 pb-6' };
    $titleSize = match($size) { 'xl' => 'text-3xl sm:text-5xl', 'lg' => 'text-4xl', default => 'text-3xl' };
    $subSize   = match($size) { 'xl' => 'text-xs sm:text-sm', 'lg' => 'text-sm', default => 'text-xs' };
    $spacing   = match($size) { 'xl' => 'space-y-2.5 sm:space-y-5', 'lg' => 'space-y-3.5', default => 'space-y-2.5' };
    $itemSize  = match($size) { 'xl' => 'text-[16px] sm:text-[22px]', 'lg' => 'text-[18px]', default => 'text-[15px]' };
    $numW      = match($size) { 'xl' => 'w-6 sm:w-9', 'lg' => 'w-7', default => 'w-6' };
    $badgeSize = match($size) { 'xl' => 'text-[10px] sm:text-[13px]', 'lg' => 'text-[11px]', default => 'text-[10px]' };
    $stripe    = match($size) { 'xl' => 'h-2.5 sm:h-4', 'lg' => 'h-3.5', default => 'h-2.5' };
@endphp

<div {{ $attributes->class("phase10-card-bg relative mx-auto w-full {$maxW} overflow-hidden rounded-2xl text-white shadow-xl ring-1 ring-black/10") }}>
    <div class="{{ $padding }}">
        <div class="flex items-end justify-between">
            <div class="{{ $titleSize }} leading-none font-black tracking-tight">
                {{ \Illuminate\Support\Str::before($title, '10') }}<span class="text-phase-yellow">10</span>
            </div>
            @if ($subtitle)
                <div class="text-right {{ $subSize }} font-medium text-white/60">{{ $subtitle }}</div>
            @endif
        </div>

        <ol class="mt-5 {{ $spacing }}">
            @foreach ($phases as $i => $phase)
                <li class="flex items-baseline gap-2.5 {{ $itemSize }} leading-snug">
                    <span class="{{ $numW }} shrink-0 text-right font-bold text-white/70 tabular-nums">{{ $i + 1 }}.</span>
                    <span class="font-semibold">{{ $phase->label }}</span>
                    @if ($showBands)
                        <span class="ml-auto shrink-0 self-center rounded-full px-2 py-0.5 {{ $badgeSize }} font-bold tracking-wide uppercase text-white/90 ring-1 ring-white/25">
                            {{ $phase->band->label() }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>

    <div class="phase10-stripe {{ $stripe }} w-full"></div>
</div>
