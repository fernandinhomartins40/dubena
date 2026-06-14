# PRD por Módulo — Índice Mestre

> **Objetivo:** mapear 100% da aplicação atual (ctrl-web + módulos api/monitora),
> módulo por módulo, documentando regra de negócio, gambiarras e dívida técnica,
> e **decidir por módulo** se o caminho é **REFATORAR** (manter lógica, modernizar)
> ou **REESCREVER** (Laravel 12 + boas práticas + visual novo).
>
> Não eliminamos o legado: cada módulo migra quando seu PRD estiver pronto e a
> decisão tomada (padrão Strangler Fig). O sistema nunca para.
>
> Base: 161 controllers no ERP. Data de início: 2026-06-14.

---

## Como ler / preencher
- Cada módulo tem um arquivo `NN_dominio.md` seguindo `_TEMPLATE.md`.
- Status do PRD: ⬜ não iniciado · 🟦 em levantamento · ✅ pronto.
- Decisão (preenchida ao fim do PRD do módulo): **REFATORAR** · **REESCREVER** ·
  **MANTER** (sem mexer por ora).
- Criticidade: 🔴 fiscal/financeiro (erro = multa) · 🟠 transacional (uso diário) ·
  🟡 cadastro/apoio · 🟢 relatório/consulta.

---

## Domínios e módulos

### D01 — Vendas / Pedidos 🟠🔴
Núcleo transacional. Pedido move estoque + financeiro + (às vezes) NF.
`Pedido, Pedidooperacao, Pedidosituacao, Pedidomotivoatraso, Vendaativa,
VendaAtivaOcorrenciaTipos, Atualizarprecos, Promocao, Promotor`
| Status | Decisão | PRD |
| ⬜ | — | `01_vendas_pedidos.md` |

### D02 — Fiscal: NF-e / NFC-e 🔴
O coração de risco. Emissão, impostos, operações fiscais, cupom.
`Nfemitida, Nfweb, Nfrecebida, CupomFiscal, Impostonf, NfOperacao, Nfsituacao,
Nfgrupofiscal, Nficms, Nfpis, Nfcofins, Nfipi, Nfcst, Nfclasstrib, IBPT,
ConfigNfcePedido, Ocorrenciasremessas`
| Status | Decisão | PRD |
| ⬜ | — | `02_fiscal_nfe.md` |

### D03 — Fiscal: SPED 🔴
`Spedfiscal, Spedcontribuicao, Spedcreditos`
| ⬜ | — | `03_fiscal_sped.md` |

### D04 — Financeiro / Caixa / PIX / Boleto 🔴
`Financeiro, Caixa, Pix, Boleto, BoletoPdf, Boletoremessa, Conta,
Contamovimentotipo, Condicaopagamento, Conciliacao, Importextrato, Layoutbanco,
Chequeemitido, Chequerecebido, Descontocheque, Planoconta, Centrocusto`
| ⬜ | — | `04_financeiro.md` |

### D05 — Clientes / CRM 🟠
`Cliente, Clientecontato, Clientecontatosituacao, Clientecontatotipo,
Clienteproduto, Tipopessoa, Segmento, Maladireta, Posvenda, Posvendacadastro`
| ⬜ | — | `05_clientes.md` |

### D06 — Produtos / Estoque 🟠
`Produto, Produtoclasse, Estoquefisico, Estoquesetor, Estoquesetoracerto,
Estoquerequisicao, EstoqueTransferencias, Inventario, Testeestoque,
Unidademedida, Tipocombustivel`
| ⬜ | — | `06_produtos_estoque.md` |

### D07 — Vale-Gás / Convênio / Comodato 🟠
Específico do negócio de gás.
`Valegasvenda, Valegasbaixar, Valegascancelar, Valegasconsulta,
Fechamentoconvenio, Conveniogbgestao, Comodato, Comodatogestao, Cupons (cupons),
Mcmm, Fechamentomalote`
| ⬜ | — | `07_valegas_convenio.md` |

### D08 — Colaboradores / RH 🟡
`Colaborador, Colaboradorcomissoes, Colaboradorfamilia, Setorcolaboradores,
Cargo, Estadocivil, Parentesco, Recessos, Recessotipo, Turno, Tipoexame,
Checklist, Cadastrochecklist`
| ⬜ | — | `08_colaboradores.md` |

### D09 — Frota / Veículos 🟡
`Veiculo, Veiculotipo, Veiculoabastecimento, Veiculodocumento, Veiculoentradasaida,
Veiculopneu, Veiculotrocaoleo`
| ⬜ | — | `09_frota.md` |

### D10 — Cadastros base / Geográfico 🟡
`Empresa, EmpresasGrupo, Empresaconfig, Empresabens, Configuracoesgerais,
Bairro, Cidade, Rua, Regiao, Setor, Banco, Documento, Documentogestao,
Documentotipo, Motivonaovenda, Sorteio`
| ⬜ | — | `10_cadastros_base.md` |

### D11 — Acesso / Permissões / Menu 🟠
**Onde está a "gambiarra do menu" que você apontou.** Auth, usuários, papéis,
e o sistema de menu data-driven que controla o que aparece.
`Auth, Users, Role, Menu`
| ⬜ | — | `11_acesso_permissoes.md` |

