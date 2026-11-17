# G — Produto e simplificação para o usuário leigo (protocolo §7) · Rodada 3

**Agente:** G · **Data:** 2026-10-31 · **Ambiente:** o do `G_INVENTARIO_CRUZADO.md` §1 (`pie_r3_g`, API 8217 como `erp_app`, SPA 5217).
**Método:** análise de tarefa → passada cognitiva (4 perguntas por passo: *sabe o que fazer? vê o controle? entende que é o certo? percebe que deu certo?*) → heurísticas de Nielsen → regra dos 3 passos e **ELIMINAR → JUNTAR → AUTOMATIZAR → ANTECIPAR → CRIAR**.

**Grau de evidência de cada número** (honestidade sobre o que foi medido):
- **[M] medido no navegador** — o spec `erp-novo/e2e/produto/tarefas-atendimento.spec.ts` (passos contados pela classe `Tarefa` de `produto/apoio.ts`, pedido relido no banco) está versionado, mas **nesta rodada não concluiu**: nas três tentativas o login no navegador falhou ("O serviço de acesso está indisponível", `/api/login` em 29–33 s com seis agentes na máquina). Os [M] abaixo vêm da Rodada 2 (Frente D) e estão marcados como tal;
- **[A] medido pela API real** (status e tempo da chamada que a tela faz);
- **[C] contado no código** (componente, rota do expo-router, campos do formulário) — é contagem, não observação; vale como teto/piso e precisa de confirmação com usuário real.

**Ressalva de tempo:** os tempos em segundos desta máquina **não** representam produção — o `php artisan serve` do Windows, dividido com seis agentes, levou 11–32 s para logar e até 34 s num `/api/me`. Compare passos e telas; não compare segundos.

---

## 1. Tarefas por perfil — hoje

"Passo" = ação deliberada (abrir, escolher, digitar campo que não vem pronto, confirmar). Navegar pelo menu conta 1.

### 1.1 Operador / atendente (SPA)

| # | Tarefa (frequência) | Passos hoje | Telas | Evidência |
|---|---|---|---|---|
| A1 | **Cliente conhecido liga e quer "o de sempre"** (dezenas/dia) | **6** com a sugestão (menu Pedidos → Novo pedido → abrir busca → digitar telefone → escolher → Repetir último pedido → Criar = 7 ações, 6 sem o menu); **9+** quando a revenda não tem situação/operação/setor sugeridos (abre "mais opções") | 1 tela + 1 diálogo | [C] passo a passo do `NovoPedidoDialog.tsx` (botões e `salvar()`); o spec [M] existe e **não concluiu** — o login no navegador estourou ("O serviço de acesso está indisponível", `/api/login` em 29–33 s nesta máquina). Rodada 2 J03 [M]: busca por telefone acha; nome sem acento não |
| A2 | Cliente **novo** liga | 2 até o diálogo + atalho "Cliente novo? Cadastrar sem sair do pedido" (adicionado pelo agente A nesta rodada, `NovoPedidoDialog.tsx`) + campos do cadastro rápido + itens + condição + situação/operação/setor + criar | 1 + 2 diálogos | [C]; na Rodada 2 eram 7–9 passos com troca de tela (P2-A-13). |
| A3 | Despachar para entregador | Central de entregas → Atribuir → escolher → confirmar = **3–4** por pedido | 1 | [C] (`central/CentralPage.tsx`); modo `auto` existe e não é o padrão |
| A4 | Mudar situação até "Concluído" | abrir pedido → situação → confirmar = **3** | 1 | Rodada 2 J05 [M] |
| A5 | Achar cliente por nome sem acento | "joao" não acha "João" | — | P2-D-05 (aberto); a **listagem** de clientes também não busca por telefone (Rodada 2) |

### 1.2 Caixa (perfil inexistente — R3-G-11)

O banco-base só tem **Administrador, Gerente, Operador, Entregador** (`roles`, lido no banco). O caixa da revenda trabalha como Operador, que acumula atendimento e dinheiro (`caixa.edit`, `caixa.fechar`, `financeiro.baixar`). Para o protocolo §5.1 não há conta "caixa" a medir.

| # | Tarefa | Passos hoje | Evidência |
|---|---|---|---|
| C1 | Abrir o caixa do dia | Financeiro → aba Caixa → Abrir caixa = **3** | [C] (`financeiro/tabs/CaixaTab.tsx:68`; o "Abrir" foi corrigido na Rodada 2/3, P2-D-09) |
| C2 | Receber um título | Financeiro → A pagar e receber → achar título → Baixar → conta, valor, desconto → confirmar = **6+** | [C] (`BaixaDialog.tsx`); recebimento parcial era P2-D-16 |
| C3 | Fechar malote do entregador | Financeiro → Malote → período/entregador → conferir → fechar = **4–5** | [C]; `malotes/conferencia` exige `inicio`/`fim` [A: 422 sem eles] |
| C4 | Dar recibo ao cliente do balcão | **impossível pela tela**: `GET caixa/movimentos/{id}/recibo` pronto e sem botão | [C] inventário §2.3 #9 |
| C5 | Sangria / transferência entre contas | **impossível pela tela** (`POST caixa/transferencias` sem consumidor) | [C] #45 |

