# Cache, estatísticas e correção em massa

O sistema usa Laravel: o navegador acessa controllers HTTP autenticados e o backend consulta o banco via Eloquent. Já existiam endpoints JSON para respostas e status da IA. Agora também existem `GET /professor/api/estatisticas` e `GET /professor/api/questoes` (usar `Accept: application/json`), com sessão autenticada, conta ativa, e-mail verificado e restrição de professor. Questões são sempre filtradas pelo usuário autenticado; a listagem JSON não inclui gabaritos nem instruções privadas.

## Armazenamento

O cache padrão passa a ser arquivo, fora de `public`, em `storage/framework/cache/data`. A configuração antiga `CACHE_STORE=database` é convertida para `file`; `array` continua disponível para testes. Os dados permanentes continuam no banco. Nenhuma tabela foi removida.

Listagens e seletores de questões usam cache privado criptografado com a chave da aplicação, separado por professor, filtro e página, com duração de 60 segundos. Criação, edição e arquivamento invalidam a versão das listagens do proprietário. Estatísticas agregadas têm cache por professor de 30 segundos; os contadores gerais do dashboard mantêm 60 segundos. O prompt-base reutiliza o cache existente de 300 segundos. O preenchimento acontece sob demanda, evitando carregar todas as respostas e dados pessoais antecipadamente.

Respostas de alunos, credenciais, notas individuais, gabaritos e permissões não são adicionados aos novos caches de listagem. As autorizações são verificadas em cada requisição. Respostas HTTP autenticadas usam `Cache-Control: private, no-store`. Proteger permissões de `storage` e a chave da aplicação continua sendo necessário. Cache de arquivo é local a uma instância; implantação com várias instâncias exige armazenamento compartilhado adequado.

## Correção

“Corrigir todos com IA”, dentro da atividade, solicita análise das respostas dissertativas de todas as entregas enviadas/em processamento. Rascunhos e entregas já revisadas/publicadas são excluídos. Cada resposta mantém um job e chave de idempotência; repetir o botão não recria análises já solicitadas para a mesma versão e orientações. As questões objetivas continuam usando o gabarito. As sugestões precisam de revisão e publicação pelo professor.

Disparar vários jobs juntos não reduz o número de tokens. Agrupar respostas em um único prompt pode economizar a repetição do contexto, mas o consumo e a qualidade precisam ser medidos com respostas reais. A implementação mantém requisições por resposta e registra consumo informado pelo provedor, sem fazer chamadas pagas durante os testes. A Batch API do Gemini oferece desconto de preço de 50% com processamento assíncrono, segundo https://ai.google.dev/gemini-api/docs/batch-api; essa modalidade externa não foi implementada. Desconto de preço não significa redução na quantidade de tokens.

Para N respostas da mesma questão, se o contexto comum tiver P tokens, requisições separadas repetem aproximadamente N × P tokens de contexto. Um prompt agrupado poderia usar P mais os identificadores de cada resposta, economizando aproximadamente (N − 1) × P, antes de considerar saída, limites de contexto e novas tentativas. Essa é uma estimativa estrutural, não uma medição do tokenizer. A escolha atual permite repetir somente a resposta que falhou e mantém o vínculo individual entre resposta, sugestão e revisão.

## Ativação

Executar `php artisan migrate` para criar `activity_questions.import_correction`, e `php artisan config:clear` caso exista configuração compilada. O worker da fila `ai` e o agendador continuam necessários (fluxo existente em `composer dev`). A migração foi validada em SQLite temporário; não foi aplicada ao banco configurado da aplicação.

A escolha de importar resposta esperada, rubrica e instruções é armazenada por questão da atividade e respeitada na publicação. Desativar usa avaliação geral. O gabarito de alternativas é sempre preservado para correção objetiva.

`FORM_AI_TEACHER_INSTRUCTION_MAX=4001` acomoda as orientações gerais (2.000 caracteres), uma quebra de linha e as orientações da questão (2.000). Instalações com limite explícito menor continuam truncando o texto nesse limite.

O ambiente de desenvolvimento estava sem `pdo_sqlite`. Para a validação foi extraído um pacote de driver em `/tmp`, sem instalação no sistema. O fallback de banco foi desativado em testes para impedir a troca automática para outro banco. A suíte geral ainda apresenta falhas nas expectativas dos protótipos e no redirecionamento do cadastro; os testes dos fluxos alterados são executados separadamente.
