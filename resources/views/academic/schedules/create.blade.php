@extends('layouts.app')

@section('header', 'Tambah Jadwal')

@section('content')
@php
    $classOptions = [];
    if(isset($classes)) {
        foreach($classes as $c) {
            $classOptions[$c['Class_ID'] ?? ''] = ($c['Class_Name'] ?? 'Unknown') . ' (' . ($c['Program_Name'] ?? '') . ')';
        }
    }
    
    $subjectOptions = [];
    if(isset($subjects)) {
        foreach($subjects as $s) {
            $subjectOptions[$s['Subject_ID'] ?? ''] = ($s['Subject_Name'] ?? 'Unknown') . ' (' . ($s['Subject_Code'] ?? '') . ')';
        }
    }
    
    $teacherOptions = [];
    if(isset($teachers)) {
        foreach($teachers as $t) {
            $teacherOptions[$t['Teacher_ID'] ?? ''] = ($t['Full_Name'] ?? 'Unknown') . ' (' . ($t['Specialization'] ?? '') . ')';
        }
    }
    
    $ayOptions = [];
    if(isset($academicYears)) {
        foreach($academicYears as $ay) {
            $ayId = trim((string) ($ay['Academic_Year_ID'] ?? ''));
            if($ayId === '') {
                continue;
            }
            $ayOptions[$ayId] = trim(($ay['Name'] ?? 'Tahun ajaran') . ' - ' . ($ay['Semester'] ?? ''));
        }
    }
    $selectedAcademicYearId = (string) old('Academic_Year_ID', array_key_first($ayOptions) ?? '');
    if(!array_key_exists($selectedAcademicYearId, $ayOptions)) {
        $selectedAcademicYearId = (string) (array_key_first($ayOptions) ?? '');
    }
@endphp

<div class="space-y-6">
    <x-page-header 
        title="Formulir Jadwal Kelas" 
        description="Buat jadwal pelajaran baru untuk kelas tertentu."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Akademik' => '#', 'Jadwal' => route('schedules.index'), 'Tambah Baru' => '#']"
    />

    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-6 py-4 rounded-xl flex items-start shadow-sm">
            <svg class="w-6 h-6 mr-3 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div>
                <h4 class="font-bold text-sm mb-1">Terjadi Kesalahan!</h4>
                <ul class="list-disc list-inside text-sm">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <x-card class="p-0 overflow-hidden">
        <form action="{{ route('schedules.store') }}" method="POST">
            @csrf
            
            <div class="px-6 md:px-12">
                <x-form-section title="Informasi Jadwal" description="Tautkan kelas, materi, dan pengajar.">
                    <div>
                        <x-universal.searchable-select 
                            name="Class_ID" 
                            label="Kelas" 
                            :options="$classOptions" 
                            :required="true" 
                            value="{{ old('Class_ID') }}" 
                        />
                    </div>

                    <div>
                        @if(empty($subjectOptions))
                            <x-universal.select 
                                name="Subject_ID" 
                                label="Materi" 
                                :options="['' => 'Belum ada Materi tersedia']"
                                :disabled="true"
                                :required="true"
                            />
                        @else
                            <x-universal.searchable-select 
                                name="Subject_ID" 
                                label="Materi" 
                                :options="$subjectOptions" 
                                :required="true" 
                                value="{{ old('Subject_ID') }}" 
                            />
                        @endif
                    </div>

                    <div>
                        @if(!empty($currentTeacherId))
                            <x-input name="Teacher_ID_Display" label="Pengajar" value="{{ $teacherOptions[$currentTeacherId] ?? $currentTeacherId }}" readonly class="bg-slate-100 cursor-not-allowed" />
                            <input type="hidden" name="Teacher_ID" value="{{ $currentTeacherId }}">
                        @elseif(empty($teacherOptions))
                            <x-universal.select 
                                name="Teacher_ID" 
                                label="Pengajar" 
                                :options="['' => 'Belum ada Pengajar tersedia']"
                                :disabled="true"
                                :required="true"
                            />
                        @else
                            <x-universal.searchable-select 
                                name="Teacher_ID" 
                                label="Pengajar" 
                                :options="$teacherOptions" 
                                :required="true" 
                                value="{{ old('Teacher_ID') }}" 
                            />
                        @endif
                    </div>

                    @if(empty($ayOptions))
                        <div>
                            <x-universal.select name="Academic_Year_ID" label="Tahun Ajaran" :options="['' => 'Master tahun ajaran belum tersedia']" :disabled="true" :required="true" />
                            <p class="mt-1.5 text-xs font-semibold text-rose-600">Hubungi administrator akademik untuk mengaktifkan master tahun ajaran sebelum membuat jadwal.</p>
                        </div>
                    @elseif(count($ayOptions) === 1)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Ajaran <span class="text-red-500">*</span></label>
                            <input type="text" readonly value="{{ reset($ayOptions) }}" class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <input type="hidden" name="Academic_Year_ID" value="{{ $selectedAcademicYearId }}">
                        </div>
                    @else
                        <div>
                            <x-universal.searchable-select 
                                name="Academic_Year_ID" 
                                label="Tahun Ajaran" 
                                :options="$ayOptions" 
                                :required="true" 
                                value="{{ $selectedAcademicYearId }}"
                            />
                        </div>
                    @endif

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="col-span-1 sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hari <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                @foreach(['Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'] as $val => $label)
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="Day_Of_Week[]" value="{{ $val }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ (is_array(old('Day_Of_Week')) && in_array($val, old('Day_Of_Week'))) ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm text-gray-700">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <x-input name="Room" label="Ruangan (Room)" value="{{ old('Room') }}" placeholder="Contoh: Lab Komputer" />
                        </div>
                    </div>

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-input type="time" name="Start_Time" label="Waktu Mulai" required value="{{ old('Start_Time') }}" />
                        </div>
                        <div>
                            <x-input type="time" name="End_Time" label="Waktu Selesai" required value="{{ old('End_Time') }}" />
                        </div>
                    </div>
                </x-form-section>
            </div>

            <div class="px-6 md:px-12 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-2xl">
                <x-button as="a" href="{{ route('schedules.index') }}" variant="secondary">Batal</x-button>
                <x-button type="submit" variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Simpan Jadwal
                </x-button>
            </div>
        </form>
    </x-card>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = `<svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
        });
    });
</script>
@endsection



