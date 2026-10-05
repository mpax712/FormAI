# Correção com IA: configuração e implantação

## Comportamento

As atividades salvam `feedback_detail` (`short`, `medium`, `detailed`) e `intelligence_profile` (`economy`, `balanced`, `advanced`). O padrão é médio/equilibrado. Os POSTs existentes de correção aceitam os mesmos dois campos opcionais; sem campos, herdam a atividade. Uma seleção pontual não altera os padrões da atividade.

Cada execução armazena o conteúdo renderizado do pedido, instrução de sistema, critérios, versões da resposta/entrega e opções. O snapshot é criptografado com `APP_KEY` e excluído da serialização JSON. Não rotacione/remova a chave sem planejar a leitura dos snapshots antigos. `teacher_feedback` é privado, omitido do JSON e mostrado apenas na tela autorizada do professor; não é usado para preencher o feedback do aluno. Sugestões anteriores continuam legíveis sem esse campo.

Pedidos ativos e sugestões concluídas com o mesmo conteúdo/opções são reutilizados. Um novo pedido para a mesma questão, mesmo com opções diferentes, espera cinco minutos após a conclusão do anterior (`AI_RETRY_COOLDOWN_SECONDS`, mínimo 60). Ao criar a nova execução, solicitações antigas encerradas com falha e suas tentativas são removidas do banco; sugestões concluídas e decisões humanas são preservadas. As reservas de quota do provedor não são apagadas. Os endpoints de solicitação também limitam cada professor a 3 pedidos/minuto e 20/hora. A revisão humana e a publicação continuam sendo ações separadas. Reabrir/alterar a versão da entrega invalida pedidos pendentes anteriores.

Uma requisição externa por resposta, sem Batch API nem agrupamento de alunos. Até quatro chamadas externas por execução, incluindo erros HTTP e chamadas cujo worker foi interrompido. Uma interrupção pode ter consumido tokens sem devolver o resultado; esse consumo permanece desconhecido, nunca zero. As travas impedem workers concorrentes de criar sugestões duplicadas; não há garantia de execução externa exatamente uma vez após uma queda de processo.

## Habilitação administrativa

