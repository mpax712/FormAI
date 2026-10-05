# Cadastro e autorização no piloto escolar

O texto exibido em `/termos-e-privacidade` é identificado pela versão em `config/legal.php`. Os três cadastros exigem aceite; a conta guarda a versão e a data. Contas criadas antes desta mudança continuam sem registro presumido de aceite.

Para aplicar o fluxo, execute `php artisan migrate` no banco usado pela instalação. O cadastro de menores de 13 anos cria a conta bloqueada e envia um link ao e-mail do responsável. O link vence em sete dias. Depois da autorização, o aluno recebe um código de 6 números para confirmar seu próprio e-mail. Quando entra por código, a matrícula na turma pode ser automática ou aguardar a aprovação do professor, conforme a configuração da turma no momento do cadastro. O código do aluno vence em 15 minutos por padrão e um reenvio invalida o anterior.

Em desenvolvimento, `MAIL_MAILER=log` grava o e-mail no log e não o entrega ao responsável. Para usar o fluxo na escola, configure um transporte de e-mail real, `MAIL_FROM_ADDRESS` válido e teste entrega, expiração e reenvio. Mantenha o agendador Laravel ativo para a rotina de anonimização de contas desativadas.

Antes de usar o fluxo com menores em uma escola real, a escola e o responsável pelo projeto devem revisar juridicamente o texto, a confirmação de idade e a forma de comprovar a responsabilidade legal. O link atual confirma acesso à caixa de e-mail informada; ele não comprova a identidade do responsável.
