@props([
    'title' => 'Selamat Datang',
    'name' => '',
])

<div class="relative overflow-hidden rounded-2xl bg-white text-slate-800 shadow-sm border border-gray-200 mb-6">
    <!-- Background Panorama & Fade Overlay -->
    <div class="absolute inset-0 z-0 flex justify-end">
        <div class="absolute inset-0 bg-gradient-to-r from-white via-white/80 to-transparent z-10 w-3/4 lg:w-2/3"></div>
        <img src="https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?q=80&w=2070&auto=format&fit=crop" alt="Japan Panorama" class="w-2/3 lg:w-1/2 h-full object-cover object-center opacity-90 mix-blend-multiply">
    </div>

    <!-- Banner Content -->
    <div class="relative z-10 p-8 md:p-10 max-w-3xl">
        <h2 class="text-2xl font-bold tracking-tight mb-4 text-slate-800">
            {{ $title }}{{ $name ? ', ' . $name : '' }}!
        </h2>
        <p class="text-slate-600 text-[15px] font-medium mb-3">
            Anda berhasil login ke <span class="text-emerald-600 font-bold uppercase">{{ $brand['application']['name'] }}</span>
        </p>
        <p class="text-slate-500 text-[14px] font-medium mb-0">
            Semangat bekerja dan terus memberikan yang terbaik.
        </p>
        <div class="flex flex-wrap gap-4 mt-6">
            {{ $slot }}
        </div>
    </div>
</div>



