<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $companyProfile['company']['name'] ?? $brand['application']['name'] }}</title>
    <link rel="icon" type="image/png" href="{{ asset($brand['assets']['favicon']) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: {{ $themeTokens['primary'] ?? '#38BDF8' }};
            --color-secondary: {{ $themeTokens['secondary'] ?? '#0F172A' }};
            --color-sidebar-bg: {{ $themeTokens['sidebar_bg'] ?? '#111827' }};
            --color-sidebar-text: {{ $themeTokens['sidebar_text'] ?? '#94A3B8' }};
            --color-sidebar-active-bg: {{ $themeTokens['sidebar_active_bg'] ?? '#1E293B' }};
            --color-sidebar-active: {{ $themeTokens['sidebar_active'] ?? '#38BDF8' }};
            --color-topbar-bg: {{ $themeTokens['topbar_bg'] ?? '#FFFFFF' }};
            --color-page-bg: {{ $themeTokens['page_bg'] ?? '#E2E8F0' }};
            --color-card-bg: {{ $themeTokens['card_bg'] ?? '#FFFFFF' }};
        }
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        
        /* Dark Scrollbar for Sidebar */
        .dark-scrollbar::-webkit-scrollbar {
            width: 5px;
        }
        .dark-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .dark-scrollbar::-webkit-scrollbar-thumb {
            background-color: #334155; /* slate-700 */
            border-radius: 20px;
        }
        .dark-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: #475569; /* slate-600 */
        }
    </style>
    <script>
        // Force light mode to prevent white text on the forced light background
        document.documentElement.classList.remove('dark');
        localStorage.setItem('color-theme', 'light');
    </script>
</head>
<body style="background-color: var(--color-page-bg, #E2E8F0);" class="{{ strtoupper(trim($userRole ?? '')) === 'STUDENT' ? 'wms-student-shell' : '' }} text-slate-800 antialiased selection:bg-blue-600 selection:text-white">

    <div class="wms-shell flex min-h-screen lg:h-screen lg:overflow-hidden">
        
        <!-- Sidebar Overlay (Visible on mobile when sidebar is open) -->
        <div id="mobile-sidebar-overlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 hidden transition-opacity duration-300 opacity-0 lg:hidden" aria-hidden="true" onclick="closeMobileSidebar()"></div>

        <!-- Framework Component: Sidebar -->
        <x-dashboard.sidebar :userRole="$userRole ?? 'Unknown'" />

        <!-- Main Content -->
        <main class="main-content min-w-0 flex-1 flex flex-col relative z-0 bg-gray-300">
            
            <!-- Framework Component: Topbar -->
            <x-dashboard.topbar :userRole="$userRole ?? 'Unknown'">
                <x-slot:header>
                    @yield('header')
                </x-slot:header>
            </x-dashboard.topbar>

            <!-- Mobile Header -->
            <header class="wms-mobile-header lg:hidden sticky top-0 z-30 bg-white/95 backdrop-blur-xl border-b border-slate-200 shadow-sm px-3 py-2.5 sm:px-4 sm:py-3" data-mobile-app-header>
                <div class="flex items-center gap-3">
                    <button type="button"
                            onclick="toggleSidebar()"
                            aria-controls="sidebar"
                            aria-expanded="false"
                            aria-label="Buka navigasi lengkap"
                            class="wms-mobile-menu-trigger inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"></path>
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">{{ $userRole ?? 'WMS' }}</p>
                        <h1 class="line-clamp-2 text-sm font-extrabold leading-tight text-slate-900 sm:text-base" title="{{ strip_tags($__env->yieldContent('header', 'Dasbor')) }}">{!! strip_tags($__env->yieldContent('header', 'Dasbor')) !!}</h1>
                    </div>

                    @if(Route::has('notifications.index'))
                        @php
                            if (isset($unreadNotifications)) {
                                $mobileUnreadCount = (int) $unreadNotifications;
                            } else {
                                try {
                                    $mobileUnreadCount = app(\App\Services\Core\NotificationService::class)
                                        ->summarizeForUser(null, null, 1)['unreadCount'] ?? 0;
                                } catch (\Throwable) {
                                    $mobileUnreadCount = 0;
                                }
                            }
                        @endphp
                        <a href="{{ route('notifications.index') }}"
                           aria-label="Buka notifikasi"
                           class="relative inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            @if($mobileUnreadCount > 0)
                                <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-rose-500 px-1 text-[9px] font-black text-white">{{ $mobileUnreadCount > 99 ? '99+' : $mobileUnreadCount }}</span>
                            @endif
                        </a>
                    @endif

                    @if(Route::has('profile.index'))
                        <a href="{{ route('profile.index') }}" aria-label="Buka profil" class="shrink-0 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                            <x-user-avatar class="h-10 w-10" text-size="text-xs" />
                        </a>
                    @endif
                </div>
            </header>

            <!-- Content -->
            <div class="wms-page-content p-4 sm:p-5 md:p-6 lg:p-8 w-full max-w-[1600px] mx-auto" id="main-content">


                @yield('content')
            </div>
        </main>
    </div>

    <!-- Sidebar Toggle Logic -->
    <script>
        function toggleSidebar() {
            window.dispatchEvent(new CustomEvent('toggle-sidebar-mobile'));
        }

        function closeMobileSidebar() {
            window.dispatchEvent(new CustomEvent('close-sidebar-mobile'));
        }

        let sidebarOverlayTimer = null;

        document.addEventListener('wms-sidebar-mobile-state', function(event) {
            const overlay = document.getElementById('mobile-sidebar-overlay');
            const triggers = document.querySelectorAll('.wms-mobile-menu-trigger');
            const open = Boolean(event.detail && event.detail.open);

            triggers.forEach((trigger) => trigger.setAttribute('aria-expanded', open ? 'true' : 'false'));
            if (!overlay) return;

            clearTimeout(sidebarOverlayTimer);
            if (open) {
                overlay.classList.remove('hidden');
                window.requestAnimationFrame(() => overlay.classList.remove('opacity-0'));
            } else {
                overlay.classList.add('opacity-0');
                sidebarOverlayTimer = setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        });
        
        // Global Double-Submit Protection removed due to browser compatibility issues
    </script>
    
    <x-mobile-bottom-nav :userRole="$userRole ?? 'STUDENT'" />
    <x-floating-qr-button />
    <x-toast />
    <x-confirm-dialog />
    @stack('scripts')
</body>
</html>



