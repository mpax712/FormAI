<?php

return [
    'teacher' => [
        'version' => 1,
        'welcome' => 'Bem-vindo ao FormAI! Vamos conhecer as principais funcionalidades do sistema. Este tutorial mostrará como criar turmas e atividades, acompanhar entregas e utilizar a correção assistida por inteligência artificial.',
        'steps' => [
            ['title' => 'Painel inicial', 'text' => 'O painel apresenta suas turmas, atividades, entregas e resultados recentes. Os indicadores ajudam a acompanhar o aprendizado.', 'target' => 'dashboard', 'route' => 'dashboard'],
            ['title' => 'Adicionar alunos à turma', 'text' => 'Crie uma turma em Turmas. O aluno pode entrar pelo código: aprove os pedidos em “Solicitações de entrada” ou ative “Autorizar entrada automaticamente” para novos cadastros. Você também pode convidá-lo por e-mail. Alunos com acesso veem as atividades publicadas.', 'target' => 'classrooms-menu', 'route' => 'dashboard'],
            ['title' => 'Banco de questões', 'text' => 'Cadastre questões em Questões para reutilizá-las posteriormente em diferentes atividades.', 'target' => 'questions-menu', 'route' => 'dashboard'],
            ['title' => 'Atividades', 'text' => 'Aqui você pode criar, editar, visualizar e publicar atividades. Clique em “Atividades” para continuar.', 'target' => 'activities-menu', 'route' => 'dashboard', 'interaction' => true, 'instruction' => 'Agora é sua vez: clique em “Atividades” para continuar.'],
            ['title' => 'Nova atividade', 'text' => 'Comece uma proposta para sua turma. Clique em “Nova atividade” para conhecer o formulário. Não é necessário preencher ou salvar dados durante o tutorial.', 'target' => 'new-activity', 'route' => 'teacher.activities.index', 'interaction' => true, 'instruction' => 'Agora é sua vez: clique em “Nova atividade” para continuar.'],
            ['title' => 'Prepare sua atividade', 'text' => 'Informe título, turma e prazo. Adicione dissertativas, escolha única, múltipla escolha ou blocos de contexto. Os blocos mostram textos de apoio e não valem pontos. Salve um rascunho para revisar antes de publicar.', 'target' => 'activity-data', 'route' => 'teacher.activities.create'],
            ['title' => 'Correção das entregas', 'text' => 'Abra a entrega para corrigir manualmente ou pedir uma sugestão da IA para as dissertativas. Revise nota e feedback antes de publicar. Nesta seção você define como a IA prepara a sugestão.', 'target' => 'grading-preferences', 'route' => 'teacher.activities.create'],
            ['title' => 'Perfil de correção', 'text' => 'Econômico prioriza custo e rapidez; Equilibrado é a opção intermediária; Avançado permite análise mais longa. O perfil não garante acerto. Escolha a rigidez separadamente e revise a sugestão.', 'target' => 'grading-profile', 'route' => 'teacher.activities.create'],
            ['title' => 'Análise privada para o professor', 'text' => 'Escolha análise curta, média ou detalhada conforme o que precisa revisar. Essa análise fica com o professor. O feedback ao aluno é separado e só aparece após a publicação do resultado.', 'target' => 'feedback-detail', 'route' => 'teacher.activities.create'],
            ['title' => 'Rigidez da correção', 'text' => 'Esta opção orienta o quanto a IA deve exigir ao avaliar os critérios. Ela não altera a pontuação máxima nem publica notas. Escolha um nível e confira cada sugestão antes de decidir.', 'target' => 'grading-strictness', 'route' => 'teacher.activities.create'],
            ['title' => 'Rascunho e publicação', 'text' => 'Salvar rascunho permite continuar editando. Publicar torna a atividade disponível aos alunos aprovados na turma. Confira questões, pontuação e prazo antes de publicar.', 'target' => 'activity-data', 'route' => 'teacher.activities.create'],
            ['title' => 'Entregas e prazos', 'text' => 'A lista de atividades informa quantos entregaram e quantos faltam. Abra uma atividade para ver as entregas, escolher correção manual ou com IA e revisar cada aluno.', 'target' => 'activity-list', 'route' => 'teacher.activities.index'],
            ['title' => 'Resultados e ajuda', 'text' => 'A nota só aparece ao aluno quando você publica o resultado. Em Meu perfil, você pode refazer este tutorial a qualquer momento; as dicas nas páginas explicam cada ação importante.', 'target' => 'profile-link', 'route' => 'dashboard'],
            ['title' => 'Tudo pronto!', 'text' => 'Crie sua primeira turma e atividade quando estiver pronto. As dicas continuam disponíveis nas páginas e você pode refazer este tutorial em Meu perfil.', 'route' => 'dashboard'],
        ],
    ],
    'student' => [
        'version' => 1,
        'welcome' => 'Bem-vindo ao FormAI! Este guia mostra onde encontrar atividades, salvar respostas, enviar sua entrega e consultar resultados. Você pode refazê-lo em Meu perfil.',
        'steps' => [
            ['title' => 'Seu painel', 'text' => 'Os números mostram atividades a fazer, prazos encerrados e resultados recentes. Cada cartão abaixo abre uma tarefa ou um resultado disponível.', 'target' => 'student-dashboard', 'route' => 'dashboard'],
            ['title' => 'Entrada na turma', 'text' => 'Se você entrou por código, sua entrada pode ser automática ou depender da aprovação do professor. Enquanto houver uma solicitação pendente, as atividades da turma ainda não aparecem. Um convite pode dar acesso após o cadastro e a verificação do e-mail.', 'route' => 'dashboard'],
            ['title' => 'Minhas atividades', 'text' => 'Clique em Minhas atividades para ver todas as tarefas disponíveis e seus prazos.', 'target' => 'student-activities-menu', 'route' => 'dashboard', 'interaction' => true, 'instruction' => 'Clique em “Minhas atividades” para continuar.'],
            ['title' => 'Estados e prazos', 'text' => 'Aqui aparecem atividades não iniciadas, rascunhos, entregas recebidas e resultados publicados. Se ainda não houver atividades, aguarde o professor publicar uma para sua turma.', 'target' => 'student-activity-list', 'route' => 'student.activities.index'],
            ['title' => 'Responder e salvar', 'text' => 'Abra uma atividade e leia os blocos de contexto antes das perguntas. Escreva as respostas e marque uma ou várias alternativas conforme o enunciado. Aguarde “Salvo agora” antes de sair.', 'target' => 'student-activity-list', 'route' => 'student.activities.index'],
            ['title' => 'Enviar é definitivo', 'text' => 'O botão “Enviar atividade” entrega suas respostas. Depois, apenas o professor pode reabrir para novas alterações. Confira todas as respostas antes de confirmar.', 'target' => 'student-activity-list', 'route' => 'student.activities.index'],
            ['title' => 'Notas e comentários', 'text' => 'O resultado aparece somente quando o professor o publica. Até lá, a entrega pode mostrar apenas que foi recebida. A nota final é decidida pelo professor.', 'target' => 'student-results', 'route' => 'dashboard'],
            ['title' => 'Perfil e ajuda', 'text' => 'Em Meu perfil você atualiza seu e-mail, protege a conta e pode refazer este tutorial. As dicas nas páginas explicam os controles quando precisar.', 'target' => 'profile-link', 'route' => 'dashboard'],
            ['title' => 'Tudo pronto!', 'text' => 'Você já sabe como acompanhar, responder e consultar suas atividades. Volte a este guia em Meu perfil quando quiser.', 'route' => 'dashboard'],
        ],
    ],
];
