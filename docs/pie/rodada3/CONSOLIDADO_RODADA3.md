# Auditoria PIE V2: Rodada 3, consolidação e veredito

**Data:** 2026-10-31 a 2026-11-17 · **Commit de partida:** `9fcfde5a`
**Modo:** VARREDURA + CORREÇÃO + VALIDAÇÃO EM RUNTIME ([protocolo](../../../PROTOCOLO_VARREDURA_PONTA_A_PONTA.md))

**Fontes:**

| Frente | Relatório |
|---|---|
| A, Atendimento | [A_ATENDIMENTO](A_ATENDIMENTO.md) |
| B, Caixa e financeiro | [B_CAIXA_FINANCEIRO](B_CAIXA_FINANCEIRO.md) |
| C, Plataforma e acessos | [C_PLATAFORMA_ACESSOS](C_PLATAFORMA_ACESSOS.md) |
| D, App do entregador | [D_APP_ENTREGADOR](D_APP_ENTREGADOR.md) |
| E, App do consumidor | [E_APP_CONSUMIDOR](E_APP_CONSUMIDOR.md) |
| F, Segurança, LGPD e demais módulos | [F_SEGURANCA_LGPD_MODULOS](F_SEGURANCA_LGPD_MODULOS.md), com a matriz em [F_MATRIZ_AUTORIZACAO](F_MATRIZ_AUTORIZACAO.md) |
| G, Inventário cruzado | [G_INVENTARIO_CRUZADO](G_INVENTARIO_CRUZADO.md) |
| G, Produto e simplificação | [G_PRODUTO_SIMPLIFICACAO](G_PRODUTO_SIMPLIFICACAO.md) |

Briefing comum: [00_BRIEFING_RODADA3](00_BRIEFING_RODADA3.md).

## 1. O que a Rodada 3 fez

Na Rodada 2 a auditoria apontou e corrigiu alguns itens. Na Rodada 3 cada frente seguiu o protocolo ponta a ponta. Cada uma ficou com um banco próprio (clone do `pie_r3_base`, Postgres 15 com RLS e runtime `erp_app`) e fez o ciclo completo:

1. reproduziu o defeito;
2. escreveu o teste;
3. **provou que o teste falha sem a correção**;
4. corrigiu;
5. validou na API real e, quando a máquina permitiu, no navegador.

Os agentes caíram várias vezes por limite de uso entre 31/10 e 17/11. Foram sempre retomados com o contexto que tinham, sem recomeçar.

## 2. Execuções do coordenador (17/11, máquina livre)

| Verificação | Resultado |
|---|---|
| Suíte PHP completa (sqlite) | **2103 passed, 0 falhas**, 33 pulados (os de Postgres/RLS), 7771 asserções, 20 min |
| Portão Postgres `composer test:pgsql-rls` (13 classes, `erp_teste`, `erp_app`) | **33 passed, 0 pulados**, 519 asserções. Inclui os novos `PlataformaAcessosSobRlsTest` e `EmpresaConfigSobRlsTest` |
| Migrations novas no Postgres real | `1a0100_busca_de_cliente_sem_acento` e `1f0100_motivo_ajuste_no_ponto` aplicadas sem erro num clone do banco-base |
| SPA: `tsc` / vitest | exit 0 / **181 de 181** |
| App do entregador: `tsc` / vitest (compila o `mobile-shared`) | exit 0 / **42 de 42** |
| App do consumidor: `tsc` / vitest | exit 0 / **44 de 44** |
| E2E no Chromium (desktop), API real no Postgres com RLS | ver §6 |

## 3. Achados por gravidade e situação

Os IDs remetem aos relatórios de cada frente. A seguir, só o que pesa no veredito.

### ALTOS (todos corrigidos, com teste que falha sem a correção)