O Financeiro tem **11 abas** numa linha só (`FinanceiroPage.tsx`: A pagar e receber, Caixa, Boletos/PIX, Cheques, Malote, Conciliação, DRE, Regras do extrato, Plano de contas, Centros de custo…), misturando a operação diária do caixa com configuração contábil.

### 1.3 Gerente (SPA)

| # | Tarefa | Passos | Evidência |
|---|---|---|---|
| G1 | Ver como está o dia | Início = **1** | [A] `dashboard/hoje` 200 para os três perfis (filtra bloco a bloco pela permissão) |
| G2 | Ver frota no mapa | Rastreamento = 1, **mas o mapa não carrega**: `config-global` → 403 para gerente e operador | [A] R3-G-10 |
| G3 | Aprovar desconto acima da alçada | Central de Vendas → solicitação → aprovar = **3** | [C] |
| G4 | Relatório de vendas do mês | Relatórios → escolher → período → gerar = **4**; num plano sem `relatorios_avancados` o item some (P2-A-04) | [C] |

### 1.4 Administrador / dono (SPA)

| # | Tarefa | Passos | Evidência |
|---|---|---|---|
| D1 | **Pôr um entregador novo para trabalhar no app** | Usuários → Novo usuário (nome, e-mail, senha, confirmar, papel) → salvar; Colaboradores → Novo (nome, telefone, entregador…) → salvar; **ligar um ao outro: não existe** | [C]+[A] **R3-G-01**: sem `colaboradores.user_id` o login de campo responde "Este acesso e exclusivo para colaboradores. Use o aplicativo do cliente." — tarefa **impossível** sem SQL |
| D2 | Criar papel para o caixa | Usuários e permissões → aba Perfis → novo → marcar dezenas de permissões entre 151 | [C]; sem modelo pronto "Caixa" (P2-A-14) |
| D3 | Configurar revenda nova para vender | ~35 cadastros de apoio sem roteiro (`/configuracoes`) + empresa fiscal + produtos + preços + condição de pagamento | [C] P2-A-07 / P2-D-13 |
| D4 | Mudar preço do botijão | Produtos → Preços → … | [C] |

### 1.5 Entregador (app, contagem pela navegação do expo-router)

Telas: `app-entregador/src/app/(app)/(tabs)/{inicio,entregas,rota,missao,perfil}`, `pedido/[id]/{index,concluir,ocorrencia}`, `iniciar-jornada`, `cliente-novo`, `solicitar-venda`, `vale-gas`, `ganhos`…

| # | Tarefa | Passos | Evidência |
|---|---|---|---|
| E1 | Começar o dia | Início → Iniciar jornada → veículo + KM → confirmar = **4** | [C] `inicio.tsx:112`, `iniciar-jornada.tsx` |
| E2 | **Fazer uma entrega** (dezenas/dia) | tocar o pedido (1) → Aceitar corrida (2) → [Navegar] → Concluir (3) → foto ou assinatura (4, uma é obrigatória) → Concluir entrega (5) = **5 por entrega**; forma de pagamento, troco e vasilhame **não** são registrados | [C] `pedido/[id]/index.tsx:171-182`, `concluir.tsx:153-183`; P2-C-03 (decisão do dono) |
| E3 | Vender em campo a cliente novo | Clientes → Novo cliente (nome, CPF/CNPJ, endereço, número, referência, telefone) → salvar → Solicitar venda → itens → enviar = **8+**; número com mais de 10 caracteres **dá 500** | [C]+[A] **R3-G-04** |
| E4 | Vender Vale-Gás em campo | **impossível**: o app só verifica vale; `POST missao/vale-gas` sem tela | [C] inventário #83 |
| E5 | Encerrar e conferir o acerto | Início → Encerrar → KM; "meu acerto" não existe (P2-C-13) | [C] |

### 1.6 Consumidor (app, contagem pela navegação)

| # | Tarefa | Passos | Evidência |
|---|---|---|---|
| K1 | Primeiro pedido | `index` → tutorial → selecionar revenda → login → políticas → SMS → cadastro → endereço → home → carrinho → pagamento = **~10 telas** (uma vez) | [C] Frente A Rodada 2 §1 |
| K2 | **Pedir de novo** (o uso real) | Home "Pedir de novo / Repetir" (1) → carrinho: forma de pagamento (2–3) → Finalizar (4) → PIX ou acompanhamento = **3–4** | [C] `home/index.tsx:132-145`, `carrinho.tsx:399,447` |
| K3 | Acompanhar entrega | push → `track` = **1–2** | [C] |
| K4 | Trocar endereço de entrega | Carrinho → endereço → escolher/novo (mapa + formulário de 8 campos) = **3–10** | [C] `AddressFormModal.tsx` |

