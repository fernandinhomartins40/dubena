# Analise somente leitura da VPS — Dubena

Data: 2026-08-26 (America/Sao_Paulo)

## Escopo e seguranca

A VPS foi acessada somente para leitura por SSH. Nenhum arquivo, dado, role,
container, migration, configuracao, commit ou deploy foi alterado. Credenciais
de acesso e valores de ambiente nao foram copiados para este repositorio.

## Evidencia operacional observada

- O ambiente ativo da aplicacao e o Compose `erpnovo`, com `erpnovo-app`,
  `erpnovo-db`, `erpnovo-queue`, `erpnovo-scheduler`, `erpnovo-web`, Redis e
  Reverb ativos.
- O checkout usado pelo runner esta na branch `main`, no commit
  `4d8a3f3560af2de765bb417cc1b66482ccb41adf`; nao havia alteracoes locais no
  checkout remoto no instante da leitura.
- O banco homologacao `erp_novo` ainda nao contem a migration de fronteira SaaS
  `2026_08_29_000100_create_tenant_boundary_tables`. Portanto nao foi e nao
  poderia ser aplicado qualquer mapping SaaS na VPS nesta analise.

## Estrutura de dados confirmada

| Grupo | Descricao | Empresas | Leitura |
|---|---:|---:|---|
| 2 | Grupo Dubena | 11 | Operacao a ser adaptada para um unico TenantAccount Dubena. |
| 3 | Grupo Padrao | 1 | Cadastro de teste; fica fora da proposta de mapping. |

A empresa 2 e a matriz `Distribuidora Dubena Ltda`; as unidades 114, 115, 116,
117, 134 e 135 compartilham a raiz de CNPJ da Dubena e sao filiais. Os demais
cadastros do grupo 2 tambem pertencem ao escopo declarado pelo operador e sao
mantidos como empresas/filiais do mesmo tenant, sem transformarem nomes ou
convencoes Dubena em regra do produto SaaS.

O usuario legado de id 3, identificado como `Vilso Dubena (dono da rede)`, esta
ativo, no grupo 2, e possui vinculos legados com dez unidades. A declaracao do
operador nesta conversa afirma sua propriedade sobre a rede; ela e a decisao
de negocio usada pela proposta abaixo, enquanto os vinculos legados servem
apenas como evidencia corroborativa, nao como inferencia automatica.

## Decisao de adaptacao

A proposta em `mapeamentos/DUBENA_VPS_2026-08-26_PROPOSTA.json` cria:

- um unico `TenantAccount` para a Distribuidora Dubena;
- as 11 empresas do Grupo Dubena como `TenantCompany` desse tenant;
- Vilso (user 3) como membership OWNER com leitura e operacao nas 11 empresas;
- nenhum tenant, grant ou regra de kernel para o Grupo Padrao de teste.

Ela e um input de **dry-run**, nao uma alteracao de banco. Antes de aplicar,
executar o importador no ambiente alvo confirmado e revisar o resultado. O
deploy continua sendo exclusivamente via commit e push para `main`; esta
analise nao executou nenhum dos dois.
