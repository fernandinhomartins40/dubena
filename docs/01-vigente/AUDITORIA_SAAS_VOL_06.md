# Auditoria SaaS — Volume 6: Fiscal

**Recorte:** `app/Domain/Fiscal/` — 18 arquivos, 2.918 linhas.
**Leitura:** 18/18 lidos integralmente (conferido por `wc -l`: 2.918 = soma do recorte).
**Data:** 2026-08-25.
**Método:** ver [AUDITORIA_SAAS.md](AUDITORIA_SAAS.md). Nenhuma documentação foi
consultada para formar os achados — só o código.

---

## Arquivos lidos

| Arquivo | Linhas |
|---|---:|
| DanfePdfService.php | 350 |
| FiscalService.php | 334 |
| SpedFiscalService.php | 274 |
| CupomTextoService.php | 213 |
| XmlNfeBuilder.php | 212 |
| ResolucaoTributariaService.php | 208 |
| SpedContribuicoesService.php | 195 |
| Drivers/NFePHPSefazDriver.php | 191 |
| NfEntradaService.php | 184 |
| CalculoImpostoService.php | 182 |
| CertificadoService.php | 142 |
| CodigoBarras128C.php | 135 |
| IbptService.php | 76 |
| ImpostoItem.php | 67 |
| Drivers/FakeSefazDriver.php | 59 |
| Contracts/SefazDriver.php | 42 |
| ModeloDocumento.php | 28 |
| SituacaoNota.php | 26 |
| **Total** | **2.918** |

---

## Leitura geral do domínio

O Fiscal é, de longe, o domínio mais bem escrito do sistema até aqui. O cálculo
de imposto (`CalculoImpostoService`) e a resolução tributária
(`ResolucaoTributariaService`) são portes fiéis e comentados do legado, com as
regras legisladas em constantes nomeadas e o *porquê* de cada conjunto de CST
registrado. `CodigoBarras128C` e `CupomTextoService` explicam decisões de forma
exemplar — por que Code 128C e não I2of5, por que 55 colunas, por que cortar em
vez de quebrar. Isso não é ressalva de cortesia: é o padrão contra o qual os
achados abaixo se destacam.

O problema deste domínio **não é a regra fiscal — é a fronteira**. Três fronteiras
estão mal fechadas:

1. **A fronteira do tenant.** O domínio quase sempre filtra `empresa_id`
   corretamente (mérito), mas onde não filtra, o dado que vaza é fiscal — o pior
   tipo. E o `NfEntradaService` filtra por `grupo_id`, não por empresa.

2. **A fronteira do dado da empresa.** Existe uma cadeia de defaults que
   silenciosamente atribui a **São Paulo** e a **homologação** tudo o que não
   estiver preenchido. Para a Dubena (PR) isso já está errado; para um SaaS com N
   revendas em N estados, é estrutural.

3. **A fronteira do vocabulário.** O campo `tipo` da nota é lido com três
   valores diferentes em três arquivos.

O padrão dominante da auditoria reaparece aqui na sua terceira variante — *o
conceito existe, é calculado corretamente e é descartado na saída*: o
`ResolucaoTributariaService` resolve `origem_icms`, CST de PIS/COFINS e ST com
fidelidade ao legado, e o `XmlNfeBuilder` grava `orig = 0`, `CST = '01'` e nenhum
grupo de ST no XML que vai à SEFAZ.

---

## Achados

### A-6.1 (ALTA) — `tpAmb = 2` (homologação) fixo no código: o sistema não sabe emitir em produção

**Critério:** C4 — convenção não declarada.

`Drivers/NFePHPSefazDriver.php:127` (`toolsDaEmpresa`), `:150` (`tools`) e
`:172` (`dadosEmitente`) fixam o ambiente:

```php
'ambiente' => 2,
...
'tpAmb' => (int) ($emit['ambiente'] ?? 2),
'tpAmb' => 2,
```

E `XmlNfeBuilder.php:69`: `$std->tpAmb = (int) ($emitente['ambiente'] ?? 2); // 2=homologação`.

`dadosEmitente()` é a **única** fonte de `ambiente` para o caminho de transmissão,
e ela devolve o literal `2`. Não há leitura de `EmpresaConfig`, de `.env`, nem de
coluna alguma. **Não existe caminho de código que emita em produção.**

