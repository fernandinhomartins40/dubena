<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FASE 5 (unificação): classe base dos models do módulo Api (ex-api-app-gc).
 *
 * Os dados do app continuam no banco `sgcm_api` (estratégia de unificação
 * estrutural: a API roda DENTRO do ERP, mas as tabelas espelho *_importacao
 * permanecem por ora — a eliminação/migração de dados é etapa posterior).
 * Centralizar a conexão aqui evita repetir em cada model e facilita a futura
 * troca para a conexão `pgsql` quando as tabelas forem unificadas.
 */
abstract class ApiModel extends Model
{
    protected $connection = 'sgcm_api';
}
