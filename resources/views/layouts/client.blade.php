<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'פאנל לקוח') - {{ config('app.name') }}</title>

    <!-- RTL Support -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS with RTL -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Assistant', 'ui-sans-serif', 'system-ui'],
                    }
                }
            }
        }
    </script>
    
    <!-- Custom Styles -->
    <style>
        .rtl { direction: rtl; }
        .ltr { direction: ltr; }
        body { font-family: 'Assistant', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
    
    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-50 rtl">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <a href="{{ route('account.dashboard') }}" class="text-xl font-bold text-gray-900">
                            {{ config('app.name') }}
                        </a>
                    </div>
                    
                    <!-- Navigation Links -->
                    <div class="hidden md:flex items-center space-x-reverse space-x-8">
                        <a href="{{ route('account.dashboard') }}" 
                           class="{{ request()->routeIs('account.dashboard') ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium">
                            דשבורד
                        </a>
                        
                        <a href="{{ route('account.payments') }}" 
                           class="{{ request()->routeIs('account.payments*') ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium">
                            תשלומים
                        </a>
                        
                        <a href="{{ route('account.orders') }}" 
                           class="{{ request()->routeIs('account.orders*') ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium">
                            הזמנות
                        </a>
                        
                        <a href="{{ route('account.payment-methods') }}" 
                           class="{{ request()->routeIs('account.payment-methods*') ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium">
                            אמצעי תשלום
                        </a>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="relative">
                        <button onclick="toggleUserMenu()" 
                                class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900 focus:outline-none focus:text-gray-900">
                            <span>{{ auth()->user()->name }}</span>
                            <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                        
                        <div id="userMenu" class="hidden absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <a href="{{ route('account.profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                פרופיל
                            </a>
                            
                            <form method="POST" action="{{ route('logout') }}" class="block">
                                @csrf
                                <button type="submit" class="w-full text-right px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    התנתקות
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Heading -->
        @if (isset($header))
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
        @endif
        
        <!-- Flash Messages -->
        @if (session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
        @endif
        
        @if (session('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
        @endif
        
        <!-- Page Content -->
        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            @yield('content')
        </main>
    </div>
    
    <!-- Scripts -->
    <script>
        function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.classList.toggle('hidden');
        }
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('userMenu');
            const button = e.target.closest('button');
            
            if (!button || !button.onclick?.toString().includes('toggleUserMenu')) {
                menu.classList.add('hidden');
            }
        });
    </script>
    
    @stack('scripts')
</body>
</html>