### 1.7 Superadmin (SPA da plataforma)

| # | Tarefa | Passos | Evidência |
|---|---|---|---|
| S1 | **Criar revenda** | **impossível pelo produto** (sem botão nem `POST` de criação em `superadmin/api.ts`) | [C] P2-A-01 (aberto) |
| S2 | Suspender / reativar | Empresas → linha → Suspender → confirmar = **3** | [C] `superadmin/api.ts:180-181`; funcionava sob RLS desde a correção P2-E-01 |
| S3 | Trocar plano | Empresas → linha → plano → salvar = **3–4** | [C] |
| S4 | Ver saúde das revendas | 6 contadores; sem métricas de uso (P2-A-12) | [C] |

---

## 2. Passada cognitiva e Nielsen — onde o usuário leigo trava

| Passo | Pergunta que falha | Heurística | Observado | Dono |
|---|---|---|---|---|
| D1 ligar usuário ↔ colaborador | *Sabe o que fazer?* Não: não há controle em lugar nenhum | Reconhecimento em vez de memória; prevenção de erro | O dono cria usuário com papel "Entregador" e acha que terminou; o entregador recebe "Use o aplicativo do cliente" | C |
| Erro de CPF/CEP do colaborador | *Percebe o que deu errado?* Não: "Verifique os campos." | Recuperação de erro | `max:11` sem normalizar (R3-G-07) | F |
| Mapa do Rastreamento (gerente) | *Vê o controle?* Vê o item, a tela abre, o mapa não | Status visível | 403 silencioso em `config-global` (R3-G-10) | F/C |
| Aba Boletos | *Entende que faz o que quer?* A aba diz "a geração ocorre no fluxo de cobrança" — fluxo que não existe | Correspondência com o mundo real | R3-G-05 | B |
| Abas de Frota/RH | *Vê o controle?* A aba "Abastecimento" lista, mas não tem "Registrar" | Consistência | R3-G-06 | F |
| Novo pedido: situação/operação/setor | *Sabe o que fazer?* São termos do sistema, não do atendente; ficam em "mais opções" e o toast "Falta informar a operação" aparece quando a sugestão não vem | Linguagem do usuário | `NovoPedidoDialog` (P2-D-13 numa revenda sem cadastro) | A |
| App do entregador, login sem vínculo | *Percebe o que deu errado?* A mensagem manda ao app do consumidor | Linguagem / recuperação | `AppAuthController.php:150` | D |
| Termos técnicos visíveis | — | Linguagem do usuário | "Operação", "Setor de entrega", "Plano de contas", "Centros de custo", "Regras do extrato", "MCMM", "SPED", "Conciliação contábil", "Satélites" — na mesma barra que "Pedidos" | C (menu) |

---

## 3. Propostas — ELIMINAR → JUNTAR → AUTOMATIZAR → ANTECIPAR → CRIAR

Formato: meta de passos · impacto · esforço (P/M/G) · risco · **F** = fundamentada (medida) / **H** = hipótese a validar com revendas.

