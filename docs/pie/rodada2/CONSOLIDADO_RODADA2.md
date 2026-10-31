# Auditoria PIE V2 — Rodada 2: consolidação, correções e veredito

**Data:** 2026-10-30/31 · **Commit de partida:** `133f588d` · **Modo:** AUDITORIA + CORREÇÃO
**Fontes:** [Frente A — Produto/SaaS](FRENTE_A_PRODUTO_SAAS.md) · [Frente B — App do consumidor](FRENTE_B_APP_CONSUMIDOR.md) ·
[Frente C — App do entregador](FRENTE_C_APP_ENTREGADOR.md) · [Frente D — SPA executada no navegador](FRENTE_D_SPA_EXECUTADA.md) ·
[Frente E — Módulos do erp-novo](FRENTE_E_MODULOS_ERP.md) · briefing comum: [00_BRIEFING_RODADA2.md](00_BRIEFING_RODADA2.md)

## 1. O que a Rodada 2 fez de diferente

| Lacuna da Rodada 1 | O que foi feito agora |
|---|---|
| Produto = 11 correções de bug | Frente A avaliou o sistema como **SaaS para N revendas**: personas, onboarding, empacotamento, convenções da Dubena no código, lacunas de mercado, métricas |
| Apps lidos só pelo recorte de segurança | Frentes B e C: inventário de telas, jornada inteira, lojas, offline, GPS, dinheiro em campo, contrato app × API. A fila offline ganhou **11 testes executados** |
| R2 estático (nenhum clique) | Frente D subiu Postgres **com RLS** + Laravel + Vite e percorreu **13 jornadas no Chromium**, desktop e celular, com 127 capturas e axe |
| INV-13..22 e superadmin sem revisão adversarial | Frente E: 11 lotes de testes (529 verdes) + sondas que provaram 4 defeitos ALTOS que a suíte não pega |

As cinco frentes rodaram como subagentes **independentes**, com o mesmo briefing e sem ver as conclusões umas das outras.

**89 achados** no total: 1 crítico, 34 altos, 41 médios e 13 baixos.

## 2. Contestações do Auditor-Chefe

| Ponto | Resolução |
|---|---|
| **P2-B-01** ("o app não cria pedido") é extraordinário demais para aceitar por relato | Conferido pelo Auditor-Chefe no código (`carrinho.tsx` × `AppPedidoController::criarPedido`) e depois por teste com o payload literal do carrinho: **422 antes, 201 depois**. CONFIRMADO |
| **P2-A-02, P2-D-04 e P2-E-01** (superadmin cego) vieram de três frentes | A Frente A mediu o banco (FI); a D viu na tela; a E provou por sonda no Postgres. Reprovado de novo pelo Auditor-Chefe no Postgres local (`SuperAdminSobRlsTest` falha sem a correção). CONFIRMADO, consolidado em um item |
| **P2-E-02** dizia que `cobranca/*` furava a licença no plano Essencial | O Essencial **contrata** `cobranca` (`PlanosSeeder::ESSENCIAL`); a sonda da Frente E usava um plano sem cobrança. O furo é real, mas para plano que não contrata cobrança. O teste de aceite foi escrito sem contrato nenhum, para cada alias medir o próprio recurso |
| **P2-D-03** (papel global vaza) | A Frente D disse que "o Gate de RBAC não é afetado". Conferido: `temPermissao` usa outra consulta, mas `papeisEfetivos` alimenta o **ABAC** (`PolicyEvaluator`), a **trava contra escalonamento** (`PapelController`) e o `/me`. Mantida ALTA |
| **P2-D-01**: a correção proposta (abrir o `WITH CHECK`) não bastou | Com a policy corrigida, o login continuou dando 500: o `INSERT … RETURNING` do Eloquent faz o Postgres exigir também o `USING` de leitura. A correção virou policy **mais** insert sem `RETURNING` para o evento sem tenant. Registrado como armadilha no `CLAUDE.md` |
| O teste do P2-D-03 escrito pelo Auditor-Chefe falhou **com** a correção | A causa era o teste: a factory de `User` já dá a todo usuário o papel "Administrador de teste". As asserções foram isoladas para medir só o vazamento do papel global, e o teste foi reprovado de novo sem a correção |

