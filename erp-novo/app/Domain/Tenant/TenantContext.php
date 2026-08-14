<?php

namespace App\Domain\Tenant;

/**
 * Contexto de tenant da requisição atual.
 *
 * Substitui o `Session::get('empresa_padrao')` do legado (presente em ~126
 * controllers): em vez de estado preso à sessão web, o tenant é resolvido por
 * requisição a partir do usuário autenticado / do token, e fica disponível via
 * injeção de dependência (singleton no container) ou facade.
 *
 * DOIS CONCEITOS, como no ctrl-web — e confundi-los foi um erro que custou caro:
 *
 *  - **empresa ativa** (`empresaId`): a empresa de CONTEXTO. Define config,
 *    numeração fiscal, caixa que se opera, cabeçalho da NF-e. É UMA só.
 *    Equivale ao `empresa_padrao` da sessão do legado.
 *
 *  - **empresas visíveis** (`empresasVisiveis`): o CONJUNTO de empresas cujos
 *    dados o usuário enxerga nas listagens. Equivale a `empresas_permitidas`,
 *    que o legado calcula no login e usa em `whereIn('pedido.empresa_id', ...)`.
 *
 * Por que importa: numa rede com matriz + filiais, o dono espera ver a operação
 * da REDE ao abrir uma listagem — não só a da empresa em que está "posicionado".
 * Tratar a troca de empresa como um interruptor exclusivo faz 400 mil pedidos
 * sumirem da tela ao selecionar uma filial vazia, e foi exatamente o que
 * aconteceu aqui.
 *
 * O grupo continua sendo a fronteira dura: empresas visíveis nunca cruzam redes.
 */
class TenantContext
{
    private ?int $empresaId = null;

    private ?int $grupoId = null;

    /** @var list<int> empresas cujos dados são visíveis (inclui a ativa). */
    private array $empresasVisiveis = [];

    /**
     * Define o tenant ativo.
     *
     * @param  list<int>  $visiveis  empresas visíveis; vazio = só a ativa.
     */
    public function set(int $empresaId, int $grupoId, array $visiveis = []): void
    {
        $this->empresaId = $empresaId;
        $this->grupoId = $grupoId;
        $this->empresasVisiveis = $this->normalizar($visiveis, $empresaId);
    }

    /** Limpa o tenant (ex.: logout / contexto sem tenant). */
    public function clear(): void
    {
        $this->empresaId = null;
        $this->grupoId = null;
        $this->empresasVisiveis = [];
    }

    public function hasTenant(): bool
    {
        return $this->empresaId !== null && $this->grupoId !== null;
    }

    /** id da empresa ATIVA (contexto), ou null se não resolvido. */
    public function empresaId(): ?int
    {
        return $this->empresaId;
    }

    /** id do grupo ativo, ou null se não resolvido. */
    public function grupoId(): ?int
    {
        return $this->grupoId;
    }

    /**
     * Empresas cujos dados o usuário VÊ nas listagens.
     *
     * Sempre contém a empresa ativa. Com uma empresa só, é equivalente ao
     * comportamento anterior — a mudança só se manifesta em rede com filiais.
     *
     * @return list<int>
     */
    public function empresasVisiveis(): array
    {
        if ($this->empresasVisiveis !== []) {
            return $this->empresasVisiveis;
        }

        return $this->empresaId !== null ? [$this->empresaId] : [];
    }

    /**
     * Restringe a visibilidade a UMA empresa (o filtro "empresa" da tela).
     *
     * É o equivalente ao combo do legado: `if ($empresa_id != 0) where(...)`.
     * Ignora silenciosamente uma empresa fora do conjunto permitido — filtro de
     * tela não concede acesso.
     */
    public function filtrarPorEmpresa(?int $empresaId): void
    {
        if ($empresaId === null || $empresaId <= 0) {
            return;
        }
        if (in_array($empresaId, $this->empresasVisiveis(), true)) {
            $this->empresasVisiveis = [$empresaId];
        }
    }

    /**
     * id da empresa ativa, exigindo que exista (uso em Services).
     *
     * @throws TenantNotResolvedException
     */
    public function requireEmpresaId(): int
    {
        if ($this->empresaId === null) {
            throw new TenantNotResolvedException('Empresa (tenant) não resolvida no contexto da requisição.');
        }

        return $this->empresaId;
    }

    /**
     * id do grupo ativo, exigindo que exista.
     *
     * @throws TenantNotResolvedException
     */
    public function requireGrupoId(): int
    {
        if ($this->grupoId === null) {
            throw new TenantNotResolvedException('Grupo (tenant) não resolvido no contexto da requisição.');
        }

        return $this->grupoId;
    }

    /**
     * Lista de ids única, só inteiros positivos, sempre com a ativa dentro.
     *
     * @param  list<int>  $visiveis
     * @return list<int>
     */
    private function normalizar(array $visiveis, int $empresaId): array
    {
        $ids = array_map('intval', $visiveis);
        $ids[] = $empresaId;

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }
}
