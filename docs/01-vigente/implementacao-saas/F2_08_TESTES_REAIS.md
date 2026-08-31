# F2-08 — Testes que medem o produto entregue, não o mecanismo

Data: 2026-08-31 (America/Sao_Paulo)

Três frentes, e a diferença entre elas e o que já existia é sempre a mesma:
**testar o que o cliente recebe, em vez de testar que a engrenagem gira.**

---

## 1. Troca adversarial de IDs — varrendo as rotas de verdade

O ataque é o mais barato que existe num SaaS: o usuário legítimo da revenda A
troca o id na URL pelo de um registro da revenda B. Sem ferramenta, sem
credencial roubada — basta editar o número na barra de endereço.

Já havia testes cross-tenant pontuais. Eles cobrem **o que alguém lembrou de
escrever**. Este teste descobre as rotas sozinho, a partir do roteador, e por
isso alcança também o que ninguém pensou em cobrir — inclusive rotas que ainda
não existiam quando o arquivo foi escrito.

### Vazamento encontrado

`GET /api/admin/produtos/{id}/estoque` devolvia **200 para produto de outro
tenant**.

Os saldos não vazavam — `EstoqueSaldo` tem RLS e o conjunto vinha vazio. Mas o
200 confirma que aquele id existe em algum lugar, que é metade do que um atacante
quer ao varrer números. `EstoqueController::porProduto` simplesmente não
verificava o dono do produto.

Corrigido com `abort_unless(Produto::query()->whereKey($id)->exists(), 404)` — o
global scope faz a verificação valer só dentro do tenant. **404 e não 403:**
"existe, mas você não pode" já é a informação que se queria negar.

### Duas salvaguardas contra o teste virar teatro

- **contraprova positiva**: as mesmas rotas respondem 200 para o id próprio.
  Sem isso, o teste passaria com a aplicação inteira fora do ar — 404 em tudo
  também é "nenhum 200";
- **guarda do mapa**: recurso novo com `{id}` **falha** o teste até alguém
  declarar a que grupo pertence (dado de empresa, ou catálogo de plataforma).
  Sem isso a cobertura encolheria silenciosamente a cada rota nova.

---

## 2. Matriz com os papéis REAIS

Os papéis que uma revenda recebe são os do `RbacSeeder`: Administrador, Gerente,
Operador, Entregador. Testes que montam papéis sob medida provam que o mecanismo
de permissão funciona — **não que os papéis entregues estão desenhados
corretamente.** São perguntas diferentes, e só a segunda diz o que o cliente
recebe.

A parte negativa é a que importa. Uma matriz que só verifica o que cada papel
*pode* fazer passa com um papel que pode tudo — e é exatamente assim que a
separação de funções apodrece: alguém acrescenta uma permissão para resolver um
chamado, e nada acusa.

13 testes, nenhum usando `support`. Os que mais pesam:

- **Gerente não administra acesso nem exclui** — `usuarios`/`papeis` são
  privilégio do Administrador, e `.delete` fica fora do papel por desenho;
- **Operador não alcança os verbos que desfazem dinheiro** — `estornar` e
  `aprovar` ficam fora (default-deny); `baixar` continua valendo, porque é o
  cotidiano dele;
- **Entregador vê só pedido e monitoramento** — é o papel mais exposto do
  produto: roda num celular que anda pela rua e pode ser perdido ou roubado.
  Cada permissão a mais ali é um vazamento com pernas;
- **a escada não se inverte** — cada papel é subconjunto *estrito* do anterior.
  Se um papel menor ganhar algo que o maior não tem, a escada deixou de ser
  escada, e isso não apareceria em nenhum teste positivo.

Os 13 passaram na primeira execução: a hierarquia do produto já estava coerente.
O valor aqui não foi achar defeito — foi passar a detectar quando ela deixar de
estar.

---

## 3. Ausência de assinatura nas PORTAS

`LicencaService` já era fail-closed, com teste provando. Mas um serviço que
decide certo e uma porta que não pergunta a ele produzem exatamente o mesmo
resultado que não ter licença nenhuma — foi assim que o `recurso:` ficou em 0 de
604 rotas até F2-03.

Este arquivo mede a outra ponta: a requisição HTTP de uma empresa sem contrato é
recusada com **402**, e o usuário permanece autenticado e autorizado.

A distinção de status não é detalhe: 402 permite a tela dizer *"seu plano não
inclui isto"*. Um 403 mandaria o cliente pedir acesso a alguém que não pode
concedê-lo.

E o núcleo do ERP continua respondendo. Uma revenda com pendência comercial não
pode perder acesso ao próprio cadastro — isso transformaria uma cobrança em
perda de dados operacionais.

A varredura pergunta ao próprio `RecursoPorRota::recursoDaRota()`, que resolve o
recurso por **prefixo de caminho** — procurar um middleware `recurso:` declarado
nas rotas não acharia nada, porque não é assim que ele funciona. Perguntar ao
middleware faz o teste acompanhar o mapa: prefixo novo lá passa a ser exigido
aqui sem ninguém editar o teste.

---

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 3 (adversarial) + 13 (matriz) + 6 (assinatura) = **22** |
| Suíte integral, modo padrão | **1445 passes / 4547 assertions** |
| Suíte integral, enforcement ligado | **1445 passes**, zero falhas |
| Pint | aprovado |

## Estado da F2

Fechadas: F2-02, F2-02A, F2-03, F2-04, F2-05, F2-06, F2-07, F2-08.
Aberta: **F2-01** (schema de request/response por rota) — a parte de
action/resource/capability e catálogo já foi entregue; falta o schema.
