<?php

namespace App\Domain\Auditoria;

/**
 * Tradução da trilha para linguagem de gente.
 *
 * `audit_logs` guarda o nome da TABELA (`clientes`) e um verbo técnico
 * (`atualizado`). Isso serve para depurar, não para o dono do negócio
 * responder "quem desativou este cliente e por quê". Este catálogo é a camada
 * que transforma linha de log em frase legível.
 *
 * É também o ponto único onde se decide o que a auditoria cobre: um model com
 * `use Auditavel` que não esteja aqui aparece na trilha com o nome cru da
 * tabela — funciona, mas fica feio. Ao auditar um model novo, registre-o aqui.
 */
final class CatalogoAuditoria
{
    /**
     * Tabela → rótulo singular exibido na trilha.
     *
     * A ordem não importa; o que importa é a cobertura. Tabela de apoio e cache
     * ficam DE FORA de propósito: auditar 'bairros' ou cache de rota enche a
     * linha do tempo de ruído e esconde a decisão humana, que é o que se busca.
     *
     * @var array<string, string>
     */
    public const ENTIDADES = [
        // Cadastros
        'clientes' => 'Cliente',
        'colaboradores' => 'Colaborador',
        'produtos' => 'Produto',
        'empresas' => 'Empresa',
        'empresa_configs' => 'Configuração da empresa',
        'config_globais' => 'Configuração global',
        'veiculos' => 'Veículo',
        // Operação
        'pedidos' => 'Pedido',
        'pedidoitens' => 'Item do pedido',
        'financeiros' => 'Título financeiro',
        'financeiroparcelas' => 'Parcela',
        'contas' => 'Conta',
        'contamovimentos' => 'Movimento de conta',
        'cheques' => 'Cheque',
        'notas_fiscais' => 'Nota fiscal',
        'estoquemovimentos' => 'Movimento de estoque',
        'comodatos' => 'Comodato',
        'valegas' => 'Vale-gás',
        'missoes' => 'Missão',
        // Acesso (quem pode o quê — mudança aqui é decisão sensível)
        'users' => 'Usuário',
        'roles' => 'Papel',
        'permissions' => 'Permissão',
    ];

    /**
     * Ação → como se lê na linha do tempo.
     *
     * Além do CRUD do trait, ações SEMÂNTICAS: sem elas, desativar um cliente
     * fica indistinguível de corrigir o CEP dele — as duas viravam
     * "clientes/atualizado". A pergunta que motivou a auditoria ("quem tirou
     * este cliente da lista?") só tem resposta se o verbo for próprio.
     *
     * @var array<string, string>
     */
    public const ACOES = [
        'criado' => 'Criou',
        'atualizado' => 'Alterou',
        'excluido' => 'Excluiu',
        'desativou' => 'Desativou',
        'reativou' => 'Reativou',
        'encerrou_conta' => 'Encerrou a conta',
        'situacao_alterada' => 'Mudou a situação',
        'baixou' => 'Baixou',
        'estornou' => 'Estornou',
        'aprovou' => 'Aprovou',
        'recusou' => 'Recusou',
        'cancelou' => 'Cancelou',
        'emitiu' => 'Emitiu',
        'vendeu_em_campo' => 'Vendeu em campo',
    ];

    /**
     * Ações que representam DECISÃO, não digitação.
     *
     * A tela destaca estas: numa lista longa, "Desativou" e "Estornou" importam
     * mais do que "Alterou". Não altera o que é gravado, só o que salta à vista.
     *
     * @var list<string>
     */
    public const ACOES_SENSIVEIS = [
        'desativou', 'reativou', 'excluido', 'encerrou_conta',
        'estornou', 'aprovou', 'recusou', 'cancelou',
    ];

    public static function rotuloEntidade(string $tabela): string
    {
        return self::ENTIDADES[$tabela] ?? ucfirst(str_replace('_', ' ', $tabela));
    }

    public static function rotuloAcao(string $acao): string
    {
        return self::ACOES[$acao] ?? ucfirst($acao);
    }

    public static function acaoSensivel(string $acao): bool
    {
        return in_array($acao, self::ACOES_SENSIVEIS, true);
    }

    /**
     * Campos que nunca viajam para a tela, mesmo gravados.
     *
     * `password`/`remember_token` são óbvios; os `*_id` de tenant e os
     * timestamps só poluem o diff sem dizer nada ao leitor humano.
     *
     * @var list<string>
     */
    public const CAMPOS_OCULTOS = [
        'password', 'remember_token', 'two_factor_secret', 'api_token',
        'empresa_id', 'grupo_id', 'created_at', 'updated_at',
    ];

    /**
     * Campo → rótulo legível no diff. Sem isto o dono lê `pedidosituacao_id`.
     *
     * @var array<string, string>
     */
    public const CAMPOS = [
        'ativo' => 'Situação do cadastro',
        'desativado_em' => 'Desativado em',
        'desativado_por' => 'Desativado por',
        'motivo_desativacao' => 'Motivo',
        'nome' => 'Nome',
        'fantasia' => 'Nome fantasia',
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'credito_limite' => 'Limite de crédito',
        'credito_saldo' => 'Saldo de crédito',
        'convenio_limite' => 'Limite do convênio',
        'valor' => 'Valor',
        'valor_venda' => 'Valor da venda',
        'valor_desconto' => 'Desconto',
        'pedidosituacao_id' => 'Situação do pedido',
        'entregador_user_id' => 'Entregador',
        'baixado' => 'Baixado',
        'cancelado' => 'Cancelado',
        'data_desligamento' => 'Data de desligamento',
    ];

    public static function rotuloCampo(string $campo): string
    {
        return self::CAMPOS[$campo] ?? ucfirst(str_replace('_', ' ', $campo));
    }
}