| ID | Problema | Correção e prova |
|---|---|---|
| **R3-G-01 / D-01 / C-09** | Entregador criado no painel não entrava no app: faltava o vínculo `colaboradores.user_id`, e nenhuma tela permitia fazê-lo | Campo "Usuário de acesso ao app" na ficha do colaborador, validado na mesma rede e sem duplicar a ligação. Aviso na lista de usuários. O login do app responde com `motivo: sem_colaborador` e diz o que fazer |
| **R3-C-11 / F-01** | Configuração, certificado e integrações da empresa davam 500 em **toda revenda nova**: um GET que gravava sob RLS | O GET não grava mais, e a linha nasce com a chave do envelope. `EmpresaConfigSobRlsTest` (Pg) |
| **R3-C-01** | O admin da instalação nova nascia sem papel e não administrava nada | O `RbacSeeder` dá o papel Administrador a quem ainda não tem papel nenhum |
| **R3-C-02 / C-03** | Superadmin sem 2FA efetivo e sem bloqueio por tentativas. Conta desativada continuava operando por até 24 h | 2FA obrigatório antes do token. Bloqueio em 5 falhas / 15 min. `TokenDeContaAtiva` corta o token na hora. `PlataformaAcessosSobRlsTest` (Pg) |
| **R3-E-01** | Qualquer cliente pegava o preço do Gás do Povo mandando `gasdopovo: true` | Exige o programa ligado na revenda e o cliente marcado como beneficiário |
| **R3-E-06** | O store do app do consumidor não persistia o token renovado | Setters persistem. No entregador, conferido que o `persist` do zustand já grava |
| **R3-D-02 / P2-C-05** | Reenvio de "concluir" dava 422 e deixava foto órfã no disco | Situação conferida sob lock antes de gravar o arquivo. Reenvio idempotente. Fila do app com `Idempotency-Key` |
| **R3-D-03** | A renovação de token revogava o antigo na hora: resposta perdida no 3G derrubava a sessão no meio da rota | O token antigo vale por mais 120 s. Renovação centralizada no `mobile-shared` |
| **R3-G-04** | Cadastro de cliente em campo dava 500 com número de endereço acima de 10 caracteres | `max:10` alinhado à coluna, com guardião que compara a regra à migration |
| **R3-A-01 / A-02** | Busca de cliente sensível a acento, e o índice trigram nunca usado sob RLS (`LIKE` não é leakproof) | `BuscaCliente`: candidatos por função SECURITY DEFINER e leitura final sob RLS |
| **R3-A-04** | CPF/CNPJ sem dígito verificador e gravado com máscara | `DocumentoFiscal`, aplicado também no app (R3-E-07) e no colaborador (R3-G-07) |
| **R3-F-04** | CC-e numerada por `max+1` sem trava, com a SEFAZ chamada dentro da transação | Número reservado sob lock, SEFAZ fora da transação. `CartaCorrecaoSequenciaTest` |
| **R3-B-03** | Recebimento: "1.000,00" virava R$ 1 e "-10" aumentava o valor | Campos lidos por `decimalBR`, recusados antes de enviar e conferidos de novo no servidor |

### MÉDIOS e BAIXOS corrigidos (resumo)

- **Caixa:**
  - painel de movimentos legível (R3-B-01);
  - estado da conta, abrir e fechar (B-02);
  - recebimento parcial (B-04);
  - telas de estorno e recibo (B-05);
  - malote (B-06);
  - nome do cliente (B-07);
  - cheque e lançamento manual em `decimalBR` (**B-09**, coordenador);
  - seed da demo pelo `criarConta` (**B-10**, coordenador).
- **Atendimento:** busca por telefone, teto de quantidade configurável, aviso de item inválido, confirmação antes de descartar o pedido, cliente novo sem sair do pedido, telefone no cadastro, teclado no `AsyncSelect`.
- **Plataforma:**
  - mensagens em pt-BR (`lang/pt_BR`);
  - sessão vencida reabre o login sem perder a tela;
  - 404 sem derrubar a SPA;
  - marca neutra no login;
  - menu amarrado à licença (`MenuLicencaContratoTest`).
