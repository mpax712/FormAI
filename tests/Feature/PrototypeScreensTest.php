<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrototypeScreensTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function publicScreens(): array
    {
        return [
            'landing' => ['/', 'Mais tempo para'],
            'login' => ['/login', 'Bem-vindo de volta'],
            'register' => ['/register', 'Crie sua conta'],
            'forgot password' => ['/forgot-password', 'Recupere sua senha'],
            'admin dashboard' => ['/demo/admin', 'Saúde dos serviços'],
            'admin users' => ['/demo/admin/usuarios', 'Convidar usuário'],
            'admin metrics' => ['/demo/admin/metricas', 'Limites de segurança'],
            'teacher dashboard' => ['/demo/professor', 'Sua atenção'],
            'teacher exams' => ['/demo/professor/provas', 'Minhas provas'],
            'create exam' => ['/demo/professor/provas/nova', 'Contexto para a IA'],
            'question bank' => ['/demo/professor/questoes', 'Banco de questões'],
            'grading queue' => ['/demo/professor/correcoes', 'Aguardando revisão'],
            'grading review' => ['/demo/professor/correcoes/2048', 'A decisão é sua'],
            'student dashboard' => ['/demo/aluno', 'Próximas avaliações'],
            'student exams' => ['/demo/aluno/provas', 'Minhas provas'],
            'take exam' => ['/demo/aluno/provas/1/realizar', 'Salvamento automático ativo'],
            'student result' => ['/demo/aluno/resultados/1', 'Excelente resultado'],
            'profile security' => ['/demo/perfil/seguranca', 'Sessões ativas'],
        ];
    }

    #[DataProvider('publicScreens')]
    public function test_public_prototype_screen_renders(string $url, string $expectedText): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSeeText($expectedText);
    }
}
