@extends('layouts.app')
@section('header', $quiz ? 'Edit Kuis' : 'Buat Kuis')
@section('content')
@php
    $seed = old('questions', collect($questions)->map(fn($q) => (array)$q)->values()->all());
    $maxQuestions = (int) config('quiz.max_questions', 100);
    $lockedClass = $lockedClass ?? null;
    $formAction = $quiz
        ? route('teacher.quizzes.update', $quiz['Quiz_ID'])
        : ($lockedClass ? route('teacher.quizzes.class.store', $lockedClass['Class_ID']) : route('teacher.quizzes.store'));
@endphp
<form method="POST" action="{{ $formAction }}" class="mx-auto max-w-5xl space-y-6 pb-24" x-data="quizBuilder(@js($seed), {{ $maxQuestions }})">
    @csrf @if($quiz) @method('PUT') @endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-7"><h2 class="text-xl font-black">Informasi Kuis</h2>
        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
            <label class="sm:col-span-2 text-sm font-bold">Judul Kuis<input name="Title" value="{{ old('Title', $quiz['Title'] ?? '') }}" required maxlength="160" class="mt-2 min-h-12 w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500"></label>
            @if($lockedClass)
                <label class="text-sm font-bold">Kelas<input type="hidden" name="Class_ID" value="{{ $lockedClass['Class_ID'] }}"><span class="mt-2 flex min-h-12 items-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-slate-800">{{ $lockedClass['Class_Name'] ?? 'Kelas Anda' }}</span></label>
            @else
                <label class="text-sm font-bold">Kelas<select name="Class_ID" required class="mt-2 min-h-12 w-full rounded-xl border-slate-300"><option value="">-- Pilih Kelas --</option>@foreach($classes as $class)<option value="{{ $class['Class_ID'] }}" @selected(old('Class_ID', $quiz['Class_ID'] ?? '') === $class['Class_ID'])>{{ $class['Class_Name'] ?? $class['Class_ID'] }}</option>@endforeach</select></label>
            @endif
            <label class="text-sm font-bold">Durasi (menit)<input type="number" min="1" max="480" name="Duration_Minutes" value="{{ old('Duration_Minutes', $quiz['Duration_Minutes'] ?? 30) }}" required class="mt-2 min-h-12 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-bold">Mulai<input type="datetime-local" name="Start_At" value="{{ old('Start_At', isset($quiz['Start_At']) ? str_replace(' ', 'T', substr($quiz['Start_At'], 0, 16)) : '') }}" required class="mt-2 min-h-12 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-bold">Selesai<input type="datetime-local" name="End_At" value="{{ old('End_At', isset($quiz['End_At']) ? str_replace(' ', 'T', substr($quiz['End_At'], 0, 16)) : '') }}" required class="mt-2 min-h-12 w-full rounded-xl border-slate-300"></label>
        </div>
    </section>
    <section class="min-w-0 rounded-2xl bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><h2 class="text-xl font-black">Soal A–D</h2><p class="text-sm text-slate-500">Skor maksimum: <strong x-text="maximum"></strong></p></div>
            <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-[minmax(0,12rem)_auto] sm:items-end lg:w-auto">
                <label class="text-sm font-bold text-slate-700">Jumlah Soal
                    <input type="number" min="1" max="{{ $maxQuestions }}" step="1" x-model.number="requestedCount" @keydown.enter.prevent="applyQuestionCount" class="mt-2 min-h-11 w-full rounded-xl border-slate-300" aria-describedby="question-count-help question-count-error">
                </label>
                <button type="button" @click="applyQuestionCount" class="min-h-11 rounded-xl bg-sky-50 px-4 py-2 font-bold text-sky-700" x-text="questionActionLabel">Buat Soal</button>
                <input type="hidden" name="question_count" :value="questions.length">
                <p id="question-count-help" class="text-xs text-slate-500 sm:col-span-2">Minimal 1, maksimal {{ $maxQuestions }} soal. Menambah jumlah tidak menghapus jawaban yang sudah diisi.</p>
                <p id="question-count-error" x-cloak x-show="countError" x-text="countError" role="alert" class="text-sm font-bold text-rose-700 sm:col-span-2"></p>
            </div>
        </div>
        <div class="mt-5 space-y-5">
            <template x-for="(q, i) in questions" :key="q.key">
                <fieldset class="min-w-0 rounded-2xl border border-slate-200 p-4 sm:p-5" data-question-card>
                    <legend class="px-2 text-lg font-black" x-text="`Soal ${i + 1}`"></legend>
                    <label class="block text-sm font-bold text-slate-700">
                        Pertanyaan
                        <textarea :name="`questions[${i}][Question_Text]`" x-model="q.Question_Text" rows="3" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Tulis pertanyaan..."></textarea>
                    </label>
                    <div class="mt-5">
                        <p class="text-sm font-black text-slate-800">Pilihan Jawaban</p>
                        <div class="mt-3 space-y-3">
                            <template x-for="letter in ['A', 'B', 'C', 'D']" :key="`${q.key}-${letter}`">
                                <label class="grid min-w-0 grid-cols-[2.75rem_minmax(0,1fr)] items-center gap-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-100 font-black text-sky-800" x-text="letter"></span>
                                    <span class="sr-only" x-text="`Pilihan ${letter}`"></span>
                                    <input :name="`questions[${i}][Option_${letter}]`" x-model="q[`Option_${letter}`]" :placeholder="`Masukkan pilihan ${letter}...`" class="min-h-11 min-w-0 w-full rounded-xl border-slate-300" data-option-input>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="text-sm font-bold text-slate-700">Jawaban Benar
                            <select :name="`questions[${i}][Correct_Option]`" x-model="q.Correct_Option" class="mt-2 min-h-11 w-full rounded-xl border-slate-300"><option>A</option><option>B</option><option>C</option><option>D</option></select>
                        </label>
                        <label class="text-sm font-bold text-slate-700">Poin
                            <input type="number" min="0.01" step="0.01" :name="`questions[${i}][Point]`" x-model.number="q.Point" class="mt-2 min-h-11 w-full rounded-xl border-slate-300">
                        </label>
                    </div>
                </fieldset>
            </template>
        </div>
    </section>
    <div class="sticky bottom-20 z-20 flex gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur md:bottom-4"><button name="intent" value="draft" class="min-h-12 flex-1 rounded-xl border border-slate-300 font-black">Simpan Draft</button><button name="intent" value="publish" class="min-h-12 flex-1 rounded-xl bg-sky-600 font-black text-white">Terbitkan</button></div>