- **RH e LGPD:** CPF mascarado na listagem; ponto de dia fechado só muda com motivo; trilha de auditoria em ponto, comissão, ASO, recesso e familiar.
- **Autorização antes da validação (R3-F-05):** cliente, pedido, produto e baixa no caixa (**coordenador**: `AutorizaAntesDeValidarTest`, 6 de 7 endpoints falham sem a correção), `POST empresas` (C) e `pagar` do app (E).
- **Apps:**
  - "sem conexão" no lugar de "vazio" e leitura offline;
  - GPS em 2º plano com token próprio;
  - confirmação ao iniciar a rota;
  - contraste e rótulos de acessibilidade;
  - PIX abandonado pode ser reaberto ou cancelado, e saiu o texto falso de "cancelado em 5 min";
  - "em rota" vem do `situacao_papel`.
- **Colaborador com CPF/CEP mascarado (R3-G-07, coordenador):** agora é aceito. O dígito do CPF só é conferido quando o CPF muda, para não travar a edição de colaborador migrado com CPF ruim.

### Matriz de autorização (F)

O F varreu 617 rotas com 2.996 requisições:
- **Isolamento entre revendas:** nenhum vazamento de dados da revenda A para a B, contando listas, CSV/PDF e `X-Empresa-Id` forjado.
- **Tokens negados:** sem token, token de app no painel e token do painel no superadmin foram todos barrados.
- **Lacuna:** em 125 rotas não havia objeto da revenda A no banco-base, e nelas BOLA e BFLA ficaram sem medição.

## 4. Abertos

| ID | O que falta | Por que não foi feito |
|---|---|---|
| R3-F-09 | Chave Google `AIza…` versionada em 4 arquivos (2 no `ctrl-web`, 1 no `legado-nfweb`, 1 .md) | Rotacionar é ação no console do Google, do dono. O `ctrl-web` não pode ser alterado |
| R3-F-10 | `composer audit`: `symfony/yaml` alta (CVE-2026-109413). `mobile-shared` com 2 altas no `npm audit` | Atualização de dependência fica para um commit próprio, com a suíte |
| R3-F-06 | Subrecursos respondem 200 vazio a quem não enxerga o pai | Baixa, sem vazamento de dado |
| R3-A-11 | Revenda sem condição de pagamento não explica o porquê | Depende do onboarding (P2-A-07) |
| D | A venda de missão não tem chave estável por tentativa, e um reenvio pode duplicar | Não coube na rodada |
| E | `package-lock.json` do app do consumidor fora de sincronia (`npm ci` falha) | Regenerar o lock muda as versões resolvidas; vai num commit próprio |
| P2-E-07, P2-E-13, P2-E-10, PIE-26 | PABX multi-revenda, filhas de configuração por grupo, demais itens do F | Exigem desenho |
| Apps | Nenhuma tela de app foi renderizada: sem aparelho, emulador nem Maestro nesta máquina | Jornadas dos apps ficam **PARCIAIS**: lógica testada em vitest e contrato na API real |

## 5. Decisões do dono (nenhuma frente implementou)

1. **Pagamento na entrega** (P2-C-03): como pagou, valor recebido e troco, Vale Gás na mesma transação.
2. **"Fechar o dia" do entregador** (P2-C-13), e encerramento automático de jornada esquecida.
3. **Exclusão de conta no app** (P2-B-05): anonimizar, mantendo o que a lei fiscal exige. Depende de validação jurídica.
4. **Formas de pagamento no app** (P2-B-06): campo `disponivel_app` na condição.
5. **Termos e política por revenda** (P2-B-07).
6. **Cancelar com o entregador a caminho:** esconder o botão ou só confirmar.
7. **Criar revenda pelo superadmin** (P2-A-01), catálogo inicial e condições padrão (P2-A-07), período de teste e plano.
8. **Licença dos apps:** gate no login por `app_entregador` / `app_consumidor`.
9. **Papéis da plataforma** e **nome comercial do produto** no login.
10. **Contraste da cor da marca:** branco sobre `#FF6200` dá 3:1.
11. **RH:** permissões próprias para saúde e remuneração, e o padrão da janela de edição do ponto.

## 6. E2E no navegador