Isso é diferente de "está configurado para homologação": não há configuração. A
nota emitida em `tpAmb=2` recebe autorização da SEFAZ, ganha chave e protocolo,
vira `AUTORIZADA` no nosso banco, imprime DANFE — e **não tem valor fiscal
nenhum**. Todo o resto do sistema (financeiro, SPED, DANFE) trata essa nota como
válida.

**Por que é ALTA no SaaS:** cada revenda tem seu próprio momento de homologação
fiscal. Uma já em produção e outra ainda homologando precisam coexistir. Com o
literal no driver, ou todas emitem de brincadeira, ou alguém troca o código e
todas passam a emitir de verdade — inclusive a que ainda está testando.

---

### A-6.2 (ALTA) — O emitente do XML é montado sem IE, sem endereço, sem município e sem UF: a nota sai como se fosse de São Paulo

**Critério:** C4 — convenção não declarada / C1 — conceito ausente no fluxo.

`NFePHPSefazDriver::dadosEmitente()` (`:164-176`) devolve exatamente cinco
campos: `razao_social`, `nome_fantasia`, `cnpj`, `uf`, `ambiente`.

O `XmlNfeBuilder` consome esse array e precisa de muito mais. O que ele faz com o
que não recebeu:

| Campo do XML | Linha | Default aplicado | Consequência |
|---|---|---|---|
| `cUF` (código IBGE da UF) | `:60` | `35` | **São Paulo** |
| `cMunFG` (município do fato gerador) | `:66` | `0` | município inexistente |
| `idDest` | `:65` | `1` (interna) | interestadual vira interna |
| `indFinal` | `:71` | `1` (consumidor final) | sempre consumidor final |
| `IE` do emitente | `:83` | `null` | emitente sem inscrição estadual |
| `CRT` | `:84` | `3` (regime normal) | Simples Nacional emite como normal |
| `xLgr`/`nro`/`xBairro`/`xMun` | `:92-97` | `'N/I'`, `'S/N'`, `'N/I'`, `'N/I'` | endereço fictício |
| `cMun` do endereço | `:95` | `0` | idem |
| `UF` do endereço | `:97` | `'SP'` | **São Paulo** — e aqui o default nem é o `uf` que veio |
| `CEP` | `:98` | `null` | — |
| `natOp` | `:61` | `'VENDA'` | natureza genérica, ignora a operação fiscal |

Note a assimetria: `dadosEmitente` *devolve* `uf` (a UF real da empresa), e o
builder usa esse valor no `tagenderEmit`... não, usa `$e['uf'] ?? 'SP'` — que
funciona. Mas `cUF` (o código numérico da UF, `:60`) **não tem correspondente no
array** e cai em 35 sempre. Uma nota da Dubena (PR, cUF 41) sairia com `cUF=35` e
`UF=PR` — inconsistente com ela mesma, rejeição certa.

Isto conecta com a memória [[geografico-ibge-e-logradouros]]: *código IBGE errado
= rejeição da SEFAZ*. O sistema já aprendeu essa lição para o município do
cliente e a repete aqui para o emitente.

**Por que é ALTA no SaaS:** o default `'SP'` é a assinatura de um sistema
escrito para um emitente só. Não há erro nem aviso quando os dados faltam — a
nota é montada e transmitida com dados fictícios, e a rejeição volta da SEFAZ
como mensagem de schema, longe da causa.

---

### A-6.3 (ALTA) — O XML descarta a tributação resolvida: `orig` fixo, CST de PIS/COFINS fixo em '01', ST não emitida

**Critério:** C1 — conceito ausente (o cálculo existe, a saída não o usa).

O `ResolucaoTributariaService` resolve com fidelidade: `origem_icms`,
`cst_pis`/`cst_cofins`, `mva_st`, `aliq_icms_st`, `perc_bc_icms_st`,
`aliq_diferimento`, `aliq_fcp`, DIFAL. O `CalculoImpostoService` calcula tudo e
o `ImpostoItem::toArray()` grava 22 colunas na nota, incluindo `valor_icms_st`,
`bc_fcp`, `valor_fcp`, `valor_difal_dest`, `valor_difal_remet`.

O `XmlNfeBuilder::det()` (`:118-160`) emite:

```php
$icms->orig = 0;                       // sempre nacional
$icms->CST = $item->cst_icms ?? '00';
$icms->modBC = 3;                      // sempre "valor da operação"
$icms->vBC  = (float) $item->bc_icms;
$icms->pICMS = ...; $icms->vICMS = ...;
$make->tagICMS($icms);

$pis->CST = '01';                      // literal
$cofins->CST = '01';                   // literal
```

