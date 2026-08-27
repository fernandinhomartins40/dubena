# F0-04F — Owner em estoque, logística, cheque, extrato e PDF

**Data:** 2026-08-25  
**Estado:** CONCLUÍDO COMO CONTENÇÃO  
**Achados contidos:** caminhos demonstrados de A-12.2, A-12.3, A-7.4 e A-7.10.

## Releitura executada

Foram relidos integralmente os trechos aplicáveis dos Volumes 7 e 12 e os fontes atuais de estoque, central logística/autoatribuição, encontro de contas por cheque, conciliação/regras de extrato, boleto PDF, controllers, models e testes atingidos. Também foram relidos os chamadores de produção da porta de estoque: pedido, NF de entrada, carga de franqueado e comodato.

## Alterações

### Estoque

- a porta central valida o par setor/produto pelo mesmo `empresa_id`;
- operações HTTP informam obrigatoriamente a empresa ativa resolvida por `TenantContext`;
- validações `exists` de setor/produto foram tornadas tenant-aware;
- saldos, históricos e fechamentos consultados com `withoutTenant()` recebem owner explícito;
- requisição e inventário persistem owner explícito e o propagam até transferências/acertos;
- pedido, NF de entrada, carga de franqueado e comodato propagam o owner do agregado.

### Atribuição logística

- atribuição e redistribuição exigem `empresaId` explícito;
- o pedido é relido por `id + empresa_id` e bloqueado na transação;
- entregador precisa estar ativo e autorizado a operar na empresa;
- veículo explícito ou herdado da jornada precisa pertencer à empresa e estar ativo;
- controller e job propagam a empresa ativa/serializada, em vez da empresa padrão do usuário.

### Cheque e baixa

- encontro de contas exige owner, relê e bloqueia cheque e parcela;
- parcela alheia ou já baixada é recusada;
- cobertura parcial não é mais registrada como baixa integral;
- a mudança do cheque para `REPASSADO` permanece atômica com a baixa.

### Extrato e boleto PDF

- conciliação exige que a conta pertença à empresa e escopa movimentos/regras pelo owner;
- CRUD de regras valida a conta ativa e persiste owner explícito;
- boleto PDF só carrega cliente cujo `empresa_id` coincide com o boleto;
- geração, remessa, retorno, PDF e download de remessa usam a empresa ativa do `TenantContext`.

## Evidência

Execução integrada do microlote:

```text
Tests: 140 passed (333 assertions)
Duration: 10.95s
```

Sintaxe PHP aprovada nos serviços centrais alterados. `git diff --check` sem erro de whitespace; somente avisos de normalização CRLF em arquivos já presentes no workspace.

As provas negativas incluem setor/produto alheios, pedido/entregador alheios, parcela de cheque alheia, baixa parcial, regra/conta alheias e cliente alheio no PDF.

## Limites deliberados desta contenção

- argumentos opcionais de owner continuam temporariamente disponíveis na porta de estoque para chamadores internos/harnesses; F4 deve torná-los obrigatórios em todos os fluxos e substituir `Setor` textual por `StockLocation` tipado;
- `BaixaService` como único escritor, origem de pagamento e detecção formal de pagamento duplicado continuam em F5-02;
- FITID persistido e matching auditável continuam em F5-04;
- associação canônica entregador↔empresa e identidade multiempresa serão modeladas em F1/F3; a contenção usa a autorização atual do usuário;
- a varredura total de todas as 152 validações `exists` pertence ao catálogo e às políticas tenant-aware de F1/F2; este microlote conteve as portas demonstradas no achado.

