# F0-03 — segredos versionados

**Estado:** PARCIAL — código contido; rotação externa e limpeza de histórico pendentes  
**SHA inicial:** `4d8a3f3560af2de765bb417cc1b66482ccb41adf`  
**Data:** 2026-08-25

## Evidência reconfirmada

Os scripts `database/etl/espelhar_oracle.py` e `database/etl/migrar_posicoes.py` continham credenciais/DSNs literais. Os valores não foram reproduzidos em logs, diário ou resposta.

## Implementação

- Oracle exige `ORACLE_CONNECTION`.
- PostgreSQL de destino exige `ETL_PG_DSN`.
- Monitora exige host, porta, usuário, senha e banco em variáveis `MONITORA_MYSQL_*`.
- Ausência de valor produz erro explícito; não existe default de credencial.
- Os três templates `.env*example` documentam somente nomes e valores vazios.
- Teste de regressão impede voltar a atribuições literais nos dois scripts.

## Validação

- AST dos dois scripts Python: aprovado.
- `VersionedSecretsTest`: 1 teste, 11 assertions, aprovado.
- `git diff --check`: sem erro; somente avisos CRLF→LF.

## Pendências externas

- rotacionar/revogar as credenciais que estavam no repositório;
- identificar owner e destinos autorizados antes da rotação;
- limpar histórico Git somente com backup, coordenação dos clones e plano de force-push;
- confirmar secret manager/executor que injetará as variáveis.

Essas pendências não autorizam reutilizar os valores removidos e não impedem avançar em contenções locais independentes.
