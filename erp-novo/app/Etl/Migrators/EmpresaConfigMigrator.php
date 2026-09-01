<?php

namespace App\Etl\Migrators;

use App\Domain\Integracao\IntegracaoTenant;
use App\Etl\Contracts\Migrator;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;
use App\Etl\Support\PreservaIdsDoLegado;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Configurações e credenciais por empresa (`empresaconfigs` do legado).
 *
 * Traz o que o ERP precisa para operar de verdade e que estava ficando para
 * trás: chave PIX + client_id/secret, chave do Google Maps, senha mestra e
 * parâmetros de e-mail.
 *
 * Segredos: as integrações ficam em
 * `empresa_configs.dados['integracoes'][<serviço>]`. Cifrar é seletivo, não
 * geral — quem manda é `IntegracaoTenant::cifrarBloco`, usado aqui e no
 * controller de gravação, para os dois caminhos não divergirem:
 *
 *  - CIFRADOS: `client_secret`, `webhook_hmac_secret` (o GET devolve só
 *    `*_configurado: bool`);
 *  - EM CLARO: `chave` (a chave PIX é pública — é o que o pagador lê) e
 *    `client_id`, que o GET devolve para preencher a tela.
 *
 * Cifrar a mais quebra a tela: o GET não decifra esses dois, então a página de
 * Configurações exibia `eyJpdiI6...` no lugar da credencial.
 *
 * Google Maps é de REDE: vai para `config_globais.google_maps_key` do grupo,
 * onde `IntegracaoTenant::googleMapsKey()` lê — não em `empresa_configs`.
 *
 * Nota sobre o certificado A1: a coluna `EMPRESAS.CERTIFICADODIGITAL` está
 * NULA nas 7 empresas do dump (só a senha do PFX veio). Não há o que migrar —
 * o certificado precisa ser enviado pelo painel.
 */
