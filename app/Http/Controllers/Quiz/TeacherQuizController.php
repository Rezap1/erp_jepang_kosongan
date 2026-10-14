<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Services\Quiz\QuizScopeService;
use App\Services\Quiz\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TeacherQuizController extends Controller
{
    public function __construct(private QuizService $quizzes, private QuizScopeService $scope) {}

    public function index(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $classIds = $classes->pluck('Class_ID')->all();

        return view('quiz.teacher.index', [
            'classes' => $this->quizzes->teacherClassIndex(
                $teacher['Teacher_ID'],
                $classes,
                $this->scope->studentCountsForClasses($classIds),
            ),
        ]);
    }

    public function create(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());

        return view('quiz.teacher.form', ['quiz' => null, 'questions' => collect(), 'classes' => $this->scope->classesForTeacher($teacher['Teacher_ID'])]);
    }

    public function classHub(Request $request, string $class)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $selectedClass = $classes->first(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($class)
        );
        abort_unless($selectedClass, 403, 'Kelas berada di luar penugasan kelas Anda.');
        $hub = $this->quizzes->teacherClassHub(
            $teacher['Teacher_ID'],
            (string) $selectedClass['Class_ID'],
            $this->scope->studentsForClass((string) $selectedClass['Class_ID']),
        );

        return view('quiz.teacher.class-hub', $hub + [
            'class' => $selectedClass,
        ]);
    }

    public function createForClass(Request $request, string $class)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $selectedClass = $classes->first(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($class)
        );
        abort_unless($selectedClass, 403, 'Kelas berada di luar penugasan kelas Anda.');

        return view('quiz.teacher.form', [
            'quiz' => null,
            'questions' => collect(),
            'classes' => collect([$selectedClass]),
            'lockedClass' => $selectedClass,
        ]);
    }

    public function store(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $data = $this->validated($request);
        $this->scope->assertTeacherClass($teacher['Teacher_ID'], $data['Class_ID']);
        $id = $this->quizzes->create($data, $teacher, (string) $request->user()->User_ID);

        return redirect()->route('teacher.quizzes.show', $id)->with('success', 'Kuis berhasil disimpan.');
    }

    public function storeForClass(Request $request, string $class)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $selectedClass = $classes->first(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($class)
        );
        abort_unless($selectedClass, 403, 'Kelas berada di luar penugasan kelas Anda.');
        $request->merge(['Class_ID' => (string) $selectedClass['Class_ID']]);
        $data = $this->validated($request);
        $id = $this->quizzes->create($data, $teacher, (string) $request->user()->User_ID);

        return redirect()->route('teacher.quizzes.show', $id)->with('success', 'Kuis berhasil disimpan.');
    }

    public function show(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $classIds = $classes->pluck('Class_ID')->all();
        $row = $this->quizzes->teacherQuiz($quiz, $teacher['Teacher_ID'], $classIds);

        return view('quiz.teacher.show', [
            'quiz' => $row,
            'className' => $classes->firstWhere('Class_ID', $row['Class_ID'])['Class_Name'] ?? 'Kelas Anda',
        ]);
    }

    public function edit(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $row = $this->quizzes->teacherQuiz($quiz, $teacher['Teacher_ID'], $classes->pluck('Class_ID')->all());
        abort_unless($row['Editable'], 409, 'Kuis tidak lagi dapat diedit.');

        return view('quiz.teacher.form', ['quiz' => $row, 'questions' => $row['questions'], 'classes' => $classes]);
    }

    public function update(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classIds = $this->scope->classesForTeacher($teacher['Teacher_ID'])->pluck('Class_ID')->all();
        $data = $this->validated($request);
        abort_unless(in_array($data['Class_ID'], $classIds, true), 403, 'Kelas berada di luar penugasan kelas Anda.');
        $this->quizzes->update($quiz, $data, $teacher, (string) $request->user()->User_ID, $classIds);

        return redirect()->route('teacher.quizzes.show', $quiz)->with('success', 'Kuis berhasil diperbarui.');
    }

    public function destroy(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classIds = $this->scope->classesForTeacher($teacher['Teacher_ID'])->pluck('Class_ID')->all();
        try {
            $deleted = $this->quizzes->deleteQuiz(
                $quiz,
                $teacher['Teacher_ID'],
                $classIds,
                (string) $request->user()->User_ID,
                $request->ip(),
                $request->userAgent(),
            );
        } catch (\RuntimeException $exception) {
            Log::error('Quiz permanent deletion failed verification.', [
                'quiz_id' => $quiz,
                'teacher_id' => $teacher['Teacher_ID'],
                'exception' => $exception,
            ]);

            return back()->with('error', 'Kuis belum dapat dihapus sepenuhnya. Tidak ada tahap berikutnya yang dijalankan; silakan coba lagi.');
        }

        return redirect()->route('teacher.quizzes.class', $deleted['quiz']['Class_ID'])->with('success', 'Kuis berhasil dihapus. Soal, hasil siswa, dan poin leaderboard terkait juga telah dihapus.');
    }

    public function results(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $requestedClass = trim((string) $request->query('class'));
        if ($requestedClass !== '' && ! $classes->contains(
            fn ($class) => trim((string) ($class['Class_ID'] ?? '')) === $requestedClass
        )) {
            abort(403, 'Kelas berada di luar penugasan kelas Anda.');
        }
        $selectedClass = $requestedClass;
        $roster = $selectedClass !== '' ? $this->scope->studentsForClass($selectedClass) : collect();
        $overview = $selectedClass !== ''
            ? $this->quizzes->teacherResults($teacher['Teacher_ID'], $selectedClass, $roster)
            : ['groups' => collect(), 'result_count' => 0, 'student_count' => 0];

        return view('quiz.teacher.results', [
            'overview' => $overview,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'selectedClassRow' => $classes->firstWhere('Class_ID', $selectedClass),
        ]);
    }

    public function leaderboard(Request $request, string $class)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $selectedClass = $classes->first(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($class)
        );
        abort_unless($selectedClass, 403, 'Kelas berada di luar penugasan kelas Anda.');

        return view('quiz.leaderboard', $this->quizzes->leaderboard($class) + [
            'currentStudentId' => null,
            'backRoute' => route('teacher.quizzes.class', $class),
            'classId' => $class,
            'className' => $selectedClass['Class_Name'] ?? $class,
            'classOptions' => $classes,
        ]);
    }

    public function result(Request $request, string $result)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classIds = $this->scope->classesForTeacher($teacher['Teacher_ID'])->pluck('Class_ID')->all();

        return view('quiz.teacher.result', [
            'result' => $this->quizzes->teacherResult($result, $teacher['Teacher_ID'], $classIds),
        ]);
    }

    private function validated(Request $request): array
    {
        $maxQuestions = (int) config('quiz.max_questions', 100);
        $validator = Validator::make($request->all(), [
            'Title' => ['required', 'string', 'max:160'],
            'Class_ID' => ['required', 'string', 'max:80'],
            'Start_At' => ['required', 'date'],
            'End_At' => ['required', 'date', 'after:Start_At'],
            'Duration_Minutes' => ['required', 'integer', 'min:1', 'max:'.config('quiz.max_duration_minutes', 480)],
            'intent' => ['required', 'in:draft,publish'],
            'question_count' => ['required', 'integer', 'min:1', 'max:'.$maxQuestions],
            'questions' => ['required', 'array', 'min:1', 'max:'.$maxQuestions],
            'questions.*.Question_Text' => ['required_if:intent,publish', 'nullable', 'string', 'max:2000'],
            'questions.*.Option_A' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Option_B' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Option_C' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Option_D' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Correct_Option' => ['required_if:intent,publish', 'nullable', 'in:A,B,C,D'],
            'questions.*.Point' => ['required_if:intent,publish', 'nullable', 'numeric', 'gt:0', 'max:10000'],
        ], [
            'question_count.required' => 'Jumlah soal wajib diisi.',
            'question_count.integer' => 'Jumlah soal harus berupa bilangan bulat.',
            'question_count.min' => 'Jumlah soal minimal 1.',
            'question_count.max' => "Jumlah soal maksimal {$maxQuestions}.",
            'questions.required' => 'Minimal satu soal diperlukan.',
            'questions.required_if' => 'Minimal satu soal diperlukan sebelum kuis diterbitkan.',
            'questions.min' => 'Minimal satu soal diperlukan sebelum kuis diterbitkan.',
            'questions.max' => "Jumlah soal maksimal {$maxQuestions}.",
            'questions.*.Question_Text.required_if' => 'Pertanyaan wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Option_A.required_if' => 'Pilihan A wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Option_B.required_if' => 'Pilihan B wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Option_C.required_if' => 'Pilihan C wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Option_D.required_if' => 'Pilihan D wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Correct_Option.required_if' => 'Jawaban benar wajib dipilih sebelum kuis diterbitkan.',
            'questions.*.Correct_Option.in' => 'Jawaban benar harus A, B, C, atau D.',
            'questions.*.Point.required_if' => 'Poin wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Point.numeric' => 'Poin harus berupa angka.',
            'questions.*.Point.gt' => 'Poin harus lebih besar dari 0.',
        ]);
        $validator->after(function ($validator) use ($request) {
            $questions = $request->input('questions', []);
            if (is_array($questions)) {
                if (! array_is_list($questions)) {
                    $validator->errors()->add('questions', 'Urutan soal tidak valid. Gunakan indeks berurutan mulai dari 0.');
                }

                $requestedCount = filter_var($request->input('question_count'), FILTER_VALIDATE_INT);
                if ($requestedCount !== false && $requestedCount !== count($questions)) {
                    $validator->errors()->add('question_count', 'Jumlah soal tidak sesuai dengan form soal yang dikirim.');
                }
            }

            if ($request->input('intent') === 'publish' && count((array) $request->input('questions', [])) === 0) {
                $validator->errors()->add('questions', 'Minimal satu soal diperlukan sebelum kuis diterbitkan.');
            }
        });
        $data = $validator->validate();
        $data['questions'] = collect(array_values($data['questions'] ?? []))->map(fn ($question) => [
            'Question_Text' => trim((string) ($question['Question_Text'] ?? '')),
            'Option_A' => trim((string) ($question['Option_A'] ?? '')),
            'Option_B' => trim((string) ($question['Option_B'] ?? '')),
            'Option_C' => trim((string) ($question['Option_C'] ?? '')),
            'Option_D' => trim((string) ($question['Option_D'] ?? '')),
            'Correct_Option' => strtoupper(trim((string) ($question['Correct_Option'] ?? 'A'))),
            'Point' => (float) ($question['Point'] ?? 10),
        ])->all();

        return $data;
    }
}
