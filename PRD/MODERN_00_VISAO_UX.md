# MODERNIZAÇÃO — Visão de UX/UI e Arquitetura de Navegação (alvo 2026)

> Documento-guia. Define o PARADIGMA-ALVO da modernização. Todos os `MODERN_NN_*.md`
> seguem este guia. Escrito após auditar o código real do legado (pós F0–F4A).
> **Decisão do cliente (norteadora):** o sistema atual tem "cara de dinossauro" — menu
> no banco, telas fragmentadas, submenus, níveis de acesso complexos. O alvo é um app
> moderno: **sidebar + header + área de conteúdo**, **página completa por entidade**,
> navegação **declarativa** (não no banco), permissões simples por recurso/ação.

---

## 1. O QUE MUDA DE PARADIGMA (legado → alvo)

| Tema | Legado (hoje, auditado) | Alvo moderno (2026) |
|---|---|---|
| **Navegação/menu** | Tabela `menus` (5 raízes + 212 filhos) com HTML montado no Model e cacheado na Session no login | **Declarativa**: cada recurso Filament se registra no sidebar (grupo/ícone/ordem). SEM tabela de menu. Muda permissão → reflete na hora |
| **Estrutura de tela** | Fragmentada: "menu Cadastro de Cliente" → tela lista → botão "Novo" → outra tela; editar endereço/telefone/contato em telas separadas | **Página completa por entidade**: 1 recurso `Cliente` com lista + ficha; dentro da ficha, tudo (dados, endereço, telefones, contatos, pedidos, financeiro) em **abas / RelationManagers** |
| **Layout** | AdminLTE, dropdowns multi-nível, telas densas, HTML/Form no backend | **Sidebar + header + conteúdo à direita**; telas completas; componentes do Filament (TALL stack) |
| **Permissão** | `menuusers` = user×empresa×menu×6 flags (matriz manual por usuário) | **Por recurso/ação** via Policy + **papéis (roles)**; atribuição por papel, não item-a-item |
| **Fluxo de gravação** | POST de form gigante; cálculos só no submit; muito AJAX manual | Reactive/Livewire: feedback em tempo real (estoque, preço, total); wizard quando faz sentido |

---

## 2. PRINCÍPIOS (todos os módulos seguem)

1. **Uma entidade = uma página completa.** Lista + criação + edição + tudo relacionado no
   mesmo recurso. Relacionados como **RelationManagers** (abas), não telas soltas.
2. **Navegação declarativa.** O recurso declara `navigationGroup`, `navigationIcon`,
   `navigationSort`. O sidebar é consequência. **Não existe tabela de menu no alvo.**
3. **Permissão por recurso/ação.** `viewAny/view/create/update/delete` via Policy; papéis
   (roles) agrupam permissões. Sem "permissão por linha de menu".
4. **Regra de negócio em Service/Action**, não no controller/God. UI fina.
5. **Sem SQL/HTML no controller.** Query builder/Eloquent parametrizado; apresentação na view.
6. **Multi-empresa por escopo** (global scope/tenant), não filtros espalhados.
7. **Layout único**: sidebar + header + conteúdo; responsivo; tema Dubena (já na F3).

---

## 3. NAVEGAÇÃO-ALVO (sidebar declarativo — esboço)

> Grupos do sidebar (cada item é um Resource Filament; sem tabela `menus`):

- **Cadastros**: Clientes · Produtos · Fornecedores · Geográfico (Cidade/Bairro/Rua) ·
  Bancos · Empresas/Config
- **Vendas**: Pedidos · Vale-Gás · Promoções · Venda Ativa
- **Estoque**: Saldos · Requisições/Transferências · Inventário · Fechamento
- **Financeiro**: Lançamentos · Caixa/Tesouraria · Cheques · Boletos · Plano/Centro de Contas
- **Fiscal**: NF-e/NFC-e · Malha Fiscal · SPED
- **RH**: Colaboradores · Comissões · Recessos
- **Frota**: Veículos (ficha completa)
- **Relatórios**: (área dedicada)
- **Admin**: Usuários · Papéis/Permissões · Configurações

> Cada grupo acima vira `navigationGroup`. A hierarquia some do banco e vira código.

---

## 4. MODELO DE PERMISSÕES-ALVO (resumo; detalhe em MODERN_11)

- **Hoje:** `menuusers` (user×empresa×menu×flags) — auditado, complexo.
- **Alvo:** **papéis (roles)** (ex.: Vendedor, Caixa, Gerente, Admin) com permissões nomeadas
  por recurso/ação. Atribuir papel = 1 clique. Migração: mapear menuusers → permissões.
  Transição segura: a F4A já lê menuusers via `podeNoMenu`; introduzir papéis por cima e
  migrar gradualmente (sem big-bang). A tabela `menus` deixa de existir no alvo
  (navegação declarativa); `menuusers` é substituída por roles/permissions.

---

## 5. COMO LER OS MODERN_NN_*.md

Cada um traz: (1) **Antes×Agora** (bugs do PRD fiel vs. código atual, com arquivo:linha);
(2) **Regras de negócio a preservar** (o que NÃO pode regredir); (3) **Página-alvo**
(como a entidade vira 1 recurso completo, abas/RelationManagers, lugar no sidebar);
(4) **Pendências residuais** (arquivo:linha). O "layout/fluxo antigo" do legado é
explicitamente DESCARTADO — só as regras de negócio são preservadas.