O `.env.example` habilita OpenRouter nos três perfis com [`stealth/space-bunny-alpha`](https://openrouter.ai/stealth/space-bunny-alpha), raciocínio habilitado (`EFFORT=enabled`), JSON Object e até 8192 tokens de saída. Os três perfis usam o mesmo modelo nesta configuração. Gemini e OpenAI permanecem desabilitados como alternativas. Alterar apenas `AI_PROVIDER` ou o modelo padrão do serviço não habilita um destino de perfil.

Preencha `OPENROUTER_API_KEY` somente no `.env`. A integração usa `https://openrouter.ai/api/v1/chat/completions`, `Authorization: Bearer`, mensagens `system`/`user`, `reasoning.enabled=true` e `response_format=json_object`. O schema completo é incluído na instrução e notas/feedbacks são validados localmente. O endpoint atual da Space Bunny Alpha pode retornar texto inválido quando recebe JSON Schema, por isso esse modo não é usado. Também não envie `provider.require_parameters=true`: o OpenRouter filtra o único endpoint do modelo e responde HTTP 404. Saídas incompletas e recusas não geram sugestões. Os tokens de `usage.prompt_tokens`/`completion_tokens` alimentam o histórico de consumo. O formato fica no plano da execução e participa da chave de idempotência.

No momento desta configuração, a Space Bunny Alpha informa preços de entrada e saída iguais a zero no catálogo do OpenRouter. O teto administrativo permanece US$ 1,00 por execução para compatibilidade com a validação dos perfis; com preços zero, a estimativa de uma chamada concluída com telemetria é zero. Os limites locais são 10 chamadas/minuto, 50/hora, 50/dia, 100000 tokens/minuto e uma chamada concorrente, sujeitos à margem de 80% já utilizada pela aplicação (até 40 chamadas locais por 24 horas). Confira o painel e `GET /api/v1/key` porque preços e limites de um modelo alpha podem mudar. Chamadas realizadas fora do sistema e os testes reais também consomem a quota externa. O job respeita HTTP 429 e `Retry-After`. Consulte os [limites do OpenRouter](https://openrouter.ai/docs/api_reference/limits). HTTP 402 é tratado como saldo/quota esgotado; erros retornados no corpo de uma resposta HTTP 200 também são identificados.

Configure `OPENROUTER_API_KEY`, `GEMINI_API_KEY` ou `OPENAI_API_KEY`, sem incluí-las no controle de versão. Para cada combinação de perfil (`ECONOMY`, `BALANCED`, `ADVANCED`) e provedor (`OPENROUTER`, `GEMINI`, `OPENAI`), preencha:

| Sufixo de `AI_{PERFIL}_{PROVEDOR}_` | Valor necessário |
| --- | --- |
| `ENABLED` | `true` somente depois das verificações abaixo |
| `MODEL` | Identificador disponível na sua conta |
| `EFFORT` | `enabled`/`disabled` para enviar `reasoning.enabled`, um nível de esforço suportado pelo modelo, ou `omit` para não enviar raciocínio |
| `SUPPORTED_EFFORTS` | Lista verificada separada por vírgulas; inclua `enabled`, `disabled`, níveis aceitos ou `omit`, conforme aplicável |
| `STRUCTURED_OUTPUT` | `true` para saída JSON compatível com o mapper; em `json_object`, o schema é instruído no prompt e validado localmente |
| `RESPONSE_FORMAT` (OpenRouter) | `json_schema` para modelos com suporte nativo ao schema; `json_object` como alternativa compatível |
| `MAX_OUTPUT_TOKENS` | Orçamento total de saída, incluindo tokens de raciocínio quando cobrados/limitados como saída |
| `INPUT_USD_PER_MTOK` / `OUTPUT_USD_PER_MTOK` | Preços verificados por milhão de tokens, sem contar com descontos |
| `QUOTA_GROUP` | `openrouter`, `gemini` ou `openai` por padrão |

Defina `AI_{PERFIL}_COST_CEILING_USD`, teto conservador para as quatro chamadas. Cada destino precisa caber em um quarto desse teto; preços diferentes entre destinos não permitem ultrapassá-lo. A estimativa considera bytes UTF-8 de entrada como reserva conservadora de tokens e o orçamento máximo de saída. Não é uma medição de cobrança. O custo conhecido por tentativa fica em `grading_attempts` enquanto a execução existir; ao substituir uma falha, seu histórico de tentativas é excluído do banco. Os contadores de quota continuam no cache para não recuperar artificialmente a capacidade consumida. Valores não retornados pelo provedor ficam nulos.

A saída mínima é 1200/2200/3800 tokens para curto/médio/detalhado, acrescida de 180 por critério. Modelos que usam raciocínio podem precisar de orçamento maior para não truncar o JSON. A escolha do professor seleciona um perfil; ela não promete maior acerto e não é enviada como um esforço universal para as duas APIs.

Confirme os modelos/capacidades/preços na documentação e no painel da conta antes de habilitar. `ai:preflight` faz apenas validação local, **não comprova** que o modelo existe, que o preço continua correto ou que a credencial tem acesso. Testes reais pagos devem ser autorizados e executados separadamente.

## Quotas e operação em uma máquina

Preencha `AI_OPENROUTER_RPM`, `AI_OPENROUTER_RPH`, `AI_OPENROUTER_RPD`, `AI_OPENROUTER_TPM` e as equivalentes `AI_GEMINI_*`/`AI_OPENAI_*` para os destinos habilitados. Use os limites reais disponíveis; onde não há limite do provedor, defina um orçamento operacional explícito. Zero mantém o grupo bloqueado. `RPM` significa por **minuto**, `RPH` por hora, `RPD` por dia. A aplicação usa janelas móveis conservadoras e 80% dos valores. Comece com concorrência 1 em cada grupo.

Grupos representam quotas compartilhadas do projeto/modelo, não professores nem chaves individuais. Por padrão todos os perfis de um provedor compartilham um grupo conservador. Se separar grupos em `config/ai_grading.php`, não duplique a capacidade de uma mesma quota compartilhada. Quotas de diferentes modelos ainda podem compartilhar um limite de projeto: nesse caso mantenha um único grupo com os limites mais restritivos.

O cache de arquivo guarda somente contadores/reservas/travas, sem respostas ou chaves. Todos os workers devem usar o mesmo diretório e ter permissão de leitura/escrita. Reduções de quota valem também para pedidos já enfileirados. Não limpe o cache durante o processamento: isso apagaria o histórico de limites. Após perda do cache, pause a fila por uma janela de 24 horas antes de retomar, para não reutilizar capacidade já consumida. Esta implementação não atende múltiplas máquinas e não conhece chamadas feitas fora da aplicação.

Tokens são reservados conservadoramente pelo máximo de cada chamada, sem devolução baseada na resposta. Isso pode reduzir a vazão, mas evita subcontar chamadas sem telemetria. O espaçamento e a concorrência são compartilhados entre professores e workers. A espera libera o job sem incrementar `grading_runs.attempts`; somente uma chamada admitida incrementa a contagem.

Em 429, respeita `Retry-After`/`RetryInfo`, sem trocar de provedor para contornar limite. Quota diária identificada ou cobrança esgotada encerra o pedido com orientação específica. Timeout/conexão/5xx: uma repetição espaçada no principal; persistindo, alternativa válida. Autenticação/modelo indisponível pausa o destino e permite alternativa. Três falhas técnicas consecutivas pausam o destino por cinco minutos. Recusa, certificado e resposta inválida não causam repetição cega.

Cada chamada tem até 60s, worker 90s, trava/lease 100s e `retry_after` 120s. O prazo global padrão é 24h (`AI_QUEUE_TIMEOUT_SECONDS=86400`), separado do timeout HTTP. Esperar mais de cinco minutos não cancela automaticamente a correção.

## Implantação

1. Bloqueie novos pedidos de IA e drene a fila antiga **com a versão antiga**. Não publique este código enquanto workers antigos estiverem executando.
2. Faça backup do banco e da `APP_KEY`. Publique o código e aplique `php artisan migrate` no ambiente pretendido. A migration é aditiva; não apaga sugestões antigas. O rollback remove os novos campos/histórico e deve ser evitado após uso.
3. Preencha modelos, capacidades, preços, tetos e quotas. Recarregue a configuração com `php artisan config:cache` e execute `php artisan ai:preflight` usando o mesmo usuário do worker.
4. Reinicie os workers com `php artisan queue:restart`. Mantenha o scheduler ativo; para worker contínuo: `php artisan queue:work database --queue=ai,default --timeout=90`. O job tem tentativas de fila ilimitadas até seu prazo global, mas no máximo quatro chamadas externas.
5. Libere pedidos depois de validar com mocks, conferir a interface e observar fila/histórico. O cache de quota deve ser preservado durante reinícios.

Nenhuma migration foi aplicada automaticamente ao banco configurado no `.env`; os testes usam SQLite isolado e APIs simuladas. Não houve chamadas pagas.
