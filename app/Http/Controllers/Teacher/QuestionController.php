<?php

namespace App\Http\Controllers\Teacher;

use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Question::class);
        $questions = Question::query()->where('owner_id', $request->user()->id)->withCount(['options', 'rubricCriteria'])->latest()->paginate(15);
        return view('teacher.questions.index', compact('questions'));
    }
    public function create(): View { $this->authorize('create', Question::class); return view('teacher.questions.form'); }
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Question::class);
        $question = DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $question = $request->user()->questions()->create(Arr::except($data, ['options', 'rubric', 'teacher_instruction']) + ['teacher_instruction' => null]);
            $this->syncDetails($question, $data);
            return $question;
        });
        return redirect()->route('teacher.questions.index')->with('status', 'Questao criada.');
    }
    public function edit(Question $question): View { $this->authorize('update', $question); $question->load(['options', 'rubricCriteria']); return view('teacher.questions.form', compact('question')); }
    public function update(Request $request, Question $question): RedirectResponse
    {
        $this->authorize('update', $question);
        DB::transaction(function () use ($request, $question) { $data = $this->validated($request); $question->update(Arr::except($data, ['options', 'rubric', 'teacher_instruction']) + ['teacher_instruction' => null]); $this->syncDetails($question, $data); });
        return redirect()->route('teacher.questions.index')->with('status', 'Questao atualizada sem alterar atividades publicadas.');
    }
    public function destroy(Question $question): RedirectResponse { $this->authorize('delete', $question); $question->delete(); return back()->with('status', 'Questao arquivada.'); }

    private function validated(Request $request): array
    {
        $options = collect($request->input('options', []))
            ->filter(fn ($item) => filled($item['text'] ?? null))
            ->values()
            ->all();
        $rubric = collect($request->input('rubric', []))
            ->filter(fn ($item) => filled($item['label'] ?? null) || filled($item['description'] ?? null) || filled($item['weight'] ?? null))
            ->values()
            ->all();

        $request->merge([
            'options' => $options === [] ? null : $options,
            'rubric' => $rubric === [] ? null : $rubric,
        ]);
        $data = $request->validate([
            'type' => ['required', Rule::enum(QuestionType::class)], 'body' => ['required', 'string', 'max:10000'],
            'expected_answer' => ['nullable', 'string', 'max:10000'], 'teacher_instruction' => ['prohibited'],
            'max_score' => ['required', 'numeric', 'gt:0', 'max:1000'], 'options' => ['nullable', 'array', 'min:2', 'max:10'],
            'options.*.text' => ['required_with:options', 'string', 'max:2000'], 'options.*.is_correct' => ['sometimes', 'boolean'],
            'rubric' => ['nullable', 'array', 'min:1', 'max:10'], 'rubric.*.label' => ['required_with:rubric', 'string', 'max:120', 'distinct'],
            'rubric.*.description' => ['required_with:rubric', 'string', 'max:3000'], 'rubric.*.weight' => ['required_with:rubric', 'numeric', 'gt:0', 'max:1'],
        ]);
        if ($data['type'] === QuestionType::SingleChoice->value && collect($data['options'] ?? [])->where('is_correct', true)->count() !== 1) {
            throw ValidationException::withMessages(['options' => 'Marque exatamente uma alternativa correta.']);
        }
        if ($data['type'] === QuestionType::Essay->value) {
            if (! filled($data['expected_answer'] ?? null)) throw ValidationException::withMessages(['expected_answer' => 'Informe a resposta esperada para a questao dissertativa.']);
            $sum = collect($data['rubric'] ?? [])->sum(fn ($item) => (float) ($item['weight'] ?? 0));
            if (abs($sum - 1.0) > 0.001) throw ValidationException::withMessages(['rubric' => 'A soma dos pesos da rubrica deve ser 1,00.']);
        }
        return $data;
    }
    private function syncDetails(Question $question, array $data): void
    {
        $question->options()->delete(); $question->rubricCriteria()->delete();
        if ($question->type === QuestionType::SingleChoice) {
            foreach (array_values($data['options'] ?? []) as $i => $item) $question->options()->create(['option_key' => chr(65 + $i), 'text' => $item['text'], 'is_correct' => (bool) ($item['is_correct'] ?? false), 'position' => $i + 1]);
        } else {
            foreach (array_values($data['rubric'] ?? []) as $i => $item) $question->rubricCriteria()->create($item + ['position' => $i + 1]);
        }
    }
}
