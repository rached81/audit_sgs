<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SGS Audit Stock</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
    <!-- Scripts -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>
        .loader {
            border-top-color: #3498db;
            -webkit-animation: spinner 1.5s linear infinite;
            animation: spinner 1.5s linear infinite;
        }
        @keyframes spinner {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="antialiased bg-gray-50 min-h-screen text-gray-800">
    <nav class="bg-green-700 text-white shadow-lg">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-7">
                    <div>
                        <!-- Website Logo -->
                        <a href="#" class="flex items-center py-4 px-2">
                            <span class="font-semibold text-lg tracking-tight">SGS Audit</span>
                        </a>
                    </div>
                    <!-- Primary Navbar items -->
                    <div class="hidden md:flex items-center space-x-1">
                        <a href="{{ route('import.index') }}" class="py-4 px-2 {{ request()->routeIs('import.index') ? 'border-b-4 border-yellow-400 font-semibold' : 'text-green-100 hover:text-white transition duration-300' }}">Importation</a>
                        <a href="{{ route('consultation.index') }}" class="py-4 px-2 {{ request()->routeIs('consultation.index') ? 'border-b-4 border-yellow-400 font-semibold' : 'text-green-100 hover:text-white transition duration-300' }}">Consultation</a>
                        <a href="{{ route('audit.index') }}" class="py-4 px-2 {{ request()->routeIs('audit.index') ? 'border-b-4 border-yellow-400 font-semibold' : 'text-green-100 hover:text-white transition duration-300' }}">Audit</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto py-10 px-4">
        @yield('content')
    </div>

    @yield('scripts')
</body>
</html>
