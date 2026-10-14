@extends('layouts.app')
@section('header', 'Tambah Penilaian')

@section('content')
<div class="space-y-6" x-data="assessmentForm(@js(old('Assessment_Category', '')))">
    <x-page-header 
        title="Tambah Penilaian" 
        description="Masukkan penilaian siswa di kelas Anda."
        :breadcrumbs="['Dashboard' => route('dashboard.teacher'), 'Penilaian' => route('teacher.workspace.scores'), 'Tambah' => '#']"
    />

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <form action="{{ route('teacher.workspace.scores.store') }}" method="POST" class="p-6 md:p-8 space-y-6">
            @csrf
            <input type="hidden" name="Score_ID" value="{{ old('Score_ID', 'SCR-' . \Illuminate\Support\Str::uuid()) }}">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Siswa -->
                <div class="space-y-2">
                    <label for="score-student" class="block text-sm font-bold text-slate-700">Pilih Siswa</label>
                    <select id="score-student" name="Student_ID" required x-model="selectedStudent" :disabled="selectedSchedule === ''" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50 disabled:opacity-60 disabled:cursor-not-allowed">
                        <option value="" x-text="selectedSchedule === '' ? 'Pilih jadwal terlebih dahulu' : '-- Pilih Siswa --'"></option>
                        <template x-for="student in filteredStudents" :key="student.Student_ID">
                            <option :value="student.Student_ID" x-text="student.label"></option>
                        </template>
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="score-class" class="block text-sm font-bold text-slate-700">Kelas <span class="text-rose-600" aria-hidden="true">*</span></label>
                    <select id="score-class" name="Class_ID" required x-model="selectedClass" @change="selectedSchedule = ''; selectedStudent = ''" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach($classes as $class)
                            <option value="{{ $class['Class_ID'] }}" @selected(old('Class_ID') === $class['Class_ID'])>{{ $class['Class_Name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="score-schedule" class="block text-sm font-bold text-slate-700">Kelas / Jadwal <span class="text-rose-600" aria-hidden="true">*</span></label>
                    <select id="score-schedule" name="Schedule_ID" required x-model="selectedSchedule" :disabled="selectedClass === ''" @change="selectedStudent = ''" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50 disabled:opacity-60 disabled:cursor-not-allowed">
                        <option value="" x-text="selectedClass === '' ? 'Pilih kelas terlebih dahulu' : '-- Pilih Jadwal Pengajaran --'"></option>
                        <template x-for="schedule in filteredSchedules" :key="schedule.Schedule_ID">
                            <option :value="schedule.Schedule_ID" x-text="schedule.label"></option>
                        </template>
                    </select>
                    @if($schedules->isEmpty())
                        <p class="text-xs font-semibold text-amber-700">Belum ada jadwal pengajaran aktif dalam scope Anda.</p>
                    @endif
                </div>

                <div class="space-y-2">
                    <label for="score-date" class="block text-sm font-bold text-slate-700">Tanggal Penilaian</label>
                    <input id="score-date" type="date" name="Date" required value="{{ old('Date', date('Y-m-d')) }}" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50">
                </div>

                <!-- Kategori Penilaian -->
                <div class="space-y-2 md:col-span-2">
                    <label for="score-category" class="block text-sm font-bold text-slate-700">Kategori Penilaian</label>
                    <select id="score-category" name="Assessment_Category" x-model="category" required class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($assessmentConfigs as $config)
                            <option value="{{ $config['Category_ID'] }}">{{ $config['Category_Name'] ?? $config['Category_ID'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-show="isNumeric" class="space-y-2 pt-4 border-t border-slate-100" style="display: none;">
                <label for="score-value" class="block text-sm font-bold text-slate-700"><span x-text="numericLabel">Nilai Ujian Bab</span> <span class="text-rose-600" aria-hidden="true">*</span></label>
                <input id="score-value" type="number" name="Score" min="0" max="100" step="any" inputmode="decimal" value="{{ old('Score', old('Score_Value')) }}" x-bind:required="isNumeric" class="w-full min-h-12 rounded-xl border-slate-300 bg-slate-50 px-3 text-base focus:border-blue-500 focus:ring-blue-500">
                <p class="text-xs text-slate-500">Masukkan angka 0 sampai 100.</p>
            </div>

            <!-- Aspek Penilaian Dinamis -->
            <div x-show="category !== '' && hasAspects" class="space-y-6 pt-4 border-t border-slate-100" style="display: none;">
                <h4 class="font-bold text-slate-800 text-lg" x-text="'Aspek Penilaian ' + (activeConfig ? activeConfig.Category_Name : '')"></h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <template x-for="aspect in activeAspects" :key="aspect.id">
                        <div class="space-y-1">
                            <label class="block text-sm font-semibold text-slate-600" x-text="aspect.label"></label>
                            <select :name="aspect.id" required class="w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-sm">
                                <option value="">-- Pilih Tingkat --</option>
                                <option value="1">1 - Sangat Kurang</option>
                                <option value="2">2 - Kurang</option>
                                <option value="3">3 - Cukup</option>
                                <option value="4">4 - Baik</option>
                                <option value="5">5 - Sangat Baik</option>
                            </select>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Aspek Kosong / Belum Ada Konfigurasi -->
            <div x-show="category !== '' && !hasAspects && !isNumeric" class="space-y-6 pt-4 border-t border-slate-100" style="display: none;">
                <x-empty-state icon="cog" title="Konfigurasi Penilaian Belum Tersedia" message="Kategori ini belum memiliki aspek penilaian yang dapat digunakan. Hubungi Administrator untuk menyiapkan konfigurasi penilaian kategori ini." />
            </div>

            <!-- Catatan -->
            <div x-show="category !== ''" class="space-y-2 pt-4 border-t border-slate-100" style="display: none;">
                <label class="block text-sm font-bold text-slate-700">Catatan (Opsional)</label>
                <textarea name="Notes" rows="3" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 bg-slate-50" placeholder="Tambahkan catatan khusus terkait penilaian ini...">{{ old('Notes') }}</textarea>
            </div>

            <div class="pt-6 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('teacher.workspace.scores') }}" class="px-5 py-2.5 rounded-xl font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                    Batal
                </a>
                <button type="submit" x-bind:disabled="category === '' || (!hasAspects && !isNumeric)" class="px-6 py-2.5 rounded-xl font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    Simpan Penilaian
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('assessmentForm', (initialCategory = '') => ({
        category: initialCategory,
        selectedClass: @json(old('Class_ID', '')),
        selectedSchedule: @json(old('Schedule_ID', '')),
        selectedStudent: @json(old('Student_ID', '')),
        students: @json($students ?? []),
        schedules: @json($schedules ?? []),
        studentsBySchedule: @json($studentsBySchedule ?? []),
        studentsByClass: @json($studentsByClass ?? []),
        configs: @json($assessmentConfigs ?? []),
        get filteredSchedules() {
            if (this.selectedClass === '') return [];
            return this.schedules.filter(schedule => schedule.Class_ID === this.selectedClass);
        },
        get filteredStudents() {
            if (this.selectedSchedule === '') return [];
            const allowed = this.studentsBySchedule[this.selectedSchedule] || [];
            return this.students
                .filter(student => allowed.includes(student.Student_ID))
                .map(student => ({
                    ...student,
                    label: `${student.Full_Name || student.Username || 'Siswa'} - ${student.Class_Name || 'Kelas tidak ditemukan'}`
                }));
        },
        get activeConfig() {
            return this.configs.find(c => String(c.Category_ID).toUpperCase() === String(this.category).toUpperCase());
        },
        get isNumeric() {
            const type = String(this.activeConfig?.Score_Type || this.activeConfig?.ScoreType || this.activeConfig?.Input_Type || '').toUpperCase();
            return type === 'NUMERIC' || type === 'NUMBER' || type === 'SCORE' || String(this.category).toUpperCase() === 'UJIAN_BAB';
        },
        get numericLabel() {
            return String(this.category).toUpperCase() === 'UJIAN_BAB' ? 'Nilai Ujian Bab' : 'Nilai (0-100)';
        },
        get activeAspects() {
            if (!this.activeConfig || !this.activeConfig.Aspects_JSON) return [];
            try {
                return JSON.parse(this.activeConfig.Aspects_JSON);
            } catch (e) {
                return [];
            }
        },
        get hasAspects() {
            return this.activeAspects.length > 0;
        }
    }))
})
</script>
@endsection
