@extends('layouts.app')
@section('title', 'Termos de Uso e Privacidade · FormAI')
@section('content')
<article class="legal-document card p-4 p-md-5 mx-auto" aria-labelledby="legal-title">
    <p class="text-secondary mb-2">Piloto escolar · versão {{ config('legal.terms_version') }}</p>
    <h1 id="legal-title">Termos de Uso e Privacidade</h1>
    <p>O FormAI é um projeto piloto para organizar atividades escolares e apoiar a correção. O contato para dúvidas sobre o serviço ou seus dados é <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>. Leia estas informações antes de criar sua conta.</p>

    <h2>O que você pode fazer</h2>
    <p>Professores criam turmas, questões e atividades, acompanham entregas e revisam resultados. Alunos entram por código ou convite, respondem às atividades e veem notas e comentários quando o professor os publica. Ao entrar por código, o acesso à turma pode ser automático ou depender da aprovação do professor, conforme a configuração da turma. Proteja sua senha e use a conta apenas para suas atividades escolares.</p>

    <h2>Quais dados são usados</h2>
    <p>O sistema guarda nome, e-mail, senha protegida por hash, vínculo com turmas, questões, respostas, notas, comentários, o aceite dos termos e registros necessários para operar e proteger o serviço. No cadastro de menores de 13 anos, também guarda a faixa etária informada, o e-mail do responsável e o registro da decisão dele. Professores e administradores autorizados podem acessar informações necessárias às suas funções. O aluno vê suas próprias atividades e resultados publicados.</p>

    <h2>Correção com inteligência artificial</h2>
    <p>Quando o professor solicitar correção por IA, o texto da questão, a resposta do aluno, a resposta esperada, critérios e instruções podem ser enviados ao provedor configurado. Nome, e-mail e turma não são enviados como campos de identificação, mas o próprio texto digitado pode conter dados pessoais. A disponibilidade, o processamento e a retenção pelo provedor dependem das condições desse serviço. A IA pode errar: suas notas são sugestões e só chegam ao aluno após revisão e publicação pelo professor. Também é possível corrigir manualmente.</p>

    <h2>Alunos menores de idade</h2>
    <p>O conteúdo é apresentado de forma simples para alunos. Para menores de 13 anos, o acesso só é liberado depois que o responsável informado confirmar a autorização pelo link enviado ao seu e-mail. O link comprova o acesso à caixa de e-mail informada, mas não verifica por si só a idade nem a responsabilidade legal. Isso não substitui os cuidados e as autorizações adicionais que a escola ou a legislação possam exigir.</p>

    <h2>Conta, exclusão e contato</h2>
    <p>Você pode pedir a desativação da conta pelo perfil. A conta é desativada imediatamente. A rotina agendada do sistema anonimiza os dados de identificação da conta depois de 30 dias, quando estiver em execução. Respostas, notas, registros operacionais e cópias de segurança podem permanecer; esse pedido não significa apagamento integral de todos os dados. Para dúvidas, correções ou pedidos relacionados aos seus dados, escreva para <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>

    <h2>Seu aceite</h2>
    <p>Ao marcar a caixa no cadastro, você confirma que leu estes termos e concorda com as regras de uso desta versão. O sistema registra a versão e a data do aceite. Para menores de 13 anos, o responsável precisa confirmar separadamente a autorização de acesso.</p>
</article>
@endsection