| ID | Tarefa | Movimento | Proposta | Meta | Impacto | Esforço | Risco | Base |
|---|---|---|---|---|---|---|---|---|
| R3-G-12 | A1 "o de sempre" | **ANTECIPAR + ELIMINAR** | Com a bina (`telefonia/fila`) o cliente já vem escolhido; "Repetir último pedido" vira o botão principal e já cria o pedido com a sugestão (condição, setor e operação do último pedido, que a `PedidoSugestaoService` já devolve). Situação/operação/setor saem da tela quando a sugestão os trouxe | **2** (atender → confirmar) | o atendente, dezenas de vezes por dia | P (dados já existem) | baixo: confirmação mostra o resumo | H (contagem [C]; medir com o spec quando a máquina estiver livre) |
| R3-G-13 | D1 entregador no app | **JUNTAR + CRIAR** | No cadastro de Colaborador com "entregador" marcado, um bloco "Acesso ao app" (e-mail + senha provisória) que cria o usuário, dá o papel Entregador e grava `user_id` numa transação. Mensagem do login de campo: "Seu usuário ainda não está ligado a um cadastro de entregador. Peça ao gerente." | **1 tela, 1 salvar** (hoje impossível) | toda revenda, a cada contratação | M | médio: identidade (fail-closed, sem `firstOrCreate` por e-mail — CLAUDE.md) | **F** (R3-G-01, provado) |
| R3-G-14 | C1–C5 caixa | **JUNTAR + CRIAR** | Tela "Caixa do dia" separada do Financeiro contábil: abrir, receber (total/parcial), recibo, sangria/transferência, estorno, fechar — as rotas já existem (inventário #9, #43–47). As abas contábeis vão para Configurações > Financeiro | receber = **3** (achar → valor → confirmar) | caixa | M (só tela) | baixo | **F** (rotas prontas sem tela) + H (ordem das ações) |
| R3-G-15 | Perfil Caixa | **CRIAR** (modelo) | Papel padrão "Caixa" no `RbacSeeder` (sem `pedido.create`, com `caixa.*`, `financeiro.baixar`, `financeiro.view`) — junta com P2-A-14 | — | separação de funções no dinheiro | P | baixo; decisão do dono sobre o recorte | H |
| R3-G-16 | E2 entrega | **JUNTAR** | Concluir = uma tela: forma de pagamento + valor recebido/troco + vasilhame + comprovação, com o que não se aplica escondido; "Aceitar" automático ao abrir a entrega que já está atribuída a ele | **3** (abrir → preencher → concluir) | entregador, cada entrega; o malote fecha sozinho | M | **depende da decisão §5.3 da Rodada 2** | H (sem aparelho) |
| R3-G-17 | E3 cliente novo em campo | **ELIMINAR** | Corrigir R3-G-04 e pedir só nome + telefone + "aqui" (GPS); o resto o atendente completa depois (a revisão de identidade já existe) | **3** | venda em campo não aborta | P | baixo | **F** (500 provado) |
| R3-G-18 | Frota/RH | **CRIAR** (botões) | "Registrar" nas abas que só leem (abastecimento, óleo, pneus, documentos, pátio, comissões, recessos) — 16 rotas prontas | **2** por registro | gestor de frota; o alerta de "troca vencida" deixa de ficar aceso para sempre | P–M | baixo | **F** |
| R3-G-19 | Cobrança | **CRIAR** (fluxo) | Na parcela: "Gerar boleto" → imprimir; na aba Boletos: "Gerar remessa" / "Importar retorno" (rotas #6–8, #41–42 prontas) | **2** por boleto | revenda que cobra a prazo | M | médio (banco real) — validar com leitor (GUIA_DO_DONO) | **F** (rotas) + H (fluxo bancário) |
| R3-G-20 | Rastreamento | **ELIMINAR** (porta) | Chave do mapa por rota de `monitora.view` (R3-G-10) | 1 | gerente vê o mapa | P | baixo | **F** |
| R3-G-21 | Menu | **JUNTAR + linguagem** | Menu por perfil: o atendente vê 5 itens (Pedidos, Clientes, Central, Caixa, Início); MCMM, SPED, Satélites, Plano de contas ficam atrás de "Mais" ou Configurações; licença e permissão na mesma fonte (R3-G-08) | — | todos | M | baixo | H |
| R3-G-22 | D3 onboarding | **AUTOMATIZAR** | Catálogo inicial de GLP, condições de pagamento padrão, situações (com "Em Rota") e checklist "pronto para vender" — a O8 da Frente A | **≤ 10 min** | revenda nº 2 | M | baixo | H (P2-A-07) |
| R3-G-23 | S1 criar revenda | **CRIAR** | "Nova revenda" no superadmin (P2-A-01) | **1 formulário** | venda do SaaS | G | alto (identidade/tenant) | F (Rodada 2) |

**Telas que podem ser unificadas:** Pedidos × Central de entregas (o despacho podia ser uma coluna do Kanban de pedidos — H); Clientes × Clientes duplicados (a revisão vira um aviso na ficha — H); Cartões × Gás do Povo × Vale-Gás × Convênios (formas de recebimento — H); Caixa (operação) × Financeiro (contabilidade) — proposta R3-G-14.

**Etapas desnecessárias medidas/contadas:** situação/operação/setor no pedido quando já há sugestão; "Aceitar corrida" para pedido já atribuído ao próprio entregador; confirmar senha no "Novo usuário" quando o sistema poderia gerar senha provisória com troca obrigatória.

---

## 4. Não medido e por quê
- **Tarefas do caixa, gerente, admin e superadmin no navegador:** só contadas no código. O spec de atendimento é o modelo (`Tarefa` em `produto/apoio.ts`); os demais ficam como próxima rodada — a API local não aguentou jornadas longas em paralelo com seis agentes.
- **Apps:** sem aparelho (proibido emulador). Contagem pelo expo-router e pelos componentes; jornada PARCIAL por definição (protocolo §3.4).
- **Tempo em segundos:** não comparável a produção (§ ressalva).
- **Teste com usuário leigo real:** nenhuma proposta H foi validada com revenda.
