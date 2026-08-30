# F2-03 — A licença passa a decidir

Data: 2026-08-30 (America/Sao_Paulo)

## O achado

`LicencaService`, o catálogo de 10 recursos e o middleware `recurso:chave` já
existiam — e estavam **corretos**. O problema era outro:

| | |
|---|---|
| Rotas usando o middleware `recurso:` | **0** de 604 |
| Assinaturas no banco | **0** |

A licença existia e não decidia nada. E como o serviço é **fail-closed** (sem
assinatura, `$base = []` e nenhum recurso é liberado), a ordem importa: semear
plano e assinatura **antes** de ligar o enforcement, senão quem já opera perde os
módulos de uma vez.

### Uma documentação que dizia o contrário do código

O docblock do `PlanosSeeder` afirmava: *"empresas sem assinatura têm tudo
liberado (fail-open do LicencaService)"*. Verifiquei em teste — é **fail-closed**:
`recursosEfetivos()` devolve `[]`. O comentário descrevia um comportamento
antigo, e foi corrigido. Documentação errada sobre fail-open é pior que nenhuma.

## A grade: dois planos, ambos pagos

Desenhada a partir do uso **real** medido na cópia da Dubena, não de palpite:

| Medição | Leitura |
|---|---|
| 241.021 notas fiscais | fiscal é essencial, não diferencial |
| 21.135 boletos + 4.961 PIX | cobrança idem |
| 16.153.938 posições GPS | monitoramento é uso pesado, de quem tem frota |
| 3.097 pós-vendas, 20 sorteios | CRM é maturidade, não partida |

**Essencial — R$ 349,90**: `app_consumidor`, `app_entregador`, `cobranca`,
`nfce`. O que toda revenda precisa no dia um.

**Completo — R$ 749,90**: o catálogo inteiro. Declarado por
`RecursoCatalogo::chaves()`, não por lista fixa — recurso novo entra nele
sozinho, e o que fica de fora do Essencial vira diferencial por construção.

Os três planos antigos (Básico/Pro/Enterprise) foram **desativados, não
excluídos**: uma assinatura antiga apontando para um plano apagado ficaria órfã,
e o tenant perderia todos os recursos de uma vez.

## Enforcement por prefixo, não rota a rota

O middleware `recurso:chave` exigia ser escrito em cada rota — e é justamente
isso que não aconteceu em nenhuma das 604. `RecursoPorRota` resolve o recurso
pelo **caminho**:

```
api/admin/monitora/*   -> monitora
api/admin/pos-vendas*  -> crm
api/admin/veiculos*    -> frota
api/admin/boletos|pix  -> cobranca
api/admin/notas|fiscal -> nfce
api/admin/relatorios/* -> relatorios_avancados
```

Vale mais que declarar rota a rota por dois motivos: não exige reescrever
`routes/api.php`, e **rota nova de um domínio já nasce coberta** — o
esquecimento, que é o modo de falha real, deixa de existir.

Rota fora do mapa não é barrada. Cliente, produto, pedido, estoque e financeiro
são o núcleo do ERP: o que a revenda contrata por definição, não add-on.

## Assinar virou um ato executável

`saas:assinatura:criar <empresa|tenant> <plano>` — no console, porque assinar é
ato comercial da plataforma, não algo que o tenant faz sobre si mesmo.

- aceita uma empresa ou **todas as empresas de um tenant** (`tenant --tenant=1`),
  restrito às que têm `TenantCompany` **APROVADO** — assinar empresa fora da
  fronteira criaria licença para algo que o resolver nega de qualquer forma;
- recusa substituir assinatura vigente sem `--force`;
- substituir **cancela** a anterior em vez de apagar: a trilha do que a empresa
  teve contratado sobrevive à troca;
- invalida o cache por empresa — sem isso, a empresa continuaria vendo a lista
  vazia de antes;
- registra `assinatura.criada` na trilha de plataforma.

## Evidência

- `LicencaEnforcementTest`: **8 testes / 24 assertions** — sem assinatura não
  libera nada; módulo não contratado dá **402**; contratado passa; o núcleo do
  ERP nunca depende de plano; flag desligada é passagem livre; a grade tem dois
  planos e nenhum gratuito; plano legado fica inativo e não é apagado; e o mapa
  de rota cobre os módulos opcionais sem pegar o núcleo.
- Comando verificado: assinatura criada, 10 recursos liberados, duplicada
  recusada.
- Suíte integral verde nos dois modos.

## Como ligar, quando for a hora

1. `php artisan db:seed --class=PlanosSeeder` (idempotente)
2. `php artisan saas:assinatura:criar tenant completo --tenant=1` — a Dubena usa
   o sistema inteiro, então o plano dela reflete isso e ligar não muda nada para
   ela
3. conferir `GET /api/me` → `features` com os 10 recursos
4. `SAAS_ENFORCE_LICENCA=true`

A ordem não é sugestão: inverter os passos 2 e 4 tira os módulos do ar.

## O que este microlote NÃO fez

A tarefa F2-03 completa pede também **limites e add-ons por assinatura**
(ex.: teto de usuários, de veículos rastreados) e **overrides temporários
auditados**. `RecursoOverride` já existe como tabela e é lido pelo
`LicencaService`; o que falta é a porta para operá-lo e o conceito de limite
numérico, que hoje não existe no catálogo — recurso é booleano.

Fica registrado como pendência, não como esquecimento.

`erp-novo/perda.sql` segue pré-existente e intocado.
