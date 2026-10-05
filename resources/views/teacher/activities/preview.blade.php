@extends('layouts.app')

@section('title', 'Pré-visualização · '.$activity->title.' · FormAI')
@section('body-class', 'app-page activity-preview-page')
@section('main-class', 'preview-main')

@section('content')
<div class="preview-teacher-bar">
    <div class="container preview-teacher-inner">
        <div class="preview-mode"><span class="preview-mode-icon" aria-hidden="true">◉</span><div><strong>Modo de pré-visualização</strong><small>Esta é a experiência que o aluno verá. Nenhuma resposta será salva.</small></div></div>
        <a class="btn btn-light" href="{{ route('teacher.activities.edit', $activity) }}">← Voltar para edição</a>
    </div>
</div>

<section class="preview-hero">
    <div class="container preview-container">
        <div class="preview-breadcrumb"><span>Visão do aluno</span><i aria-hidden="true"></i><span>{{ $activity->classroom->name }}</span></div>
        <div class="preview-title-row">
            <div><span class="eyebrow">Atividade avaliativa</span><h1>{{ $activity->title }}</h1>@if($activity->description)<p class="question-rich-text">{{ $activity->description }}</p>@endif</div>
            <span class="preview-draft-badge">Prévia do rascunho</span>
        </div>
        <div class="preview-metadata">
            <div><span class="preview-meta-icon" aria-hidden="true">◷</span><span><small>Prazo de entrega</small><strong>{{ $activity->deadline_at?->format('d/m/Y · H:i') ?? 'Sem prazo' }}</strong></span></div>
            <div><span class="preview-meta-icon" aria-hidden="true">#</span><span><small>Quantidade</small><strong>{{ $activity->questions->count() }} {{ $activity->questions->count() === 1 ? 'item' : 'itens' }}</strong></span></div>
            <div><span class="preview-meta-icon" aria-hidden="true">★</span><span><small>Valor total</small><strong>{{ number_format((float) $activity->total_score, 2, ',', '.') }} pontos</strong></span></div>
        </div>
    </div>
</section>

<section class="container preview-container preview-content" aria-label="Questões da atividade">
    <div class="preview-progress" aria-hidden="true"><span style="width: 0%"></span></div>
    @forelse($activity->questions as $question)
        <article class="preview-question-card">
            <div class="preview-question-top">
                <div class="preview-question-identity"><span>{{ str_pad((string) $question->position, 2, '0', STR_PAD_LEFT) }}</span><div><small>{{ $question->type === App\Domain\QuestionBank\Enums\QuestionType::Context ? 'Texto de apoio' : 'Questão '.$question->position }}</small><strong>{{ match($question->type) { App\Domain\QuestionBank\Enums\QuestionType::Context => 'Bloco de contexto', App\Domain\QuestionBank\Enums\QuestionType::MultipleChoice => 'Múltipla escolha', App\Domain\QuestionBank\Enums\QuestionType::Essay => 'Dissertativa', default => 'Escolha única' } }}</strong></div></div>
                @if($question->type !== App\Domain\QuestionBank\Enums\QuestionType::Context)<span class="preview-score">{{ number_format((float) $question->max_score, 2, ',', '.') }} pts</span>@endif
            </div>
            <h2 class="visually-hidden">{{ $question->type === App\Domain\QuestionBank\Enums\QuestionType::Context ? 'Texto de apoio' : 'Questão '.$question->position }}</h2>
            <div class="question-rich-text preview-question-text">{{ $question->body }}</div>

            @if($question->type === App\Domain\QuestionBank\Enums\QuestionType::Essay)
                <label class="form-label" for="preview-answer-{{ $question->id }}">Sua resposta</label>
                <textarea class="form-control preview-answer" id="preview-answer-{{ $question->id }}" rows="7" placeholder="O aluno escreverá a resposta aqui..." disabled></textarea>
            @elseif($question->type !== App\Domain\QuestionBank\Enums\QuestionType::Context)
                <fieldset class="preview-options" disabled>
                    <legend class="visually-hidden">{{ $question->type === App\Domain\QuestionBank\Enums\QuestionType::MultipleChoice ? 'Escolha as alternativas corretas' : 'Escolha uma alternativa' }}</legend>
                    @foreach($question->options_snapshot ?? [] as $option)
                        <label class="preview-option" for="preview-q{{ $question->id }}-{{ $option['key'] }}">
                            <input id="preview-q{{ $question->id }}-{{ $option['key'] }}" name="preview-question-{{ $question->id }}" type="{{ $question->type === App\Domain\QuestionBank\Enums\QuestionType::MultipleChoice ? 'checkbox' : 'radio' }}">
                            <span class="preview-option-key">{{ $option['key'] }}</span><span class="question-rich-text">{{ $option['text'] }}</span>
                        </label>
                    @endforeach
                </fieldset>
            @endif
            @if($question->type !== App\Domain\QuestionBank\Enums\QuestionType::Context)<div class="preview-save-state"><span aria-hidden="true">○</span> As respostas do aluno serão salvas automaticamente</div>@endif
        </article>
    @empty
        <div class="preview-empty"><strong>Nenhuma questão adicionada</strong><p>Volte para a edição e inclua ao menos uma questão antes de publicar.</p></div>
    @endforelse

    <div class="preview-submit-card">
        <div><strong>Finalizar atividade</strong><p>Na versão do aluno, este botão enviará as respostas definitivamente.</p></div>
        <button class="btn btn-success btn-lg" type="button" disabled>Enviar atividade</button>
    </div>
</section>
@endsection
