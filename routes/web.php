<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ClassCodeAccessController;
use App\Http\Controllers\GuardianAuthorizationController;
use App\Http\Controllers\EmailVerificationCodeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\ActivityController as StudentActivityController;
use App\Http\Controllers\Teacher\ActivityController;
use App\Http\Controllers\Teacher\ClassroomController;
use App\Http\Controllers\Teacher\GradingController;
use App\Http\Controllers\Teacher\InvitationController;
use App\Http\Controllers\Teacher\MembershipRequestController;
use App\Http\Controllers\Teacher\QuestionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('home'))->name('home');
Route::view('/termos-e-privacidade', 'legal.terms')->name('legal.terms');
Route::view('/responsavel/pendente', 'guardian.pending')->name('guardian.pending');
Route::get('/responsavel/autorizar/{token}', [GuardianAuthorizationController::class, 'show'])->name('guardian.show');
Route::post('/responsavel/autorizar/{token}', [GuardianAuthorizationController::class, 'decide'])->middleware('throttle:registration')->name('guardian.decide');
Route::post('/responsavel/reenviar', [GuardianAuthorizationController::class, 'resend'])->middleware('throttle:registration')->name('guardian.resend');
Route::get('/responsavel/resultado/{status}', [GuardianAuthorizationController::class, 'result'])->name('guardian.result');
Route::get('/cadastro', fn () => redirect('/register', 301))->name('legacy.register');
Route::get('/entrar', fn () => redirect('/login', 301))->name('legacy.login');
Route::get('/senha/esqueci', fn () => redirect('/forgot-password', 301))->name('legacy.password.request');

Route::middleware('guest')->group(function () {
    Route::get('/acesso-por-codigo', [ClassCodeAccessController::class, 'create'])->name('class-code.create');
    Route::post('/acesso-por-codigo', [ClassCodeAccessController::class, 'lookup'])->middleware('throttle:class-code')->name('class-code.lookup');
    Route::get('/acesso-por-codigo/cadastro', [ClassCodeAccessController::class, 'register'])->name('class-code.register');
    Route::post('/acesso-por-codigo/cadastro', [ClassCodeAccessController::class, 'store'])->middleware('throttle:registration')->name('class-code.store');
});
Route::get('/convites/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.accept');
Route::post('/convites/{token}', [InvitationAcceptanceController::class, 'store'])->middleware('throttle:registration')->name('invitations.store');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/email/verify', [EmailVerificationCodeController::class, 'show'])->name('verification.notice');
    Route::post('/email/verify', [EmailVerificationCodeController::class, 'verify'])->middleware('throttle:email-code')->name('verification.code.verify');
    Route::post('/email/verification-notification', [EmailVerificationCodeController::class, 'resend'])->middleware('throttle:email-code-resend')->name('verification.send');

    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/perfil/foto', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/perfil/foto', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::middleware('verified')->group(function () {
        Route::post('/professor/tutorial/visto', [\App\Http\Controllers\Teacher\TutorialController::class, 'seen'])->middleware('role:teacher')->name('teacher.tutorial.seen');
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::delete('/conta', [AccountController::class, 'destroy'])->name('account.destroy');

        Route::prefix('professor')->name('teacher.')->middleware('role:teacher,admin')->group(function () {
            Route::get('api/estatisticas', [DashboardController::class, 'statistics'])->name('statistics');
            Route::get('api/questoes', [QuestionController::class, 'index'])->name('questions.api');
            Route::post('atividades/{activity}/corrigir-selecionados', [GradingController::class, 'generateSelected'])->middleware('throttle:ai')->name('grading.ai-selected');
            Route::post('atividades/{activity}/corrigir-todos', [GradingController::class, 'generateActivity'])->middleware('throttle:ai')->name('grading.ai-activity');
            Route::get('atividades/{activity}/resultados/editar', [GradingController::class, 'editActivityResults'])->name('grading.activity-results.edit');
            Route::put('atividades/{activity}/resultados', [GradingController::class, 'republishActivityResults'])->name('grading.activity-results.update');
            Route::resource('turmas', ClassroomController::class)->parameters(['turmas' => 'classroom'])->except('destroy')->names('classrooms');
            Route::patch('turmas/{classroom}/entrada-automatica', [ClassroomController::class, 'updateAutoApproveJoin'])->name('classrooms.auto-approve-join');
            Route::post('turmas/{classroom}/convites', [InvitationController::class, 'store'])->middleware('throttle:invites')->name('classrooms.invite');
            Route::patch('turmas/{classroom}/solicitacoes/{student}/aprovar', [MembershipRequestController::class, 'approve'])->name('classrooms.requests.approve');
            Route::delete('turmas/{classroom}/solicitacoes/{student}', [MembershipRequestController::class, 'reject'])->name('classrooms.requests.reject');
            Route::resource('questoes', QuestionController::class)->parameters(['questoes' => 'question'])->except('show')->names('questions');
            Route::resource('atividades', ActivityController::class)->parameters(['atividades' => 'activity'])->names('activities');
            Route::get('atividades/{activity}/visualizar', [ActivityController::class, 'preview'])->name('activities.preview');
            Route::post('atividades/{activity}/publicar', [ActivityController::class, 'publish'])->name('activities.publish');
            Route::get('entregas/{submission}/corrigir', [GradingController::class, 'show'])->name('grading.show');
            Route::get('entregas/{submission}/status-da-ia', [GradingController::class, 'aiStatus'])->name('grading.ai-status');
            Route::post('entregas/{submission}/corrigir-com-ia', [GradingController::class, 'generateAll'])->middleware('throttle:ai')->name('grading.ai-all');
            Route::post('entregas/{submission}/respostas/{answer}/corrigir-com-ia', [GradingController::class, 'generateOne'])->middleware('throttle:ai')->name('grading.ai-answer');
            Route::put('entregas/{submission}/revisar', [GradingController::class, 'review'])->name('grading.review');
            Route::post('entregas/{submission}/publicar', [GradingController::class, 'release'])->name('grading.release');
            Route::post('entregas/{submission}/reabrir', [GradingController::class, 'reopen'])->name('grading.reopen');
        });

        Route::prefix('aluno')->name('student.')->middleware('role:student')->group(function () {
            Route::post('tutorial/visto', [\App\Http\Controllers\Student\TutorialController::class, 'seen'])->name('tutorial.seen');
            Route::get('atividades', [StudentActivityController::class, 'index'])->name('activities.index');
            Route::get('atividades/{activity}', [StudentActivityController::class, 'show'])->name('activities.show');
            Route::put('entregas/{submission}/questoes/{question}', [StudentActivityController::class, 'save'])->middleware('throttle:autosave')->name('answers.save');
            Route::post('entregas/{submission}/enviar', [StudentActivityController::class, 'submit'])->name('submissions.submit');
            Route::get('entregas/{submission}/resultado', [StudentActivityController::class, 'result'])->name('submissions.result');
        });

        Route::prefix('admin')->name('admin.')->middleware(['role:admin', 'audit.sensitive'])->group(function () {
            Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/usuarios', [AdminController::class, 'users'])->name('users');
            Route::get('/academico', [AdminController::class, 'academic'])->name('academic');
            Route::put('/usuarios/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        });
    });
});
