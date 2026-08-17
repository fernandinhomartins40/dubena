# Runbook — backup e restore do ERP-NOVO

> **Para quem está com um incidente em mãos.** Vá direto para
> [Restaurar em produção](#restaurar-em-produção-desastre). O resto é contexto.
>
> **RTO medido: ~3 minutos** para o banco (169 s, aferido em 17/08/2026 sobre um
> backup real de produção de 418 MB / 4,1 GB de banco). Some o tempo de decidir
> e de subir a aplicação: planeje **10 a 15 minutos** de indisponibilidade total.

---

## O essencial

| | |
|---|---|
| Script de backup | `deploy/backup/backup.sh` |
| Script de restore | `deploy/backup/restore.sh` |
| Destino na VPS | `/opt/backups-erpnovo/` |
| Cópia dos scripts na VPS | `/opt/dubena-deploy/backup/` |
| Agendamento | cron do **host**, 03:15 UTC (ver [Agendamento](#agendamento)) |
| Retenção | 7 diários · 4 semanais · 6 mensais |
| O que é copiado | banco (`pg_dump -Fc`) + `storage/app` (certificados A1, uploads) |

---

## Fazer um backup

```bash
ssh root@gasemcasa.com
cd /opt/dubena-deploy && bash backup/backup.sh
```

Leva ~2 minutos. Termina com `CONCLUÍDO:` e o nome do arquivo. Qualquer falha
devolve exit ≠ 0 — inclusive dump ilegível, checksum divergente ou disco cheio.

Variantes:

```bash
bash backup/backup.sh --sem-storage        # só o banco (mais rápido)
DESTINO=/mnt/outro bash backup/backup.sh   # outro destino
BACKUP_REMOTO=user@host:/bk bash backup/backup.sh   # replica para fora do host
```

### O que o script valida antes de dizer "ok"

1. o container do banco existe;
2. há espaço em disco de pelo menos **2× o tamanho do banco** (um backup que
   enche o disco derruba a produção que deveria proteger);
3. o dump não está vazio **e é legível** — `pg_restore -l` lista >100 objetos;
4. o tar do storage abre sem erro;
5. o SHA-256 de cada artefato confere.

---

## Testar o restore (faça isto periodicamente)

**Um backup nunca testado não é um backup.** O modo padrão é seguro: sobe um
Postgres descartável na porta 55433 e não toca em nada em execução.

```bash
cd /opt/dubena-deploy
bash backup/restore.sh /opt/backups-erpnovo/<arquivo>.dump
```

Ao final ele imprime as verificações e o tempo. **Compare os números com a
produção** — devem ser idênticos:

```
soma financeiros : 250029904.80
titulos          : 443714
pedidos          : 400070
notas fiscais    : 241021
clientes         : 55453
orfaos (deve 0)  : 0
```

O container de teste fica de pé para inspeção. Descarte com:

```bash
docker rm -f erpnovo-restore-teste
```

**Cadência sugerida:** mensal, e sempre depois de mudar o schema de forma
relevante. Registre a data e o tempo obtido na tabela do fim deste arquivo.

---

## Restaurar em produção (desastre)

> ⚠️ **Destrutivo.** Substitui o banco de produção. Tudo gravado depois do
> horário do backup é perdido.

**Antes de rodar, responda:**

1. **É mesmo necessário?** Se o problema é de código, o caminho é
   `deploy/rollback.sh` (T3.8), que é reversível. Restore de banco não é.
2. **Qual backup?** Confira o timestamp no nome (UTC). O mais recente nem sempre
   é o certo: se a corrupção entrou às 14h, o backup das 15h já a contém.
3. **O que foi gravado desde então?** Pedidos, pagamentos e NF-e emitidos após o
   backup **somem**. Para NF-e isso tem consequência fiscal — anote os números
   antes, se conseguir.

```bash
# 1. Coloque a aplicação em manutenção (evita escrita durante o restore)
docker exec erpnovo-app php artisan down --render="errors::503"

# 2. Restaure
cd /opt/dubena-deploy
bash backup/restore.sh /opt/backups-erpnovo/<arquivo>.dump --em-producao
#    → pede a confirmação literal: RESTAURAR-PRODUCAO
#    → antes de tocar em nada, grava PRE_RESTORE_<timestamp>.dump

# 3. Volte a aplicação
docker exec erpnovo-app php artisan up

# 4. Confira
curl -s -o /dev/null -w '%{http_code}\n' https://gasemcasa.com/novo/api/health
docker exec erpnovo-app php artisan cutover:check
```

**O script grava um dump de segurança do estado atual antes de sobrescrever**
(`PRE_RESTORE_*.dump`). Se você restaurou o backup errado, é por ele que se
volta.

### Se falhar no meio

O `pg_restore` roda com `--clean --if-exists`: um restore interrompido deixa o
banco **parcialmente restaurado** — pior que o estado inicial. Nesse caso:

1. **não tente "consertar" à mão**;
2. rode o restore de novo, do mesmo arquivo, até o fim (é idempotente);
3. se o arquivo estiver corrompido (checksum falha), use o backup anterior;
4. se nenhum backup servir, restaure o `PRE_RESTORE_*.dump` para voltar ao
   estado de antes da tentativa e escale.

---

## Agendamento

O cron roda no **host**, não pelo `schedule:work` do Laravel — o backup precisa
funcionar exatamente quando a aplicação está quebrada.

```bash
# crontab -e  (como root, na VPS)
15 3 * * * cd /opt/dubena-deploy && bash backup/backup.sh >> /var/log/erpnovo-backup.log 2>&1
```

Instalação assistida:

```bash
bash deploy/backup/instalar-cron.sh
```

---

## ⚠️ Pendência conhecida: a cópia está só neste host

`BACKUP_REMOTO` não está configurado, então os arquivos vivem **no mesmo host que
eles protegem**. Um incidente de host (disco, provedor, `rm -rf`) leva junto o
backup, e a auditoria aponta volume Docker como ponto único de falha.

Para resolver, defina um destino externo (S3/B2 via `rclone`, ou outro servidor
via `rsync`) e passe-o ao script:

```bash
BACKUP_REMOTO=usuario@servidor:/backups/erpnovo bash backup/backup.sh
```

Enquanto isso não existir, o script **avisa em toda execução** — de propósito.

---

## Certificados A1

Hoje **nenhuma empresa tem certificado carregado** (verificado: zero registros em
`empresa_configs` e a pasta `storage/app/certificados/` sequer existe). Isso é
esperado — o upload é a T3.10.

**Depois que o primeiro certificado for carregado**, o backup passa a incluí-lo
automaticamente (o tar cobre `storage/app` inteiro) e a linha
`storage: N caminho(s) de certificado` deixará de mostrar 0. Confira isso na
primeira execução após o upload: certificado A1 é insubstituível sem custo e
prazo com a certificadora.

---

## Histórico de testes de restore

| Data | Backup usado | Tempo | Resultado |
|---|---|---:|---|
| 2026-08-17 | `erpnovo_20260817_113913` (418 MB) | **169 s** | ✅ todos os números batem com a produção |