## 3. Achados por causa raiz

### RC1 — Defeito que só existe no Postgres (a suíte roda em sqlite)
A mesma classe do `CLAUDE.md` ("Comando de console lê ZERO linhas sob RLS"), agora em rotas HTTP.

| ID | Problema | Situação |
|---|---|---|
| P2-E-01 / A-02 / D-04 | SuperAdmin vê 0 empresas; suspender e trocar plano dão 404 (o PIE-04 ficava sem porta) | **CORRIGIDO**: middleware `plataforma.owner`; `SuperAdminSobRlsTest` (Pg) |
| P2-D-01 | Login de usuário `support` dá 500 (`security_events` sem tenant) | **CORRIGIDO**: migration de policy + insert sem `RETURNING`; `LoginSuporteSobRlsTest` (Pg) |
| P2-D-02 | Usuário criado em "Usuários e permissões" leva 403 em tudo (sem membership/grant) | **CORRIGIDO**: `AcessoDoColaborador` concede na empresa ativa; `UsuarioNovoSobRlsTest` (Pg) |
| P2-D-07 | `GET /config-global` dá 500 em revenda nova (quebra rastreamento e Maps) | **CORRIGIDO**: `BelongsToGrupo` detecta a chave pela tabela; `ConfigGlobalSobRlsTest` (Pg) |
| P2-E-07 | Webhook do PABX 404 sob RLS; telefonia presa a uma empresa por env | PENDENTE (exige desenho multi-revenda da telefonia) |
| P2-E-13 | Filhas de configuração por grupo sem RLS por migration | PENDENTE (baixa) |

### RC2 — Contrato app × API sem teste
| ID | Problema | Situação |
|---|---|---|
| **P2-B-01** | **CRÍTICA**: todo pedido do app recebia 422 (`lat/lng` exigidos, o app não manda) — desde junho | **CORRIGIDO**: cliente do token resolvido antes da validação; `PieAppConsumidorPedidoTest` com o payload literal do carrinho |
| P2-B-02 | Endereço escolhido ignorado; pedido a 250 km aceito com raio de 5 km | **PARCIAL**: o servidor usa a coordenada do `endereco_id` (ou do favorito) para cerca e setor, e recusa endereço alheio. Pendente: gravar o endereço de entrega **no pedido** (coluna nova + app do entregador) |
| P2-B-21, P2-C-18 | Apps sem teste de tela/contrato; o entregador não tinha runner | **PARCIAL**: vitest no app do entregador (15 testes); contrato ainda sem teste automatizado |

### RC3 — Regra inferida do texto em vez da marca declarada
| ID | Problema | Situação |
|---|---|---|
| P2-C-04 | "Saiu para entrega" contém "entreg": ao iniciar a rota, tudo ia para o Histórico | **CORRIGIDO**: API expõe `situacao_efeito`/`situacao_papel`; app classifica por eles (`etapaDoPedido`, 4 testes) |

### RC4 — Porta lateral na autorização e na licença
| ID | Problema | Situação |
|---|---|---|
| P2-D-03 | Papel global de QUALQUER usuário vazava para todos (`orWherePivotNull` sem parênteses) | **CORRIGIDO**; `PapelGlobalTest` |
| P2-E-02 / A-03 | Aliases `cobranca/*`, `crm/*`, `financeiro/dre`, `cupons-fiscais` furavam a licença | **CORRIGIDO**; `LicencaEnforcementTest`. Pendente: recursos de app (`app_consumidor`, `app_entregador`, `marketplace`, `tempo_real`) não são exigidos por rota nenhuma |
| P2-E-03 | Operador extraía a base de clientes pela mala direta, sem trilha | **CORRIGIDO**: `cliente.export.completo` + `security_events`; `MatrizPapeisReaisTest` |
| P2-E-04 | Gerente lia a trilha de auditoria pelo relatório | **CORRIGIDO**; `MatrizPapeisReaisTest` |
| P2-E-05 | Operador mudava preço de venda pelo cadastro | **CORRIGIDO**: campos de preço ignorados sem `produto.preco`; `MatrizPapeisReaisTest` |
| P2-E-06 | 2FA do superadmin não habilitável; sem lockout; token sobrevive à desativação | PENDENTE — **ALTA** (fluxo novo de 2FA) |
| P2-E-11 | RH: CPF em toda listagem; ASO (saúde) sob `colaborador.view`; ponto reescrevível sem trilha | PENDENTE (permissões novas mudam papéis das revendas) |
| P2-E-10 | Atos da plataforma sem trilha (migração, descartes) | PENDENTE |

