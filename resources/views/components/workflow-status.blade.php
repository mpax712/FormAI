@props(['value'])
@php($labels = ['draft' => 'Rascunho', 'published' => 'Disponível', 'closed' => 'Prazo encerrado', 'grading' => 'Em correção', 'review_ready' => 'Pronta para revisão', 'released' => 'Resultado publicado', 'submitted' => 'Aguardando correção', 'processing' => 'IA em andamento', 'reviewed' => 'Revisada', 'pending' => 'Na fila', 'retryable_failed' => 'Aguardando nova tentativa', 'succeeded' => 'Concluída', 'permanently_failed' => 'Falha na IA'])
<span class="badge text-bg-secondary">{{ $labels[$value] ?? $value }}</span>
