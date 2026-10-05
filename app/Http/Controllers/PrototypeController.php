<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PrototypeController extends Controller
{
    public function adminDashboard(): View { return view('prototype.admin.dashboard'); }
    public function users(): View { return view('prototype.admin.users', ['users' => $this->usersData()]); }
    public function metrics(): View { return view('prototype.admin.metrics'); }
    public function teacherDashboard(): View { return view('prototype.teacher.dashboard', ['exams' => $this->examsData()]); }
    public function teacherExams(): View { return view('prototype.teacher.exams.index', ['exams' => $this->examsData()]); }
    public function createExam(): View { return view('prototype.teacher.exams.create'); }
    public function questionBank(): View { return view('prototype.teacher.questions', ['questions' => $this->questionsData()]); }
    public function gradings(): View { return view('prototype.teacher.gradings.index', ['attempts' => $this->attemptsData()]); }
    public function reviewGrading(string $attempt): View { return view('prototype.teacher.gradings.show', ['attemptId' => $attempt]); }
    public function studentDashboard(): View { return view('prototype.student.dashboard'); }
    public function studentExams(): View { return view('prototype.student.exams.index'); }
    public function takeExam(string $exam): View { return view('prototype.student.exams.take', ['examId' => $exam]); }
    public function result(string $attempt): View { return view('prototype.student.results.show', ['attemptId' => $attempt]); }

    /** @return array<int, array<string, string>> */
    private function usersData(): array
    {
        return [
            ['name' => 'Marina Souza', 'email' => 'marina@escola.edu.br', 'role' => 'Professora', 'status' => 'Ativo', 'last_access' => 'Hoje, 09:42'],
            ['name' => 'Carlos Mendes', 'email' => 'carlos@escola.edu.br', 'role' => 'Professor', 'status' => 'Ativo', 'last_access' => 'Ontem, 18:10'],
            ['name' => 'Ana Beatriz', 'email' => 'ana@aluno.edu.br', 'role' => 'Aluna', 'status' => 'Ativo', 'last_access' => 'Hoje, 10:03'],
            ['name' => 'Lucas Almeida', 'email' => 'lucas@aluno.edu.br', 'role' => 'Aluno', 'status' => 'Convite pendente', 'last_access' => 'Nunca'],
            ['name' => 'Rafael Lima', 'email' => 'rafael@formai.dev', 'role' => 'Admin técnico', 'status' => 'Ativo', 'last_access' => 'Agora'],
        ];
    }

    /** @return array<int, array<string, string|int>> */
    private function examsData(): array
    {
        return [
            ['title' => 'Revolução Industrial', 'class' => 'História · 2º A', 'questions' => 10, 'submissions' => '26/30', 'status' => 'Em andamento', 'date' => '22 ago, 14:00'],
            ['title' => 'Interpretação de texto', 'class' => 'Português · 1º B', 'questions' => 8, 'submissions' => '31/31', 'status' => 'Corrigindo', 'date' => '20 ago, 08:00'],
            ['title' => 'Brasil República', 'class' => 'História · 3º A', 'questions' => 12, 'submissions' => '28/28', 'status' => 'Finalizada', 'date' => '15 ago, 10:00'],
            ['title' => 'Iluminismo', 'class' => 'História · 2º B', 'questions' => 6, 'submissions' => '—', 'status' => 'Rascunho', 'date' => 'Não publicada'],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function questionsData(): array
    {
        return [
            ['type' => 'Dissertativa', 'subject' => 'História', 'text' => 'Explique duas consequências sociais da Revolução Industrial.', 'usage' => '4 provas'],
            ['type' => 'Alternativa única', 'subject' => 'História', 'text' => 'Qual acontecimento marcou o início da Revolução Francesa?', 'usage' => '3 provas'],
            ['type' => 'Múltipla escolha', 'subject' => 'Geografia', 'text' => 'Selecione os fatores relacionados à urbanização.', 'usage' => '2 provas'],
            ['type' => 'Verdadeiro ou falso', 'subject' => 'História', 'text' => 'O Iluminismo defendia o absolutismo monárquico.', 'usage' => '1 prova'],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function attemptsData(): array
    {
        return [
            ['student' => 'Ana Beatriz', 'exam' => 'Interpretação de texto', 'submitted' => 'Hoje, 09:57', 'status' => 'Revisão necessária', 'score' => '7,8 sugerida'],
            ['student' => 'João Pedro', 'exam' => 'Interpretação de texto', 'submitted' => 'Hoje, 09:54', 'status' => 'Revisão necessária', 'score' => '8,5 sugerida'],
            ['student' => 'Mariana Alves', 'exam' => 'Revolução Industrial', 'submitted' => 'Hoje, 09:40', 'status' => 'Processando IA', 'score' => '—'],
            ['student' => 'Gabriel Rocha', 'exam' => 'Brasil República', 'submitted' => '15 ago, 10:46', 'status' => 'Publicada', 'score' => '9,0'],
        ];
    }
}
