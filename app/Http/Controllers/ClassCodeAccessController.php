<?php

namespace App\Http\Controllers;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use App\Application\Services\GuardianAuthorizationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ClassCodeAccessController extends Controller
{
    public function create(): View
    {
        return view('class-code.enter');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $code = Str::upper(preg_replace('/[^A-Z0-9]/i', '', $data['code']));
        $classroom = Classroom::query()->where('join_code', $code)->where('is_active', true)->first();

        if (! $classroom) {
            return back()->withErrors(['code' => 'Código não encontrado. Confira com o professor e tente novamente.'])->onlyInput('code');
        }

        $request->session()->put('class_join.classroom_id', $classroom->id);

        return redirect()->route('class-code.register');
    }

    public function register(Request $request): View|RedirectResponse
    {
        $classroom = $this->selectedClassroom($request);

        if (! $classroom) {
            return redirect()->route('class-code.create')->withErrors(['code' => 'Informe novamente o código da turma.']);
        }

        return view('class-code.register', compact('classroom'));
    }

    public function store(Request $request, GuardianAuthorizationService $guardianService): RedirectResponse
    {
        $classroom = $this->selectedClassroom($request);
        abort_unless($classroom, 419, 'O acesso por código expirou. Informe o código novamente.');

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'guardian_email' => Str::lower(trim((string) $request->input('guardian_email'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(config('formai.password_min_length'))],
            'age_band' => ['required', 'in:under_13,13_17,adult'],
            'guardian_email' => ['required_if:age_band,under_13', 'nullable', 'email:rfc,dns', 'max:255', 'different:email'],
            'terms' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ]);

        $needsGuardian = $data['age_band'] === 'under_13';
        [$user, $autoApprove] = DB::transaction(function () use ($classroom, $data, $needsGuardian): array {
            $currentClassroom = Classroom::query()->whereKey($classroom->id)
                ->where('is_active', true)->lockForUpdate()->firstOrFail();
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'role' => UserRole::Student,
                'is_active' => ! $needsGuardian,
                'age_band' => $data['age_band'],
                'terms_version' => config('legal.terms_version'),
                'terms_accepted_at' => now(),
            ]);

            $autoApprove = $currentClassroom->auto_approve_join;
            $currentClassroom->members()->attach($user->id, [
                'status' => $autoApprove ? 'approved' : 'pending',
                'approved_at' => $autoApprove ? now() : null,
            ]);

            return [$user, $autoApprove];
        });

        if ($needsGuardian) {
            $guardianService->request($user, $data['guardian_email']);
            $request->session()->forget('class_join');
            return redirect()->route('guardian.pending')->with('status', 'Conta criada. Enviamos um link de autorização ao responsável; o acesso ficará bloqueado até a confirmação.');
        }
        event(new Registered($user));
        Auth::login($user);
        $request->session()->forget('class_join');
        $request->session()->regenerate();

        return redirect()->route('verification.notice')->with('status', $autoApprove
            ? 'Cadastro realizado. Verifique seu e-mail para acessar a turma.'
            : 'Cadastro realizado. Após verificar o e-mail, aguarde a aprovação do professor para entrar na turma.');
    }

    private function selectedClassroom(Request $request): ?Classroom
    {
        return Classroom::query()->whereKey($request->session()->get('class_join.classroom_id'))->where('is_active', true)->first();
    }
}
