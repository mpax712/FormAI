# Revisão de segurança e acesso a dados — 28/09/2026

Esta revisão cobre o código da aplicação e os endpoints de estatísticas e questões. Não é um teste de intrusão nem uma avaliação da configuração externa do Supabase ou dos provedores de IA.

## Controles encontrados

- Laravel Fortify faz autenticação, verificação de senha com hash, confirmação de e-mail e oferece autenticação em dois fatores/passkeys. Login, cadastro, acesso por código, convites, autosave e solicitações de IA têm limites de requisição. Contas inativas são bloqueadas por middleware.
- Rotas e políticas restringem professor, aluno e administrador; consultas de questões e estatísticas obtêm o proprietário do usuário autenticado. Operações de escrita usam validação e proteção CSRF. Os acessos administrativos sensíveis são registrados em `audit_logs`.
- Respostas autenticadas têm `Cache-Control: private, no-store`. Há redirecionamento HTTPS em produção, HSTS sobre HTTPS, CSP, bloqueio de frames, `nosniff` e política de referência. O cache privado no servidor é separado por proprietário e criptografado; o snapshot de correção da IA também usa cast criptografado.
- O snapshot enviado à IA omite nome, e-mail e turma e usa um identificador HMAC. Ainda contém a pergunta, a resposta do aluno, rubrica e instruções: a transferência ao provedor deve ser considerada no aviso de privacidade e no contrato com o provedor.

## SQL injection e APIs

As entradas HTTP inspecionadas chegam ao banco por Eloquent/Query Builder com parâmetros vinculados. Os `selectRaw` encontrados em estatísticas usam apenas expressões SQL fixas. O comando de criação de schema em `routes/console.php` interpola `DB_SCHEMA` somente depois de validá-lo como identificador alfanumérico/underscore; não recebe entrada HTTP. Não foi encontrada interpolação direta de entrada do navegador em SQL bruto.

Testes de feature cobrem autenticação, papel, isolamento entre professores, filtros inválidos, entradas semelhantes a SQL injection, payload mínimo da API agregada e exclusão de respostas esperadas/instruções privadas da API de questões. Isso não prova ausência universal de falhas. A busca textual por questões usa `LIKE` parametrizado: `%` e `_` digitados pelo usuário podem ampliar a busca, mas não alteram a estrutura da consulta SQL.

## Pontos para acompanhamento

- A CSP permite `unsafe-inline` em estilos e carrega Bootstrap de um CDN com SRI. Reduzir essa exceção e hospedar os arquivos localmente diminuiria a dependência externa; exige revisão visual e de compatibilidade antes da troca.
- A segurança e retenção dos dados enviados aos provedores de IA dependem também das configurações e dos contratos desses serviços, fora do escopo desta revisão de código.
- O `sessionStorage` adicionado ao painel guarda apenas três contadores agregados por até 30 segundos, separados por usuário/filtro e apagados no logout. Ele não armazena respostas, notas individuais ou nomes de alunos.
