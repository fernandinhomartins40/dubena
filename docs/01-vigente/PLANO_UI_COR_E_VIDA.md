# Adendo de direção visual — cor, hierarquia e vida

Data: 10/09/2026. Complementa `PLANO_MODERNIZACAO_UI_UX_ERP_NOVO.md`, não o
substitui. Aquele plano está correto na estrutura e nos 22 achados; o que faltou
nele foi o **gate visual** — ele mesmo registra que o navegador não estava
disponível, e a paleta acabou alterada sem nunca ter sido vista.

Este adendo cobre exatamente esse buraco. Toda cor aqui tem contraste **medido**,
não estimado, e a inspeção foi feita em navegador headless contra homologação.

---

## 1. O diagnóstico: por que o design ficou morto

O commit `abddbcc8` fez três mudanças de cor. Uma estava certa, duas foram longe
demais:

| Mudança | Motivo alegado | Medição real | Veredito |
|---|---|---|---|
| `--primary-foreground` branco → grafite | branco sobre `#FF6200` = 3,00:1, reprova AA | confirmado: 3,00:1 → 5,51:1 | **Correto**, mas resolvido pelo lado errado |
| `.text-primary` → laranja L32% | laranja puro como texto reprova | 3,00:1 → 6,44:1; **L38% já daria 4,91:1** | **Exagerado**: escureceu 6 pontos além do necessário |
| `--success` lime → verde | separar sucesso de destaque de marca | — | **Correto** conceitualmente, mas deixou o lime órfão |

O efeito somado é o que se vê na tela: o lime `#DBFB3B` sobrou em **um único
lugar** da SPA inteira (variante `warning` do badge), e o laranja virou marrom no
texto. Sobrou branco, cinza e preto.

Há ainda um erro de método: o override foi escrito como

```css
@layer utilities { .text-primary { color: hsl(var(--accent-foreground)); } }
```

Isso sequestra **toda** ocorrência de `text-primary` na aplicação, inclusive
ícones, bordas e elementos gráficos — onde o mínimo do WCAG é 3:1, não 4,5:1, e
o laranja puro já passava. Um requisito de texto foi aplicado a coisas que não
são texto.

---

## 2. A regra que resolve o falso dilema

Acessibilidade e vivacidade não estão em conflito aqui. O que estava faltando é
a distinção que o próprio WCAG faz:

| Papel do laranja | Mínimo exigido | `#FF6200` puro | Pode usar puro? |
|---|---|---|---|
| Texto corrido (< 18,66px bold) | 4,5:1 | 3,00:1 | **Não** |
| Texto grande (≥ 24px, ou ≥ 18,66px bold) | 3:1 | 3,00:1 | **Sim** |
| Borda, ícone, indicador, gráfico | 3:1 | 3,00:1 | **Sim** |
| Superfície com texto grafite por cima | 4,5:1 (do texto) | 5,51:1 | **Sim** |

**Portanto: o `#FF6200` continua vivo em quase todo lugar.** Ele só não serve
para texto pequeno sobre branco. Escurecer a marca inteira para resolver um caso
foi o erro.

### Os tokens revisados

| Token | Hoje | Proposto | Contraste medido | Papel |
|---|---|---|---|---|
| `--primary` | `#FF6200` | **mantém** | 3,00:1 s/ branco | Marca. Superfícies, bordas, ícones, texto grande |
| `--primary-foreground` | grafite 12% | **grafite 12%** | 5,51:1 | Texto sobre laranja. Mantém |
| `--primary-acao` | *(não existe)* | **`#CC4E00`** (L40%) | **4,50:1 c/ branco** | Só o botão de ação primária, com texto branco |
| `--primary-texto` | L32% (`#A33F00`) | **L38% (`#C24A00`)** | **4,91:1** | Links e texto laranja. Passa AA com 0,4 de folga |
| `--destaque` (lime) | `#DBFB3B` | **mantém** | **14,26:1 sobre grafite** | Deixa de ser órfão — ver §3 |
| `--success` | verde 140° | **mantém** | — | Separação correta do Astra |

`--primary-acao` é a peça nova e o ponto central da proposta. Medição:

```
#FF6200 + texto branco  : 3,00:1  vivo, reprova
#FF6200 + texto grafite : 5,51:1  passa, visual pesado (o atual)
#CC4E00 + texto branco  : 4,50:1  passa E parece um botão aceso
```

O botão primário recupera o texto branco — que é o que faz um botão parecer
clicável — pagando 10% de luminosidade num único componente, em vez de escurecer
a marca inteira.

**Correção obrigatória de método:** remover o override de `.text-primary` do
`@layer utilities`. Texto laranja passa a usar `--primary-texto` explicitamente;
ícones, bordas e gráficos voltam a usar `--primary` puro.

---

## 3. Onde o lime volta a viver

O lime é a cor mais forte da marca e hoje aparece em um lugar. A medição mostra
por que ele estava sendo desperdiçado: **14,26:1 sobre grafite** — contraste
altíssimo, seguro para texto e para números grandes.

Regra: **o lime vive em fundo escuro, nunca sobre branco** (1,16:1 sobre branco —
invisível). Isso lhe dá um território próprio em vez de competir com o laranja.