</form>
@push('scripts')
<script>
function quizBuilder(seed, maxQuestions) {
    const fresh = () => ({
        key: `${Date.now()}-${Math.random()}`,
        Question_Text: '',
        Option_A: '',
        Option_B: '',
        Option_C: '',
        Option_D: '',
        Correct_Option: 'A',
        Point: 10,
    });
    const normalize = (question, index) => ({
        ...fresh(),
        ...question,
        key: question.Question_ID || `${Date.now()}-${index}-${Math.random()}`,
    });

    const initialQuestions = seed.length ? seed.map(normalize) : [fresh()];

    return {
        questions: initialQuestions,
        requestedCount: initialQuestions.length,
        maxQuestions,
        countError: '',
        get maximum() {
            return this.questions.reduce((total, question) => total + (Number(question.Point) || 0), 0);
        },
        get questionActionLabel() {
            const count = Number(this.requestedCount);
            return Number.isInteger(count) ? `Buat ${count} Soal` : 'Buat Soal';
        },
        applyQuestionCount() {
            const target = Number(this.requestedCount);
            if (!Number.isInteger(target) || target < 1 || target > this.maxQuestions) {
                this.countError = `Jumlah soal harus berupa bilangan bulat antara 1 dan ${this.maxQuestions}.`;
                return;
            }

            this.countError = '';
            if (target < this.questions.length) {
                const removed = this.questions.length - target;
                const confirmed = window.confirm(`Kurangi jumlah soal menjadi ${target}? Data pada ${removed} soal terakhir akan dihapus dari formulir.`);
                if (!confirmed) {
                    this.requestedCount = this.questions.length;
                    return;
                }
                this.questions.splice(target);
            } else {
                while (this.questions.length < target) {
                    this.questions.push(fresh());
                }
            }
            this.requestedCount = this.questions.length;
        },
    };
}
</script>
@endpush
@endsection
