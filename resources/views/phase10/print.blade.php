<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title }} — Print</title>

        @vite(['resources/css/app.css'])
        @fluxAppearance

        <style>
            @page {
                size: letter portrait;
                margin: 0.5in;
            }

            html, body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            @media print {
                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                .no-print {
                    display: none !important;
                }
            }
        </style>
    </head>
    <body class="min-h-full bg-zinc-100 dark:bg-zinc-100">
        <div class="no-print sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-zinc-200 bg-white px-6 py-3">
            <a href="{{ $backUrl }}" class="text-sm font-semibold text-zinc-600 hover:text-zinc-900">
                ← Back to generator
            </a>
            <button
                type="button"
                onclick="window.print()"
                class="phase10-card-bg rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90"
            >
                Print / Save as PDF
            </button>
        </div>

        <div class="mx-auto flex max-w-2xl justify-center px-6 py-10">
            @if (count($phases))
                <x-phase10.card :phases="$phases" :title="$title" subtitle="{{ count($phases) }} phases" class="max-w-md" />
            @else
                <div class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center text-zinc-500">
                    No phases to print. Head back and generate a game first.
                </div>
            @endif
        </div>
    </body>
</html>
