# F3-08 (parcial) — O código IBGE deixa de ser um inteiro digitado à mão

Data: 2026-08-31 (America/Sao_Paulo)

## O escopo, dito antes de tudo

A tarefa F3-08 pede: *"município IBGE como catálogo autoritativo; cidade/área de
cobertura referenciam-no; eliminar três catálogos concorrentes por fases"*.

**Este lote faz o primeiro pedaço, não a tarefa inteira.** Os três catálogos
continuam existindo:

| Tabela | O que é | Escopo |
|---|---|---|
| `municipios_ibge` | catálogo oficial (PK = `cod_ibge`) | nacional |
| `cidades` | cidade do tenant | **por grupo** |
| `cidades_plataforma` | cidade de cobertura do SaaS | plataforma |

Unificá-los alcança `Cidade` em 27 arquivos e mexe em dado fiscal. Não é
trabalho para fazer sem validação, e por isso fica registrado como aberto.

## O que estava furado, e o que já estava bom

O vínculo `cidades.municipio_ibge → municipios_ibge` **já existia** (migration de
2026-08-23). O que faltava era a porta de escrita usá-lo.

`POST /geo/cidades` aceitava `cod_ibge` como `nullable|integer` — um número
livre, digitado à mão, sem conferência nenhuma. Um código errado **não dá erro no
cadastro**: dá rejeição da SEFAZ na primeira nota emitida para aquela cidade,
quando ninguém lembra de onde veio o número.

### Uma leitura minha que estava errada

No caminho, achei que `'cod_municipio' => (int) ($municipio?->cod_ibge ?? 0)` no
`NFePHPSefazDriver` mandaria `cMun = 0` para a SEFAZ. **Não manda.** Logo abaixo
há uma validação que exige `cod_municipio >= 1000000`, `cuf > 0` e a UF batendo
com a do município, e lança erro claro de "cadastro fiscal incompleto".

Registro porque a conclusão apressada teria produzido uma "correção" para um
problema inexistente — e porque a validação que já existia é boa.

## A correção

`normalizarCidade()` no `GeoController`:

- **se veio `municipio_ibge`**, o `cod_ibge` e a `uf` são **derivados** dele. Não
  se confia em dois campos que podem discordar;
- **se veio só `cod_ibge`**, ele é conferido contra o catálogo e vira o vínculo.
  Código inexistente é recusado na hora — onde custa um minuto, em vez de na
  SEFAZ, onde custa uma nota;
- **UF divergente do próprio código é recusada.** Uma cidade com UF que não bate
  com o IBGE é rejeitada pela SEFAZ, e o cadastro não tem como saber qual das
  duas o operador quis;
- vale também na **edição** — senão bastaria criar certo e editar errado.

## O que deliberadamente NÃO se exige

**Cidade sem código continua podendo ser criada.**

Exigir o código aqui travaria o cadastro de quem ainda não migrou, e a emissão
fiscal já barra o que falta com erro claro. A garantia deste lote é sobre código
**errado**, não sobre código **ausente** — são problemas diferentes, e só o
primeiro é silencioso.

## Verificação

| Portão | Resultado |
|---|---|
| Testes focais | 6 (`CidadeMunicipioIbgeTest`) |
| Suíte integral | ver ESTADO_ATUAL |
| Pint | aprovado |

## O que fica aberto da F3-08

- unificar os três catálogos (o pedaço grande);
- `cidades_plataforma` também aceita `cod_ibge` livre — mesma correção, outra
  porta;
- decidir se `cidades` deve continuar sendo por grupo, já que município é fato
  nacional. Hoje duas revendas podem ter "Guarapuava/PR" com códigos
  diferentes; com esta mudança, ao menos ambos os códigos são válidos.