### RC5 — Dinheiro em campo
| ID | Problema | Situação |
|---|---|---|
| P2-C-01 | "Recusar" em pedido concluído tirava a venda do malote do entregador | **CORRIGIDO**: só PENDENTE, sob lock; o app esconde ações de pedido encerrado e confirma a recusa. `PieEntregadorCampoTest` |
| P2-C-02 | Vale Gás não pago, expirado ou vencido aprovado; nunca consumido | **PARCIAL**: verificação só aceita PAGO e válido. Pendente: **resgatar** o vale na conclusão (depende de P2-C-03) |
| P2-C-03 | Entrega concluída sem forma de pagamento, valor recebido, troco nem vasilhame | PENDENTE — **ALTA**, regra de negócio (ver §5) |
| P2-C-05 | Concluir exige rede e não é idempotente (reenvio → 422 e arquivo órfão) | PENDENTE — ALTA (multipart na fila) |
| P2-C-13 | Sem carga embarcada, vasilhame nem "meu acerto" | PENDENTE (produto) |

### RC6 — Fila offline (completa a correção do PIE-16)
| ID | Problema | Situação |
|---|---|---|
| P2-C-06 | 4xx descartado em silêncio; 409 "em processamento" tratado como sucesso; item 5xx travava a fila; ação nova passava à frente da pendente; duas sincronizações simultâneas | **CORRIGIDO**: lista "precisa de atenção" com a frase do servidor (tela de Ganhos e aviso no logout), 409 transitório, trava por processo, ordem preservada, falta de sinal não esgota. 11 testes; **7 falham sem a correção** |
| P2-C-08 | Erro de validação em inglês ("Request failed with status code 422") | **CORRIGIDO** (mensagem do servidor) |

### RC7 — Sessão de 24 h sem renovação nos dois apps
P2-B-03, P2-C-09 — PENDENTE, **ALTA**: o consumidor cai numa tela de erro sem saída; o entregador é deslogado no meio da rota e o GPS para. Exige `token/refresh` no núcleo `mobile-shared` com teste.

### RC8 — O produto ainda não é vendável para a revenda nº 2
P2-A-01 (não há como criar revenda pelo produto), P2-A-05 (sem faturamento nem ciclo de vida de assinatura), P2-A-07 / D-14 (revenda nasce vazia, sem catálogo nem a situação "Em Rota"), P2-D-13 (sem condição de pagamento, o pedido falha sem explicar), P2-A-04 (empacotamento corta o básico do Essencial), P2-A-08 (marca "Dubena" no login de todas as revendas), P2-A-10 (fuso único), P2-A-12 (sem métricas de produto), P2-A-06, A-11, A-13, A-14 — **PENDENTES**. São decisões de produto e de negócio (§5); o roteiro priorizado está na Frente A (O1–O10).

### RC9 — LGPD e lojas
P2-B-05 (excluir conta só desativa; CPF, e-mail e telefones ficam), P2-B-07 (termos de outra empresa; `/privacidade` e `/excluir-conta` com 404), P2-B-09 (a ficha promete cartão), P2-B-10 (captura da Play com nome e celular possivelmente reais), P2-B-11 (chave Maps no cliente), P2-C-07 (GPS fora do turno), P2-A-09 (modelo marketplace × white-label) — **PENDENTES**, com decisão jurídica ou comercial.
P2-E-08 (chave do Google e endereço no log) — **CORRIGIDO**: `MensagemSemSegredo`; o job relança sem encadear a exceção original.

### RC10 — UX da SPA e dos apps, observada de verdade
| Corrigido | Pendente |
|---|---|
| P2-D-06 (`whereKey` em `DB::table` → `where "key"`; guardião que detecta, provado com regressão plantada) · P2-D-08 ("R$ 1.000" virava R$ 1,00) · P2-E-09 (replay do dia vazio) | P2-D-05 (busca diferencia acento — **ALTA**, exige `unaccent` ou coluna normalizada), P2-B-04 (PIX abandonado prende o cliente — **ALTA**), P2-B-06 (consumidor escolhe condição a prazo — **ALTA**, configuração por revenda), D-09, D-10, D-11, D-12, D-15, D-16, D-17, B-08, B-12, B-13, B-15, B-16, C-10, C-11, C-12, C-14 (parcial), C-15, C-16 |

