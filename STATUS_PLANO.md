# Status de Execução do Plano de Modernização

> Auditoria honesta de cada item do `PLANO_MODERNIZACAO_ECOSSISTEMA.md` contra o
> **código real** (não só a memória). Objetivo: antes de iniciar o Multi-tenant
> (Fase 6), confirmar que os pontos das auditorias foram de fato ajustados.
>
> Legenda: ✅ feito e verificado · 🟡 parcial / precisa validação · ❌ pendente
>
> Data desta auditoria: 2026-06-14 · Decisão: **Multi-tenant ADIADO** até fechar os 🟡/❌.

---

## Fase 0 — Docker + extração de segredos
| Item | Status | Observação |
| --- | --- | --- |
| docker-compose por sistema | ✅ | ctrl-web (dev+prod). monitora/api fundidos no ctrl-web. |
| Subir em container | ✅ | Em produção na VPS (gasemcasa.com). |
| Extrair segredos do código | 🟡 | Backend: feito (.env). **Rotação das credenciais expostas no Git ainda pendente** (ver Fase 1). |
| .gitignore cobrindo .env/segredos | ✅ | SEGREDOS_LOCAIS.md gitignored. |

## Fase 1 — Blindagem de Segurança
| Achado | Status | Verificação |
| --- | --- | --- |
| Webhook PIX: token + valor pago==cobrado + binding | ✅ | PixController:79-84 (hash_equals token); PixService:198 (abs(pago-cobrado)>0.001). |
| /savePosition exige token | ✅ | Monitora/ApiController: X-Integration-Token / INTEGRATION_TOKEN. |
| getUsuarios não retorna password | 🟡 | **VERIFICAR** Monitora/ApiController::getUsuarios (não confirmado nesta auditoria). |
| testarToken=='123456' removido | ✅ | Monitora/ApiController:46 (comentário FASE 1, mecanismo real). |
| encodeSecret 'secret' literal → env | ✅ | Monitora/customHelper:600 SECRET_HMAC_KEY/app.key. |
| Token api-app-gc (sha1 app_key) → APP_TOKEN_KEY | ✅ | Módulo App\Api (Fase 5). |
| Cripto base64 → Crypt::encrypt | ✅ | customHelper:1152 customCrypt usa Crypt::encrypt; fallback legado base64. |
| SQLi (Metavenda/Cliente/integration) | 🟡 | Metavenda/Cliente: feito (binding). integration/ eliminado. **Revisar whereRaw com interpolação remanescentes** (há vários nos relatórios fiscais). |
| IDOR veiculos/dropdown + middleware access | 🟡 | Veiculo do monitora filtra por empresa_padrao. **Confirmar dropdown específico.** |
| Política de senha min:8 | ✅ | User.php ERP + Monitora/User + Monitora/UsersController (corrigido min:4→min:8 nesta auditoria). |
| **Rotacionar TODAS as credenciais expostas no Git** | ❌ | **PENDENTE.** Estão no histórico do Git original. SEGREDOS_LOCAIS.md tem checklist. Crítico antes de produção real. |
| **App mobile: TLS forçado (remover NSAllowsArbitraryLoads/allowHttp/cleartext)** | ❌ | **PENDENTE — CRÍTICO (S2).** app.json:28,52 + AndroidManifest ainda permitem HTTP. "Grave por haver pagamento". |
| App mobile: app_key/Maps → EAS Secrets; MMKV encryptionKey; remover console/dd | ❌ | **PENDENTE.** App não foi tocado (só hardening previsto, não executado). |

## Fase 2 — Testes de Caracterização
| Item | Status | Observação |
| --- | --- | --- |
| Golden-master fiscal/financeiro (pedido, NF-e, SPED, PIX, boleto) | 🟡 | CaracterizacaoFase2Test cobre cripto + helpers fiscais. **NÃO há golden-master de pedido→estoque/financeiro, NF-e completa, SPED.** Cobertura real é de fluxo (navegação 200), não de valores fiscais. |
| Fluxo do app (token→cliente→pedido→pagamento) | 🟡 | Rotas /api existem; sem teste ponta-a-ponta com app real. |
| Baseline congelado | ❌ | Não há baseline fiscal numérico. Os testes garantem "não-500", não "mesmo valor de imposto". |

## Fase 3 — Oracle → PostgreSQL
| Item | Status | Observação |
| --- | --- | --- |
| Schema migrado (tabelas/sequences/índices) | ✅ | 625 migrations, 214 tabelas public + 32 api + 18 monitora. |
| SQL Oracle traduzido (TO_DATE/LISTAGG/ROWNUM/CONNECT BY/NVL/etc.) | ✅ | Auditado e convertido; validado em prod (login+98 módulos+relatórios 200). |
| Objetos ocultos (triggers/procedures/views/synonyms) | ❌ | **PENDENTE — depende de acesso ao Oracle de produção.** Não versionados. Pode haver lógica fiscal em trigger/procedure que não veio. |
| Driver oci8 → pgsql | ✅ | Removido. |
| Migração de DADOS reais (ETL anonimizado) | ❌ | **PENDENTE.** Schemas nascem vazios (seeders só admin). Sem dados, relatórios fiscais hierárquicos não validáveis por valor. |

## Fase 4 — Framework/Runtime
| Item | Status | Observação |
| --- | --- | --- |
| Laravel 5.4→5.8 | ✅ | Os 3 backends em 5.8. |
| Laravel 5.8→6 LTS→8 + PHP 8 | ❌ | **PENDENTE.** Parou em 5.8 (deps travadas: laravelcollective/excel/sped). Plano previa até 8/PHP8. |
| Reescrever integration/ mysql_* | ✅ | Eliminado; virou Command sync:posicoes-sgcasa. |
| Eliminar código morto (3.414 linhas monitora) | ✅ | Removido na Fase 4. |
| Build Gulp/Elixir → Mix | ✅ | webpack.mix.js. |

## Fase 5 — Unificação Web + API
| Item | Status | Observação |
| --- | --- | --- |
| API como módulo /api no ERP | ✅ | App\Api, schema api. |
| **Monitoramento como módulo /monitora** | ✅ | App\Monitora, schema monitora — validado em prod. |
| Tabelas espelho *_importacao → leitura direta | 🟡 | Tabelas espelho ainda existem; repositories não 100% reapontados. |
| Versionamento retrocompatível (contratos do app) | 🟡 | Rotas preservadas; **sem validação com app real apontando para a API unificada.** |

## Fase 6 — Multi-tenant
| Item | Status |
| --- | --- |
| Tudo | ❌ **ADIADO** deliberadamente até fechar os 🟡/❌ acima. |

---

## Pendências que BLOQUEIAM o multi-tenant (prioridade)
1. ❌ **Rotacionar credenciais expostas no Git** (Fase 1) — segurança.
2. ❌ **App mobile: forçar TLS + segredos** (Fase 1, S2 crítico — há pagamento).
3. ❌ **Objetos ocultos do Oracle** (triggers/procedures/views) — risco fiscal silencioso.
4. ❌ **Baseline fiscal/financeiro real** (Fase 2) — sem ele, não há rede de proteção numérica.
5. 🟡 **Revisar SQLi/whereRaw remanescentes** + getUsuarios password + IDOR dropdown.
6. ❌ **Migração de dados reais** — para validar relatórios fiscais por valor.

## Pendências que NÃO bloqueiam (podem vir depois)
- Laravel 6→8 / PHP 8 (Fase 4) — sistema funciona em 5.8/7.4.
- Tabelas espelho *_importacao (Fase 5) — funcional, só não-ideal.
