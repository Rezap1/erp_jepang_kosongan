@props([
    'name' => 'Pengguna',
    'url' => null,
    'class' => 'h-9 w-9',
    'textSize' => 'text-xs',
    'borderClass' => 'border-emerald-200',
    'loading' => 'lazy',
])

@php
    $avatarName = trim((string) $name) ?: 'Pengguna';
    $words = collect(preg_split('/\s+/u', $avatarName))->filter()->values();
    $initials = $words->count() >= 2
        ? mb_strtoupper(mb_substr($words->first(), 0, 1).mb_substr($words->last(), 0, 1))
        : mb_strtoupper(mb_substr((string) $words->first(), 0, 2));
    $initials = $initials !== '' ? $initials : 'US';
@endphp

@if($url)
    <span class="relative inline-flex shrink-0 {{ $class }}">
        <img
            src="{{ $url }}"
            alt="Foto profil {{ $avatarName }}"
            loading="{{ $loading }}"
            class="h-full w-full rounded-full border-2 {{ $borderClass }} object-cover shadow-sm"
            onerror="this.hidden=true; this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('flex');"
        >
        <span class="hidden h-full w-full items-center justify-center rounded-full border-2 {{ $borderClass }} bg-gradient-to-br from-slate-800 to-emerald-600 font-black text-white shadow-sm {{ $textSize }}" role="img" aria-label="Foto profil {{ $avatarName }} tidak tersedia">{{ $initials }}</span>
    </span>
@else
    <span class="inline-flex shrink-0 items-center justify-center rounded-full border-2 {{ $borderClass }} bg-gradient-to-br from-slate-800 to-emerald-600 font-black text-white shadow-sm {{ $class }} {{ $textSize }}" role="img" aria-label="Foto profil {{ $avatarName }} tidak tersedia">{{ $initials }}</span>
@endif
