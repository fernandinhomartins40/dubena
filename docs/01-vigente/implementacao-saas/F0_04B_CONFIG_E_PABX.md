# F0-04B — Configuração genérica e PABX

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO  
**Achados contidos:** A-12.4 e A-12.5  
**Substituição canônica futura:** F2/F6 — catálogo versionado, capabilities e `IntegrationAccount` por owner.

## Releitura executada

Foram lidos integralmente o Volume 12, `EmpresaConfigController`, `PabxWebhookController`, `IntegracaoTenant`, `config/services.php`, `EmpresaConfig`, `EmpresaConfigMigrator` e os testes de empresa/telefonia relacionados. A busca de consumidores confirmou que a conversão grava configurações operacionais planas com nomes canônicos e que os blocos de PIX/cartão possuem endpoint próprio.

## Contenções

- o endpoint genérico de configuração agora aceita somente colunas estruturais e uma lista explícita de chaves operacionais planas;
- chave desconhecida ou valor estruturado falha com 422;
- `integracoes` não pode mais ser sobrescrita pelo endpoint genérico;
- o segredo temporário do PABX exige `PABX_EMPRESA_ID` e só pode gravar para essa empresa;
- tentativa de reutilizar o token em outra empresa responde 401 e não cria chamada;
- templates de ambiente documentam o owner obrigatório do PABX.

## Compatibilidade com a conversão

A lista permitida foi derivada dos nomes de destino de `EmpresaConfigMigrator::configOperacional()`. O teste da API passou a usar o nome canônico `valida_atraso`, em vez do nome físico legado `validaatraso`. A migração continua responsável por adaptar a cópia Dubena ao contrato SaaS.

## Evidência

```text
Tests: 36 passed (99 assertions)
Duration: 44.06s
```

Arquivos cobertos: `EmpresaTest`, `TelefoniaTest`, `IntegracaoTenantTest` e `ConfigOperacionalEComodatoTest`. Sintaxe PHP aprovada nos quatro arquivos alterados.

## Limite operacional observado

Dois subagentes somente-leitura atingiram limite do serviço, que informou retomada às 16:17 de 2026-08-25 (America/Sao_Paulo). A execução principal continuou localmente, sem espera ociosa.
