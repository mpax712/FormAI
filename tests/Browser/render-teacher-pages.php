<?php
foreach (['APP_CONFIG_CACHE' => 'bootstrap/cache/testing-config.php', 'APP_ROUTES_CACHE' => 'bootstrap/cache/testing-routes.php', 'APP_ENV' => 'testing', 'APP_URL' => 'http://formai.test', 'APP_KEY' => 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'DB_FALLBACK_ENABLED' => 'false', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$teacher = App\Domain\Identity\Models\User::factory()->teacher()->create();
// These existing screen/editor fixtures represent an onboarded teacher.
$teacher->forceFill(['teacher_tutorial_seen_at' => now()])->save();
$classroom = App\Domain\Classrooms\Models\Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Turma de teste', 'is_active' => true]);
$question = App\Domain\QuestionBank\Models\Question::create(['owner_id' => $teacher->id, 'type' => 'essay', 'body' => 'Explique o ciclo da água e sua importância.', 'expected_answer' => 'Evaporação e condensação.', 'teacher_instruction' => 'Valorize exemplos.', 'max_score' => 1, 'is_active' => true]);
$question->rubricCriteria()->create(['label' => 'Conceito', 'description' => 'Explicação correta.', 'weight' => 1, 'position' => 1]);
$activity = App\Domain\Activities\Models\Activity::create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'title' => 'Teste de correção', 'status' => 'grading', 'published_at' => now(), 'deadline_at' => now()->addDays(2), 'total_score' => 10]);
$activityQuestion = $activity->questions()->create(['type' => 'essay', 'body' => 'Explique o tema.', 'max_score' => 10, 'position' => 1]);
$student = App\Domain\Identity\Models\User::factory()->student()->create(['name' => 'Ana Maria de Albuquerque Silva com nome muito longo']);
$classroom->members()->attach($student->id, ['status' => 'approved']);
$submission = $activity->submissions()->create(['student_id' => $student->id, 'status' => 'submitted', 'submitted_at' => now()]);
$submission->answers()->create(['activity_question_id' => $activityQuestion->id, 'response_text' => 'Resposta de teste.', 'version' => 1]);
$secondStudent = App\Domain\Identity\Models\User::factory()->student()->create(['name' => 'Bruno Santos']);
$classroom->members()->attach($secondStudent->id, ['status' => 'approved']);
$second = $activity->submissions()->create(['student_id' => $secondStudent->id, 'status' => 'submitted', 'submitted_at' => now()]);
$second->answers()->create(['activity_question_id' => $activityQuestion->id, 'response_text' => 'Outra resposta.', 'version' => 1]);
$published = $activity->submissions()->create(['student_id' => App\Domain\Identity\Models\User::factory()->student()->create()->id, 'status' => 'released', 'submitted_at' => now(), 'released_at' => now(), 'final_score' => 8]);
$publishedAnswer = $published->answers()->create(['activity_question_id' => $activityQuestion->id, 'response_text' => 'Resposta publicada para revisão.', 'version' => 1]);
$publishedAnswer->gradingDecision()->create(['reviewer_id' => $teacher->id, 'score' => 8, 'feedback' => 'Feedback inicial.', 'confirmed_at' => now()]);
$classroom->members()->attach($published->student_id, ['status' => 'approved']);
$failed = $second->answers->first()->gradingRuns()->create(['idempotency_key' => hash('sha256', 'ui-failure'), 'status' => 'permanently_failed', 'provider' => 'openrouter', 'model' => 'test', 'prompt_version' => 1, 'error_message' => str_repeat('O provedor está temporariamente indisponível. ', 12)]);
$admin = App\Domain\Identity\Models\User::factory()->admin()->create();
App\Domain\Administration\Models\AuditLog::create(['actor_id' => $admin->id, 'event' => 'admin.sensitive_access', 'route' => 'admin.dashboard', 'metadata' => []]);
config(['services.gemini.key' => 'fake-browser-test']);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$pages = [];
foreach (['questions' => '/professor/questoes', 'editor' => '/professor/questoes/create', 'activity' => '/professor/atividades/create', 'dashboard' => '/dashboard', 'grading' => '/professor/entregas/'.$submission->public_id.'/corrigir', 'deliveries' => '/professor/atividades/'.$activity->public_id, 'activityResults' => '/professor/atividades/'.$activity->public_id.'/resultados/editar', 'classrooms' => '/professor/turmas', 'classroom' => '/professor/turmas/'.$classroom->public_id, 'activities' => '/professor/atividades', 'profile' => '/perfil'] as $name => $url) {
    Illuminate\Support\Facades\Auth::setUser($teacher);
    $response = $kernel->handle(Illuminate\Http\Request::create($url));
    if ($response->getStatusCode() !== 200) throw new RuntimeException("$url: ".$response->getStatusCode());
    $pages[$name] = $response->getContent();
}
foreach (['student' => [$student, '/dashboard'], 'studentActivities' => [$student, '/aluno/atividades'], 'studentAnswer' => [$secondStudent, '/aluno/atividades/'.$activity->public_id], 'studentResult' => [$published->student, '/aluno/entregas/'.$published->public_id.'/resultado'], 'admin' => [$admin, '/admin'], 'adminUsers' => [$admin, '/admin/usuarios'], 'adminAcademic' => [$admin, '/admin/academico']] as $name => [$user, $url]) {
    if ($name === 'studentAnswer') $second->update(['status' => 'draft']);
    Illuminate\Support\Facades\Auth::setUser($user);
    $response = $kernel->handle(Illuminate\Http\Request::create($url));
    if ($response->getStatusCode() !== 200) throw new RuntimeException("$url: ".$response->getStatusCode());
    $pages[$name] = $response->getContent();
}
Illuminate\Support\Facades\Auth::forgetGuards();
foreach (['landing' => '/', 'login' => '/login', 'register' => '/register', 'forgot' => '/forgot-password'] as $name => $url) {
    $response = $kernel->handle(Illuminate\Http\Request::create($url));
    if ($response->getStatusCode() !== 200) throw new RuntimeException("$url: ".$response->getStatusCode());
    $pages[$name] = $response->getContent();
}
$pages['confirmPassword'] = view('auth.confirm-password')->render();
$pages['twoFactor'] = view('auth.two-factor-challenge')->render();
echo json_encode($pages, JSON_THROW_ON_ERROR);
