<?php

namespace App\Monitora\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UNIFICAÇÃO: classe base dos models do módulo Monitoramento (ex-app
 * monitoramento-veiculos, agora App\Monitora dentro do ERP ctrl-web).
 *
 * As tabelas do monitoramento vivem no schema PostgreSQL dedicado `monitora`
 * (isolado de `public` do ERP e `api` do módulo da API — há tabelas homônimas
 * como empresas/menus/setors/users/veiculos nos diferentes schemas).
 *
 * A conexão `monitora` aponta para o MESMO PostgreSQL, com search_path no
 * schema monitora. Centralizar aqui evita repetir em cada model.
 */
abstract class MonitoraModel extends Model
{
    protected $connection = 'monitora';
}
