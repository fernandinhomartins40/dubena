# F2-07 — Três defeitos de segurança que só existem porque agora há N revendas

Data: 2026-08-31 (America/Sao_Paulo)

O TOTP anti-replay já havia sido fechado num microlote anterior. Restavam três
itens, e os três têm a mesma forma: **código correto para uma revenda, defeituoso
para muitas.**

---

## 1. O lockout atravessava tenants por NAT

O lockout conta falhas em dois eixos: por e-mail (brute-force contra uma conta) e
por IP (varredura de muitos e-mails a partir de um ponto). Os dois são
necessários.

O eixo do IP, num SaaS, era uma arma apontada para o próprio cliente:

- duas revendas atrás do mesmo CGNAT de operadora — comum em cidade pequena, que
  é exatamente o público de um ERP de distribuidora de GLP — compartilhavam o
  contador. A primeira a errar cinco vezes **tirava a segunda do ar**, sem saber
  que ela existe;
- e o atacante tinha uma alavanca gratuita: cinco tentativas com e-mails
  inventados derrubavam o IP de um escritório inteiro.

**A correção:** o contador por IP passa a ser escopado ao tenant do e-mail alvo.

As duas defesas seguem intactas — varredura dentro de um tenant continua barrada,
ataque a uma conta de vários IPs continua barrado — e só o dano deixa de
atravessar a fronteira. É a diferença entre defender o cliente e puni-lo pelo
comportamento de um estranho.

E-mail que não corresponde a usuário nenhum não tem tenant, e portanto não tem
contador de IP. Isso é deliberado: contá-lo num balde global é justamente a
alavanca acima. A conta segue protegida pelo eixo do e-mail, e o volume bruto
pelo throttle da rota — que é o lugar certo para tratar tráfego sem identidade.

**Achado no caminho:** a falha por senha errada — a mais comum de todas — não
gravava `empresa_id` no `login_logs`. O contador por IP nunca enxergaria
justamente as tentativas que deveria contar. Corrigido derivando a empresa do
dono do e-mail.

---

## 2. A política de senha tinha o dono errado

A política é declarada por **empresa**. A senha é do **usuário** — que pode
operar várias empresas do mesmo tenant.

O defeito é concreto: um gerente que atende as filiais A (mínimo 12, com
complexidade) e B (mínimo 8) trocava a senha com a filial B ativa e passava com 8
caracteres. **A senha que ele acabou de enfraquecer é a mesma que abre a filial
A.** A política mais rígida era contornada escolhendo por qual porta entrar.

**A correção não foi mudar o dono da tabela.** A empresa continua podendo
declarar a sua exigência, e isso tem valor. O que muda é a regra aplicada a uma
pessoa: a mais rígida entre as empresas que ela alcança. Uma credencial só é tão
forte quanto a porta mais exigente que ela abre.

Duas decisões dentro disso:

- **empresa sem política não baixa a régua.** O default (mín. 8) é um piso, não
  um teto — senão bastaria uma filial sem configuração para anular a exigência
  de uma irmã;
- **`expira_dias = 0` significa "nunca expira", não "menor que tudo".** Tratá-lo
  como número faria uma empresa sem prazo cancelar o prazo de outra.

A leitura usa `withoutTenant` — necessário, porque `PasswordPolicy` é escopada
pela empresa ativa e com o escopo ligado a consulta enxergaria exatamente uma
política: a da porta mais permissiva. Mas a leitura continua estreita, restrita
aos ids que o usuário comprovadamente alcança, e há teste provando que política
de outro tenant não o influencia.

---

## 3. O catálogo de campos sensíveis não era a fonte

O mecanismo field-level estava correto. A **lista** de quais campos são sensíveis
é que vivia em dois lugares: no `PermissaoCatalogo::GRANULARES` e, de novo, numa
constante `CAMPOS_SENSIVEIS` dentro de cada resource/controller que filtra.

A consequência é silenciosa e permanente: declarar
`cliente.campo.documento.view` no catálogo **não protegia nada** até alguém
lembrar de acrescentar `'documento'` à constante do `ClienteResource`. Uma
permissão que existe, aparece na tela de papéis, pode ser negada a um usuário — e
não esconde o campo.

Isso é pior do que não ter a permissão, porque afirma uma proteção que não
acontece.

**A correção:** `CamposPermitidos::camposControlados($modulo)` deriva a lista do
catálogo, e os filtros passam a usá-la quando o chamador não informa nada. As
constantes duplicadas foram removidas — hoje há **zero** ocorrências de
`CAMPOS_SENSIVEIS` no `app/`.

O teste `test_toda_chave_granular_do_catalogo_vira_campo_controlado` varre o
catálogo inteiro e exige que cada chave `modulo.campo.X.acao` produza controle
real. Uma chave nova passa a valer sozinha.

### Sobre "default-deny"

O enunciado da tarefa pede catálogo default-deny. A convenção do projeto é que
campo sem chave declarada é livre, e trocar isso globalmente quebraria todas as
respostas da API de uma vez, sem ganho de segurança proporcional.

A leitura que implementei é a que tem efeito real: **o catálogo é a única fonte,
e um campo declarado nunca escapa por esquecimento.** O que ficava aberto não era
o default — era a segunda lista, que fazia a declaração não valer.

---

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 6 (lockout) + 7 (senha) + 5 (campos) = **18** |
| Suíte integral, modo padrão | **1423 passes / 4490 assertions** |
| Suíte integral, enforcement ligado | **1423 passes**, zero falhas |
| `RlsCoberturaTest` (PostgreSQL real) | 6/6 |
| Pint | aprovado |

## O que fica da F2

F2-01 (schema de request/response por rota) e o restante de F2-08 (matriz de
papéis reais, troca adversarial de IDs em todas as rotas).