final class EmpresaConfigMigrator implements Migrator
{
    use PreservaIdsDoLegado;

    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'empresa-config';
    }

    public function dependeDe(): array
    {
        return ['empresas'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->usarConexaoDe($ctx);

        $this->ctxAtual = $ctx;

        if (! $this->tabelaExiste($ctx, 'empresaconfigs')) {
            return new MigrationResult($this->nome(), 0, 0, 0,
                ['legado sem `empresaconfigs` — nada a migrar']);
        }

        $idsEmpresa = $this->idsDe('empresas');
        $colunas = DB::getSchemaBuilder()->getColumnListing('empresa_configs');

        $lidos = 0;
        $gravados = 0;
        $pulados = 0;
        $comPix = 0;
        $chavesOperacionais = 0;
        /** @var array<int,string> empresa_id => chave do Maps (aplicada por GRUPO no fim) */
        $mapsPorEmpresa = [];

        foreach ($ctx->legado()->table('empresaconfigs')->orderBy('id')->get() as $r) {
            $lidos++;
            $empresa = (int) $r->empresa_id;
            if (! isset($idsEmpresa[$empresa])) {
                $pulados++;

                continue;
            }

            $integracoes = [];

            // PIX: chave recebedora + credenciais do PSP.
            //
            // O que é segredo e o que NÃO é: `cifrarBloco` cifra apenas
            // client_secret e webhook_hmac_secret — exatamente os mesmos campos
            // que `EmpresaConfigController@salvarIntegracoes` cifra, e os únicos
            // que o GET devolve como booleano "configurado". `chave` (a chave
            // PIX é pública por definição: é o que o pagador enxerga) e
            // `client_id` voltam em CLARO para a tela.
            //
            // Cifrar a mais não é "mais seguro", é quebrar o contrato: o GET
            // devolve esses dois campos sem decifrar, então a tela de
            // Configurações exibia o blob `eyJpdiI6...` no lugar do valor.
            $chavePix = $this->texto($r->chavepix ?? null);
            $clientId = $this->texto($r->client_id ?? null);
            $clientSecret = $this->texto($r->client_secret ?? null);
            if ($chavePix !== null || $clientId !== null) {
                $integracoes['pix'] = IntegracaoTenant::cifrarBloco(
                    array_filter([
                        'chave' => $chavePix,
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'ambiente' => 'homologacao',
                    ], fn ($v) => $v !== null),
                    ['client_secret', 'webhook_hmac_secret'],
                );
                $comPix++;
            }

            // Google Maps NÃO mora aqui: é credencial de REDE, lida por
            // `IntegracaoTenant::googleMapsKey()` em `config_globais.google_maps_key`
            // do grupo. Gravar em `empresa_configs` deixava a chave num lugar
            // que ninguém lê. Coletada aqui e aplicada por grupo no fim.
            $maps = $this->texto($r->keygooglemaps ?? null);
            if ($maps !== null) {
                $mapsPorEmpresa[$empresa] = $maps;
            }

            $linha = [
                'empresa_id' => $empresa,
                'email_username' => $this->texto($r->emailusuario ?? null),
                'email_password' => ($s = $this->texto($r->emailsenha ?? null)) !== null
                    ? Crypt::encryptString($s) : null,
                'senha_mestra' => $this->texto($r->senhamestre ?? null),
                'created_at' => $r->created_at ?? null,
            ];

            // `dados` é um JSON compartilhado com o resto da config da empresa.
            // Sobrescrever a coluna inteira apagaria o que já estivesse lá — a
            // gravação preserva as demais chaves.
            $operacional = $this->configOperacional($r);
            $chavesOperacionais += count($operacional);

            if ($integracoes !== [] || $operacional !== []) {
                $atual = json_decode((string) $this->destino()->table('empresa_configs')
                    ->where('empresa_id', $empresa)->value('dados'), true) ?: [];
                if ($integracoes !== []) {
                    $atual['integracoes'] = array_merge($atual['integracoes'] ?? [], $integracoes);
                }
                if ($operacional !== []) {
                    $atual = array_merge($atual, $operacional);
                }
                $linha['dados'] = json_encode($atual, JSON_UNESCAPED_UNICODE);
            }

            // Só as colunas que a tabela de destino realmente tem.
            $linha = array_filter($linha, fn ($k) => in_array($k, $colunas, true), ARRAY_FILTER_USE_KEY);

            if (! $ctx->dryRun) {
                // Chave natural: uma config por empresa.
                $this->destino()->table('empresa_configs')->updateOrInsert(
                    ['empresa_id' => $empresa],
                    $linha + ['updated_at' => now()],
                );
                $gravados++;
            }
        }

        $avisos = [];
        $gruposComMaps = $ctx->dryRun ? 0 : $this->gravarMapsPorGrupo($mapsPorEmpresa);

        $avisos[] = "{$comPix} empresa(s) com credencial PIX (client_secret cifrado; "
            ."chave e client_id em claro, como a tela lê) e {$gruposComMaps} grupo(s) "
            .'com chave do Google Maps';
        $avisos[] = "{$chavesOperacionais} chave(s) de configuração operacional migradas para "
            .'`dados` (plano de contas e centro de custo padrão, regras de estoque/entrega, '
            .'percentuais contábeis, defaults de NFC-e, malote, convênio e gás do povo) — '
            .'antes só e-mail, senha-mestra, PIX e Maps vinham do legado';
        $avisos[] = 'certificado digital A1 NÃO migrado: a coluna está nula no dump '
            .'(só a senha do PFX veio). Envie o .pfx pelo painel para emitir NFC-e';
        if ($pulados > 0) {
            $avisos[] = "{$pulados} config(s) de empresa inexistente — descartadas";
        }

        return new MigrationResult(
            migrator: $this->nome(),
            lidos: $lidos,
            gravados: $ctx->dryRun ? 0 : $gravados,
            pulados: $pulados,
            avisos: $avisos,
        );
    }

    public function invariantes(): array
    {
        return [];
    }

    /** Texto do legado: string vazia e espaços viram null. */
    private function texto(mixed $v): ?string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? null : $v;
    }

    /**
     * Chaves booleanas (o legado guarda como texto '0'/'1').
     *
     * Explícito, e não inferido pelo nome: `impressao_vias_pedido` contém o
     * NÚMERO de vias e casaria com qualquer heurística sobre "impressao".
     *
     * @var list<string>
     */
    private const FLAGS = [
        'pedido_emite_nfce', 'pedido_valida_cartao', 'pedido_controla_tempo_ligacoes',
        'permite_estoque_negativo', 'valida_coordenadas_entrega', 'valida_atraso',
        'valida_pix_entrega', 'tela_controla_km', 'impressao_automatica',
        'android_utiliza', 'android_envia_todos', 'valida_gas_bolso',
        'email_requer_autenticacao', 'email_requer_tls',
    ];

    /**
     * Configuração operacional da empresa → `dados` (JSON).
     *
     * O legado tem ~95 chaves preenchidas em `empresaconfigs` e este migrator
     * trazia CINCO (e-mail, senha-mestra, PIX, Maps). Todo o resto — plano de
     * contas e centro de custo padrão, regra de estoque negativo, percentuais
     * contábeis, defaults de NFC-e, malote, convênio e gás do povo — ficava para
     * trás sem aviso: a empresa migrava "configurada" com o default do sistema
     * novo, e a divergência só apareceria na operação.
     *
     * Vão para `dados` porque é assim que a config é modelada aqui: o
     * `EmpresaConfigController::update()` já grava qualquer chave desconhecida
     * nesse JSON, então a tela lê e escreve sem mudança nenhuma. Uma chave só
     * vira coluna quando alguma consulta precisa filtrar por ela.
     *
     * @return array<string, mixed> só as chaves com valor (null não é migrado)
     */
    private function configOperacional(object $r): array
    {
        // legado => nome no JSON. Agrupado por assunto, que é como a tela edita.
        $mapa = [
            // Contábil padrão
            'planoconta_id' => 'planoconta_id',
            'centrocusto_id' => 'centrocusto_id',
            'pcreceitadesconto_id' => 'pc_receita_desconto_id',
            'pcrecetajuro_id' => 'pc_receita_juro_id',
            'pcdespesasdesconto_id' => 'pc_despesa_desconto_id',
            'pcdespesasjuro_id' => 'pc_despesa_juro_id',
            'ccreceitasjuros_id' => 'cc_receita_juro_id',
            'ccreceitasdescontos_id' => 'cc_receita_desconto_id',
            'ccdespesasjuros_id' => 'cc_despesa_juro_id',
            'ccdespesasdescontos_id' => 'cc_despesa_desconto_id',
            // Frete
            'ccfrete_id' => 'ccfrete_id',
            'pcfrete_id' => 'pcfrete_id',
            'fretemodalidade' => 'frete_modalidade',
            // Operação / pedido
            'setorprincipal_id' => 'setor_principal_id',
            'pedidostatuspadrao' => 'pedido_status_padrao',
            'pedidoemitenfce' => 'pedido_emite_nfce',
            'pedidovalidacartao' => 'pedido_valida_cartao',
            'pedidovalidacartaodias' => 'pedido_valida_cartao_dias',
            'pedidocontrolatempoligacoes' => 'pedido_controla_tempo_ligacoes',
            'operacaodisk' => 'operacao_disk',
            'pedidooperacao_id' => 'pedido_operacao_id',
            'nfoperacoes_id' => 'nf_operacao_id',
            'nfcecliente_id' => 'nfce_cliente_id',
            'presencacomprador' => 'presenca_comprador',
            'transportadorpadrao_id' => 'transportador_padrao_id',
            'quant_padrao' => 'quantidade_padrao',
            // Estoque / entrega
            'permiteestoquenegativo' => 'permite_estoque_negativo',
            'validacordenadasentrega' => 'valida_coordenadas_entrega',
            'validaatraso' => 'valida_atraso',
            'validapixentrega' => 'valida_pix_entrega',
            'diastrabalhadosemana' => 'dias_trabalhados_semana',
            'qnddiasinativocompra' => 'dias_inativo_compra',
            'telacontrolakm' => 'tela_controla_km',
            // Impressão
            'impressaoautomatica' => 'impressao_automatica',
            'impressaoqtdviaspedido' => 'impressao_vias_pedido',
            // Percentuais contábeis (relatório de resultado)
            'percentualencargos' => 'percentual_encargos',
            'percentualprovisaodevedores' => 'percentual_provisao_devedores',
            'percentualremuneracaocapital' => 'percentual_remuneracao_capital',
            'percentualdistribuicaoresul' => 'percentual_distribuicao_resultado',
            'fatorpotencialvenda' => 'fator_potencial_venda',
            // Caixa / malote
            'maloteconta_id' => 'maloteconta_id',
            'contachecktroco' => 'conta_check_troco',
            // Vale-gás
            'ccvalegas_id' => 'ccvalegas_id',
            'pcvalegas_id' => 'pcvalegas_id',
            // App / NF do app
            'androidutiliza' => 'android_utiliza',
            'androidenviatodos' => 'android_envia_todos',
            'validagasbolso' => 'valida_gas_bolso',
            'mensagemgasbolso' => 'mensagem_gas_bolso',
            'mensagemduplicata' => 'mensagem_duplicata',
            'pedidooperacaoappnf_id' => 'appnf_pedido_operacao_id',
            'presencacompradorappnf' => 'appnf_presenca_comprador',
            'fretemodalidadeappnf' => 'appnf_frete_modalidade',
            'transportadorappnf_id' => 'appnf_transportador_id',
            'contaappnf_id' => 'appnf_conta_id',
            // Convênio
            'contaconvenionf_id' => 'convenio_conta_id',
            'nfoperacaoconvenio_id' => 'convenio_nf_operacao_id',
            'ccconvenio_id' => 'convenio_cc_id',
            'pcconvenio_id' => 'convenio_pc_id',
            'ccfreteconvenio_id' => 'convenio_ccfrete_id',
            'pcfreteconvenio_id' => 'convenio_pcfrete_id',
            'setorconvenio_id' => 'convenio_setor_id',
            'veiculoconvenio_id' => 'convenio_veiculo_id',
            'condicaopagamentoconvenio_id' => 'convenio_condicaopagamento_id',
            'presencacompradorconvenionf' => 'convenio_presenca_comprador',
            'fretemodalidadeconvenionf' => 'convenio_frete_modalidade',
            'transportadorconvenionf_id' => 'convenio_transportador_id',
            // Gás do povo (programa federal)
            'produtogp_id' => 'gp_produto_id',
            'valorfretegp' => 'valorfretegp',
            'ccfretegp_id' => 'ccfretegp_id',
            'pcfretegp_id' => 'pcfretegp_id',
            'condicaopagamentofretegp_id' => 'gp_condicaopagamento_frete_id',
            'condicaopagamentogp_id' => 'gp_condicaopagamento_id',
            // Ressarcimento
            'setor_ressarcimento' => 'setor_ressarcimento_id',
            'operacao_ressarcimento' => 'operacao_ressarcimento_id',
            // E-mail (complemento do que já vira coluna)
            'emailassunto' => 'email_assunto',
            'emailcorpo' => 'email_corpo',
            'emailrequerautenticacao' => 'email_requer_autenticacao',
            'emailrequerconexaotls' => 'email_requer_tls',
        ];

        $out = [];
        foreach ($mapa as $origem => $destino) {
            $valor = $r->{$origem} ?? null;
            if ($valor === null || $valor === '') {
                continue;
            }

            // O legado guarda flag como texto '0'/'1'. Sem converter, a tela
            // recebe a STRING "0" — que é verdadeira em JavaScript, e o switch
            // apareceria ligado com a configuração desligada.
            $out[$destino] = match (true) {
                in_array($destino, self::FLAGS, true) => ! in_array((string) $valor, ['0', 'N', 'n'], true),
                is_numeric($valor) => str_contains((string) $valor, '.') ? (float) $valor : (int) $valor,
                default => $this->texto($valor),
            };
        }

        return $out;
    }

    /** @return array<int,true> */
    private function idsDe(string $tabela): array
    {
        $ids = [];
        foreach ($this->destino()->table($tabela)->select('id')->cursor() as $r) {
            $ids[(int) $r->id] = true;
        }

        return $ids;
    }

    /**
     * Grava a chave do Google Maps em `config_globais.google_maps_key` do GRUPO
     * de cada empresa — que é onde `IntegracaoTenant::googleMapsKey()` procura.
     *
     * Em claro, deliberadamente: é o formato que o leitor espera (`value()`
     * direto, sem decifrar) e o mesmo que a tela de config global grava. Uma
     * key de browser do Maps é restringida por referrer, não por sigilo.
     *
     * Quando duas empresas do mesmo grupo trazem keys diferentes, fica a
     * primeira — a rede tem uma key só, e a divergência vira aviso.
     *
     * @param  array<int,string>  $mapsPorEmpresa
     * @return int grupos que receberam chave
     */
    private function gravarMapsPorGrupo(array $mapsPorEmpresa): int
    {
        if ($mapsPorEmpresa === []) {
            return 0;
        }

        $grupoDaEmpresa = $this->destino()->table('empresas')
            ->whereIn('id', array_keys($mapsPorEmpresa))
            ->pluck('grupo_id', 'id');

        $porGrupo = [];
        foreach ($mapsPorEmpresa as $empresa => $key) {
            $grupo = (int) ($grupoDaEmpresa[$empresa] ?? 0);
            if ($grupo > 0 && ! isset($porGrupo[$grupo])) {
                $porGrupo[$grupo] = $key;
            }
        }

        foreach ($porGrupo as $grupo => $key) {
            $this->destino()->table('config_globais')->updateOrInsert(
                ['grupo_id' => $grupo],
                ['google_maps_key' => $key, 'updated_at' => now()],
            );
        }

        return count($porGrupo);
    }

    private function tabelaExiste(MigrationContext $ctx, string $tabela): bool
    {
        try {
            return $ctx->legado()->getSchemaBuilder()->hasTable($tabela);
        } catch (\Throwable) {
            return false;
        }
    }
}
