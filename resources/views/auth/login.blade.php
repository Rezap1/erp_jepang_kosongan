<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - {{ $brand['application']['name'] }} v{{ $brand['application']['version'] }}</title>
    <link rel="icon" type="image/png" href="{{ asset($brand['assets']['favicon']) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: {{ $brand['colors']['primary'] }}; }
        body { font-family: 'Inter', sans-serif; }
        
        .master-bg {
            background-image: url('{{ asset($brand['assets']['login_background']) }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .bg-overlay {
            background: rgba(15, 23, 42, 0.4);
        }

        .glass-panel {
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        
        .glass-input-container {
            position: relative;
        }
        
        .glass-input {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border-radius: 0.5rem;
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 3.2rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        /* Fix Chrome Autofill turning inputs white */
        .glass-input:-webkit-autofill,
        .glass-input:-webkit-autofill:hover, 
        .glass-input:-webkit-autofill:focus, 
        .glass-input:-webkit-autofill:active {
            transition: background-color 5000s ease-in-out 0s;
            -webkit-text-fill-color: #ffffff !important;
        }
        
        .glass-input:focus {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-color: var(--brand-primary);
            outline: none;
            box-shadow: 0 0 0 1px var(--brand-primary);
        }
        
        .glass-input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }
        
        .input-icon {
            position: absolute;
            left: 1.1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #f1f5f9;
            width: 1.25rem;
            height: 1.25rem;
            z-index: 10;
            pointer-events: none;
        }
        
        .custom-checkbox {
            appearance: none;
            background-color: transparent;
            margin: 0;
            width: 1.1rem;
            height: 1.1rem;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 4px;
            display: grid;
            place-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .custom-checkbox::before {
            content: "";
            width: 0.6em;
            height: 0.6em;
            transform: scale(0);
            transition: 120ms transform ease-in-out;
            background-color: transparent;
            transform-origin: center;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
        }
        
        .custom-checkbox:checked {
            border-color: var(--brand-primary);
            background-color: transparent;
        }
        
        .custom-checkbox:checked::before {
            transform: scale(1);
            background-color: var(--brand-primary);
            box-shadow: inset 1em 1em var(--brand-primary);
        }
        
        .login-btn {
            background: linear-gradient(90deg, var(--brand-primary) 0%, {{ $brand['colors']['secondary'] }} 100%);
            color: white;
            font-weight: 600;
            border-radius: 0.4rem;
            padding: 0.8rem;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            opacity: 0.9;
        }

        .tracking-super-wide {
            letter-spacing: 0.4em;
        }

        .brand-accent { color: var(--brand-primary); }
    </style>
</head>
<body class="min-h-screen relative flex flex-col justify-between selection:text-white overflow-y-auto overflow-x-hidden text-white master-bg">
    
    <div class="absolute inset-0 bg-overlay z-0 pointer-events-none"></div>

    <!-- Top Widgets -->
    <div class="relative z-10 w-full p-8 md:p-10 flex justify-between items-start">
        <!-- Date Widget -->
        <div class="glass-panel px-6 py-4 rounded-[1.25rem] flex items-center gap-4 border border-white/20 shadow-lg">
            <svg class="w-8 h-8 brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <div class="leading-tight">
                <p id="live-date-main" class="text-base font-bold text-white tracking-wide"></p>
                <p id="live-date-sub" class="text-sm text-gray-300"></p>
            </div>
        </div>

        <!-- Time Widget -->
        <div class="glass-panel px-6 py-4 rounded-[1.25rem] flex items-center gap-4 border border-white/20 shadow-lg">
            <svg class="w-8 h-8 brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div class="leading-tight">
                <p id="live-time" class="text-lg font-bold text-white tracking-widest"></p>
                <p class="text-xs text-gray-300 tracking-widest mt-0.5">WIB</p>
            </div>
        </div>
    </div>

    <!-- Main Center Area -->
    <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-4 w-full h-full pb-8">
        
        <!-- Branding Header -->
        <div class="text-center mb-5 flex flex-col items-center mt-[-40px]">
            <div class="w-[140px] h-[140px] rounded-full border-[5px] flex items-center justify-center mb-4 bg-white p-1 shadow-lg" style="border-color: var(--brand-primary);">
                <img src="{{ $companyProfile['company']['logo_url'] ?? asset($brand['assets']['logo']) }}" alt="{{ $companyProfile['company']['name'] ?? $brand['organization']['name'] }}" class="w-full h-full object-contain rounded-full" onerror="this.src='{{ asset($brand['assets']['logo']) }}'">
            </div>
            
            <h1 class="text-4xl md:text-6xl leading-none font-bold tracking-wider text-white drop-shadow-lg mb-2">{{ $brand['application']['short_name'] }}</h1>
            <h2 class="text-sm md:text-lg brand-accent tracking-super-wide font-medium drop-shadow-md">{{ $companyProfile['company']['tagline'] ?? $brand['organization']['tagline'] }}</h2>
            
            <div class="flex items-center justify-center gap-4 mt-3 opacity-90">
                <div class="h-[1px] w-[60px] bg-white/50"></div>
                <span class="text-[13px] tracking-widest font-medium text-white">v {{ $brand['application']['version'] }}</span>
                <div class="h-[1px] w-[60px] bg-white/50"></div>
            </div>
        </div>

        <!-- THE LOGIN BOX -->
        <div class="w-full max-w-[480px] p-9 mt-2 rounded-[1.25rem] bg-[#0f172a]/70 backdrop-blur-xl border border-white/20 shadow-2xl">
            
            <div class="text-center mb-5">
                <p class="text-[13px] font-medium text-white tracking-wide mb-0.5">ようこそ、システムへログインしてください</p>
                <p class="text-[11.5px] text-gray-300 font-light">Selamat datang, silakan login ke sistem</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 p-3 rounded-lg bg-red-500/20 border border-red-500/50 text-red-200 text-sm flex items-start">
                    <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <ul class="list-disc pl-4 space-y-1 font-medium text-[12px]">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="login-form" method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                
                <div class="glass-input-container">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <input id="login" name="login" type="text" autocomplete="username" required value="{{ old('login') }}" class="glass-input" placeholder="Nama Pengguna atau Email">
                </div>

                <div class="glass-input-container">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="glass-input pr-10" placeholder="Kata Sandi">
                    <button type="button" onclick="togglePassword()" class="absolute right-[0.9rem] top-1/2 -translate-y-1/2 text-gray-300 hover:text-white cursor-pointer transition-colors focus:outline-none">
                        <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <div class="flex items-center gap-2">
                        <input id="remember-me" name="remember-me" type="checkbox" class="custom-checkbox">
                        <label for="remember-me" class="block text-[12.5px] text-gray-200 cursor-pointer">
                            Ingat Saya
                        </label>
                    </div>
                    <a href="#" class="text-[12.5px] brand-accent hover:text-white transition-colors">
                        Lupa Kata Sandi?
                    </a>
                </div>

                <button id="login-submit" type="submit" class="login-btn mt-2" aria-live="polite">
                    <svg id="login-submit-icon" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H2.25" />
                    </svg>
                    <svg id="login-submit-spinner" class="hidden w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span id="login-submit-label">MASUK</span>
                </button>
            </form>
        </div>

        <!-- Company Info Card -->
        <div class="glass-panel w-full max-w-[480px] px-6 py-3.5 mt-3 rounded-[0.8rem] flex items-center justify-center gap-4">
            <svg class="w-9 h-9 brand-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <div class="flex flex-col">
                <p class="text-[12.5px] font-medium tracking-widest text-white">{{ $companyProfile['company']['name'] ?? $brand['organization']['name'] }}</p>
                <p class="text-[11.5px] font-medium tracking-widest brand-accent mt-0.5">{{ $brand['application']['name'] }}</p>
                <p class="text-[11px] font-light text-gray-300 mt-0.5">日本語学校トレーニングセンター</p>
            </div>
        </div>
        
    </div>

    <!-- Floating Features Widget (Right side desktop) -->
    <div class="hidden xl:block absolute right-12 top-1/2 -translate-y-1/2 glass-panel p-8 rounded-[1.5rem] w-[320px] z-10 border border-white/20 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-6 pb-4 border-b border-white/20 tracking-wide text-center">システムの特徴<br><span class="text-sm brand-accent font-medium tracking-normal mt-1 block">Fitur Sistem</span></h3>
        <ul class="space-y-6">
            <li class="flex items-center gap-5">
                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center border border-white/10 flex-shrink-0">
                    <svg class="w-6 h-6 text-[#4fc3f7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <div class="flex flex-col">
                    <p class="text-base font-bold text-white mb-0.5 tracking-wide">安全なデータ</p>
                    <p class="text-sm text-gray-300 font-light">Keamanan Data Terjamin</p>
                </div>
            </li>
            <li class="flex items-center gap-5">
                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center border border-white/10 flex-shrink-0">
                    <svg class="w-6 h-6 text-[#4fc3f7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="flex flex-col">
                    <p class="text-base font-bold text-white mb-0.5 tracking-wide">リアルタイム</p>
                    <p class="text-sm text-gray-300 font-light">Sinkronisasi Real-time</p>
                </div>
            </li>
            <li class="flex items-center gap-5">
                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center border border-white/10 flex-shrink-0">
                    <svg class="w-6 h-6 text-[#4fc3f7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div class="flex flex-col">
                    <p class="text-base font-bold text-white mb-0.5 tracking-wide">簡単アクセス</p>
                    <p class="text-sm text-gray-300 font-light">Akses Cepat & Mudah</p>
                </div>
            </li>
            <li class="flex items-center gap-5">
                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center border border-white/10 flex-shrink-0">
                    <svg class="w-6 h-6 text-[#4fc3f7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <div class="flex flex-col">
                    <p class="text-base font-bold text-white mb-0.5 tracking-wide">効率的な管理</p>
                    <p class="text-sm text-gray-300 font-light">Manajemen Efisien</p>
                </div>
            </li>
        </ul>
    </div>

    <!-- Bottom Footer Bar -->
    <div class="relative z-10 w-full bg-transparent border-t border-white/15 mt-auto">
        <div class="w-full max-w-[1600px] mx-auto px-10 xl:px-16 py-5 flex flex-col xl:flex-row items-center justify-between text-xs">
            <div class="flex flex-wrap justify-center items-center gap-8 xl:gap-12">
                @if(!empty($companyProfile['company']['website']))
                <a href="{{ $companyProfile['company']['website'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 text-gray-200 hover:text-white transition-colors">
                    <svg class="w-[22px] h-[22px] text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                    <span class="tracking-wide text-[14px] font-normal">{{ $companyProfile['company']['website'] }}</span>
                </a>
                @endif
                
                @if(!empty($companyProfile['company']['email']))
                <a href="mailto:{{ $companyProfile['company']['email'] }}" class="flex items-center gap-3 text-gray-200 hover:text-white transition-colors">
                    <svg class="w-[22px] h-[22px] text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <span class="tracking-wide text-[14px] font-normal">{{ $companyProfile['company']['email'] }}</span>
                </a>
                @endif
                
                @if(!empty($companyProfile['company']['whatsapp']))
                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $companyProfile['company']['whatsapp']) }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 text-gray-200 hover:text-white transition-colors">
                    <svg class="w-[22px] h-[22px] text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 01-.923 1.785A5.969 5.969 0 006 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" fill="white"></path></svg>
                    <span class="tracking-wide text-[14px] font-normal">{{ $companyProfile['company']['whatsapp'] }}</span>
                </a>
                @endif
                
                @if(!empty($brand['social']['instagram']))
                <a href="{{ $brand['social']['instagram'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 text-gray-200 hover:text-white transition-colors">
                    <svg class="w-[22px] h-[22px] text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                    <span class="tracking-wide text-[14px] font-normal">Instagram</span>
                </a>
                @endif
                
                @if(!empty($brand['social']['tiktok']))
                <a href="{{ $brand['social']['tiktok'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 text-gray-200 hover:text-white transition-colors">
                    <svg class="w-[22px] h-[22px] text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path></svg>
                    <span class="tracking-wide text-[14px] font-normal">TikTok</span>
                </a>
                @endif
            </div>
            
            <div class="flex items-center gap-6 mt-4 md:mt-0">
                <div class="w-px h-6 bg-gray-600 hidden md:block"></div>
                <span class="text-gray-300 tracking-wide text-[13px] font-normal">&copy; {{ date('Y') }} {{ $companyProfile['company']['name'] ?? $brand['organization']['name'] }}</span>
                <div class="px-5 py-1.5 rounded-[1.25rem] border text-[12px] font-medium tracking-wide brand-accent bg-[#021021]" style="border-color: var(--brand-primary);">Version {{ $brand['application']['version'] }}</div>
            </div>
        </div>
    </div>

    <!-- Live Clock Script -->
    <script>
        function updateDateTime() {
            try {
                const now = new Date();
                
                // Format Time in WIB (Asia/Jakarta)
                const optionsTime = { timeZone: 'Asia/Jakarta', hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' };
                const timeStr = new Intl.DateTimeFormat('id-ID', optionsTime).format(now).replace(/\./g, ':');
                document.getElementById('live-time').innerText = timeStr;

                // Format Date in WIB (Asia/Jakarta)
                const optionsDate = { timeZone: 'Asia/Jakarta', year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' };
                const formatterDate = new Intl.DateTimeFormat('id-ID', optionsDate);
                const parts = formatterDate.formatToParts(now);
                
                let weekday = '', day = '', month = '', year = '';
                parts.forEach(p => {
                    if(p.type === 'weekday') weekday = p.value;
                    if(p.type === 'day') day = p.value;
                    if(p.type === 'month') month = p.value;
                    if(p.type === 'year') year = p.value;
                });
                
                weekday = weekday.replace(',', '');

                document.getElementById('live-date-main').innerText = `${day} ${month} ${year}`;
                document.getElementById('live-date-sub').innerText = weekday;
            } catch (e) {
                console.error("Live clock error:", e);
            }
        }
        
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
            }
        }

        // Prevent concurrent login POSTs. The server remains authoritative;
        // this only gives immediate feedback and avoids session-regeneration
        // races that can make a second stale request return HTTP 419.
        document.getElementById('login-form').addEventListener('submit', function (event) {
            const form = event.currentTarget;
            const button = document.getElementById('login-submit');
            const icon = document.getElementById('login-submit-icon');
            const spinner = document.getElementById('login-submit-spinner');
            const label = document.getElementById('login-submit-label');

            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            button.setAttribute('aria-busy', 'true');
            icon.classList.add('hidden');
            spinner.classList.remove('hidden');
            label.textContent = 'MEMPROSES...';
        });
        
        setInterval(updateDateTime, 1000);
        updateDateTime();
    </script>
</body>
</html>



