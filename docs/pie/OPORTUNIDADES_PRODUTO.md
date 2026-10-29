# Oportunidades de produto — PIE V2

**Data:** 2026-10-29 · **Base:** [BANCA_R2_USUARIO.md](BANCA_R2_USUARIO.md) (avaliação estática, sem navegador nem aparelho) e achados dos demais revisores

Público principal: atendente e caixa da revenda (SPA), entregador em rota (app de
campo) e consumidor final (app do cliente). Tarefa mais frequente: **receber a
ligação, achar o cliente, lançar o pedido e despachar**.

Ordem: ELIMINAR → JUNTAR → AUTOMATIZAR → ANTECIPAR → CRIAR. "Feito" indica o que
esta auditoria já entregou.

| # | Tipo | Oportunidade | Problema demonstrado | Impacto | Esforço | Situação |
|---|---|---|---|---|---|---|
| 1 | ELIMINAR | Remover do perfil do app os campos que não são salvos ("Possuo convênio", "Gás do Povo", "Sexo") | R2-15: o usuário preenche e nada é gravado; dado sem uso também contraria a minimização da LGPD | Médio | Baixo | Pendente |
| 2 | ELIMINAR | O servidor carimba a hora de abertura e fechamento do caixa, em vez de recebê-la do navegador | R2-05: a hora vinha 3 h adiantada | Médio | Baixo | Mitigado (hora local correta); eliminar o campo continua sendo melhor |
| 3 | JUNTAR | Uma busca única de cliente (nome sem acento, dígitos do telefone, CPF/CNPJ normalizado) para lookup, listagem e bina | R2-01, R2-17 | **Alto** (jornada principal) | Médio | **Parcial**: telefone no lookup do Novo pedido |
| 4 | AUTOMATIZAR | Helpers centrais de número e data, com guardião que proíba `toISOString().slice(0,10)` e `Number(x.replace(',', '.'))` em `features/` | R2-06, R2-07 | Alto (evita a classe inteira de defeito) | Baixo | **Parcial**: `decimalBR`, `hojeISO`, `agoraLocal` criados e aplicados; o guardião ainda não existe |
| 5 | AUTOMATIZAR | Normalizar CPF, CNPJ e telefone no servidor (`prepareForValidation`) com dígito verificador | R2-17 | Médio | Baixo | Pendente |
| 6 | AUTOMATIZAR | O servidor decide se o pedido do app gera PIX (só para condição PIX) | R2-02 | Alto (cobrança indevida) | Médio | Pendente; depende da decisão de PIE-11 |
| 7 | ANTECIPAR | Modal de re-login que preserva a tela, com aviso antes de o token de 24 h vencer | R2-12: o operador perde o que digitou | Médio | Médio | Pendente |
| 8 | ANTECIPAR | Abrir o Novo pedido com o cliente da chamada em curso (bina) e "repetir último pedido": pedido em 2 passos | **Hipótese**: depende de a telefonia estar ligada | Alto, se confirmado | Médio | A validar com a operação |
| 9 | CRIAR | `Idempotency-Key` em `POST /pedidos` (admin) e `POST app/v1/pedidos`, reaproveitando o middleware `Idempotente` | R2-13, R3-08 | Alto (pedido duplicado) | Baixo | Pendente (junto com PIE-11) |
| 10 | CRIAR | Traduções pt_BR das mensagens de validação do Laravel | R2-11: mensagens em inglês ou chave crua | Médio | Baixo | Pendente |
| 11 | CRIAR | Confirmar antes de descartar o Novo pedido montado (Esc, clique fora, X) | R2-10 | Médio | Baixo | Pendente |

Nenhuma funcionalidade comercial nova foi implementada nesta execução. Tudo o que foi
entregue corrige comportamento existente.
