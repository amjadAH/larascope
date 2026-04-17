<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaraScope @hasSection('title')— @yield('title')@endif</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">

<nav class="bg-white border-b border-gray-200">
    <div class="max-w-screen-xl mx-auto px-6 py-4 flex items-center gap-3">
        <a href="{{ route('larascope.index') }}"
           class="flex items-center gap-2 text-indigo-600 font-bold text-lg tracking-tight hover:text-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            LaraScope
        </a>
    </div>
</nav>

<main class="max-w-screen-xl mx-auto px-6 py-8">
    @yield('content')
</main>

</body>
</html>
