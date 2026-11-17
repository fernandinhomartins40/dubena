# Anexo — Matriz de autorização (Agente F, Rodada 3)

Este é o anexo do relatório [F_SEGURANCA_LGPD_MODULOS.md](F_SEGURANCA_LGPD_MODULOS.md). O runner, os atores e a triagem estão descritos no §2 de lá.

**Execução de referência:** 31/10/2026 06:53 UTC, banco `pie_r3_f`, API `erp_app` com licença e envelope ligados. Foram 617 rotas do manifesto, todas varridas, em 2.996 requisições.

A matriz **linha a linha** (rota × ator × esperado × observado) é gerada pelo runner. A de 31/10 se perdeu quando a sessão caiu e o scratchpad foi limpo. Para regenerá-la:

```bash
psql -h 127.0.0.1 -p 55432 -U postgres -c "create database pie_r3_f template pie_r3_base"   # se não existir
# subir a API como erp_app (e2e/README.md), de preferência 2 instâncias com opcache
cd erp-novo
PIE_DB=pie_r3_f PIE_API_URL=http://127.0.0.1:8216,http://127.0.0.1:5216 \
PIE_DOC=../docs/pie/rodada3/F_MATRIZ_AUTORIZACAO_LINHAS.md node e2e/seguranca/matriz-autorizacao.mjs
```

O runner sai com código 1 se houver VIOLAÇÃO (dado de A visto por quem não devia) ou se varrer menos de 560 rotas.

## Matriz por área × ator (31/10)

Legenda:
- **N** = negado como esperado (401/403/404);
- **V0** = 200 com corpo vazio, sem dado de A;
- **422!** = validação antes da autorização;
- **—** = não se aplica ou não exercido;
- **CFG** = configuração do próprio grupo, visível por desenho.

| Área (rotas) | Sem token | App em painel | Admin B | Admin B + `X-Empresa-Id:1` | `emp2` (sem grant na empresa 1) | Papel sem a permissão (BFLA) |
|---|---|---|---|---|---|---|
| `/api/admin` com `{id}`, GET (≈70) | N | N | N; V0 em 3 rotas (R3-F-06) | N; V0 nas mesmas | N; V0 nas mesmas | N (403) |
| `/api/admin` com `{id}`, escrita no clone (≈200) | N | N | N; 422! em 32 | N; 422! em 25 | N; 422! em 19 | N; 422! em 8 (R3-F-05) |
| `/api/admin` listas sem id, inclusive CSV e PDF (≈250) | N | N | **nenhum dado de A** | **nenhum dado de A** | sem `empresa_id = 1`; CFG nas listas de grupo | N (403) |
| `/api/admin` com parâmetro literal (`cadastros/{tipo}`, `lookups`, `geo`, `malha`, `cep`) | N | N | V0 | V0 | CFG | N |
| `/api/app/v1` do consumidor (pedido e endereço do outro) | N | — | N | — | — | consumidor 1 no objeto do 2: N, exceto `pagar` 422! |
| `/api/app/v1/entregador/*` | N | token de cliente: N | — | — | — | — |
| `/api/superadmin` (34) | N | token de cliente: N; token do painel: N | — | — | — | — |
| Públicas e de sessão (15) | PULADA | — | — | — | — | — |

**5xx encontrados:** 3, todos com o Administrador de A em `GET empresas/{id}/config|certificado|integracoes`. Origem: `INSERT` em `empresa_configs` barrado pela RLS (R3-F-01, dono C).

## Lista das anomalias de 31/10 (com triagem)

| Rota | Ator | Esperado | Obtido | Triagem |
|---|---|---|---|---|
| `GET cadastros/{tipo}`, `lookups/{tipo}`, `fiscal/malha/{tipo}`, `geo/{entidade}`, `geo/cep/{cep}` | adminB, adminB+empresa1 | nega | 200 vazio/`null` | lista por tipo, sem dado de A: falso positivo da v1 |
| as mesmas | emp2 | nega | 200 com dado do grupo 1 | configuração da própria rede: legítimo |
| `GET comodatos/vigilancia/{cliente}`, `convenios/{id}/fechamentos` | adminB, adminB+empresa1, emp2 | nega | 200 `[]` | R3-F-06 (BAIXA) |
| `GET empresas/{id}/cidades` | emp2 | nega | 200 `[]` | R3-F-06 |
| `GET empresas/{id}/config`, `/certificado`, `/integracoes` | admin (A) | permite | **500** | **R3-F-01 (ALTA)** |
| `POST clientes`, `POST pedidos`, `POST`/`PUT produtos` | entregador | nega | 422 | R3-F-05 |
| `POST empresas` | gerente, operador, entregador | nega | 422 | R3-F-05 |
| `POST caixa/{contaId}/baixar` | entregador | nega | 422 | R3-F-05 (validação antes do `autorizar`) |
| `POST app/v1/pedidos/{id}/pagar` | consumidor 1 (pedido do 2) | nega | 422 | R3-F-05 (dono E) |
| 57 GETs de relatório, conciliação, SPED, preços e simulação | admin, gerente, operador | permite | 422 | passou da autorização e exige filtro: OK |
| `GET relatorios/auditoria` | gerente | permite (pela 1ª permissão) | 403 | correto (P2-E-04); limitação do runner |
| `GET auditoria/registro/{entidade}/{id}`, `relatorios/{slug}` | admin | permite | 404 | id ou slug literal do runner inexistente: sem defeito |
