# ÍNDICE — PRDs de IMPLEMENTAÇÃO (auditados linha-a-linha do CÓDIGO)

> Base COMPLETA para implementar a migração ao SPA React com PARIDADE + REORGANIZAÇÃO.
> Auditados no código real (controllers/requests/models/tabelas), não na documentação.
> Cada IMPL traz: colunas da tabela, métodos do controller (arquivo:linha), validações,
> regras de negócio, sub-recursos, seção Reorganização/UX (de-para legado→novo) e DoD.

## Contrato de navegação (ler primeiro)
- [MAPA_NAVEGACAO_ALVO.md](MAPA_NAVEGACAO_ALVO.md) — inventário do legado + páginas-alvo que
  consolidam telas dispersas + de-para (nada se perde). **Reorganizar ≠ eliminar.**

## PRDs por módulo (ORDEM DE IMPLEMENTAÇÃO)
| # | Módulo | PRD | Estado |
|---|--------|-----|--------|
| 0 | Cliente (1º, já implementado) | [SPEC_CLIENTE.md](SPEC_CLIENTE.md) | ✅ implementado (7 abas) |
| 1 | Produto | [IMPL_PRODUTO.md](IMPL_PRODUTO.md) | pronto p/ implementar |
| 2 | Geográfico (Cidade/Bairro/Rua/Região) | [IMPL_GEOGRAFICO.md](IMPL_GEOGRAFICO.md) | pronto |
| 3 | Empresa / EmpresaConfig / Grupos | [IMPL_EMPRESA.md](IMPL_EMPRESA.md) | pronto |
| 4 | Cadastros de apoio (consolidado) | [IMPL_CADASTROS_APOIO.md](IMPL_CADASTROS_APOIO.md) | pronto |
| 5 | Estoque (motor + telas) | [IMPL_ESTOQUE.md](IMPL_ESTOQUE.md) | pronto |
| 6 | Financeiro / Tesouraria | [IMPL_FINANCEIRO.md](IMPL_FINANCEIRO.md) | pronto |
| 7 | Fiscal (NF-e/malha/SPED) | [IMPL_FISCAL.md](IMPL_FISCAL.md) | pronto (SEFAZ bloqueante) |
| 8 | Satélites (RH/Frota/Vale-Gás/Relatórios/Monitor/Integrações) | [IMPL_SATELITES.md](IMPL_SATELITES.md) | pronto |
| 9 | Vendas / Pedidos / Caixa (ÚLTIMO) | [IMPL_VENDAS.md](IMPL_VENDAS.md) | pronto (por último) |

## Como usar
1. Ler MAPA_NAVEGACAO_ALVO (agrupamento global).
2. Para cada módulo (na ordem): seguir o IMPL_<modulo> como CONTRATO — cobrir 100% do DoD
   (campos + ações + validações + regras + sub-recursos + reorganização). Só então "pronto".
3. Implementar: API admin (app/ApiAdmin) + React (frontend) + testes + suíte verde + deploy.

## Princípios (não esquecer)
- PARIDADE: nenhuma função/campo do legado eliminado.
- REORGANIZAÇÃO: telas dispersas → páginas completas com abas, melhor visão e UX.
- TESTAR de verdade (os 500 do revisionable/convênio foram pegos por teste, não na tela).
- Motores (estoque/financeiro/fiscal) preservados com baseline; Pedido/Caixa por último.
