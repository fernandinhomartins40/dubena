# F3-10 — Medição: `empresa_configs` já é fail-closed e tipada

Data: 2026-08-31 (America/Sao_Paulo)

## O que a tarefa pede

> **F3-10 — Configuração:** schemas tipados/versionados, defaults de plataforma
> semeados no onboarding e copiados para o tenant; nenhum literal
> Dubena/Guarapuava vira regra universal.

A terceira parte foi fechada num lote anterior (o `User-Agent` que dizia
`ERP-Dubena`, mais o guardião no `EscritaCanonicaTest`). Este documento mede as
outras duas — e a conclusão é que **não há defeito ativo**.

## `dados` não é uma área livre

Eu havia registrado, na parcial anterior, que `empresa_configs.dados` era "JSON
livre, sem schema tipado". **Isso estava errado**, e a leitura completa do
`EmpresaConfigController` mostra por quê.

O endpoint genérico:

- tem **allow-list** (`COLUNAS` + `DADOS_EDITAVEIS`, ~50 chaves declaradas);
- **recusa chave inesperada** com erro explícito, em vez de gravar;
- **recusa estrutura aninhada** — *"configuração estruturada deve usar seu
  endpoint específico"*;
- valida separadamente a configuração financeira do tenant.

E os blocos estruturados têm porta própria e validada: `PUT
/empresas/{id}/integracoes` valida campo a campo (`pix.psp`, `pix.ambiente` com
`in:producao,homologacao`, `cartao.url` com `url`…) e cifra os segredos por
valor, preservando o já salvo quando o campo vem vazio.

Isso é, na prática, schema tipado por bloco. O que não existe é **versão**
declarada — e sem consumidor que precise migrar entre versões, uma coluna de
versão seria peso morto.

## Defaults de plataforma no onboarding

Empresa nova **não recebe configuração nenhuma**. Isso é fail-closed e
consistente com o resto do sistema: sem credencial da empresa, não cobra e não
autentica, em vez de cair num default da plataforma.

Semear defaults no onboarding só faria sentido com uma decisão de produto sobre
*quais* valores são universais — e a F3 inteira existe justamente para não
transformar a configuração de uma revenda em regra de todas.

## Conclusão

**F3-10 está satisfeita.** Registro a medição para que a próxima pessoa não
refaça a investigação, e para corrigir o que eu mesmo havia anotado errado no
`ESTADO_ATUAL.md`.

Se um dia um bloco de configuração precisar migrar de formato, aí a versão passa
a ter consumidor e vale a coluna. Hoje não tem.