Nenhuma tag de ST (`vBCST`, `pMVAST`, `pICMSST`, `vICMSST`), nenhuma de FCP,
nenhuma de DIFAL (`tagICMSUFDest`), nenhum `pRedBC` mesmo quando o cálculo
aplicou redução de base.

Consequências concretas:

- Um item com CST 10 ou 60 (ST) tem o ICMS-ST calculado, gravado na nota, somado
  ao `total` do XML (`:170` — `vBCST` e `vST` saem no `ICMSTot`) e **ausente no
  detalhe do item**. Total e detalhe não fecham: rejeição.
- Produto importado (`origem_icms` 1/2/6/7) sai como nacional.
- Item com PIS/COFINS monofásico (CST 04, típico de combustível — e **GLP é
  monofásico de PIS/COFINS**) sai como CST 01 tributado.

O último ponto merece destaque: o negócio auditado é revenda de GLP. A tributação
monofásica de PIS/COFINS é exatamente o caso do produto principal da empresa. O
serviço que resolve isso corretamente existe; o XML não o usa.

---

### A-6.4 (ALTA) — Tributação-padrão embutida no código quando não há regra cadastrada

**Critério:** C4 — convenção não declarada.

`FiscalService.php:26-33`:

```php
private const TRIBUTACAO_PADRAO = [
    'cst_icms' => '00', 'aliq_icms' => 18.0,
    'aliq_pis' => 1.65, 'aliq_cofins' => 7.6,
];
```

