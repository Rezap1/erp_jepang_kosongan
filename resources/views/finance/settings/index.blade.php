@extends('layouts.app')
@section('header', 'Pengaturan Finance')
@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-page-header title="Pengaturan Finance" description="Kelola konfigurasi operasional modul keuangan." :breadcrumbs="['Dasbor' => route('dashboard.finance'), 'Pengaturan Finance' => '#']" />

    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl p-4 text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 text-rose-700 border border-rose-200 rounded-xl p-4 text-sm font-bold">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Module Header -->
    <div class="bg-emerald-900/90 text-white rounded-2xl p-4 md:p-5 border border-emerald-800 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center font-black text-lg shrink-0">💰</div>
            <div>
                <h3 class="font-bold text-sm text-white">Pengaturan Modul Keuangan</h3>
                <p class="text-xs text-emerald-300/70">Pengaturan tagihan, pembayaran, rekening bank, dan parameter keuangan lainnya.</p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2">
        @foreach($tabs as $key => $tab)
            <a href="{{ route('finance.settings.index', ['tab' => $key]) }}"
               class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 min-h-[44px] {{ $activeTab == $key ? 'bg-slate-900 text-emerald-400 shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:border-emerald-300' }}">
                <span>{{ $tab['icon'] }}</span>
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>

    <!-- Settings Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 md:p-6 border-b border-slate-100">
            <h2 class="text-lg font-bold text-slate-800">{{ $tabs[$activeTab]['icon'] ?? '💰' }} {{ $tabs[$activeTab]['label'] ?? $activeTab }}</h2>
            <p class="text-xs text-slate-500 mt-1">Kelola parameter {{ strtolower($tabs[$activeTab]['label'] ?? $activeTab) }}.</p>
        </div>

        <form action="{{ route('finance.settings.update') }}" method="POST" class="p-4 md:p-6">
            @csrf
            <input type="hidden" name="active_tab" value="{{ $activeTab }}">

            @if($settings->count() > 0)
            <div class="space-y-4 mb-8">
                @foreach($settings as $s)
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 md:gap-4 py-4 border-b border-slate-100 last:border-0 hover:bg-slate-50/60 px-3 rounded-2xl transition-colors">
                        <div class="w-full md:w-5/12">
                            <label class="font-extrabold text-slate-800 text-sm block tracking-tight">{{ $s['Setting_Name'] }}</label>
                            <span class="text-xs text-slate-500 font-medium block mt-0.5 leading-relaxed">{{ $s['Description'] ?? '' }}</span>
                            @if(!empty($s['Setting_Key']))
                                <span class="text-[10px] font-mono text-slate-400 block mt-1.5 bg-slate-100 px-2 py-0.5 rounded-md w-fit border border-slate-200 font-semibold">{{ $s['Setting_Key'] }}</span>
                            @endif
                        </div>
                        <div class="w-full md:w-7/12">
                            @if(($s['Value_Type'] ?? 'text') == 'boolean')
                                <select name="settings[{{ $s['Setting_ID'] }}]" class="w-full rounded-xl border-2 border-slate-300 hover:border-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 bg-white text-slate-900 text-xs font-bold p-3 min-h-[46px] shadow-sm transition-all outline-none cursor-pointer">
                                    <option value="true" {{ $s['Setting_Value'] == 'true' ? 'selected' : '' }}>Aktif</option>
                                    <option value="false" {{ $s['Setting_Value'] == 'false' ? 'selected' : '' }}>Nonaktif</option>
                                </select>
                            @else
                                <input type="{{ ($s['Value_Type'] ?? 'text') == 'number' ? 'number' : 'text' }}" name="settings[{{ $s['Setting_ID'] }}]" value="{{ $s['Setting_Value'] }}" class="w-full rounded-xl border-2 border-slate-300 hover:border-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 bg-white text-slate-900 text-xs font-semibold p-3 min-h-[46px] shadow-sm transition-all outline-none placeholder:text-slate-400">
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            @if($parameters->count() > 0)
            <div class="space-y-4">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-100 pb-2">Parameter</h3>
                @foreach($parameters as $p)
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 md:gap-4 py-3 border-b border-slate-100 last:border-0 hover:bg-slate-50/60 px-3 rounded-2xl transition-colors">
                        <div class="w-full md:w-5/12">
                            <label class="font-extrabold text-slate-800 text-sm block tracking-tight">{{ str_replace('_', ' ', $p['Parameter_Key']) }}</label>
                            <span class="text-xs text-slate-500 font-medium block mt-0.5 leading-relaxed">{{ $p['Description'] ?? '' }}</span>
                        </div>
                        <div class="w-full md:w-7/12">
                            <input type="text" name="parameters[{{ $p['Parameter_ID'] }}]" value="{{ $p['Parameter_Value'] }}" class="w-full rounded-xl border-2 border-slate-300 hover:border-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 bg-white text-emerald-950 text-xs font-mono font-bold p-3 min-h-[46px] shadow-sm transition-all outline-none">
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            @if($settings->count() == 0 && $parameters->count() == 0)
            <div class="text-center py-12">
                <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-emerald-400 flex items-center justify-center text-2xl mx-auto mb-3">💰</div>
                <h3 class="text-slate-600 font-bold text-sm">Tidak Ada Pengaturan</h3>
                <p class="text-xs text-slate-400 mt-1">Konfigurasi bawaan keuangan berjalan otomatis.</p>
            </div>
            @else
            <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end sticky bottom-0 bg-white pb-2">
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-md transition-all flex items-center gap-2 min-h-[44px]">
                    💾 <span>Simpan Perubahan</span>
                </button>
            </div>
            @endif
        </form>
    </div>
</div>
@endsection
