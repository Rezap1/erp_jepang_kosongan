@php
    $record = $announcement ?? [];
    $state = $audienceState ?? ['audience' => 'SELECTED_ROLES', 'roles' => [], 'student_scope' => 'ALL_STUDENTS', 'batch' => '', 'classes' => []];
    $audienceType = strtoupper((string) old('Audience_Type', $state['audience'] ?? 'SELECTED_ROLES'));
    if ($audienceType === 'ROLE') $audienceType = 'SELECTED_ROLES';
    $selectedRoles = old('Target_Roles', $state['roles'] ?? []);
    $selectedRoles = is_array($selectedRoles) ? array_values($selectedRoles) : [];
    $studentScope = old('Student_Scope', $state['student_scope'] ?? 'ALL_STUDENTS') ?: 'ALL_STUDENTS';
    $targetBatch = old('Target_Batch_ID', $state['batch'] ?? '');
    $selectedClasses = old('Target_Class_IDs', $state['classes'] ?? []);
    $selectedClasses = is_array($selectedClasses) ? array_values($selectedClasses) : [];
    $dateValue = fn ($value) => $value ? rescue(fn () => \Carbon\Carbon::parse($value)->format('Y-m-d\TH:i'), '', false) : '';
@endphp
<div
    x-data="announcementAudience(
        @js($audienceType),
        @js($selectedRoles),
        @js($studentScope),
        @js((string) $targetBatch),
        @js($selectedClasses),
        @js($classesByBatch ?? []),
        @js((string) ($studentRoleId ?? ''))
    )"
    class="px-6 md:px-12 py-6"
