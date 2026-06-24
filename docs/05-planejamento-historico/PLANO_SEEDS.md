# Plano de Seeds — erp-novo (homologação)

> Objetivo: popular **todas** as ~55 tabelas de negócio com dados realistas e
> íntegros, respeitando FKs e a ordem de dependência do ETL. Nenhuma tabela de
> negócio deve ficar vazia. Baseado no schema real (migrations).

## Princípios
- Reaproveitar os **Services** (não inserir direto) onde houver regra: pedidos via
  `PedidoService`, estoque via `EstoqueService`, financeiro via `FinanceiroService`,
  caixa via `CaixaService` — assim os saldos nascem consistentes (invariantes verdes).
- Ordem topológica (igual ao `MigratorRegistry`):
  1. grupos → empresas → empresa_configs → estados
  2. RBAC (roles, permissions, permission_role, role_user) + users
  3. geográfico (cidades, bairros, ruas) + regioes
  4. cadastros de apoio (segmentos, tipopessoas, telefonetipos, bancos, contamovimentotipos, clientecontato*)
  5. clientes (+telefones, interacoes, dependentes, precos)
  6. produtos (+classes, unidades, origens)
  7. setores → estoque (entrada inicial via EstoqueService → saldos+historico) → fechamentos
  8. pedidos (situacoes/operacoes; pedidos PENDENTE/CONCLUIDO/CANCELADO via PedidoService)
  9. financeiro (planos_conta, centros_custo; títulos via FinanceiroService → parcelas/rateios)
  10. caixa (contas via CaixaService.criarConta; movimentos; baixas; cheques)
  11. cobrança (boletos via BoletoService; pix_cobrancas via PixService; remessas)
  12. fiscal (config_fiscais; notas via FiscalService.emitirDoPedido)
  13. satélites (convenios + fechamentos; vale_gas; comodatos via ComodatoService)
  14. mobile (app_devices; pagamentos_online)
  15. monitora (veiculos; posicoes via MonitoraService → ultima_posicao; cercas; rotas)

## Volume sugerido (homologação)
| Domínio | Qtd |
|---|---|
| Grupos / Empresas | 1 / 2 |
| Usuários (admin, teste, operador, entregador) | 4 + RBAC |
| Cidades/Bairros/Ruas | 5 / 15 / 30 |
| Cadastros de apoio | 5–10 por tipo |
| Clientes (com telefones/convênio) | 50 |
| Produtos (GLP/água/acessórios) | 20 (+origens) |
| Setores | 3 (Depósito, Loja, Veículo) |
| Estoque (saldo inicial) | todos os produtos × setores |
| Pedidos | 200 (mix de situações, últimos 90 dias) |
| Financeiro | gerado pelos pedidos + 30 avulsos (a pagar) |
| Contas de caixa | 3 (Caixa, Banco, Cartão) + movimentos das baixas |
| Cheques | 20 (recebidos/emitidos) |
| Boletos / PIX | 30 / 30 |
| Notas fiscais | emitidas a partir de 50 pedidos concluídos |
| Convênios / Vale-gás / Comodatos | 5 / 40 / 15 |
| Devices / Pagamentos online | 4 / 30 |
| Veículos / Posições GPS / Cercas | 5 / 500 / 3 |

## Estrutura de arquivos (proposta)
```
database/seeders/
├── DatabaseSeeder.php            (orquestra, em ordem topológica)
├── DeployAdminSeeder.php         (já existe — admin/empresa base)
├── homolog/
│   ├── RbacSeeder.php            (roles/permissions de todos os módulos)
│   ├── UsuariosSeeder.php        (teste, operador, entregador)
│   ├── GeograficoSeeder.php
│   ├── CadastrosApoioSeeder.php
│   ├── ClientesSeeder.php        (factory + sub-relações)
│   ├── ProdutosSeeder.php
│   ├── EstoqueSeeder.php         (via EstoqueService)
│   ├── PedidosSeeder.php         (via PedidoService — gera estoque+financeiro)
│   ├── FinanceiroSeeder.php      (via FinanceiroService — títulos avulsos)
│   ├── CaixaSeeder.php           (via CaixaService — contas, baixas, cheques)
│   ├── CobrancaSeeder.php        (BoletoService/PixService)
│   ├── FiscalSeeder.php          (FiscalService.emitirDoPedido)
│   ├── SatelitesSeeder.php
│   ├── MobileSeeder.php
│   └── MonitoraSeeder.php        (via MonitoraService)
```

## Comando de execução
```sh
# homologação completa
php artisan migrate:fresh --seed --seeder=Database\\Seeders\\HomologSeeder
# ou, mantendo dados:
php artisan db:seed --class=Database\\Seeders\\HomologSeeder
```

## Validação pós-seed
- `php artisan cutover:check` (invariantes — exige dados consistentes).
- Conferir: Σ estoquehistorico = estoquesaldos; Σ contamovimentos = saldo_atual;
  Σ parcelas = financeiro.valor; pedidos CONCLUIDO geraram financeiro+baixa de estoque.
- Nenhuma tabela de negócio vazia (`SELECT count(*)` por tabela).

## Cobertura por cenário de teste
| Cenário | Dados que cobrem |
|---|---|
| Venda à vista concluída | pedido CONCLUIDO + saída estoque + financeiro R baixado |
| Venda a prazo | financeiro R com N parcelas em aberto |
| Cancelamento | pedido CANCELADO + devolução estoque + estorno financeiro |
| Convênio mensal | convenio + pedidos do mês + fechamento → 1 financeiro |
| Vale-gás | emitido/pago/utilizado |
| Comodato | emprestado/parcial/devolvido (move estoque) |
| Caixa | abrir/baixar/transferir/estornar/fechar |
| Cheque | carteira→depositado→compensado (credita caixa) |
| Boleto/PIX | gerado/remessa/retorno-liquidado / cobrança-paga-webhook |
| Fiscal | NF-e autorizada (numeração sequencial) |
| GPS | veículo com histórico de posições + última posição |
```