Reconferidos no navegador pela Frente D: **PIE-15** OK, **PIE-32** OK, **PIE-17** parcial (agora corrigido por P2-D-08). **R2-09, R2-10, PIE-33, PIE-34 e PIE-35 continuam abertos**: a Rodada 1 os listava como pendentes, e a execução confirma.

### RC11 — Acessibilidade e cadeia de suprimentos
P2-B-14, P2-C-17 e P2-D-19 (0 rótulos de acessibilidade nos apps; laranja da marca com contraste 3:1; axe acusa contraste nas 5 telas principais e erro crítico de ARIA em /clientes); P2-B-20 e P2-C-18 (`npm audit`: 4 críticas no app do consumidor, 1 no do entregador, quase todas de ferramenta de build); P2-B-19 (release Android depende de `android/` local) — PENDENTES.

## 4. Correções desta rodada e prova

Cada teste de aceite foi rodado **com e sem** a correção (`git stash` do arquivo corrigido). Todos falharam sem ela, salvo os casos de guarda indicados.

| Correção | Arquivos | Teste de aceite | Sem a correção |
|---|---|---|---|
| P2-B-01, B-02 | `AppPedidoController`, `PedidoMobileService`, `carrinho.tsx` | `PieAppConsumidorPedidoTest` (6) | 4 falham (1 é guarda) |
| P2-D-03 | `User::papeisEfetivos` | `PapelGlobalTest::test_papel_global_de_um_usuario_nao_vaza_para_outro` | falha |
| P2-E-01 | `PlataformaEnxergaTodasAsEmpresas`, `routes/api.php`, `bootstrap/app.php` | `SuperAdminSobRlsTest` (**Pg**) | falha (empresa ausente da lista) |
| P2-D-01 | migration `2026_10_31_000100`, `AuditoriaSeguranca` | `LoginSuporteSobRlsTest` (**Pg**) | 500 `violates row-level security` |
| P2-D-02 | `AcessoDoColaborador`, `UsuarioController` | `UsuarioNovoSobRlsTest` (**Pg**) | 403 "Empresa sem vinculo…" |
| P2-D-07 | `BelongsToGrupo` | `ConfigGlobalSobRlsTest` (**Pg**) | 500 `violates row-level security` |
| P2-E-02 | `RecursoPorRota` | `LicencaEnforcementTest::test_aliases_…` | falha (`cobranca/boletos` 200) |
| P2-E-03, E-04, E-05 | `MalaDiretaController`, `RelatorioController`, `ProdutoController` | `MatrizPapeisReaisTest` (3 novos) | 3 falham |
| P2-C-01, C-02 | `EntregaService`, `AppSolicitacaoController`, `pedido/[id]/index.tsx` | `PieEntregadorCampoTest` (3) | 2 falham (1 é guarda) |
| P2-C-04 | `AppEntregadorController`, `helpers/situacao.ts`, telas Início e Entregas | `situacao.test.ts` (4) | — (função nova) |
| P2-C-06, C-08 | `filaOffline.ts`, `envioResiliente.ts`, `useSincronizacao`, Ganhos, Perfil | `filaOffline.test.ts` (11) | **7 falham** |
| P2-D-06 | `SorteioNumero`, `ChecklistPergunta`, `EmpresaConfigController` | `SemWhereKeyNoQueryBuilderTest` (guardião, piso de 500 arquivos) | detecta a regressão plantada |
| P2-D-08 | `frontend/src/lib/format.ts` | `format.test.ts` | — |
| P2-E-08 | `MensagemSemSegredo`, `RoadsApiAjustador`, `GeocodificarClienteJob` | `MensagemSemSegredoTest` | — (classe nova) |
| P2-E-09 | `MonitoraController` | `MonitoraApiTest::test_historico_de_um_dia_…` | falha |