>
    <x-form-section title="Isi Pengumuman" description="Sampaikan informasi dengan ringkas, jelas, dan mudah dipahami.">
        <div class="sm:col-span-2">
            <x-input name="Title" label="Judul Pengumuman" required value="{{ old('Title', $record['Title'] ?? '') }}" placeholder="Contoh: Jadwal ujian akhir semester" />
        </div>
        <div class="sm:col-span-2">
            <x-textarea name="Message" label="Isi Pengumuman" rows="7" required placeholder="Tuliskan informasi lengkap di sini...">{{ old('Message', $record['Message'] ?? $record['Content'] ?? '') }}</x-textarea>
        </div>
    </x-form-section>

    <div class="my-8 border-t border-slate-100"></div>

    <section aria-labelledby="audience-heading">
        <div class="mb-5">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-600">Penerima</p>
            <h2 id="audience-heading" class="mt-1 text-lg font-black text-slate-900">Tujuan Pengumuman</h2>
            <p class="mt-1 text-sm text-slate-500">Pilih seluruh pengguna atau satu maupun beberapa role penerima.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="audience-choice" :class="audience === 'ALL_ROLES' && 'audience-choice--active'">
                <input class="sr-only" type="radio" name="Audience_Type" value="ALL_ROLES" x-model="audience" @change="selectAllRoles()" @checked($audienceType === 'ALL_ROLES')>
                <span class="choice-mark" aria-hidden="true"></span>
                <span><strong>Semua Role</strong><small>Untuk seluruh pengguna aktif WMS</small></span>
            </label>
            <label class="audience-choice" :class="audience === 'SELECTED_ROLES' && 'audience-choice--active'">
                <input class="sr-only" type="radio" name="Audience_Type" value="SELECTED_ROLES" x-model="audience" @checked($audienceType === 'SELECTED_ROLES')>
                <span class="choice-mark" aria-hidden="true"></span>
                <span><strong>Role Tertentu</strong><small>Pilih satu atau beberapa kelompok</small></span>
            </label>
        </div>
        @error('Audience_Type')<p class="mt-2 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror

        <div x-show="audience === 'SELECTED_ROLES'" x-cloak class="mt-6 rounded-2xl bg-slate-50 p-4 sm:p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-bold text-slate-800">Pilih penerima</p>
                <p class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200" x-text="roles.length + ' role dipilih'"></p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach($roles ?? [] as $roleOption)
                    <label class="role-choice" :class="roles.includes(@js($roleOption['id'])) && 'role-choice--active'">
                        <input
                            class="sr-only"
                            type="checkbox"
                            name="Target_Roles[]"
                            value="{{ $roleOption['id'] }}"
                            x-model="roles"
                            @change="roleChanged()"
                            @checked(in_array($roleOption['id'], $selectedRoles, true))
                        >
                        <span class="role-check" aria-hidden="true">✓</span>
                        <span>{{ $roleOption['label'] }}</span>
                    </label>
                @endforeach
            </div>
            @error('Target_Roles')<p class="mt-3 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
            @error('Target_Roles.*')<p class="mt-3 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror

            <div x-show="hasStudent" x-transition.opacity x-cloak class="mt-6 border-t border-slate-200 pt-5">
                <input type="hidden" name="Student_Scope" value="{{ $studentScope }}" :value="studentScope" :disabled="!hasStudent">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700" aria-hidden="true">🎓</span>
                    <div>
                        <h3 class="font-black text-slate-900">Target Siswa</h3>
                        <p class="text-xs text-slate-500">Pembatasan ini hanya berlaku kepada role Student.</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <button type="button" class="audience-choice bg-white text-left" :class="studentScope === 'ALL_STUDENTS' && 'audience-choice--active'" @click="setStudentScope('ALL_STUDENTS')">
                        <span class="choice-mark" aria-hidden="true"></span>
                        <span><strong>Semua Siswa</strong><small>Seluruh siswa aktif</small></span>
                    </button>
                    <button type="button" class="audience-choice bg-white text-left" :class="studentScope !== 'ALL_STUDENTS' && 'audience-choice--active'" @click="setStudentScope('BATCH_ALL_CLASSES')">
                        <span class="choice-mark" aria-hidden="true"></span>
                        <span><strong>Batch / Kelas</strong><small>Satu batch dengan target kelas</small></span>
                    </button>
                </div>
                @error('Student_Scope')<p class="mt-2 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror

                <div x-show="studentScope !== 'ALL_STUDENTS'" x-transition.opacity x-cloak class="mt-5 space-y-5">
                    <div>
                        <label for="targetBatch" class="mb-1.5 block text-sm font-bold text-slate-700">Batch</label>
                        <select
                            id="targetBatch"
                            name="Target_Batch_ID"
                            x-model="batch"
                            @change="batchChanged()"
                            :disabled="!hasStudent || studentScope === 'ALL_STUDENTS'"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="">Pilih Batch</option>
                            @foreach($batches ?? [] as $batchRow)
                                <option value="{{ $batchRow['Batch_ID'] }}" @selected((string) $targetBatch === (string) $batchRow['Batch_ID'])>{{ $batchRow['Batch_Name'] ?? $batchRow['Batch_Code'] ?? $batchRow['Batch_ID'] }}</option>
                            @endforeach
                        </select>
                        @error('Target_Batch_ID')<p class="mt-1.5 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div x-show="batch" x-cloak>
                        <p class="text-sm font-bold text-slate-700">Target Kelas</p>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <button type="button" class="scope-choice" :class="studentScope === 'BATCH_ALL_CLASSES' && 'scope-choice--active'" @click="setStudentScope('BATCH_ALL_CLASSES')">
                                <span>Semua Kelas</span><small>Semua siswa dalam batch</small>
                            </button>
                            <button type="button" class="scope-choice" :class="studentScope === 'BATCH_SELECTED_CLASSES' && 'scope-choice--active'" @click="setStudentScope('BATCH_SELECTED_CLASSES')">
                                <span>Pilih Kelas</span><small>Satu atau beberapa kelas</small>
                            </button>
                        </div>

                        <div x-show="studentScope === 'BATCH_SELECTED_CLASSES'" x-cloak class="mt-4">
                            @foreach($selectedClasses as $selectedClassId)
                                <input type="hidden" name="Target_Class_IDs[]" value="{{ $selectedClassId }}" :disabled="!classes.includes(@js((string) $selectedClassId))">
                            @endforeach
                            <div x-show="loadingClasses" class="rounded-xl bg-white px-4 py-6 text-center text-sm font-semibold text-slate-500" role="status">Memuat kelas...</div>
                            <div x-show="!loadingClasses && loadError" class="rounded-xl bg-rose-50 px-4 py-5 text-center text-sm font-semibold text-rose-700">Gagal memuat kelas.</div>
                            <div x-show="!loadingClasses && !loadError && availableClasses.length === 0" class="rounded-xl bg-white px-4 py-6 text-center text-sm font-semibold text-slate-500">Belum ada kelas pada batch ini.</div>
                            <div x-show="!loadingClasses && !loadError && availableClasses.length > 0" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <template x-for="klass in availableClasses" :key="klass.id">
                                    <label class="class-choice" :class="classes.includes(klass.id) && 'class-choice--active'">
                                        <input class="sr-only" type="checkbox" name="Target_Class_IDs[]" :value="klass.id" x-model="classes">
                                        <span class="role-check" aria-hidden="true">✓</span>
                                        <span class="min-w-0 break-words" x-text="klass.name"></span>
                                    </label>
                                </template>
                            </div>
                            <p x-show="availableClasses.length > 0" class="mt-3 text-xs font-bold text-slate-500" x-text="classes.length + ' kelas dipilih'"></p>
                            @error('Target_Class_IDs')<p class="mt-2 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                            @error('Target_Class_IDs.*')<p class="mt-2 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
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
<style>
[x-cloak]{display:none!important}
.audience-choice{display:flex;min-height:82px;cursor:pointer;align-items:center;gap:.8rem;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem;transition:.18s ease}
.audience-choice:hover{border-color:#6ee7b7;background:#f8fffc}
.audience-choice:focus-visible,.scope-choice:focus-visible{outline:3px solid rgba(16,185,129,.25);outline-offset:2px}
.audience-choice--active{border-color:#10b981!important;background:#ecfdf5!important;box-shadow:0 0 0 3px rgba(16,185,129,.09)}
.audience-choice strong,.audience-choice small,.scope-choice span,.scope-choice small{display:block}
.audience-choice strong,.scope-choice span{font-size:.875rem;color:#0f172a}
.audience-choice small,.scope-choice small{margin-top:.2rem;font-size:.75rem;color:#64748b}
.choice-mark{width:1.15rem;height:1.15rem;flex:none;border:2px solid #cbd5e1;border-radius:999px}
.audience-choice--active .choice-mark{border:5px solid #10b981}
.role-choice,.class-choice{position:relative;display:flex;min-height:46px;cursor:pointer;align-items:center;gap:.55rem;border:1px solid #e2e8f0;border-radius:.8rem;background:#fff;padding:.7rem .75rem;font-size:.78rem;font-weight:800;color:#475569;transition:.18s}
.role-choice:hover,.class-choice:hover{border-color:#6ee7b7}
.role-choice:focus-within,.class-choice:focus-within{box-shadow:0 0 0 3px rgba(16,185,129,.14)}
.role-choice--active,.class-choice--active{border-color:#10b981;background:#059669;color:#fff;box-shadow:0 5px 14px rgba(5,150,105,.16)}
.role-check{display:flex;height:1.15rem;width:1.15rem;flex:none;align-items:center;justify-content:center;border:1px solid #cbd5e1;border-radius:.35rem;color:transparent;font-size:.7rem}
.role-choice--active .role-check,.class-choice--active .role-check{border-color:#fff;background:#fff;color:#059669}
.scope-choice{border:1px solid #e2e8f0;border-radius:.85rem;background:#fff;padding:.8rem;text-align:left;transition:.18s}
.scope-choice--active{border-color:#10b981;background:#ecfdf5;box-shadow:0 0 0 3px rgba(16,185,129,.08)}
</style>
<script>
function announcementAudience(audience,roles,studentScope,batch,classes,classMap,studentRoleId){
    return{
        audience,
        roles:Array.isArray(roles)?roles:[],
        studentScope:studentScope||'ALL_STUDENTS',
        batch:batch||'',
        classes:Array.isArray(classes)?classes:[],
        classMap:classMap||{},
        studentRoleId,
        loadingClasses:false,
        loadError:false,
        get hasStudent(){return this.audience==='SELECTED_ROLES'&&this.roles.includes(this.studentRoleId)},
        get availableClasses(){return Array.isArray(this.classMap[this.batch])?this.classMap[this.batch]:[]},
        selectAllRoles(){this.roles=[];this.clearStudent()},
        roleChanged(){if(!this.hasStudent)this.clearStudent()},
        clearStudent(){this.studentScope='ALL_STUDENTS';this.batch='';this.classes=[];this.loadingClasses=false;this.loadError=false},
        setStudentScope(scope){this.studentScope=scope;if(scope==='ALL_STUDENTS'){this.batch='';this.classes=[]}else if(scope==='BATCH_ALL_CLASSES'){this.classes=[]}},
        batchChanged(){
            this.classes=[];
            this.loadError=false;
            this.loadingClasses=true;
            this.$nextTick(()=>{this.loadingClasses=false;this.loadError=!Array.isArray(this.classMap[this.batch])})
        }
    }
}
</script>
@endonce
