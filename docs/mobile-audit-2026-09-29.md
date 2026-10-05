# Auditoria mobile e acessibilidade — 29/09/2026

## Resultado

Foram examinadas as páginas reais de visitantes, professores, alunos e administradores. O layout autenticado agora usa uma folha de estilos exclusiva para telas até 767 px e uma navegação inferior por papel. Os testes de navegador cobrem 23 telas em 320, 360, 390, 720, 768 e 1440 px; não encontraram rolagem horizontal da página. Seis telas autenticadas também passaram na análise automatizada de WCAG 2.2 AA em 320 px. A análise automatizada não substitui o teste com leitor de tela e aparelho físico.

| Prioridade | Ponto encontrado | Alteração e verificação |
| --- | --- | --- |
| Alta | O menu colapsado exigia abrir o topo para alternar entre áreas frequentes no celular. | Barra inferior para professor, aluno e administrador, com estado atual e alvos de pelo menos 44 px. Perfil e saída continuam no menu superior. |
| Alta | As barras fixadas da correção, do editor e da prévia podiam competir com a nova navegação e encobrir campos em telas baixas. | Essas barras deixam de ficar fixadas no celular. Teste em 390 × 450 px verificou que as ações podem ser trazidas acima da navegação. |
| Alta | Tabelas de atividades, usuários, gestão acadêmica e alunos da turma eram difíceis de ler em 320 px. | Linhas apresentadas como cartões com rótulos visíveis no celular, mantendo o conteúdo e as ações. A tabela de auditoria permanece rolável, identificada e navegável por teclado. |
| Média | Controles pequenos e textos longos tinham pouco espaço de toque e risco de corte. | Botões compactos, seleção de entregas, alternativas e interruptor de critérios receberam áreas maiores; textos e formulários ganharam quebra e largura flexível. |
| Média | O cabeçalho da turma e o código de entrada estouravam a largura com texto ampliado a 200%. | Ações passam a empilhar; o código pode quebrar após o hífen. Sete páginas representativas passaram em 320 px com fonte ampliada a 200%. |
| Média | A barra inferior poderia reduzir o espaço de digitação com o teclado virtual. | Em dispositivos de toque, ela se oculta enquanto um campo de texto ou seleção está em foco. O comportamento ainda deve ser conferido em iOS e Android físicos. |

## Critérios e limites

As verificações cobrem refluxo em 320 px, ampliação de texto, foco visível, alvos de toque e movimento reduzido conforme a [WCAG 2.2](https://www.w3.org/TR/wcag/). A interface pública conserva o CSS compartilhado e passou nos testes de largura; o novo visual de aplicativo fica restrito às páginas autenticadas. A simulação de fonte ampliada e de tela baixa não reproduz por completo o teclado virtual, o zoom nativo nem leitores de tela de aparelhos físicos.

## Verificações executadas

- Compilação de templates Blade e verificação sintática dos testes JavaScript.
- Playwright: layout responsivo, navegação, cartões, controles e análise axe em páginas autenticadas; tutorial mobile; telas públicas.
- O PHP local não tem SQLite instalado. Para executar os testes, foi carregada uma extensão temporária em `/tmp`, sem alterar a instalação do sistema ou o banco do projeto.