Infraestrutura de teste nova: vitest no `app-entregador` (o lock ganhou **só** os 84 pacotes do vitest, sem versão alterada); trait `Tests\Feature\Concerns\RevendaSobRls`; o portão `composer test:pgsql-rls` passou de 26 para **30 testes**.

## 5. Decisões que são do dono (bloqueiam itens ALTOS)

1. **Exclusão de conta no app (P2-B-05, LGPD e lojas):** anonimizar nome, CPF, e-mail, nascimento, telefones e endereços, e apagar o usuário, **mantendo** pedidos e notas pela obrigação fiscal? Esse é o desenho usual. A regra de retenção precisa de validação jurídica.
2. **Formas de pagamento no app (P2-B-06):** quais condições a revenda expõe ao consumidor. Proposta: um campo "aparece no app" por condição, desligado por padrão para a prazo.
3. **Pagamento na entrega (P2-C-03, C-02):** o entregador registra forma, valor recebido, troco e vale? E a conclusão resgata o Vale Gás? Define o malote e o acerto.
4. **Modelo dos apps (P2-A-09, C-19):** um app marketplace único com seleção de revenda, ou um white-label por revenda? Muda pacote, termos, controlador LGPD e ficha da loja.
5. **Empacotamento (P2-A-04):** relatório de vendas, fechamento de caixa e comissões entram no Essencial?
6. **Faturamento da plataforma (P2-A-05):** como a revenda paga o SaaS (PSP, carência, inadimplência).
7. **Termos e política (P2-B-07):** texto próprio do produto em `/privacidade` e `/excluir-conta`.

## 6. Veredito

### Inicial (antes das correções desta rodada): **REPROVADO**
Bloqueadores confirmados: o app do consumidor não criava pedido (P2-B-01, CRÍTICA); o entregador apagava venda do próprio malote (P2-C-01); papel de Administrador vazava para todos (P2-D-03); o superadmin não conseguia suspender revenda (P2-E-01); a revenda não conseguia dar acesso a um funcionário novo (P2-D-02); a base de clientes saía por uma porta sem permissão (P2-E-03).

### Pós-correção: **REPROVADO para produção dos apps e para venda do SaaS** · erp-novo (painel da Dubena): **NÃO COMPROVADO**
Os bloqueadores acima foram corrigidos e provados. Seguem abertos itens **ALTOS confirmados** que impedem liberar:

- **Apps:** sessão de 24 h sem renovação (RC7); PIX abandonado prende o consumidor (P2-B-04); exclusão de conta que não exclui (P2-B-05); entrega sem registro de pagamento (P2-C-03); concluir sem idempotência (P2-C-05).
- **SaaS:** não há como criar nem cobrar uma revenda pelo produto (P2-A-01, A-05).
- **Painel:** busca de cliente sensível a acento (P2-D-05); 2FA do superadmin inexistente na prática (P2-E-06).

Para o painel operado pela Dubena, os bloqueadores de código conhecidos foram fechados. Continuam valendo as pendências de credencial e ambiente da Rodada 1 (Firebase, A1/SEFAZ, PSP, produção inexistente), por isso o veredito é **NÃO COMPROVADO**, e não APROVADO.

**Não afirma** que o sistema é seguro, que está em conformidade com a LGPD, que os apps serão aprovados nas lojas, nem que "tudo funciona". Afirma o que cada teste listado em §4 prova, nos ambientes indicados: sqlite, PostgreSQL 15 local com RLS e Chromium local. Nada foi verificado em aparelho real nem na homologação.

## 7. Ordem recomendada para a próxima rodada de correção

1. **RC7** (renovação de token nos dois apps) e **P2-C-05** (concluir pela fila, com idempotência): sem decisão do dono, esforço M.
2. **P2-D-05** (busca sem acento, com `unaccent` + índice, medido como `erp_app`) e **P2-E-06** (2FA do superadmin).
3. Depois das decisões do §5: P2-B-05, B-06, C-03 (com o resgate do Vale Gás), B-04.
4. **RC8** pela ordem da Frente A: O1 (provisionar revenda numa tela), O4 (fonte única para licença e menu), O8 (catálogo inicial e checklist "pronto para vender").
5. Lote de UX da SPA (R2-09, R2-10, PIE-33/34/35, D-09..D-12, D-15..D-17) e acessibilidade (RC11).
