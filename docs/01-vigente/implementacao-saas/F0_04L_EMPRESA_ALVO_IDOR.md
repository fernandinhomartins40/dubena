# F0-04L — autorização da empresa-alvo

**Estado:** CONCLUÍDO  
**Data:** 2026-08-25 22:13 (America/Sao_Paulo)  
**Branch/SHA de referência:** `main` / `4d8a3f3`

## Evidência reutilizada

Reutilizado o cross-scan A-3.4/A-3.5. A releitura ficou restrita aos dois
controllers, `User`, `TenantContext`, enforcement de permissão, rotas e testes
contratuais diretamente envolvidos.

## Implementação

- `show/update/destroy` de empresa agora exigem vínculo real com a empresa-alvo;
- todas as portas de config/certificado/CSC/SMTP/integrações aplicam a mesma
  verificação e devolvem 404 para irmã same-group sem vínculo;
- mutações exigem empresa-alvo ativa para usuário tenant; a troca precisa ser
  explícita via `X-Empresa-Id`;
- a permissão é reavaliada no ID da empresa-alvo, não no tenant de origem;
- o bypass legado de `support` foi preservado dentro do grupo;
- novo teste percorre as 12 portas e prova ausência de efeitos colaterais.

## Validação

Suíte focal ampliada: 33 testes aprovados, 123 assertions, zero falha.  
Pint aplicado ao recorte; `git diff --check` aprovado.

## Rollback

Reverter o helper de empresa acessível, `autorizarNaEmpresa` e o teste novo.
Esse rollback reabre leitura/escrita de configurações e segredos de uma irmã sem
vínculo, portanto não é seguro sem bloquear previamente as rotas afetadas.

## Destino canônico

A contenção HTTP não substitui TenantAccount, RLS fail-closed nem autorização
por capability. A separação estrutural permanece F1/F2.
