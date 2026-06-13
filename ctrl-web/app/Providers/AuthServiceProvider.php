<?php

namespace App\Providers;

use DB;
use Laravel\Passport\Passport;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model'                     => 'App\Policies\ModelPolicy',
        'App\Cliente'                   => 'App\Policies\ClientePolicy',
        'App\Empresa'                   => 'App\Policies\EmpresaPolicy',
        'App\EmpresasGrupo'             => 'App\Policies\EmpresasgrupoPolicy',
        'App\User'                      => 'App\Policies\UserPolicy',
        'App\Regiao'                    => 'App\Policies\RegionalPolicy',
        'App\Layoutbanco'               => 'App\Policies\LayoutbancoPolicy',
        'App\Promocao'                  => 'App\Policies\PromocaoPolicy',
        'App\Segmento'                  => 'App\Policies\SegmentoPolicy',
        'App\Clientecontatosituacao'    => 'App\Policies\ClienteContatoSituacaoPolicy',
        'App\Clientecontatotipo'        => 'App\Policies\ClienteContatoTipoPolicy',
        'App\Tipopessoa'                => 'App\Policies\TipopessoaPolicy',
        'App\Telefonetipo'              => 'App\Policies\TelefonetipoPolicy',
        'App\Cargo'                     => 'App\Policies\CargoPolicy',
        'App\Colaborador'               => 'App\Policies\ColaboradorPolicy',
        'App\Estadocivil'               => 'App\Policies\EstadocivilPolicy',
        'App\Parentesco'                => 'App\Policies\ParentescoPolicy',
        'App\Tipoexame'                 => 'App\Policies\TipoexamePolicy',
        'App\Banco'                     => 'App\Policies\BancoPolicy',
        'App\Centrocusto'               => 'App\Policies\CentrocustoPolicy',
        'App\Condicaopagamento'         => 'App\Policies\CondicaopagamentoPolicy',
        'App\Conta'                     => 'App\Policies\ContaPolicy',
        'App\Contamovimentotipo'        => 'App\Policies\ContamovtipoPolicy',
        'App\Nfcofins'                  => 'App\Policies\NfcofinsPolicy',
        'App\Nficms'                    => 'App\Policies\NficmsPolicy',
        'App\Nfipi'                     => 'App\Policies\NfipiPolicy',
        'App\Nfpis'                     => 'App\Policies\NfpisPolicy',
        'App\Nfgrupofiscal'             => 'App\Policies\NfgrupofiscalPolicy',
        'App\Nfimposto'                 => 'App\Policies\NfimpostoPolicy',
        'App\Nfoperacao'                => 'App\Policies\NfoperacaoPolicy',
        'App\Motivonaovenda'            => 'App\Policies\MotivonaovendaPolicy',
        'App\Pedidomotivoatraso'        => 'App\Policies\PedidomotivoatrasoPolicy',
        'App\Pedidooperacao'            => 'App\Policies\PedidooperacaoPolicy',
        'App\Pedidosituacao'            => 'App\Policies\PedidosituacaoPolicy',
        'App\Vendaativaocorrenciatipo'  => 'App\Policies\OcorrenciatipoVAPolicy',
        'App\Colaboradorcomissao'       => 'App\Policies\ColaboradorcomissaoPolicy',
        'App\Metavenda'                 => 'App\Policies\MetavendaPolicy',
        'App\Recesso'                   => 'App\Policies\RecessoPolicy',
        'App\Recessotipo'               => 'App\Policies\RecessotipoPolicy',
        'App\Empresabem'                => 'App\Policies\EmpresabemPolicy',
        'App\Checklistform'             => 'App\Policies\ChecklistcadastroPolicy',
        'App\Checklistpesquisa'         => 'App\Policies\ChecklistpesquisaPolicy',
        'App\Posvenda'                  => 'App\Policies\PosvendaPolicy',
        'App\Posvendapesquisa'          => 'App\Policies\PosvendapesquisaPolicy',
        'App\Setor'                     => 'App\Policies\SetorPolicy',
        'App\Produtoclasse'             => 'App\Policies\ProdutoclassePolicy',
        'App\Produto'                   => 'App\Policies\ProdutoPolicy',
        'App\Tipocombustivel'           => 'App\Policies\TipocombustivelPolicy',
        'App\Tipodocumento'             => 'App\Policies\TipodocumentoPolicy',
        'App\Veiculotipo'               => 'App\Policies\VeiculotipoPolicy',
        'App\Veiculo'                   => 'App\Policies\VeiculoPolicy',
        'App\Estoquesetoracerto'        => 'App\Policies\EstoquesetoracertoPolicy',
        'App\Estoquesetor'              => 'App\Policies\EstoquesetorPolicy',
        'App\Estoquefisico'             => 'App\Policies\EstoquefisicoPolicy',
        'App\Estoquerequisicao'         => 'App\Policies\EstoquerequisicaoPolicy',
        'App\Estoquetransferencia'      => 'App\Policies\EstoquetransferenciaPolicy',
        'App\Veiculoabastecimento'      => 'App\Policies\VeiculoabastecimentoPolicy',
        'App\Veiculoentradasaida'       => 'App\Policies\VeiculoentradasaidaPolicy',
        'App\Veiculotrocaoleo'          => 'App\Policies\VeiculotrocaoleoPolicy',
        'App\Veiculopneu'               => 'App\Policies\VeiculopneuPolicy',
        'App\Valegas'                   => 'App\Policies\ValegasPolicy',
        'App\Valegasvenda'              => 'App\Policies\ValegasvendaPolicy',
        'App\Comodato'                  => 'App\Policies\ComodatoPolicy',
        'App\Conveniofechamento'        => 'App\Policies\ConveniofechamentoPolicy',
        'App\CupomFiscal'               => 'App\Policies\CupomFiscalPolicy',
        'App\Financeiro'                => 'App\Policies\FinanceiroPolicy',
        'App\Empresaconfig'             => 'App\Policies\EmpresaconfigPolicy',
        'App\Configuracoesgerais'       => 'App\Policies\ConfiguracoesGeraisPolicy',
        'App\Planoconta'                => 'App\Policies\PlanocontaPolicy',
        'App\Chequeemitido'             => 'App\Policies\ChequeemitidoPolicy',
        'App\Chequerecebido'            => 'App\Policies\ChequerecebidoPolicy',
        'App\Vendaativa'                => 'App\Policies\VendaativaPolicy',
        'App\Android'                   => 'App\Policies\AndroidPolicy',
        'App\Cupom'                     => 'App\Policies\CuponsPolicy',
        'App\Cidade'                    => 'App\Policies\CidadePolicy',
        'App\Bairro'                    => 'App\Policies\BairroPolicy',
        'App\Rua'                       => 'App\Policies\RuaPolicy',
        'App\Unidademedida'             => 'App\Policies\UnidademedidaPolicy',
        'App\Mcmm'                      => 'App\Policies\McmmPolicy',
        'App\Spedfiscal'                => 'App\Policies\SpedfiscalPolicy',
        'App\Spedcontribuicao'          => 'App\Policies\SpedcontribuicaoPolicy',
        'App\Inventario'                => 'App\Policies\InventarioPolicy',
        'App\Spedcontribuicoescredito'  => 'App\Policies\SpedcontribuicoescreditoPolicy',
        'App\Atualizacaoprecos'         => 'App\Policies\AtualizacaoprecosPolicy',
        'App\Boleto'                    => 'App\Policies\BoletoPolicy',
        'App\Boletoremessa'             => 'App\Policies\BoletoremessaPolicy',
        'App\Nfceconfigpedido'          => 'App\Policies\NfceconfigpedidoPolicy',
        'App\Nfsituacao'                => 'App\Policies\NfsituacaoPolicy',
        'App\Role'                      => 'App\Policies\RolePolicy',
        'App\Pedido'                    => 'App\Policies\PedidoPolicy',
        'App\Nfemitida'                 => 'App\Policies\NfemitidaPolicy',
        'App\Nfrecebida'                => 'App\Policies\NfrecebidaPolicy',
        'App\Appnotification'           => 'App\Policies\AppnotificationPolicy',
        'App\Promotorvenda'             => 'App\Policies\PromotorvendaPolicy',
        'App\Documentotipo'             => 'App\Policies\DocumentotipoPolicy',
        'App\Documento'                 => 'App\Policies\DocumentoPolicy',
        'App\Nfcst'                     => 'App\Policies\NfcstPolicy',
        'App\Nfclastrib'                => 'App\Policies\NfclastribPolicy',
        'App\Sorteio'                   => 'App\Policies\SorteioPolicy',
    ];

    /**
     * Register any application authentication / authorization services.
     *
     * @param  \Illuminate\Contracts\Auth\Access\Gate  $gate
     * @return void
     */
    public function boot(GateContract $gate)
    {
        $this->registerPolicies($gate);

        Passport::routes();
        // DB::listen(function($q){
        //     dump($q->sql);
        //     dump($q->time);
        // });
    }
}
