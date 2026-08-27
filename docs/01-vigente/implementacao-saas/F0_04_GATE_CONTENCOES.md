# Gate interno F0-04 — vulnerabilidades conhecidas

**Estado:** APROVADO COMO CONTENÇÃO TEMPORÁRIA  
**Data:** 2026-08-25 22:34 (America/Sao_Paulo)

## Cobertura acumulada

Os microlotes F0-04A–P contiveram os caminhos demonstrados de:

- pagamentos/PIX/boleto e drivers fake fail-closed;
- configuração e segredos PABX;
- login logs globais e portas de caixa/financeiro/CNAB;
- IDs cross-tenant em estoque, logística, cheque, extrato, fiscal e empresa-alvo;
- licença/ABAC, baixa financeira única, ETL e cutover fail-closed;
- custo de produto em produto, estoque, auditoria, comodato, SPED e NF de entrada;
- XML bruto de NF de entrada;
- empresa ativa nos relatórios e superfícies de auditoria demonstradas.

Cada recorte possui diário próprio com arquivos, testes e rollback. A
recertificação deste gate reutilizou essas provas e executou apenas os testes
afetados pelo último contrato central; a suíte integral fica para o Gate F0.

## Limites intencionais e destino canônico

- A enumeração de `exists` globais e usos restantes de empresa padrão não foi
  declarada segura: sua eliminação sistemática pertence a F1/F2.
- `$hidden` e apresentadores de custo são defesa temporária; resources/policies
  canônicos e contratos de dados entram em F2/F7.
- Validação RLS PostgreSQL com role restrita (T-02.05) continua pendente para o
  gate executável correspondente; ausência do runtime não foi convertida em sucesso.
- Rotação externa dos segredos de F0-03 continua pendente e impede o Gate F0,
  embora o código versionado e seus defaults já estejam contidos.

## Decisão

F0-04 pode encerrar e F0-05 pode iniciar. Isto não aprova F0: build imutável,
anel externo, catálogo vivo, baseline final, rotação externa e titularidade ainda
precisam satisfazer o gate da fase.