**Ambiente.** Clone `pie_r3_cons` do banco-base com as migrations novas aplicadas. API `php artisan serve` como `erp_app`, com `SAAS_ENFORCE_LICENCA` e `SAAS_ENFORCE_TENANT_ENVELOPE` ligados. SPA no Vite. Chromium 1248, projeto desktop. Specs em `erp-novo/e2e/` (caixa, plataforma, produto, smoke).

**1ª execução: 29 passed, 5 failed, 2 skipped (25,7 min).** Triagem das 5 falhas:

| Falha | Causa | Ação |
|---|---|---|
| `recusa Desconto "5%"` | **Defeito real.** O `decimalBR` aceita "5%" porque serve a campos de percentual. No recebimento, o desconto de 5% virava R$ 5,00 e a baixa era **gravada** | `BaixaDialog.lerValor` recusa `%` nos campos em reais |
| 3 jornadas de Acessos (PIE-34, criação de usuário, PIE-33) | Spec desatualizado: o campo passou a se chamar `Senha *` (asterisco de obrigatório no nome acessível) e o spec buscava `'Senha'` exato | Seletor `/^Senha( \*)?$/` |
| "o de sempre" (atendimento) | Spec desatualizado: com o rótulo ligado ao `AsyncSelect` (R3-A-10), o botão se chama `Cliente *` | Seletor `/^Cliente/` |

**2ª execução das 5: 5 passed.** O total ficou em **34 passed, 0 failed, 2 skipped**:
- skip proposital: o entregador não usa o painel;
- skip por dado: o banco-base não tem o dado exigido.

**O que o E2E provou em runtime, na tela e no banco:**
- login dos 4 perfis, com mensagem em português na senha errada;
- matriz do menu por papel e licença (admin, gerente e operador);
- caixa: saldo e movimentos legíveis, recusa de valor inválido sem chamar o servidor, recebimento parcial, malote, estorno e recibo;
- criação de usuário e o que ele enxerga;
- sessão vencida sem perder o formulário;
- 2FA obrigatório do superadmin, com suspender e reativar;
- pedido por telefone ("o de sempre") e cliente novo no meio da ligação.

**Não executado:** projeto celular (Pixel 7) e o axe completo (escritos nos specs, não rodados nesta passada). Telas dos apps (sem aparelho).

**Ferramenta.** `scripts/pie-costura.mjs` ainda lista como "rota sem consumidor" as rotas dos apps que passam pela fila offline (`concluir`, `ocorrencia`, `pagar`, `perfil/endereco`). São falsos positivos já triados pelo G: o script não reconhece o helper `enviarFormularioOuEnfileirar`.

## 7. Veredito

| Escopo | Rodada 2 | Rodada 3 |
|---|---|---|
| **Painel da revenda (SPA + API)** | NÃO COMPROVADO | **APROVADO COM RESSALVAS** |
| **Apps (consumidor e entregador)** | REPROVADO | **NÃO COMPROVADO EM APARELHO** |
| **SaaS para N revendas** | REPROVADO | **NÃO PRONTO** |

**Painel da revenda.** Suíte completa, portão Postgres e E2E no navegador estão verdes contra a API real sob RLS. Nenhum vazamento entre revendas nas 617 rotas. Os altos desta rodada estão corrigidos e provados. Ressalvas:
- R3-F-09: rotacionar a chave Google versionada;
- R3-F-10: atualizar dependências;
- o projeto celular e o axe ainda não rodaram.

**Apps.** Os bloqueios graves estão corrigidos e provados na API real e em vitest:
- entregador sem acesso;
- sessão derrubada na renovação;
- reenvio de conclusão;
- preço do Gás do Povo;
- token não persistido.

Mas nenhuma tela foi renderizada, e SMS e push reais não foram exercidos. A aprovação exige a jornada em aparelho (Maestro ou roteiro manual) e as decisões 1, 3, 4 e 5.

**SaaS para N revendas.** O isolamento entre revendas se sustentou, e a revenda nova deixou de quebrar na configuração. Mas ainda não existe:
- criar revenda pelo produto;
- catálogo e condições iniciais;
- licença dos apps;
- termos por revenda.

São as decisões 7, 8 e 5 da §5. Sem elas, a revenda nº 2 depende de operação manual.