Aplicada em `:104` sempre que `regraPara()` devolve `null`. O comentário é
honesto sobre o que é ("é exatamente o que o serviço fazia para TODO item antes
da matriz existir") e a nota ganha o campo `sem_regra_fiscal` avisando o
chamador. Isso é mais cuidado do que a média do sistema.

Ainda assim: **18% é a alíquota interna de São Paulo.** No Paraná a interna de
GLP não é 18%. E 1,65/7,60 é o regime não-cumulativo — que não vale nem para
Simples Nacional nem para o monofásico do GLP.

Emitir com alíquota errada é pior que não emitir: gera crédito indevido para o
destinatário, recolhimento a menor ou a maior, e a correção depois passa por
carta de correção (que não corrige valor) ou cancelamento fora de prazo.

O legado, segundo o comentário do próprio `ResolucaoTributariaService`, **prefere
falhar a emitir com tributo errado** (`ImpostoDB::setImpostoInter` lança exceção
quando não há linha do par UF→UF). Aqui a mesma filosofia foi aplicada ao par de
estados e abandonada na ausência de regra: uma falha vira exceção, a outra vira
18%.

**Recomendação de direção (não implementar agora):** fail-closed. Sem regra
cadastrada para o item, a emissão para — o mesmo tratamento que o par
interestadual ausente já recebe. Isto é o princípio "fail-closed em dinheiro e
identidade" do `CLAUDE.md` aplicado a imposto.

---

### A-6.5 (ALTA) — NF de entrada: unicidade de chave global, casamento de produto por descrição e escopo por grupo

**Critério:** C6 — escopo de tenant errado / C2 — classificação por texto.

Três problemas no mesmo arquivo, `NfEntradaService::importarXml()`:

**(a) Chave duplicada checada sem tenant** (`:44`):

```php
if ($chave && NfRecebida::query()->where('chave', $chave)->exists()) {
    throw ValidationException::withMessages(['xml' => 'Esta NF já foi importada (chave duplicada).']);
}
```

Se `NfRecebida` tem global scope de tenant, a query é filtrada pela empresa
**ativa na sessão** — que pode não ser `$empresaId`, já que o método recebe
`empresa_id` por parâmetro em vez de derivá-lo do contexto. Se não tem scope, um
fornecedor que emite a mesma NF para duas filiais faz a segunda importação
falhar com "já importada" apontando para a nota de outra empresa. Nos dois casos
o comportamento não é o pretendido, e qual dos dois ocorre depende de um detalhe
que o método não controla.

**(b) Produto casado por descrição** (`:71-79`):

```php
$produto = Produto::query()->where('grupo_id', $grupoId)
    ->where(function ($q) use ($codigo, $it) {
        $q->where('descricao', $it['descricao']);
        if (ctype_digit($codigo)) { $q->orWhere('id', (int) $codigo); }
    })->first();
```

A descrição comparada é a **do fornecedor**, string livre no XML dele. Dois
fornecedores escrevem "GLP P13", "BOTIJAO GLP 13KG", "P-13" para o mesmo produto.
Pior: `orWhere('id', (int) $codigo)` casa o **código do fornecedor com o nosso
id** — se o fornecedor numera itens 1, 2, 3, a NF entra dando baixa nos produtos
de id 1, 2 e 3 da nossa base. O comentário explica que essa comparação foi
*restringida* para evitar erro de tipo no Postgres, mas a restrição tratou o
sintoma (cast) e não a causa (código do fornecedor não é nosso id).

Não existe tabela de-para fornecedor→produto. Esse é o conceito ausente.

**(c) Escopo por `grupo_id`, não `empresa_id`.** O produto é buscado no grupo
inteiro. Combinado com o achado [[vinculo-vasilhame-fronteira-empresa]] do
Volume 5 — mesma classe de erro, domínio diferente — isto confirma que a
fronteira empresa×grupo não tem regra escrita: cada autor decidiu sozinho.

**Consequência no processamento** (`processar()`, `:104-140`): a entrada de
estoque e o contas a pagar são gerados a partir desse casamento. Um casamento
errado move estoque do produto errado e gera pagamento vinculado à nota errada.

---

### A-6.6 (MÉDIA) — `pf_aliq_icms_dest` usado também no ramo PJ (provável copy/paste)

**Critério:** C4.

`ResolucaoTributariaService::icmsInterestadual()`, ramo **não** consumidor final
(`:163`):

```php
'aliq_icms_dest' => (float) $uf->pf_aliq_icms_dest,
```

Todos os outros campos desse ramo leem a coluna sem prefixo (`$uf->cst_icms`,
`$uf->aliq_icms`, `$uf->mva`...). Só `aliq_icms_dest` lê a versão `pf_`. O ramo
consumidor final logo acima (`:148`) lê `pf_aliq_icms_dest` — corretamente, ali.

Na prática o impacto é contido, porque o DIFAL só é ativado para consumidor final
(`resolver()`, `:76`: `$difal = $interestadual && $consumidorFinal`), então o
valor lido no ramo PJ não chega a ser usado pelo cálculo. É um erro latente: se
alguém habilitar DIFAL para PJ (venda a contribuinte com ST partilhada), lerá a
coluna errada.

Classificado MÉDIA por ser silencioso e por estar num porte que o comentário
declara fiel ao legado — o que faz dele uma divergência do legado não documentada.

---

### A-6.7 (MÉDIA) — O campo `tipo` da nota tem três vocabulários no mesmo domínio

**Critério:** C5 — conceitos misturados / C2.

| Arquivo | Linha | Código |
|---|---|---|
| `FiscalService` | `:80` | `'tipo' => 'S'` (grava) |
| `XmlNfeBuilder` | `:64` | `$nota->tipo === 'E' ? 0 : 1` |
| `DanfePdfService` | `:167` | `$nota->tipo === 'ENTRADA' ? '0 - ENTRADA' : '1 - SAÍDA'` |
| `SpedFiscalService` | `:104` | `'1', // IND_OPER (1=saída)` — literal, ignora o campo |
| `CupomTextoService` | `:114` | `'1 - SAIDA'` — literal, ignora o campo |

O `DanfePdfService` compara com `'ENTRADA'`, valor que **nada no domínio grava**.
Portanto o DANFE de uma nota de entrada sairia rotulado "1 - SAÍDA". E o SPED
declara toda nota como saída por literal, o que só é verdade porque hoje o
sistema só emite saída — uma convenção não declarada que quebra no dia em que a
emissão de entrada (devolução, retorno de comodato) for usada.

Este é exatamente o padrão que a auditoria vem registrando: `char(1)` como
máquina de estados sem enum. `SituacaoNota` e `ModeloDocumento` são enums bem
feitos no mesmo domínio; `tipo` ficou de fora.

---

### A-6.8 (MÉDIA) — SPED: apuração sem créditos, regime presumido e IE/município em branco

**Critério:** C1 — conceito ausente / C4.

**(a) `SpedFiscalService`, registro 0000** (`:52-67`): `IE`, `COD_MUN` e `IM` saem
como string vazia. O PVA da Receita rejeita 0000 sem inscrição estadual. O dado
existe (`Empresa::inscricao_estadual` é lido pelo `DanfePdfService:158`), só não
foi ligado aqui.

**(b) Apuração E110 só com débito** (`:169-184`): `VL_TOT_CREDITOS = '0,00'` fixo,
e `VL_ICMS_RECOLHER = $totalDebito`. Nenhuma NF de entrada entra na apuração —
apesar de o `NfEntradaService` existir e gravar `NfRecebida` com itens. O
resultado é um SPED que declara recolher ICMS sobre **todo** o débito de saída,
sem abater o crédito das compras. Numa revenda de GLP, onde a compra da
distribuidora é a maior entrada, isso não é uma imprecisão pequena.

**(c) `SpedContribuicoesService` assume não-cumulativo** (`:113-131`): `M210` sai
com `COD_CONT = '01'` (não-cumulativa) e `ALIQ_PIS = '1,6500'` / `ALIQ_COFINS =
'7,6000'` **literais**, independentemente do que foi calculado por item e do
regime da empresa. Uma revenda no Simples Nacional (regime provável de boa parte
do público-alvo do SaaS) nem entrega EFD-Contribuições nesse formato.

**(d) Bloco H do SPED Fiscal** (`:187-211`) é derivado de `EstoqueSaldo` **na
data da geração**, não na data-fim do período. Um inventário de fechamento de
janeiro gerado em março declara o saldo de março. E cruza com o achado do Volume
4: `estoquefechamentos` está vazia — a foto histórica que deveria alimentar o
bloco H existe como tabela e nunca foi preenchida.

---

### A-6.9 (MÉDIA) — Certificado A1: senha decifrável no runtime e nenhuma vigilância de validade

**Critério:** C1.

`CertificadoService` é cuidadoso na parte difícil: valida com `openssl_pkcs12_read`
antes de aceitar, recusa expirado, guarda em disco privado com subpasta por
empresa, senha em cast `encrypted`, e expõe `status()` sem segredo. O comentário
sobre segregação física por tenant (F02.7) é o tipo de decisão que deveria estar
em todo lugar.

Duas lacunas:

**(a) Nada avisa quando o certificado vai vencer.** `status()` calcula
`dias_restantes`, mas só quando alguém abre a tela. Não há job, não há alerta —
apesar de existir uma `AlertaService` e uma central de alertas (Volume 5) feitas
exatamente para isso. Certificado A1 vale 1 ano; vencido, a revenda para de
faturar. Para um SaaS com N revendas, N datas de vencimento, isso é operação
diária que ninguém está fazendo.

**(b) `Certificate::readPfx($pfx, (string) $config->cert_senha)`
(`NFePHPSefazDriver:139` e `:154`) decifra a senha em memória a cada transmissão.
É inevitável para assinar; o que falta é o registro — não há log de *quem*
disparou uma emissão que usou o certificado de qual empresa. `InutilizacaoFiscal`
e `CartaCorrecao` gravam justificativa mas não `user_id`; o mesmo buraco de
autoria do achado A-5.6.

---

### A-6.10 (MÉDIA) — IBPT com alíquota inventada quando a tabela não está carregada

**Critério:** C4.

`IbptService.php:19`:

```php
private const PADRAO = ['nacional' => 12.0, 'importado' => 18.0, 'estadual' => 18.0, 'municipal' => 0.0];
```

Quando a NCM não está na `ibpt_aliquotas` (ou a tabela não existe), o serviço
devolve 30% de carga total como se fosse informação. O campo `fonte` sai como
`'padrão'` — honesto para quem lê o array, invisível para o consumidor que recebe
o cupom com "Valor aproximado dos tributos R$ X".

A Lei 12.741/2012 exige que o valor informado tenha **fonte declarada**. Informar
número estimado sem base é infração de consumo, não erro técnico.

O agravante para SaaS: a tabela IBPT é **regional e mensal**. Uma tabela carregada
serve a um estado e a um mês. Não há coluna de UF nem de vigência na consulta
(`:64`: `where('ncm', $ncm)` e nada mais), então N revendas em N estados
compartilhariam uma tabela só — a primeira que alguém carregar.

---

### A-6.11 (BAIXA) — `FakeSefazDriver` fixa UF 35 na chave fake

**Critério:** C4.

`FakeSefazDriver:22`: `sprintf('%02d%04d%014d%02d%03d%09d', 35, ...)`.

É driver de teste e o comentário diz que não deve ir a produção — a classificação
é BAIXA por isso. Fica registrado porque é a **quarta** ocorrência do literal 35/SP
no volume (com `XmlNfeBuilder:60`, `:97` e `NFePHPSefazDriver:132`, `:157`), o que
mostra que "São Paulo" foi assumido como universo em todo o domínio, inclusive nos
testes que deveriam pegar essa assunção.

Consequência prática: um teste que valide o dígito verificador ou a UF da chave
passa em SP e nunca exercitaria o PR.

---

### A-6.12 (BAIXA) — DANFE: rótulo "Base de cálculo ICMS" exibindo o valor do ICMS

**Critério:** C4.

`DanfePdfService::totais()` (`:222-226`):

```php
['Base de cálculo ICMS', $this->brl($nota->valor_icms), '20%'],
```

O quadro mostra o **valor** do imposto sob o rótulo da **base**. A nota grava as
duas coisas separadamente (o item tem `bc_icms`; o cabeçalho da nota não tem
`valor_bc_icms` totalizado — o `FiscalService:132-140` não soma a base). Então o
campo certo não existe no nível da nota, e o DANFE preencheu com o que tinha.

Também ausentes do quadro: `Base de cálculo ICMS ST` (só o valor sai), `Valor
aproximado dos tributos` (o IBPT existe e não é usado no DANFE), e `Valor do
frete` sai de `$nota->valor_frete` — coluna que o `FiscalService` nunca preenche.

É BAIXA porque é papel auxiliar e o número exibido está gravado; mas um fiscal que
confira base × alíquota × valor no DANFE encontra inconsistência aritmética.

---

## Padrões que este volume confirma

**1. O default silencioso como substituto de configuração.** Onze campos do XML,
o ambiente da SEFAZ, a alíquota de ICMS, as alíquotas do IBPT, o regime do
PIS/COFINS — todos têm um valor embutido que faz o sistema *funcionar* sem que
ninguém tenha configurado nada. Cada um desses defaults é a resposta correta para
**uma** empresa em **um** estado em **um** regime. É a assinatura mais clara do
"convenção de uma única revenda" que a memória do projeto registra.

**2. O cálculo correto descartado na fronteira.** Terceira variante do padrão
dominante, e a mais cara: `ResolucaoTributariaService` faz o trabalho difícil com
fidelidade documentada ao legado, e `XmlNfeBuilder` joga fora `orig`, `cst_pis`,
`cst_cofins`, ST, FCP, DIFAL e redução de base. Não é código faltando por
esquecimento — é uma camada terminada e outra deixada em rascunho, sem que nada
no sistema registre a diferença.

**3. Fail-closed aplicado a um caso e abandonado no vizinho.** O par
interestadual ausente lança exceção (fiel ao legado, comentado); a regra fiscal
ausente vira 18%. A mesma pergunta — "não sei tributar isto, o que faço?" —
recebe duas respostas opostas no mesmo arquivo.

**4. Autoria ausente.** Inutilização, carta de correção e emissão não registram
usuário. Mesmo buraco de A-5.6 (vale-gás e convênio). Em fiscal isso é pior:
inutilizar faixa de numeração e emitir carta de correção são atos que a Receita
atribui à empresa e que a empresa precisa atribuir a alguém.

---

## Para o plano (Volume 15)

Itens deste volume que dependem de decisão, não de código:

- **D-6.1** — Regimes tributários suportados pelo SaaS. Simples Nacional, Lucro
  Presumido e Lucro Real tributam PIS/COFINS de formas incompatíveis, e o código
  hoje assume um só. Sem essa definição não dá para desenhar nem o cadastro nem o
  SPED.
- **D-6.2** — Distribuição da tabela IBPT: por revenda (cada uma carrega a sua,
  regional e mensal) ou centralizada pela plataforma (a plataforma assina e
  distribui). Muda quem é responsável legal pelo número no cupom.
- **D-6.3** — Homologação fiscal por revenda: como o SaaS conduz N revendas por N
  homologações, com `tpAmb` por empresa e uma transição controlada de 2 para 1.

Itens de código, para o plano consolidado:

- Fonte única dos dados do emitente (uma classe que monta o emitente completo a
  partir de `Empresa` + `EmpresaConfig`, com validação fail-closed antes de
  transmitir) — resolve A-6.1, A-6.2 e parte de A-6.8.
- `XmlNfeBuilder` consumindo o `ImpostoItem` inteiro — resolve A-6.3.
- Tabela de-para fornecedor→produto e escopo por empresa na NF de entrada —
  resolve A-6.5.
- `tipo` como enum — resolve A-6.7.
- Alerta de vencimento de certificado usando a `AlertaService` que já existe —
  resolve A-6.9(a).

---

**Volume 6 fechado.** 18/18 arquivos, 2.918/2.918 linhas. 12 achados
(5 alta, 5 média, 2 baixa).