### D12 — Relatórios / Dashboards 🟢
Maior volume de funções Oracle traduzidas; valida sintaxe mas não valor.
`Report, ReportCaixa, Reportclientes, Reportclientesaniversariantes,
Reportcolaborador, Reportcomissoes, Reportcomodato, Reportconvenio, ReportEntregas,
Reportestoque, ReportFinanceiro, Reportlogs, Reportlogsenha, Reportmovimentacao,
Reportnfemitidas, Reportnfrecebida(s), Reportpromocoes, Reportpromotor,
Reportquestionarios, ReportResumoVendas, Reportvalegas, Reportveiculos,
Reportvendapdv, ReportVendas, Reportvendasmalote, Dashboardgerencial,
Vendasmensaisgestao, Fechamentomensalgestao, Metavenda, Inconsistencia`
| ⬜ | — | `12_relatorios.md` |

### D13 — Mobile API (App\Api) 🟠
Já é módulo separado (schema api). Contratos consumidos por apps publicados.
`App\Api\* (getToken, v2/order, client, produtos, video...)`
| ⬜ | — | `13_api_mobile.md` |

### D14 — Monitoramento / GPS (App\Monitora) 🟡
Já é módulo separado (schema monitora). Mapa, posições, cercas.
`App\Monitora\* (Rastreamento, Cerca, Rota, Evento, posições)`
| ⬜ | — | `14_monitoramento.md` |

### D15 — Integrações / Notificações / Misc 🟡
`Notificacoes, Appnotification, Appgiro, Appvideo, Android, Agencia, Logcerca`
| ⬜ | — | `15_integracoes_misc.md` |

---

## Painel de decisão (preenchido conforme os PRDs ficam prontos)

| Domínio | Criticidade | Status PRD | Decisão | Justificativa curta |
| --- | --- | --- | --- | --- |
| D01 Vendas/Pedidos | 🔴🟠 | ✅ | **REFATORAR** (faseado) | God controller (1661 linhas) orquestra estoque+financeiro+NF. Regra crítica — Services/Actions+transação, NÃO reescrever do zero. Baseline BLOQUEANTE. Um dos últimos |
| D02 Fiscal NF-e | 🔴 | ⬜ | — | |
| D03 Fiscal SPED | 🔴 | ⬜ | — | |
| D04 Financeiro | 🔴 | ⬜ | — | |
| D05 Clientes | 🟠 | ✅ | **REESCREVER** (faseado) | Cadastro central; God methods (update ~600 linhas); convênio/limite têm efeito financeiro. Quebrar em sub-recursos + Service de convênio |
| D06 Produtos/Estoque | 🟠/🔴 | ✅ | **REESCREVER** Produto/cadastros · **REFATORAR** motor estoque | EstoqueProcessor já é service (manter regra, robustecer); Produto carrega tributação (NF-e) — baseline |
| D07 Vale-Gás/Convênio | 🟠🔴 | ✅ | **REFATORAR** fechamentos/MCMM · **REESCREVER** consultas/comodato | Fechamentos geram financeiro; MCMM é registro fiscal ANP — baseline. Consultas/cadastros livres |
| D08 Colaboradores | 🟡/🟠 | ✅ | **REESCREVER** cadastros/checklist · **REFATORAR** comissões | Cadastros baixo risco; comissão paga gente (Service + baseline). Achado: $_GET direto em comissões |
| D09 Frota | 🟡 | ✅ | **REESCREVER** | CRUDs limpos, baixo risco; ganho de UX (timeline manutenção, consumo). Preservar vínculo veiculoerp_id↔Monitora |
| D10 Cadastros base | 🟡/🔴 | ✅ | **REESCREVER** apoio · **REFATORAR** Empresa/config | CRUDs limpos (vitrine do padrão novo); Empresaconfig é fiscal — refatorar com baseline; SQLi no Bairro |
| D11 Acesso/Menu | 🟠 | ✅ | **REESCREVER** | Fundação de acesso; HTML no model, eager 100 níveis, switch de auth — alta dívida, baixo risco fiscal |
| D12 Relatórios | 🟢 | ⬜ | — | |
| D13 API mobile | 🟠 | ⬜ | — | |
| D14 Monitoramento | 🟡 | ⬜ | — | |
| D15 Integrações/Misc | 🟡 | ⬜ | — | |

---

## Ordem de levantamento sugerida
Começar pelos **cadastros/apoio** (mais simples, valida o método e provavelmente
candidatos a REESCREVER com baixo risco), subir até o **fiscal** (mais complexo,
provavelmente REFATORAR/MANTER com baseline). Sugestão:

```
D11 Acesso/Menu (piloto — resolve a "gambiarra do menu" e valida o template)
   → D10 Cadastros base → D09 Frota → D08 Colaboradores
   → D05 Clientes → D06 Produtos/Estoque → D01 Vendas/Pedidos
   → D07 Vale-Gás → D04 Financeiro → D02 NF-e → D03 SPED
   → D12 Relatórios → D13 API → D14 Monitora → D15 Misc
```
> Racional: o fiscal (D02/D03/D04) por último porque exige o baseline (Frente D
> do PLANO_FECHAMENTO_PENDENCIAS) para qualquer decisão segura.
