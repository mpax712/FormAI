<div id="{{ auth()->user()->isTeacher() ? 'teacher-tutorial' : 'student-tutorial' }}" data-user-tutorial data-config="{{ json_encode(app(auth()->user()->isTeacher() ? App\Application\Services\TeacherTutorial::class : App\Application\Services\StudentTutorial::class)->payload(auth()->user()), JSON_THROW_ON_ERROR) }}" hidden>
    <div class="tour-shade" data-tour-shade aria-hidden="true"></div>
    <div class="tour-shade" data-tour-shade aria-hidden="true"></div>
    <div class="tour-shade" data-tour-shade aria-hidden="true"></div>
    <div class="tour-shade" data-tour-shade aria-hidden="true"></div>
    <div class="tour-ring" aria-hidden="true"></div>
    <div class="tour-arrow" aria-hidden="true" hidden>↑</div>
    <section class="tour-panel" role="dialog" aria-labelledby="tour-title" aria-describedby="tour-description tour-instruction tour-hint" tabindex="-1">
        <div class="tour-assistant" aria-hidden="true"><svg viewBox="0 0 48 48" width="40" height="40"><path d="M8 13h13l3 3 3-3h13v25H27l-3 3-3-3H8Z"/><path d="M24 17v20M13 21h6M13 27h6M29 26h6M32 5v8M28 9h8"/></svg><span class="tour-check">✓</span></div>
        <div class="tour-meter" role="progressbar" aria-label="Progresso do tutorial" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div>
        <p class="tour-progress" aria-live="polite" aria-atomic="true"></p>
        <h2 id="tour-title" class="h4"></h2>
        <p id="tour-description"></p>
        <p id="tour-instruction" class="tour-instruction" hidden></p>
        <p class="tour-loading" role="status" hidden></p>
        <p id="tour-hint" class="small">Escape pausa o tutorial. Você pode refazê-lo em Meu perfil.</p>
        <p class="tour-error" role="alert" hidden></p>
        <div class="tour-actions"></div>
    </section>
</div>
