@php
    $record = $announcement ?? [];
    $legacyType = strtoupper((string) ($record['Audience_Type'] ?? ''));
    $audienceType = old('Audience_Type', in_array($legacyType, ['ALL_ROLES', 'ROLE'], true) ? $legacyType : 'ROLE');
    $targetRole = old('Target_Role', $legacyType === 'ALL_ROLES' ? '' : ($record['Target_Role'] ?? 'STUDENT'));
    if (in_array(strtoupper((string) $targetRole), ['', 'ALL_STUDENTS', 'CLASS'], true)) $targetRole = 'STUDENT';
    $legacyClass = $legacyType === 'CLASS' ? ($record['Target_ID'] ?? $record['Audience_ID'] ?? '') : '';
    $targetClass = old('Target_Class_ID', $record['Target_Class_ID'] ?? $legacyClass);
    $derivedBatch = collect($classes ?? [])->firstWhere('Class_ID', $targetClass)['Batch_ID'] ?? '';
    $targetBatch = old('Target_Batch_ID', $record['Target_Batch_ID'] ?? $derivedBatch);
    $dateValue = fn ($value) => $value ? rescue(fn () => \Carbon\Carbon::parse($value)->format('Y-m-d\TH:i'), '', false) : '';
@endphp
<div x-data="announcementAudience(@js($audienceType), @js($targetRole), @js($targetBatch), @js($targetClass))" class="px-6 md:px-12 py-6">
    <x-form-section title="Isi Pengumuman" description="Sampaikan informasi dengan ringkas, jelas, dan mudah dipahami.">
        <div class="sm:col-span-2"><x-input name="Title" label="Judul Pengumuman" required value="{{ old('Title', $record['Title'] ?? '') }}" placeholder="Contoh: Jadwal ujian akhir semester" /></div>
        <div class="sm:col-span-2"><x-textarea name="Message" label="Isi Pengumuman" rows="7" required placeholder="Tuliskan informasi lengkap di sini...">{{ old('Message', $record['Message'] ?? $record['Content'] ?? '') }}</x-textarea></div>
    </x-form-section>
    <div class="my-8 border-t border-slate-100"></div>
    <section aria-labelledby="audience-heading">
        <div class="mb-5"><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-600">Penerima</p><h2 id="audience-heading" class="mt-1 text-lg font-black text-slate-900">Tujuan Pengumuman</h2><p class="mt-1 text-sm text-slate-500">Pilih kelompok yang benar-benar perlu menerima informasi ini.</p></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="audience-choice" :class="audience === 'ALL_ROLES' && 'audience-choice--active'"><input class="sr-only" type="radio" name="Audience_Type" value="ALL_ROLES" x-model="audience" @change="clearChildren()"><span class="choice-mark" aria-hidden="true"></span><span><strong>Semua Role</strong><small>Untuk seluruh pengguna aktif WMS</small></span></label>
            <label class="audience-choice" :class="audience === 'ROLE' && 'audience-choice--active'"><input class="sr-only" type="radio" name="Audience_Type" value="ROLE" x-model="audience"><span class="choice-mark" aria-hidden="true"></span><span><strong>Role Tertentu</strong><small>Pilih satu kelompok penerima</small></span></label>
        </div>
        @error('Audience_Type')<p class="mt-2 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
        <div x-show="audience === 'ROLE'" x-cloak class="mt-6 rounded-2xl bg-slate-50 p-4 sm:p-5">
            <p class="mb-3 text-sm font-bold text-slate-800">Pilih role penerima</p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach($roles ?? [] as $roleOption)
                    <label class="role-choice" :class="role === @js($roleOption['value']) && 'role-choice--active'"><input class="sr-only" type="radio" name="Target_Role" value="{{ $roleOption['value'] }}" x-model="role" @change="roleChanged()"><span>{{ $roleOption['label'] }}</span></label>
                @endforeach
            </div>
            @error('Target_Role')<p class="mt-2 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
            <div x-show="role === 'STUDENT'" x-cloak class="mt-5 border-t border-slate-200 pt-5">
                <div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700" aria-hidden="true">🎓</span><div><h3 class="font-black text-slate-900">Target Siswa</h3><p class="text-xs text-slate-500">Persempit berdasarkan batch dan kelas bila diperlukan.</p></div></div>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="audience-choice bg-white" :class="studentScope === 'ALL' && 'audience-choice--active'"><input class="sr-only" type="radio" value="ALL" x-model="studentScope" @change="batch = ''; klass = ''"><span class="choice-mark"></span><span><strong>Semua Siswa</strong><small>Seluruh siswa aktif</small></span></label>
                    <label class="audience-choice bg-white" :class="studentScope === 'ACADEMIC' && 'audience-choice--active'"><input class="sr-only" type="radio" value="ACADEMIC" x-model="studentScope"><span class="choice-mark"></span><span><strong>Batch / Kelas</strong><small>Penerima akademik spesifik</small></span></label>
                </div>
                <div x-show="studentScope === 'ACADEMIC'" x-cloak class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label for="targetBatch" class="mb-1.5 block text-sm font-bold text-slate-700">Batch</label><select id="targetBatch" name="Target_Batch_ID" x-model="batch" @change="klass = ''" class="w-full rounded-xl border-slate-300 bg-white text-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="">Semua Batch</option>@foreach($batches ?? [] as $batchRow)<option value="{{ $batchRow['Batch_ID'] }}">{{ $batchRow['Batch_Name'] ?? $batchRow['Batch_Code'] ?? $batchRow['Batch_ID'] }}</option>@endforeach</select>@error('Target_Batch_ID')<p class="mt-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
                    <div x-show="batch" x-cloak><label for="targetClass" class="mb-1.5 block text-sm font-bold text-slate-700">Kelas</label><select id="targetClass" name="Target_Class_ID" x-model="klass" class="w-full rounded-xl border-slate-300 bg-white text-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="">Semua Kelas dalam Batch</option>@foreach($classes ?? [] as $class)<option value="{{ $class['Class_ID'] }}" x-show="batch === @js((string) ($class['Batch_ID'] ?? ''))" :disabled="batch !== @js((string) ($class['Batch_ID'] ?? ''))">{{ $class['Class_Name'] ?? $class['Class_Code'] ?? $class['Class_ID'] }}</option>@endforeach</select>@error('Target_Class_ID')<p class="mt-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror</div>
                </div>
            </div>
        </div>
    </section>
    <div class="my-8 border-t border-slate-100"></div>
    <x-form-section title="Publikasi" description="Atur prioritas dan periode tayang dalam waktu Indonesia Barat.">
        <div><x-select name="Priority" label="Prioritas" required>@foreach(['NORMAL' => 'Informasi', 'IMPORTANT' => 'Penting', 'URGENT' => 'Mendesak'] as $value => $label)<option value="{{ $value }}" @selected(old('Priority', $record['Priority'] ?? 'NORMAL') === $value)>{{ $label }}</option>@endforeach</x-select></div>
        <div><x-select name="Status" label="Status" required>@foreach(['PUBLISHED' => 'Terpublikasi', 'DRAFT' => 'Draf', 'INACTIVE' => 'Nonaktif'] as $value => $label)<option value="{{ $value }}" @selected(old('Status', $record['Status'] ?? 'PUBLISHED') === $value)>{{ $label }}</option>@endforeach</x-select></div>
        <div><x-input type="datetime-local" name="Start_At" label="Mulai Tayang (WIB)" value="{{ old('Start_At', $dateValue($record['Start_At'] ?? $record['Publish_Date'] ?? null)) }}" /><p class="mt-1 text-xs text-slate-500">Kosongkan untuk tayang segera.</p></div>
        <div><x-input type="datetime-local" name="Expires_At" label="Berakhir (WIB)" required value="{{ old('Expires_At', $dateValue($record['Expires_At'] ?? $record['Expired_Date'] ?? null)) }}" /></div>
    </x-form-section>
