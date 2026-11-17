# G — Inventário cruzado (protocolo §4) · Rodada 3

**Agente:** G (inventário cruzado e produto) · **Data:** 2026-10-31 · **Commit de partida:** `9fcfde5a` + working tree com as correções em andamento dos agentes A–F (o que li é o estado do disco entre ~01h e ~03h; partes da SPA e dos apps mudaram durante a varredura — onde isso importa, está dito).
**Não corrige código de produto.** Cada achado tem dono sugerido (briefing §3). IDs `R3-G-NN` (numeração única para este documento e o `G_PRODUTO_SIMPLIFICACAO.md`).

## 1. Ambiente

| Item | Valor |
|---|---|
| Banco | `pie_r3_g` (`create database pie_r3_g template pie_r3_base`), Postgres 15 local 55432 |
| API | `php artisan serve --port=8217` como `erp_app`, `SAAS_ENFORCE_LICENCA=true`, `SAAS_ENFORCE_TENANT_ENVELOPE=true` (receita do `erp-novo/e2e/README.md`) |
| SPA | `npx vite --port 5217` (proxy `/novo/api` feito pela fixture do Playwright) |
| Ajuste manual no MEU banco | `update colaboradores set user_id=4 where id=4` — sem isso o `entregador@pie.local` não entra no app (ver R3-G-01). Nada foi gravado no `pie_r3_base`. |
| Scripts (scratchpad, não versionados) | `costura-estrita.mjs` (cópia do `pie-costura` que expande o CRUD genérico), `menu.mjs` (menu × autorizar × licença, estático), `matriz-api2.mjs` (menu × perfil pela API real), `catchphp.mjs`, `toast.mjs` |
| Specs versionados | `erp-novo/e2e/produto/menu-permissao-licenca.spec.ts`, `erp-novo/e2e/produto/tarefas-atendimento.spec.ts`, `erp-novo/e2e/produto/apoio.ts` |

**Limitação de ambiente (real, medida):** com sete agentes na máquina, o `php artisan serve` do Windows respondeu `/api/me` em 5–34 s e `/api/login` em 11–16 s, com um `Maximum execution time of 30 seconds exceeded` no log. As duas execuções do spec de matriz no navegador estouraram o tempo do login (a fixture `entrar` desiste em 10 s — por isso criei `entrarDevagar` em `produto/apoio.ts`) e a sessão caiu duas vezes por limite de uso. A matriz §4.3 foi então **medida pela API** (mesmo token, mesmas rotas que cada tela chama) — ver §4. O spec de navegador fica versionado para ser reexecutado com a máquina livre.

---

## 2. §4.1 — Rota × consumidor

### 2.1 Saída do script

`node scripts/pie-costura.mjs` (31/10): **617 rotas, 451 chamadas** (SPA 384, consumidor 29, entregador 38), **0 chamadas sem rota, 0 métodos divergentes, 89 rotas sem consumidor visível.**

### 2.2 O próprio script esconde rotas sem consumidor — R3-G-02

O casamento trata segmento dinâmico da chamada como coringa. A SPA tem um CRUD genérico com o **primeiro** segmento em variável (`frontend/src/features/acessos/api.ts:119-132`, `recursoEstrutura`: ``api.get(`/${rota}`)``, ``api.put(`/${rota}/${id}`)``, ``api.post(`/${rota}`)``, ``api.delete(`/${rota}/${id}`)``). Essas quatro chamadas "consomem" **toda** rota de 1 ou 2 segmentos com o mesmo método. Expandindo o `rota` pelos três valores reais (`unidades`, `departamentos`, `setores-org`), a contagem sobe de **89 para 110**. As 21 rotas mascaradas:

| Rota mascarada | Situação real |
|---|---|
| `GET/POST cidades`, `PUT/DELETE cidades/{id}`, idem `regioes` e `setores` (12) | Duplicatas legadas: a SPA usa o alias `/cadastros/{tipo}` (`CadastroSlugs.php:50-55`). Legado. |
| `GET assinatura` | **Metade**: a revenda não tem tela "meu plano" (liga com P2-A-05). Dono C |
| `POST boletos` (gerar boleto de uma parcela) | **Metade** — ver R3-G-05 (cobrança sem tela). Dono B |
| `POST vale-gas` (vender vale no painel) | **Metade**: a tela de Vale-Gás só lista, imprime e baixa (`valegas/ValeGasPage.tsx`). Dono F |
| `PUT/DELETE pedidos/{id}` | Editar/excluir pedido não existe na SPA (só troca de situação). Provável decisão de produto; registrar. Dono A |
| `PUT fiscal/config`, `GET logradouros-oficiais`, `GET conciliacao-contabil`, `GET notas` | Duplicatas/aliases (config fiscal vive em `empresas/{id}/fiscal`; demais sem tela, ver §2.3) |

**Segundo ponto cego:** chamada que só existe em **código morto** conta como consumidor. O Knip (§5) achou 2 arquivos e 10 hooks exportados sem uso, e as rotas que só eles chamam aparecem como "consumidas" (R3-G-03).

