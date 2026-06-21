<?php

namespace App\Domain\Tenant;

use RuntimeException;

/**
 * Lançada quando uma operação exige tenant (empresa/grupo) mas ele não foi
 * resolvido no contexto da requisição (ex.: chamada sem autenticação ou usuário
 * sem empresa ativa). O handler HTTP traduz para 409/422.
 */
class TenantNotResolvedException extends RuntimeException
{
}