| Lugar | Uso | Por quê |
|---|---|---|
| Sidebar grafite | Indicador do item ativo | A sidebar já é escura; é o lugar natural do lime, e hoje o ativo é laranja competindo com os botões |
| KPI do dashboard | Acento `lime` do `StatCard` (já existe, ninguém usa) | Número grande sobre tint de lime; o `StatCard` já suporta |
| Landing, hero grafite | Já usa — **manter** | É o único lugar onde o lime está certo hoje |
| Badge de destaque | Já usa | Manter |

Nada disso exige componente novo: `StatCard` já tem o acento `lime` implementado
e sem consumidor.

---

## 4. O que a inspeção visual revelou (e ninguém tinha visto)

Com o navegador headless rodando contra homologação, a landing e o login foram
capturados pela primeira vez. A estrutura melhorou muito — mas a página acumula
marcas de layout gerado por IA que não vêm da marca nem do negócio:

| Achado | Onde | Correção |
|---|---|---|
| Eyebrow em CAIXA ALTA espaçada acima de **toda** seção | `GESTÃO PARA GÁS E ÁGUA`, `MENOS RETRABALHO`, `O PRODUTO POR DENTRO`, `UMA ROTINA CONECTADA`, `ANTES DE COMEÇAR` | Remover. Onde a seção precisa de contexto, o próprio título diz |
| Marcadores `01/02/03` em conteúdo que **não é sequência** | Os três benefícios (atendimento, vasilhame, fechamento) são paralelos, não etapas | Remover a numeração. Mantê-la só no fluxo pedido→entrega→conferência, que é sequência de verdade |
| Metadados unidos por `·` | `Clientes · pedidos · relacionamento` | Reescrever como frase ou lista |
| Seta colada ao texto do botão | `Entrar ↗`, `Conhecer o sistema ↓` | Tirar do rótulo; a seta é ícone, não palavra |
| Cards idênticos, mesmo raio e mesma sombra | Benefícios, módulos, CTA | Diferenciar por hierarquia: o que é mais importante ganha peso, não outro card |
| Botão primário com texto quase preto | Login, "Entrar" | `--primary-acao` + texto branco (§2) |

O último é literalmente o que motivou este adendo: no login, "Entrar" parece
desabilitado.

Vale registrar o que **está certo** e deve ser preservado: hero grafite com lime,
mockup honestamente identificado como ilustrativo, ausência de número inventado,
sem depoimento falso, sem carrossel. A disciplina do Astra em não inventar prova
comercial foi correta e não deve ser desfeita ao mexer no visual.

---

## 5. Onde gastar a ousadia

O princípio é gastar boldness num lugar só e manter o resto quieto. Para um ERP
de GLP, o lugar é **o número**.

Quem usa este sistema passa o dia com quantidade: botijões em poder do cliente,
pedidos na fila, parcelas em aberto, saldo por setor. O número é o conteúdo — não
o card que o embrulha, não o ícone ao lado. Hoje os KPIs têm `text-3xl` e o
mesmo peso visual do resto da página.

Direção: **o número é o elemento tipográfico da interface.** Grande, tabular,
apertado no tracking, com o rótulo discreto embaixo. Tudo em volta — borda,
sombra, tint do ícone — recua. É a única licença estética que este adendo pede,
e ela vem do assunto: uma revenda de gás mede tudo em unidades cheias e vazias.

Corolário: nada de gradiente decorativo, nada de sombra colorida, nada de
animação de entrada por seção. O movimento fica reservado para responder a uma
ação da pessoa (abrir, confirmar, mover) — que é onde ele informa.

---

## 6. Execução

Ordem proposta, cada item verificável isoladamente:

| Lote | Entrega | Gate |
|---|---|---|
| **V1** | Tokens: `--primary-acao`, `--primary-texto` L38%; remover o override de `.text-primary`; ajustar `button.tsx` | Contraste recalculado por script; captura antes/depois do login e de uma lista |
| **V2** | Lime na sidebar (item ativo) e nos KPIs via acento existente do `StatCard` | Captura da sidebar e do dashboard nos dois temas |
| **V3** | Número como elemento tipográfico no `StatCard` | Captura do dashboard; conferir que o rótulo continua legível |
| **V4** | Landing: remover eyebrows, numeração não-sequencial, `·` e setas no rótulo | Captura da página inteira; conferir que nenhum texto novo foi inventado |
| **V5** | Tema escuro completo nos quatro lotes acima | Captura em ambos os temas, nas larguras 390 e 1280 |

**Gate obrigatório para todos:** captura em navegador antes e depois. A skill
`browser-automation` está instalada e comprovadamente funciona contra
homologação (`gasemcasa.com/novo/`) — foi assim que os achados da §4 apareceram.
Nenhum lote fecha sem alguém ter olhado a tela.

Cada lote é isolado: reverte-se um sem tocar nos outros. Nenhum toca backend,
schema, permissão ou dado.

---

## 7. O que este adendo não resolve

- **Contraste do tema escuro** ainda não foi medido caso a caso; a §2 mede o
  claro. O lote V5 existe por isso.
- **Piloto com operadores** (U14 do plano original) continua aberto. Medição de
  contraste não substitui alguém usando o sistema.
- **Decisão comercial da landing** (destino do CTA, hierarquia Gás em
  Casa/Dubena) segue com o dono — este adendo só mexe em forma, não em promessa.
- **Os 13 consumidores de `DataTable` sem `error`** são dívida do plano original
  (U10/U12), não deste adendo, e continuam pendentes.

Nada aqui autoriza antecipar cutover, alterar schema ou pular gate do plano SaaS.