**Terceiro ponto cego (falso negativo novo, ~03h):** rodando o script de novo no fim, o total foi a **90**: `POST caixa/movimentos/{id}/estornar` ganhou consumidor (o agente B pôs o estorno na `CaixaTab`), mas `POST entregador/pedidos/{id}/concluir` e `.../ocorrencia` **saíram** da lista de consumidas. Não é regressão do app: o agente D trocou `PrepareForm` por `enviarFormularioOuEnfileirar` (`app-entregador/src/services/entrega.service.ts:77,97`), helper que o script não conhece. Precisa de um padrão novo (método fixo POST), senão a costura passa a acusar a rota mais importante do entregador.

**Recomendação ao coordenador (dono do script):** expandir chamadas com 1º segmento em variável a partir dos valores literais passados à função (ou ao menos avisar), e descontar chamadas em arquivos que o Knip marca como não usados. Não editei o script (fora da minha área).

### 2.3 Triagem das 89, uma a uma

Classes: **FP** = falso positivo (consumidor real indicado) · **METADE** = backend pronto, falta tela/fluxo · **LEG** = legado, alias ou integração (consumidor indicado ou redundante com outro caminho que tem consumidor) · **MORTA** = sem consumidor e sem papel claro.

| # | Rota | Controller@método | Classe | Consumidor real / tela que deveria chamar | Dono |
|---|---|---|---|---|---|
| 1 | `DELETE colaboradores/{id}/comissoes/{cid}` | ColaboradorController@deleteComissao | METADE | Aba Comissões (`rh/tabs/ComissoesTab.tsx`) é só leitura (`useComissoes`) | F |
| 2 | `DELETE colaboradores/{id}/recessos/{rid}` | @deleteRecesso | METADE | Aba Recessos só leitura (`RecessosTab.tsx:7`) | F |
| 3 | `DELETE financeiro/lancamentos/{id}` | FinanceiroController@cancelar | METADE | Cancelar título: `LancamentosTab` não tem a ação | B |
| 4 | `DELETE veiculos/{id}/pneus/{pid}` | VeiculoController@excluirPneu | METADE | Aba Pneus é `TimelineTab` só leitura (`frota/VeiculosPage.tsx:99`) | F |
| 5 | `DELETE veiculos/{id}/trocas-oleo/{tid}` | @excluirTrocaOleo | METADE | Aba Óleo só leitura (`VeiculosPage.tsx:98`) | F |
| 6 | `GET boletos/remessas` | BoletoController@remessas | METADE | Aba Boletos (`FinanceiroExtraTabs.tsx:163`) só lista e diz "a geração ocorre no fluxo de cobrança" — fluxo que não existe (R3-G-05) | B |
| 7 | `GET boletos/remessas/{id}/arquivo` | @baixarRemessa | METADE | idem (baixar .rem) | B |
| 8 | `GET boletos/{id}/pdf` | @pdf | METADE | Imprimir boleto (comentário do controller: "lacuna BLOQUEANTE … o boleto nunca virava papel") — a SPA continua sem o botão | B |
| 9 | `GET caixa/movimentos/{id}/recibo` | CaixaController@recibo | METADE | Recibo do balcão: `CaixaTab` não chama | B |
| 10 | `GET cobranca/boletos` | BoletoController@index | LEG | Alias F08 de `GET boletos` (consumido em `financeiro/api.ts:141`) | B |
| 11 | `GET cobranca/remessas` | @remessas | LEG | Alias de #6 | B |
| 12 | `GET cobranca/remessas/{id}/arquivo` | @baixarRemessa | LEG | Alias de #7 | B |
| 13 | `GET comodatos/{id}/movimentos/{m}/recibo` | ComodatoController@recibo | **FP** | `abrirPdf` em `comodatos/api.ts:108` ← `ComodatoDetalhe.tsx:174` | F |
| 14 | `GET crm/mala-direta` | MalaDiretaController@index | MORTA | Nenhuma tela; a exportação de clientes (`ExportarClientesDialog`) cobre o caso com a mesma permissão. Candidata a remoção (reduz superfície LGPD) | F |
| 15 | `GET empresas/{id}/cidades` | CidadeController@cidadesDaEmpresa | METADE | Cidades de atuação da revenda no marketplace: nenhuma tela em `features/empresas` | C |
| 16 | `GET financeiro/conciliacao-contabil` | FinanceiroController@conciliacaoContabil | METADE | Sem aba; o `conciliacao-contabil` sem prefixo também não tem consumidor | B |
| 17 | `GET fiscal/config` | ConfigFiscalController@show | LEG | Duplicata: a configuração fiscal é feita em `empresas/{id}/fiscal` (`FiscalDaEmpresa.tsx`) | F |
| 18 | `GET fiscal/ibpt` | NotaFiscalController@ibpt | METADE | Consulta de carga tributária (Lei 12.741) sem tela; serve ao cálculo interno | F |
| 19 | `GET fiscal/nf-entrada/{id}` | NfEntradaController@show | METADE (baixa) | Detalhe da NF de entrada: a aba só lista/importa/processa | F |
| 20 | `GET fiscal/nfe/{id}/danfe` | @danfe | LEG | Alias de `notas/{id}/danfe` (consumido, #27) | F |
| 21 | `GET fiscal/sped-contribuicoes` | @spedContribuicoes | METADE | EFD-Contribuições: a aba SPED só gera o ICMS/IPI (`fiscal/api.ts:92`) | F |
| 22 | `GET logradouros-oficiais/divergencias` | LogradouroOficialController@divergencias | METADE | Relatório do passivo de ruas — sem tela em `geografico` | F |
| 23 | `GET logradouros-oficiais/municipios` | @municipios | METADE | idem (importações CNEFE) | F |
| 24 | `GET logradouros-oficiais/sugerir` | @sugerir | METADE | "Você quis dizer…" ao digitar rua nova — deveria ser chamado no cadastro de rua/cliente; não é | A/F |
| 25 | `GET logradouros/importacoes/{id}` | ImportacaoLogradouroController@show | METADE (baixa) | Andamento de uma importação; a tela só relista | F |
| 26 | `GET lookups/{tipo}` | LookupController@index | **FP** | Prop `endpoint` do `AsyncSelect` (`ClienteFormPage.tsx:148,152,200,204`, `NovoPedidoDialog`…) | A |
| 27 | `GET notas/{id}` | NotaFiscalController@show | MORTA | A lista `fiscal/nfe` já traz o necessário; nenhum detalhe | F |
| 28 | `GET notas/{id}/danfe` | @danfe | **FP** | `abrirDanfe` (`fiscal/api.ts:130`) ← `NfeTab.tsx:43` | F |
| 29 | `GET papeis/{id}/historico` | PapelController@historico | METADE | Histórico de versões do papel (A6) sem tela em `acessos` | C |
| 30 | `GET pedidos/{id}/comanda` | PedidoController@comanda | METADE | Comanda de separação/entrega (o controller diz que sem ela "a conferência da carga volta a ser de cabeça") — nenhum botão em Pedidos/Central | A |
| 31 | `GET telefonia/buscar` | TelefoniaController@buscar | MORTA | O lookup de cliente por telefone (PIE-15) e a fila da bina (`pedidos/api.ts:153`) cobrem | A |
| 32 | `GET vale-gas/{id}/duplicata` | ValeGasController@duplicata | **FP** | `abrirDuplicata` ← `ValeGasPage.tsx:41` | F |
| 33 | `GET vale-gas/{id}/pdf` | @pdf | **FP** | `abrirValePdf` ← `ValeGasPage.tsx:37` | F |
| 34 | `GET veiculos/{id}/documentos` | VeiculoController@documentos | METADE | Documentos do veículo (vencimento) sem aba | F |
| 35 | `GET veiculos/{id}/entradas-saidas` | @entradasSaidas | METADE | Pátio sem aba | F |
| 36 | `GET app/v1/entregador/fiscal/notas/{id}/cupom` | AppFiscalController@cupomNota | METADE | NF em campo (P2-C-15). Atenção: o app chama `entregador/pedidos/{id}/cupom` (`venda.service.ts:87`), outra rota | D |
| 37 | `GET app/v1/entregador/fiscal/notas/{id}/danfe` | @danfe | METADE | idem P2-C-15 | D |
| 38 | `GET app/v1/perfil/endereco` | AppPerfilController@obterEndereco | LEG | Endereço "inline" da F3; o app usa `app/v1/enderecos` (`address.service.ts:17`) | E |
| 39 | `GET superadmin/migracoes/{id}/descartes` | MigracaoController@descartes | LEG | A tela usa `descartes_count` + o CSV (#40); o JSON paginado é redundante | C |
| 40 | `GET superadmin/migracoes/{id}/descartes.csv` | @descartesCsv | **FP** | URL montada em `superadmin/api.ts:406` ← `SaMigracaoPage.tsx:424` | C |
| 41 | `POST boletos/remessa` | BoletoController@remessa | METADE | Gerar remessa CNAB (R3-G-05) | B |
| 42 | `POST boletos/retorno` | @retorno | METADE | Importar retorno CNAB (R3-G-05) | B |
| 43 | `POST caixa/contas` | CaixaController@criarConta | METADE | Criar conta de caixa/banco: não há tela (a conta só nasce por cadastro de apoio/ETL) | B |
| 44 | `POST caixa/movimentos/{id}/estornar` | @estornar | METADE → resolvida durante a rodada | Estorno sem botão às 01h; às 03h a `CaixaTab` já chama `useEstornarMovimento` (agente B) e a costura deixou de acusar | B |
| 45 | `POST caixa/transferencias` | @transferir | METADE | Sangria/transferência entre contas sem tela | B |
| 46 | `POST caixa/{conta}/baixar-titulos` | @baixarTitulos | METADE | Baixa em lote sem tela (só a baixa unitária) | B |
| 47 | `POST caixa/{conta}/lancar-fechado` | @lancarFechado | METADE | Lançamento retroativo autorizado sem tela | B |
| 48 | `POST central/pedidos/{id}/reagendar` | CentralController@reagendar | METADE | Central de entregas não oferece reagendar | A |
| 49 | `POST checklists/{id}/executar` | CrmController@checklistExecutar | METADE | `ChecklistPage` só cadastra o roteiro; ninguém executa (nem o app) | F |
| 50 | `POST cheques/{id}/encontro-de-contas` | ChequeController@encontroDeContas | METADE | Sem tela de cheques com essa ação | B |
| 51 | `POST cobranca/boletos` | BoletoController@gerar | LEG | Alias de `POST boletos` (também sem consumidor, mascarado — §2.2) | B |
| 52 | `POST cobranca/remessas` | @remessa | LEG | Alias de #41 | B |
| 53 | `POST cobranca/retorno` | @retorno | LEG | Alias de #42 | B |
| 54 | `POST colaboradores/{id}/comissoes` | @addComissao | METADE | Aba só leitura | F |
| 55 | `POST colaboradores/{id}/recessos` | @addRecesso | METADE | Aba só leitura | F |
| 56 | `POST estoque/acerto` | EstoqueController@acerto | LEG | Substituído de propósito por entrada/saída (`estoque/api.ts:109`, teste `api.test.tsx:40`) | F |
| 57 | `POST estoque/entrada` | @entrada | **FP** | Ternário em `estoque/api.ts:117` ← `AcertoTab` | F |
| 58 | `POST estoque/fechamentos/abrir` | @abrirFechamento | LEG | A tela usa `POST estoque/fechamentos` (`estoque/api.ts:182`) | F |
| 59 | `POST estoque/inventarios` | @inventarioCriar | LEG | Substituído por `estoque/fisico` (comentário em `api.test.tsx:104`) | F |
| 60 | `POST estoque/saida` | @saida | **FP** | Ternário em `estoque/api.ts:117` | F |
| 61 | `POST financeiro/conciliacao` | @conciliacao | METADE | A aba usa só o GET; não há upload de OFX | B |
| 62 | `POST financeiro/conciliacao/casar` | @conciliacaoCasar | METADE | Casamento manual (F5-04) sem tela | B |
| 63 | `POST financeiro/conciliacao/desfazer` | @conciliacaoDesfazer | METADE | idem | B |
| 64 | `POST financeiro/lancamentos/agrupar` | @agrupar | METADE | Agrupar títulos sem tela | B |
| 65 | `POST financeiro/lancamentos/{id}/desagrupar` | @desagrupar | METADE | idem | B |
| 66 | `POST fiscal/inutilizacoes` | NotaFiscalController@inutilizar | METADE | Inutilização de faixa (obrigação fiscal quando há salto) sem tela | F |
| 67 | `POST fiscal/nfe/{id}/carta-correcao` | @cartaCorrecao | METADE | CC-e sem botão na `NfeTab` | F |
| 68 | `POST logradouros-oficiais/normalizar` | @normalizar | METADE | Par de #22 | F |
| 69 | `POST monitora/sync` | MonitoraController@sincronizar | LEG | Gatilho manual; o agendamento `monitora:sync-positions` roda a cada 30 s (`routes/console.php:29`). Ver R3-G-09 | F |
| 70 | `POST monitora/veiculos/{id}/posicoes` | @ingerirPosicao | LEG | Ingestão por token de usuário: rastreador real entra pelos drivers (Traccar/SGCasa); sem consumidor na prática | F |
| 71 | `POST notas/emitir` | @emitir | LEG | A SPA emite por `pedidos/{id}/emitir-nfce` (`pedidos/api.ts:129`) | F |
| 72 | `POST notas/{id}/cancelar` | @cancelar | LEG | A SPA cancela por `fiscal/nfe/{id}/cancelar` (`fiscal/api.ts:87`) | F |
| 73 | `POST notas/{id}/carta-correcao` | @cartaCorrecao | METADE | Alias de #67 (os dois sem tela) | F |
| 74 | `POST pedidos/{id}/justificar-atraso` | PedidoController@justificarAtraso | METADE | Sem ação em Pedidos/Central; o relatório de atrasos fica sem causa | A |
| 75 | `POST pedidos/{id}/nao-venda` | @registrarNaoVenda | METADE | Nem a SPA nem o app do entregador registram "não venda" | A/D |
| 76 | `POST pix/cobrancas` | PixController@criar | METADE | Cobrar uma parcela por PIX a partir do painel: sem botão (só `pix/config`) | B |
| 77 | `POST veiculos/{id}/abastecimentos` | @registrarAbastecimento | METADE | Aba Abastecimento só leitura (`VeiculosPage.tsx:97`) | F |
| 78 | `POST veiculos/{id}/documentos` | @registrarDocumento | METADE | sem aba | F |
| 79 | `POST veiculos/{id}/entradas-saidas` | @registrarEntradaSaida | METADE | sem aba | F |
| 80 | `POST veiculos/{id}/pneus` | @registrarPneu | METADE | Aba só leitura | F |
| 81 | `POST veiculos/{id}/trocas-oleo` | @registrarTrocaOleo | METADE | Aba só leitura; o próprio controller diz que sem POST "o alerta de troca vencida fica aceso para sempre" | F |
| 82 | `POST app/v1/entregador/fiscal/emitir` | AppFiscalController@emitir | METADE | NF-e em campo (P2-C-15, só `approle:industrial`) | D |
| 83 | `POST app/v1/entregador/missao/vale-gas` | AppMissaoController@venderValeGas | METADE | O app só **verifica** vale (`venda.service.ts:127`); vender em campo não tem tela | D |
| 84 | `POST app/v1/pedidos/{id}/pagar` | AppPedidoController@pagar | METADE | Cartão no app (P2-B-09; depende da decisão §5.2 da Rodada 2) | E |
| 85 | `PUT colaboradores/{id}/comissoes/{cid}` | @updateComissao | METADE | Aba só leitura | F |
| 86 | `PUT colaboradores/{id}/recessos/{rid}` | @updateRecesso | METADE | Aba só leitura | F |
| 87 | `PUT empresas/{id}/cidades` | @definirCidadesDaEmpresa | METADE | Par de #15 | C |
| 88 | `PUT veiculos/{id}/pneus/{pid}` | @atualizarPneu | METADE | Aba só leitura | F |
| 89 | `PUT app/v1/perfil/endereco` | AppPerfilController@atualizarEndereco | LEG | O app usa `enderecos`. Além disso aceita `numero` até 20 e grava em `clientes.numero varchar(10)` → 500 latente (mesma classe de R3-G-04) | E |

**Resumo da triagem:** **FP 8** · **METADE 60** · **LEG 18** · **MORTA 3** (total 89).
Por dono: F 44 · B 26 · A 5 · C 5 · D 4 · E 3 · compartilhados 2 (A/D `nao-venda`, A/F `sugerir`).

**Leitura:** o que a costura acusa é, em dois terços, funcionalidade de backend sem tela. Concentra-se em três blocos que valem mais do que a soma das rotas: **caixa/financeiro** (criar conta, transferir, estornar, recibo, baixa em lote, cancelar título, conciliação manual) — liga com P2-D-09 e P2-D-16; **cobrança CNAB** inteira (R3-G-05); **frota e RH** com abas que só leem (R3-G-06).

### 2.4 Rotas "consumidas" só por código morto — R3-G-03

Pelo Knip (§5.1), com leitura confirmando que nenhum outro arquivo importa o símbolo:

| Código morto | Rotas que ficam sem tela de verdade | Dono |
|---|---|---|
| `central-vendas/EstoqueFranqueadoDialog.tsx` + `central-vendas/estoqueApi.ts` (arquivos inteiros não importados) | `GET franqueados/{id}/estoque`, `POST franqueados/{id}/carga`, `POST franqueados/{id}/devolucao` — **controle de estoque do franqueado sem nenhuma tela alcançável** | A |
| `central/api.ts: useRedistribuir` | `POST central/pedidos/{id}/redistribuir` | A |
| `clientes/revisoes/api.ts: useSugestoesCliente` | `GET clientes/sugestoes` (identidade antes de cadastrar) | A |
| `configuracoes/taxas-entrega/api.ts: useSimularTaxa` | `GET taxas-entrega/simular` | C |
| `empresas/api.ts: useSalvarNfceToken` | `PUT empresas/{id}/nfce-token` (o CSC já é salvo em `FiscalDaEmpresa`; duplicata) | C |
| `financeiro/api.ts: useReparcelarLancamento` | `POST financeiro/lancamentos/{id}/reparcelar` | B |
| `missoes/api.ts: useAtribuirMissao` | `POST missoes/{id}/atribuir` | F |
| `satelites/extraApi.ts: useTipos, useCriarTipo, useSalvarVeiculo, useHistorico` | `GET/POST monitora/tipos`, `POST/PUT monitora/veiculos`, `GET monitora/veiculos/{id}/historico` (os homônimos usados são de `frota/` e `clientes/`) | F |

---

## 3. §4.2 — Tela × servidor (formulários)

Método: leitura do `mutationFn`/payload × `validate()`/FormRequest × `$fillable` (lido em runtime com `php -r` no model) × tamanho real da coluna no `pie_r3_g` (`information_schema.columns`). Prova de runtime onde o defeito é de banco.

### R3-G-04 — Cadastro de cliente em campo dá 500 com número de endereço acima de 10 caracteres (ALTA · dono D, com F/A no padrão)
- **Onde:** `AppSolicitacaoController.php:126` (`'numero' => 'nullable|string|max:20'`) e `AppMissaoController.php:228` (idem) gravam em `clientes.numero varchar(10)`. Mesmo padrão em `AppPerfilController.php:97` (PUT `perfil/endereco`, sem consumidor hoje). O cadastro do painel está certo (`ClienteRequest.php:57`, `max:10`).
- **Reprodução (API real, `pie_r3_g`, token do app do entregador):**
  ```
  POST /api/app/v1/entregador/clientes {"nome":"PIE G Numero Longo","numero":"1234 fundos B",...}
  → HTTP 500  SQLSTATE[22001]: value too long for type character varying(10)
  POST ... {"numero":"1234", ...} → HTTP 201 (cliente 201)
  ```
- **Esperado × observado:** 422 com mensagem, ou coluna do tamanho do contrato × 500 e venda em campo abortada. Pela fila offline (`cliente-novo.tsx` usa `enviarOuEnfileirar`), o 5xx é tratado como transitório e o item **fica retentando para sempre** — o cliente nunca chega.
- **Por que nenhum teste pegou:** sqlite não valida `varchar` (CLAUDE.md, "Teste passa em sqlite e quebra em Postgres").
- **Correção sugerida:** alinhar validação ao contrato (`max:10`) ou expandir a coluna (migration expand, sem destruir); guardião que compara `max:` das regras de `numero/cpf/cep/telefone` com o tamanho da coluna no Postgres.

### R3-G-07 — Colaborador: CPF e CEP digitados com máscara são recusados (MÉDIA · dono F)
- `ColaboradorController.php:386` `cpf max:11` e `:402` `cep max:8`, sem normalização (o `ClienteRequest` normaliza em `prepareForValidation`). O formulário (`rh/ColaboradoresPage.tsx:200,210`) não tem máscara nem `maxLength`. "123.456.789-09" (14) ou "85010-000" (9) → 422, e a tela só diz "Verifique os campos." (`ColaboradoresPage.tsx:174`). Colunas: `colaboradores.cpf(11)`, `cep(8)`. Não há validação de dígito do CPF (classe PIE-35).

### R3-G-01 — Não existe caminho no produto para dar acesso ao app a um entregador (ALTA · dono C, com F)
- O login de campo exige `colaboradores.user_id` ligado a colaborador ativo (`AppAuthController.php:261-273`; sem vínculo → "Este acesso e exclusivo para colaboradores. Use o aplicativo do cliente.").
- `colaboradores.user_id` está no `$fillable`, mas **nenhuma** regra de `ColaboradorController::validar` (`:384-407`) aceita `user_id`, o formulário (`ColaboradoresPage.tsx:157`) não tem o campo, e `UsuarioController`/`AcessoDoColaborador` não criam nem ligam colaborador. A única escrita de `user_id` encontrada é o ETL (`RhMigrator.php:89`). Li o fluxo de criação de usuário e de colaborador inteiro, não só o grep.
- **Prova:** no banco-base, `entregador@pie.local` não tinha colaborador; `POST /api/app/v1/login` → mensagem acima. Só depois de `update colaboradores set user_id=4` no meu banco o login devolveu token.
- **Impacto:** a revenda nº 2 (e a Dubena, para todo entregador contratado depois do cutover) não consegue pôr um entregador no app sem SQL. A mensagem ainda manda o funcionário para o app do consumidor. O banco-base dos agentes também não exercita o app do entregador sem o ajuste — **o agente D precisa saber disso**.

### Outros cruzamentos conferidos
| Formulário | Resultado |
|---|---|
| Cliente (SPA) `ClienteFormPage` × `ClienteRequest` × `Cliente::$fillable` | Campos enviados ⊂ validados ⊂ fillable. Tamanhos batem (CPF/CNPJ normalizados para dígitos). P2-D-10 (sem telefone no cadastro) segue: o form não envia `telefones` |
| Colaborador (SPA) | Datas sem underscore são aceitas e mapeadas (`:393-419`). Defeitos: R3-G-07, R3-G-01 |
| Veículo/abastecimento | A aba mostra `kmatual/totallitros/mediaconsumo`, a escrita valida `km/litros` — coerente só porque não há escrita na SPA (R3-G-06) |
| Novo pedido (SPA) | Payload (`NovoPedidoDialog.salvar`) bate com `PedidoRequest`; o diálogo mudou durante a varredura (agente A adicionou "Cliente novo? Cadastrar sem sair do pedido") |
| Perfil do consumidor `perfil-dados.tsx` × `AppPerfilController@atualizarPerfil` | Campos batem; `datanascimento` em ISO (`date_format:Y-m-d`). CPF sem dígito verificador no app (no painel há) — classe PIE-35, dono E |
| Cadastro do consumidor `newuser.tsx` × `executarCadastroCliente` | Bate; o app não coleta e-mail (opcional no servidor) |
| Cliente em campo (entregador) × `AppSolicitacaoController@cadastrarCliente` | Campos batem; R3-G-04 |
| Cliente em missão × `AppMissaoController@cadastrarCliente` × `VendaCampoService` | `latitude/longitude` → `lat/lng` mapeados corretamente; R3-G-04 |

**Não feito:** a captura `request.postDataJSON()` de todos os formulários no navegador (a API local ficou lenta demais para rodar dezenas de jornadas — §1). Os cruzamentos acima são por leitura + prova HTTP direta onde o defeito é de banco.

---

## 4. §4.3 — Menu × permissão × licença × backend

### 4.1 Medido pela API real (`matriz-api2.mjs`, `pie_r3_g`, licença `legacy-full`)

Para cada perfil, o item é "visível" pela mesma regra do `AppShell` (`AppShell.tsx:188-190`: permissão + recurso) e as rotas são as que a tela chama ao abrir (levantadas de `features/*/api.ts` por `menu.mjs`). Resultado em `scratchpad/g-matriz.txt`:

| Item do menu | Admin | Gerente | Operador |
|---|---|---|---|
| Início | visível · 200 | visível · 200 | visível · 200 |
| Pedidos (`pedidos`, `pedidos/situacoes`, `telefonia/fila`) | 200 | 200 | 200 |
| Clientes · Central de entregas · Comodatos · Missões · Endereços · Produtos | 200 | 200 | 200 |
| **Rastreamento** (`satelites/*`, `monitora/veiculos`, `config-global`) | 200 | **visível · `config-global` 403, `satelites/integracoes` 403** | **visível · `config-global` 403, `satelites/relatorios` 403, `satelites/integracoes` 403** |
| **Estoque** (`estoque/saldos`, `estoque/fechamentos`, `fiscal/inventarios`) | 200 | 200 | **visível · `fiscal/inventarios` 403** (aba Inventário fiscal exige `fiscal.view`) |
| Financeiro (`lancamentos`, `caixa/contas`, `boletos`, `pix/config`, `financeiro/dre`) | 200 | 200 | 200, salvo `financeiro/dre` = 500 por **tempo** (30 s, máquina saturada — inconclusivo, não é achado) |
| Vale-Gás · Convênios | 200 | 200 | 200 |
| Relatórios · Colaboradores | 200 | 200 | oculto · 403 (coerente) |
| Configurações (`config-global`) | 200 | oculto · 403 (coerente) | oculto · 403 (coerente) |

`malotes/conferencia` = 422 sem `inicio`/`fim` nos três perfis — é a chamada sem filtro do meu script, não da tela. **Nenhuma porta lateral** (item oculto com API liberando) apareceu nos itens medidos. Divergências reais: Rastreamento (R3-G-10) e a aba Inventário fiscal do Estoque para o Operador (R3-G-24, baixa · dono F/C: esconder a aba sem `fiscal.view`).

### 4.2 Divergências estáticas (licença) — R3-G-08 (MÉDIA · dono C)

O banco-base tem o plano `legacy-full` (todos os recursos), então a divergência de **licença** não aparece em runtime; ela é estrutural. O menu só declara `feature` em 5 itens (`AppShell.tsx:56,67,85,86,90`), mas `RecursoPorRota::MAPA` (`RecursoPorRota.php:38-62`) barra muito mais:

| Item de menu | `feature` no menu | Recurso exigido pela API (MAPA) | Efeito num plano sem o recurso |
|---|---|---|---|
| Pós-venda, Metas de venda, Checklists | nenhum | `crm` (`pos-vendas`, `metas`, `checklists`) | menu mostra, tela inteira 402 |
| Notas fiscais, Cupons fiscais (SAT) | nenhum | `nfce` (`fiscal/`, `notas`, `cupons-fiscais`) | menu mostra, 402 |
| Estoque → aba Inventário fiscal | nenhum | `nfce` (`fiscal/inventarios`) | aba quebra no Essencial |
| Financeiro → abas Boletos/PIX | nenhum | `cobranca` (`boletos`, `pix`) | `pix/config` e `boletos` 402 ao abrir o Financeiro |
| Financeiro → DRE | nenhum | `relatorios_avancados` (`financeiro/dre`) | aba 402 |
| Relatórios | `relatorios_avancados` | `relatorios/` → `relatorios_avancados` | coerente, mas tira o relatório de vendas do Essencial (P2-A-04) |
| Rastreamento | `monitora` | `monitora/` | coerente; a tela também chama `satelites/*` e `config-global` (sem licença) |
| Veículos | `frota` | `veiculos` | coerente |

As rotas da SPA (`routes.tsx`) não olham `feature`: por URL direta, a tela abre e cada chamada volta 402. **Proposta (já é a O4 da Frente A):** uma fonte única — o `/me` devolver o mapa rota→recurso e o menu e as abas usarem o mesmo `RecursoPorRota`.

### 4.3 Outras divergências de permissão
- **R3-G-10 — Rastreamento sem mapa para quem não administra (MÉDIA · dono F, com C):** as abas Mapa ao vivo, Rota e Cercas buscam a chave do Google por `GET /config-global` (`satelites/extraApi.ts:205-206`, usado em `MapaAoVivoTab.tsx:58`, `RotaTab.tsx:66`, `CercasTab.tsx:31`), que exige `grupo.view` (`ConfigGlobalController.php:23`). Gerente e Operador têm `monitora.view` e **não** têm `grupo.view`: medido, `config-global=403` para os dois (matriz acima) — o item aparece no menu e o mapa não carrega. Além disso a tela lê a configuração inteira do grupo para usar um campo. Proposta: rota própria `monitora/mapa-config` sob `monitora.view`, devolvendo só a chave (e restrita por referer, P2-B-11). `satelites/integracoes` (`empresa.view`) também volta 403 para o gerente.
- Início: o menu não exige permissão e a API não nega — `RelatorioController@dashboardHoje` filtra **bloco a bloco** pelo Gate (`:60-68`). Medido 200 para os três perfis. Sem divergência (a leitura estática do `menu.mjs` sugeria `relatorio.view`; a execução corrigiu).
- **`/satelites` e `/seguranca` roteadas sem permissão** (`routes.tsx:125,189`); `/satelites` chama `relatorio.view`, `empresa.view`, `monitora.*`. Sem item de menu — porta lateral só de UI (a API guarda). Dono C/F, baixa.
- **Não existe papel "Caixa"** no banco-base (papéis: Administrador, Gerente, Operador, Entregador). O Operador acumula caixa (`caixa.edit/fechar`, `financeiro.baixar`) com atendimento. Já é P2-A-14; registro porque o protocolo §5.1 pede o perfil caixa e não há conta para ele.

---

## 5. §4.4 — Varreduras estáticas

### 5.1 Knip (SPA)
`npm i knip@5.62.0 typescript@5.6.3` numa pasta do scratchpad; `node <scratch>/knip/node_modules/knip/bin/knip.js --reporter compact` dentro de `erp-novo/frontend`. Saída: **2 arquivos não usados, 1 dependência não usada (`@tanstack/react-table`), 56 exports e 34 tipos não usados.**
- ~40 dos 56 exports são **falsos positivos**: as páginas são carregadas por `lazyNamed(() => import(...), 'NomeDaPagina')` (`routes.tsx`), que o Knip não segue pelo nome em string.
- Reais: os arquivos e hooks de §2.4 (R3-G-03); `lib/api.ts: MENSAGEM_SESSAO_EXPIRADA, reautenticacaoPendente` (provavelmente trabalho em curso do agente C na sessão vencida — conferir antes do commit); `layouts/BotaoNovo.tsx: ACOES_NOVO`, `AppShell.tsx: itemAtivo`, `format.ts: vazio`.
- **Apps:** não rodei o Knip neles (tempo de máquina; os dois apps estavam sendo editados por D e E). Limitação.

### 5.2 `catch` que engole erro
- **SPA e apps:** 31 ocorrências de `catch {}`/`.catch(() => …)`; todas lidas. São armazenamento opcional (localStorage), permissões do sistema, splash, GPS opcional e o "best-effort" documentado da avaliação (`EvaluateModal.tsx:24-35`). Nenhuma esconde gravação de negócio.
- **Backend:** 157 `catch`, 118 sem log/relançamento pelo filtro; fora de ETL/console, 28. Relevantes:
  - **R3-G-09 (MÉDIA · dono F):** `Monitora/Drivers/SgcasaHttpDriver.php:51` devolve `[]` para qualquer falha do provedor; `POST monitora/sync` responde **"Sync concluído: 0 posição(ões)"** (`MonitoraController.php:531-535`) quando o SGCasa está fora ou a credencial errada. Erro vira sucesso; o agendamento de 30 s também não registra nada.
  - Registradores de trilha que engolem falha: `AutorizaPorPermissao.php:77` (negação de autorização), `AuditoriaSeguranca.php:62`, `AuditoriaPlataforma.php:63`, `Auditavel.php:93`, `RegistroAcao.php`, `ConsumoDeIntegracao.php:112,134`, `PedidoService.php:486` (recusa de desconto). É decisão consciente ("trilha não derruba a venda"), mas é a mesma classe que escondeu o defeito de `integracao_consumos` em produção (CLAUDE.md). Sugestão: manter o `catch` e acrescentar `report($e)`. Dono F.
- `TODO|FIXME|não implementado`: **nenhum** marcador real no código de produto (só "TODO" em caixa alta dentro de frases e `product.service.ts:9` `TODO(F3)` desatualizado no app do consumidor — dono E, baixa).
- Botão sem ação / `onClick` vazio: **0** (os dois `<Button>` sem `onClick` são gatilhos de `DropdownMenu` com `asChild`).
- Toast de sucesso sem mutação: 178 varridos, 4 suspeitos, todos lidos e falsos (o toast está no `onSuccess`).

### 5.3 Ferramentas que não rodaram
| Ferramenta | Motivo |
|---|---|
| Semgrep | Há Python 3.13 na máquina agora (`C:\Users\fusea\AppData\Local\Programs\Python\Python313`), ao contrário do que o briefing supunha; **não instalei** por tempo de máquina. Recomendo `python -m venv` no scratchpad + `pip install semgrep` numa próxima rodada |
| gitleaks | não instalado |
| Larastan, Schemathesis, `npm audit`/`composer audit` | fora do meu recorte nesta rodada |

---

## 6. Para outros donos (resumo)

| ID | Dono | O quê |
|---|---|---|
| R3-G-01 | C (+F) | Ligar usuário ↔ colaborador no produto (criar acesso de entregador pela tela de Colaboradores ou de Usuários); mensagem do login de campo |
| R3-G-04 | D (+E, F) | `numero max:20` → `clientes.numero(10)`: 500 no cadastro em campo e na missão; guardião regra × coluna |
| R3-G-05 | B | Cobrança CNAB sem tela (gerar boleto, imprimir, remessa, retorno) — a aba promete "fluxo de cobrança" que não existe |
| R3-G-06 | F | Frota e RH com abas só leitura (10 + 6 rotas de escrita prontas) |
| R3-G-03 | A, B, C, F | Código morto que mascara rotas sem tela (estoque do franqueado!) |
| R3-G-07 | F | CPF/CEP do colaborador com máscara → 422 genérico |
| R3-G-08 | C | Menu × licença: 5 áreas sem `feature` que a API barra |
| R3-G-09 | F | Sync do rastreador relata sucesso quando falha |
| R3-G-10 | F (+C) | Mapa do Rastreamento 403 para gerente/operador (`config-global` exige `grupo.view`) |
| R3-G-24 | F/C | Aba Inventário fiscal do Estoque visível ao Operador e negada pela API |
| R3-G-02 (parte 3) | coordenador / D | `enviarFormularioOuEnfileirar` não é reconhecido pela costura: `concluir` e `ocorrencia` do entregador viram "sem consumidor" |
| R3-G-02 | coordenador | `pie-costura.mjs` mascara 21 rotas (CRUD genérico) e conta código morto |
| Triagem §2.3 | A–F | 59 rotas METADE com a tela sugerida |

## 7. Estado deixado
API 8217 e Vite 5217 encerrados. Banco `pie_r3_g` **mantido** (alterações minhas: `colaboradores.id=4 → user_id=4`; clientes `PIE G Numero Curto` id 201 e as tentativas de R3-G-04). Nenhum código de produto alterado; arquivos novos só em `docs/pie/rodada3/G_*.md` e `erp-novo/e2e/produto/`.

## 8. Não testado
- Matriz §4.3 no navegador (spec versionado, não concluiu — §1). A matriz foi medida pela API.
- Captura de payload por Playwright em todos os formulários.
- Knip nos dois apps; Semgrep; gitleaks.
- Planos sem recurso (todo o banco-base é `legacy-full`); a divergência de licença é estática.
