<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ClassCodeAccessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\ActivityController as StudentActivityController;
use App\Http\Controllers\Teacher\ActivityController;
use App\Http\Controllers\Teacher\ClassroomController;
use App\Http\Controllers\Teacher\GradingController;
use App\Http\Controllers\Teacher\InvitationController;
use App\Http\Controllers\Teacher\MembershipRequestController;
use App\Http\Controllers\Teacher\QuestionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::get('/health', HealthController::class)->name('health');

Route::middleware('guest')->group(function () {
    Route::get('/acesso-por-codigo', [ClassCodeAccessController::class, 'create'])->name('class-code.create');
    Route::post('/acesso-por-codigo', [ClassCodeAccessController::class, 'lookup'])->middleware('throttle:class-code')->name('class-code.lookup');
    Route::get('/acesso-por-codigo/cadastro', [ClassCodeAccessController::class, 'register'])->name('class-code.register');
    Route::post('/acesso-por-codigo/cadastro', [ClassCodeAccessController::class, 'store'])->middleware('throttle:registration')->name('class-code.store');
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:auth');
    Route::get('/cadastro', [RegisterController::class, 'create'])->name('register');
    Route::post('/cadastro', [RegisterController::class, 'store'])->middleware('throttle:registration');
    Route::get('/senha/esqueci', [PasswordController::class, 'forgot'])->name('password.request');
    Route::post('/senha/email', [PasswordController::class, 'email'])->middleware('throttle:auth')->name('password.email');
    Route::get('/senha/redefinir/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/senha/redefinir', [PasswordController::class, 'update'])->middleware('throttle:auth')->name('password.update');
});
Route::get('/mfa', [MfaController::class, 'create'])->name('mfa.challenge');
Route::post('/mfa', [MfaController::class, 'store'])->middleware('throttle:auth')->name('mfa.verify');
Route::get('/convites/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.accept');
Route::post('/convites/{token}', [InvitationAcceptanceController::class, 'store'])->middleware('throttle:registration')->name('invitations.store');

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/perfil/foto', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/perfil/foto', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::get('/email/verificar', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verificar/{id}/{hash}', [VerificationController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/email/reenviar', [VerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::delete('/conta', [AccountController::class, 'destroy'])->name('account.destroy');

        Route::prefix('professor')->name('teacher.')->middleware('role:teacher,admin')->group(function () {
            Route::resource('turmas', ClassroomController::class)->parameters(['turmas' => 'classroom'])->except('destroy')->names('classrooms');
            Route::post('turmas/{classroom}/convites', [InvitationController::class, 'store'])->middleware('throttle:invites')->name('classrooms.invite');
            Route::patch('turmas/{classroom}/solicitacoes/{student}/aprovar', [MembershipRequestController::class, 'approve'])->name('classrooms.requests.approve');
            Route::delete('turmas/{classroom}/solicitacoes/{student}', [MembershipRequestController::class, 'reject'])->name('classrooms.requests.reject');
            Route::resource('questoes', QuestionController::class)->parameters(['questoes' => 'question'])->except('show')->names('questions');
            Route::resource('atividades', ActivityController::class)->parameters(['atividades' => 'activity'])->except('destroy')->names('activities');
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
