<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaraScope @hasSection('title')— @yield('title')@endif</title>

    <script>
        // Runs before first paint so the stored theme never flashes.
        (function () {
            var storedTheme = null;

            try {
                storedTheme = localStorage.getItem('larascope-theme');
            } catch (error) {
                // Storage can be unavailable (private mode); fall back to the OS setting.
            }

            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            if (storedTheme === 'dark' || (storedTheme === null && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        ground:    'rgb(var(--ls-ground) / <alpha-value>)',
                        surface:   'rgb(var(--ls-surface) / <alpha-value>)',
                        raised:    'rgb(var(--ls-raised) / <alpha-value>)',
                        rule:      'rgb(var(--ls-rule) / <alpha-value>)',
                        ink:       'rgb(var(--ls-ink) / <alpha-value>)',
                        muted:     'rgb(var(--ls-muted) / <alpha-value>)',
                        signal:    'rgb(var(--ls-signal) / <alpha-value>)',
                        nominal:   'rgb(var(--ls-nominal) / <alpha-value>)',
                        attention: 'rgb(var(--ls-attention) / <alpha-value>)',
                        fault:     'rgb(var(--ls-fault) / <alpha-value>)',
                        info:      'rgb(var(--ls-info) / <alpha-value>)',
                    },
                },
            },
        };
    </script>

    <style>
        /*
         * Theme tokens as raw RGB triples so Tailwind can apply opacity
         * modifiers. One class name (bg-surface, text-muted) therefore works
         * in both themes without a dark: variant on every element.
         */
        :root {
            /* Native controls (selects, date pickers, scrollbars) follow this. */
            color-scheme: light;

            --ls-ground:    247 248 250;
            --ls-surface:   255 255 255;
            --ls-raised:    241 245 249;
            --ls-rule:      226 232 240;
            --ls-ink:        15  23  42;
            --ls-muted:     100 116 139;
            --ls-signal:     14 116 144;
            --ls-nominal:    21 128  61;
            --ls-attention: 180  83   9;
            --ls-fault:     185  28  28;
            --ls-info:        3 105 161;
        }

        .dark {
            color-scheme: dark;

            --ls-ground:     11  15  20;
            --ls-surface:    18  24  32;
            --ls-raised:     23  31  41;
            --ls-rule:       30  42  54;
            --ls-ink:       226 232 240;
            --ls-muted:     124 140 160;
            --ls-signal:     34 211 238;
            --ls-nominal:    74 222 128;
            --ls-attention: 251 191  36;
            --ls-fault:     248 113 113;
            --ls-info:       56 189 248;
        }

        :focus-visible {
            outline: 2px solid rgb(var(--ls-signal));
            outline-offset: 2px;
        }

        /* The disclosure marker is replaced by a rotating caret of our own. */
        summary::-webkit-details-marker { display: none; }
        summary { list-style: none; }
        details[open] .ls-caret { transform: rotate(90deg); }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="h-full bg-ground text-ink antialiased [font-variant-numeric:tabular-nums]">

<nav class="border-b border-rule bg-surface">
    <div class="max-w-screen-2xl mx-auto px-6 h-14 flex items-center justify-between gap-4">
        <a href="{{ route('larascope.index') }}"
           class="group flex items-center gap-2.5 font-mono text-sm font-semibold tracking-[0.18em] uppercase text-ink hover:text-signal transition-colors">
            {{-- A scope trace: the instrument the package is named for. --}}
            <svg viewBox="0 0 28 24" class="h-4 w-7 text-signal" fill="none" stroke="currentColor"
                 stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M0 12h6l3-7 4 14 3-9 2 2h10" />
            </svg>
            LaraScope
        </a>

        <button type="button" data-theme-toggle aria-label="Switch colour theme"
                class="h-8 w-8 grid place-items-center rounded border border-rule text-muted hover:text-ink hover:border-muted transition-colors">
            <svg class="h-4 w-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                 stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
            </svg>
            <svg class="h-4 w-4 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                 stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36l-1.41-1.41M7.05 7.05L5.64 5.64m12.72 0l-1.41 1.41M7.05 16.95l-1.41 1.41M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        </button>
    </div>
</nav>

<main class="max-w-screen-2xl mx-auto px-6 py-8">
    @yield('content')
</main>

<script>
    document.querySelector('[data-theme-toggle]').addEventListener('click', function () {
        var isDark = document.documentElement.classList.toggle('dark');

        try {
            localStorage.setItem('larascope-theme', isDark ? 'dark' : 'light');
        } catch (error) {
            // Theme still applies for this page view even if it cannot be stored.
        }
    });
</script>

</body>
</html>
