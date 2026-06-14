# PRD — [Nome do Domínio]  ·  Dxx

> Template de PRD por módulo. Preencher com base no CÓDIGO REAL (ler controllers,
> models, views, rotas). Objetivo duplo: (1) documentar o que o sistema faz hoje
> (a documentação que nunca existiu); (2) embasar a decisão REFATORAR×REESCREVER.

- **Status:** ⬜ não iniciado · 🟦 levantamento · ✅ pronto
- **Criticidade:** 🔴/🟠/🟡/🟢
- **Decisão:** REFATORAR · REESCREVER · MANTER  _(preencher ao final)_

---

## 1. Escopo (o que está neste módulo)
- Controllers: `...`
- Models / tabelas (schema): `...`
- Rotas (paths + nomes): `...`
- Views (blades): `...`
- Jobs/commands/processors relacionados: `...`

## 2. O que o módulo FAZ (regra de negócio — em português)
> A parte mais importante. Descrever os fluxos como um analista de negócio
> entenderia, SEM jargão de código. Ex: "ao baixar um título no caixa, o sistema
> verifica X, lança em Y, gera Z...". É isto que precisa sobreviver a qualquer
> reescrita.
- Fluxo principal 1: ...
- Fluxo principal 2: ...
- Regras/validações de negócio (inclusive as "estranhas" que parecem gambiarra
  mas podem ser fiscais): ...
- Integrações (NF-e, PIX, Maps, banco, outro módulo): ...

## 3. Como FAZ hoje (implementação atual)
- Padrões de UI (template AdminLTE? data-driven menu? modais?): ...
- Origem dos dados (Eloquent? whereRaw? $_GET direto?): ...
- Dependências de outros módulos/schemas: ...

## 4. Gambiarras / dívida técnica / práticas amadoras (achados)
> Listar objetivamente, com `arquivo:linha`. É o inventário do que NÃO replicar.
- [ ] ... (ex.: lê `$_GET` direto; SQL cru interpolado; lógica no controller; etc.)
- [ ] ...

## 5. Riscos de tocar neste módulo
- Fiscal/financeiro? Quebra emite nota/calcula imposto? ...
- Acoplamentos perigosos (outro módulo lê a mesma tabela?): ...
- Falta de teste/baseline? ...

## 6. Estado de compatibilidade Postgres (já feito x pendente)
- SQL já traduzido? whereRaw com input? CONNECT BY? ...

## 7. Visão do módulo REESCRITO (Laravel 12 + boas práticas)
> Como ficaria se reescrito: entidades, casos de uso, endpoints/telas, UX moderno.
- Modelo de dados (mantém tabelas? normaliza?): ...
- Camadas (Service/Action, FormRequest, Resource, Policy): ...
- UI proposta (stack de frontend, telas): ...
- Contratos a preservar (rotas/payloads que apps/integrações consomem): ...

## 8. DECISÃO e justificativa
- **Decisão:** REFATORAR / REESCREVER / MANTER
- **Por quê:** (peso de risco fiscal × dívida técnica × esforço × valor)
- **Pré-requisitos antes de migrar:** (ex.: baseline fiscal, objetos ocultos)
- **Esforço estimado:** ...
- **Ordem/dependências:** (o que precisa vir antes/depois)