</div>
@once
<style>[x-cloak]{display:none!important}.audience-choice{display:flex;min-height:82px;cursor:pointer;align-items:center;gap:.8rem;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem;transition:.18s ease}.audience-choice:hover{border-color:#6ee7b7;background:#f8fffc}.audience-choice--active{border-color:#10b981!important;background:#ecfdf5!important;box-shadow:0 0 0 3px rgba(16,185,129,.09)}.audience-choice strong,.audience-choice small{display:block}.audience-choice strong{font-size:.875rem;color:#0f172a}.audience-choice small{margin-top:.2rem;font-size:.75rem;color:#64748b}.choice-mark{width:1.15rem;height:1.15rem;flex:none;border:2px solid #cbd5e1;border-radius:999px}.audience-choice--active .choice-mark{border:5px solid #10b981}.role-choice{cursor:pointer;border:1px solid #e2e8f0;border-radius:.75rem;background:#fff;padding:.75rem;text-align:center;font-size:.78rem;font-weight:800;color:#475569;transition:.18s}.role-choice:hover{border-color:#6ee7b7}.role-choice--active{border-color:#10b981;background:#059669;color:#fff;box-shadow:0 5px 14px rgba(5,150,105,.16)}</style>
<script>function announcementAudience(audience,role,batch,klass){return{audience,role,batch,klass,studentScope:batch||klass?'ACADEMIC':'ALL',clearChildren(){this.role='';this.batch='';this.klass='';this.studentScope='ALL'},roleChanged(){if(this.role!=='STUDENT'){this.batch='';this.klass='';this.studentScope='ALL'}}}}</script>
@endonce
