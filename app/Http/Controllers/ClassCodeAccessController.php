<?php

namespace App\Http\Controllers;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
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

    public function store(Request $request): RedirectResponse
    {
        $classroom = $this->selectedClassroom($request);
        abort_unless($classroom, 419, 'O acesso por código expirou. Informe o código novamente.');

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(config('formai.password_min_length'))],
            'terms' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ]);

        $user = DB::transaction(function () use ($classroom, $data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'role' => UserRole::Student,
                'is_active' => true,
            ]);

            $classroom->pendingStudents()->attach($user->id, ['status' => 'pending']);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->forget('class_join');
        $request->session()->regenerate();

        return redirect()->route('verification.notice')->with('status', 'Cadastro realizado. Após verificar o e-mail, aguarde a aprovação do professor para entrar na turma.');
    }

    private function selectedClassroom(Request $request): ?Classroom
    {
        return Classroom::query()->whereKey($request->session()->get('class_join.classroom_id'))->where('is_active', true)->first();
    }
}
