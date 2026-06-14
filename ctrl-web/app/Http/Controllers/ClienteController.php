<?php

namespace App\Http\Controllers;

use App\Clienteprodutosconvenio;
use DB;
use Illuminate\Support\Collection;
use Input;
use Session;
use Redirect;
use App\Cidade;
use App\Cliente;
use App\Empresa;
use App\Tipopessoa;
use App\Comodato;
use App\Clientecontato;
use App\Clienteproduto;
use App\Clientetelefone;
use App\Clienteconvenio;
use App\Clientepromocao;
use App\Clienteconveniodependente;
use App\Repository\ClienteRepository as Repository;
use App\Http\Requests\ClienteRequest;
use App\Repository\MobileRepository;
use App\Rua;
use App\Services\CarbonCustom as Carbon;
use \Venturecraft\Revisionable\Revision as Revision;

class ClienteController extends Controller
{

    protected $cliente;
    protected $cliente_id;
    protected $grupo_id;
    protected $empresa_id;
    protected $clientes;
    protected $empresaConveniada;
    protected $segmentos;
    protected $telefonetipos;
    protected $contatosituacoes;
    protected $contatotipos;
    protected $setores;
    protected $estadocivils;
    protected $parentesco;
    protected $produtos;
    protected $condicaopagamentos;
    protected $promocoes;
    protected $estados;
    protected $cepempresa;
    protected $allTablesView;

    /**
     * @param Cliente $cli
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Cliente $cli)
    {
        $this->authorize('view', $cli);
        $nome = strtolower(replaceAccents(str_encode_to_query(e(Input::get('name', '')))));
        $id = (int) e(Input::get('cod', 0));
        if ($nome || $id > 0) {
            $clientes = Cliente::where('empresa_id', Session::get('empresa_padrao')->id);

            if ($nome) {
                $raw = "(" . rawTranslateSpecialChars("nome") . " LIKE '%$nome%' OR "
                    . rawTranslateSpecialChars("fantasia") . " LIKE '%$nome%' " . ")";
                $clientes->whereRaw($raw);
            }

            if ($id) {
                $clientes->where("id", $id);
            }

            $clientes = $clientes->select(['nome', 'id', 'ativo', 'cliente', 'fornecedor', 'transportador'])->orderBy('nome')->get();
        } else {
            $clientes = [];
        }

        return view('clientes.clientes', compact('clientes'));
    }

    /**
     * @param Cliente $cli
     * @param $empresa_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function createFromPedidos(Cliente $cli, $empresa_id)
    {
        return $this->create($cli, $empresa_id, Input::all());
    }

    /**
     * @param Cliente $cli
     * @param null $empresa_id
     * @param null $dataFromPedido
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create(Cliente $cli, $empresa_id = null, $dataFromPedido = null)
    {
        $this->authorize('create', $cli);
        $this->definitions($empresa_id);

        $cidades =  [];
        $cidadesN = [];
        $estados = Repository::getEstados();
        $bairros = [];
        $ruas = [];
        $telefonetipos = Repository::getTelefoneTipos($this->grupo_id);
        $contatosituacoes = Repository::getContatoSituacoes($this->grupo_id);
        $contatotipos = Repository::getContatoTipos($this->grupo_id);
        $empresaConveniada = Repository::getEmpresaConveniada($this->empresa_id);
        $segmentos = Repository::getSegmentos($this->grupo_id);
        $setors = Repository::getSetores($this->empresa_id);
        $estadocivils = Repository::getEstadoCivil($this->grupo_id);
        $parentesco = Repository::getParentesco($this->grupo_id);
        $produtos = Repository::getProdutos($this->empresa_id);
        $condicaoPagamento = Repository::getCondicaoPagamento($this->grupo_id);
        $promocoes = Repository::getPromocoes($this->empresa_id);
        $descpara = $this->getDescontoPara();
        $cep = $this->cepempresa;
        $tipopessoa = Tipopessoa::where([
            ['tipopessoacadastro', 'F'],
            ['ativo', 1],
            ['grupo_id', $this->grupo_id]
        ])->get()->pluck('id')->first() . 'F';
        $tipopessoas = $this->selectTipoPessoas();

        if (isset($_GET['telefone']))
            $telefone = $_GET['telefone'];
        else
            $telefone = null;

        $compact = compact(
            'cidades',
            'cep',
            'tipopessoa',
            'estados',
            'bairros',
            'telefonetipos',
            'cidadesN',
            'contatosituacoes',
            'contatotipos',
            'segmentos',
            'tipopessoas',
            'setors',
            'estadocivils',
            'parentesco',
            'empresaConveniada',
            'ruas',
            'produtos',
            'condicaoPagamento',
            'promocoes',
            'empresa_id',
            'telefone',
            'dataFromPedido',
            'descpara'
        );

        if (is_null($empresa_id))
            return view('clientes.cliente_form', $compact);
        else
            return view('pedido.partials.modal_cliente', $compact);
    }

    /**
     * @param ClienteRequest $request
     * @param Cliente $cli
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    public function store(ClienteRequest $request, Cliente $cli, $returnClient = false)
    {
        $this->authorize('create', $cli);
        $data = $request->all();
        $isApi = starts_with(request()->path(), 'api');

        if (isset($data['clienteempresa_id']) && $data['clienteempresa_id'] != '') {
            $empresa_id = $data['clienteempresa_id'];
        } else {
            $empresa_id = Session::get('empresa_padrao')->id;
        }

        $this->definitions($empresa_id);

        DB::beginTransaction();
        try {
            $data = $this->dadosExtras($data);
            $fromPedido = array_key_exists('fromPedidos', $data); // o campo fromPedidos só vem quando o cliente é editado pela tela de pedidos

            if (!isset($data['conveniolimite'])) {
                $data['conveniolimite'] = 0;
            }

            $this->verificaEndereco($data);

            $cliente = Cliente::create($data);

            // inserindo convenio
            if (isset($data["convenioativo"]) && $data["convenioativo"] !== '') {
                $conv = $this->insertUpdateConvenio($data, $cliente->id);

                if ($conv === false) throw new \Exception('Os campos de Representante Legal são obrigatórios!');
            }

            $this->insertUpdateOthers($request->only('telefones', 'contatos', 'parentesco', 'produtos', 'promocoes', 'condicoespagamento', 'clienteprodutosconvenios'), $cliente, false);
        } catch (\Exception $e) {
            DB::rollback();

            $message = $e->getMessage();
            if (str_contains($message, "unique constraint")) {
                $message = "Já existe um cliente cadastrado para este endereço";
            }

            if ($returnClient) {
                throw new \Exception($message);
            }

            if ((!isset($fromPedido) || !$fromPedido) && !$isApi) {
                return Redirect::to('/cliente/create')->withErrors($message)->withInput();
            } else if ($fromPedido && !$isApi) {
                return Redirect::to("cliente.createFromPedidos/" . $data['clienteempresa_id'])->withErrors($message)->withInput();
            } else {
                return internalResponseError($message, $e->getCode());
            }
        }
        DB::commit();

        if ($returnClient) return $cliente;

        if (!$fromPedido && !$isApi) {
            Session::flash("message_success", 'Cliente cadastrado com sucesso!');
            return Redirect::to('cliente');
        }

        if ($isApi) return internalResponseSuccess($cliente, "Sucesso!");

        return $this->fecharModalIframe($cliente->id);
    }

    /**
     * @param $cliente_id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function fecharModalIframe($cliente_id)
    {
        return view('general.close_modal', compact('cliente_id'));
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show($id)
    {
        return $this->form($id, false, false);
    }

    /**
     * @param $id
     * @param Cliente $cli
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function editFromPedidos($id, Cliente $cli)
    {
        return $this->edit($id, $cli, true);
    }

    /**
     * @param $id
     * @param Cliente $cli
     * @param bool $fromPedido
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit($id, Cliente $cli, $fromPedido = false)
    {
        $this->authorize('update', $cli);
        return $this->form($id, $fromPedido);
    }

    /**
     * @param $id
     * @param $fromPedido
     * @param bool $edit
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function form($id, $fromPedido, $edit = true)
    {
        $cliente = Cliente::find($id);
        $cliente->load(['clienteConvenio', 'produtoconvenio.produto', 'clienteProduto.produto', 'condicaoPagamento']);
        $this->definitions($cliente->empresa_id);

        if (!$fromPedido)
            $this->authorize('igualdade', $cliente);

        $cliente->datanascimento = requestDataOracle($cliente->datanascimento);
        if (isset($cliente->clienteConvenio->comissao)) {
            $cliente->clienteConvenio->comissao = requestPercentualOracle($cliente->clienteConvenio->comissao);
        }
        $estados = Repository::getEstados();
        $cidade = Cidade::find($cliente->cidade_id);
        $cidades = Repository::getCidades($this->grupo_id, $cidade->uf);
        $bairros = Repository::getBairros($cidade->id, $this->grupo_id);
        $ruas = Repository::getRuas($cliente->cidade_id, $this->grupo_id);
        $telefonetipos = Repository::getTelefoneTipos($this->grupo_id);
        $contatosituacoes = Repository::getContatoSituacoes($this->grupo_id);
        $contatotipos = Repository::getContatoTipos($this->grupo_id);
        $empresaConveniada = Repository::getEmpresaConveniada($this->empresa_id);
        $segmentos = Repository::getSegmentos($this->grupo_id);
        $setors = Repository::getSetores($this->empresa_id);
        $estadocivils = Repository::getEstadoCivil($this->grupo_id);
        $parentesco = Repository::getParentesco($this->grupo_id);
        $produtos = Repository::getProdutos($this->empresa_id);
        $condicaoPagamento = Repository::getCondicaoPagamento($this->grupo_id);
        $promocoes = Repository::getPromocoes($this->empresa_id);
        $tipopessoas = $this->selectTipoPessoas();
        $tipopessoaCliente = Tipopessoa::find($cliente->tipopessoa_id);
        $tipopessoa = $tipopessoaCliente->id . $tipopessoaCliente->tipopessoacadastro;
        $pedidoController = new PedidoController();
        $historico = $pedidoController->historicoToCliente($id);
        $comodato = Comodato::where('cliente_id', $id)->where('ativo', 1)->count() > 0;
        $empresa_id = Session::get('empresa_padrao')->id;
        $descpara = $this->getDescontoPara();
        $compact = compact(
            'cliente',
            'tipopessoa',
            'cidades',
            'estados',
            'bairros',
            'telefonetipos',
            'contatosituacoes',
            'contatotipos',
            'segmentos',
            'tipopessoas',
            'setors',
            'estadocivils',
            'parentesco',
            'empresaConveniada',
            'ruas',
            'produtos',
            'condicaoPagamento',
            'promocoes',
            'historico',
            'empresa_id',
            'comodato',
            'descpara'
        );

        if (!$edit) {
            $compact['show'] = true;
        }

        if ($fromPedido) {
            $view = 'pedido.partials.modal_cliente';
        } else {
            $view = 'clientes.cliente_form';
        }

        return view($view, $compact);
    }

    /**
     * @param ClienteRequest $request
     * @param $id
     * @param Cliente $cli
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    public function update(ClienteRequest $request, $id, Cliente $cli, $returnClient = false)
    {
        $data = $request->all();
        $fromPedido = isset($data['fromPedidos']); // o campo fromPedidos só vem quando o cliente é editado pela tela de pedidos
        $this->authorize('update', $cli);
        $cliente = Cliente::findOrFail($id);
        if (!$fromPedido)
            $this->authorize('igualdade', $cliente);

        if (isset($data['clienteempresa_id']) && $data['clienteempresa_id'] != '')
            $empresa_id = $data['clienteempresa_id'];
        else
            $empresa_id = Session::get('empresa_padrao')->id;
        $this->definitions($empresa_id);
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data, $dadosextras);

        $data["rgorgao"] = "X";
        $data = array_merge($data, $dadosextras);

        DB::beginTransaction();
        try {
            if (!isset($data['conveniolimite']))
                $data['conveniolimite'] = 0;

            $this->verificaEndereco($data, $cliente);
            $cliente->update($data);

            // inserindo convenio
            if (isset($data["convenioativo"]) && $data["convenioativo"] !== 0) {
                $conv = $this->insertUpdateConvenio($data, $cliente->id);
                if ($conv === false)
                    throw new \Exception('Os campos de Representante Legal são obrigatórios');
            }
            $this->insertUpdateOthers($request->only(
                'telefones',
                'contatos',
                'parentesco',
                'produtos',
                'promocoes',
                'condicoespagamento',
                'clienteprodutosconvenios'
            ), $cliente);
        } catch (\Exception $e) {
            DB::rollback();

            if ($returnClient) {
                throw new \Exception($e->getMessage());
            }

            if (!$fromPedido)
                return Redirect::to('cliente/' . $id . '/edit/')->withErrors($e->getMessage())->withInput();

            return Redirect::to("cliente.editFromPedidos/" . $id)->withErrors($e->getMessage())->withInput();
        }
        DB::commit();

        if ($returnClient) {
            return $cliente;
        }

        if (!$fromPedido) {
            Session::flash("message_success", 'Cliente atualizado com sucesso!');
            return Redirect::to('cliente');
        }

        return $this->fecharModalIframe($id);
    }

    /**
     * @param $data
     * @return mixed
     */
    protected function dadosExtras($data)
    {
        $data["user_id"] = \Auth::user()->id;
        if (isset($data["datanascimento"]))
            $data["datanascimento"] = insertDataOracle($data["datanascimento"]);

        if (str_contains($data['tipopessoa_id'], 'J')) {
            $data['cpf'] = null;
            $data['rg'] = null;
        } else {
            $data['cnpj'] = null;
            $data['inscricao_estadual'] = null;
        }
        $data['cliente'] = isset($data['cliente']) && $data['cliente'];
        $data['fornecedor'] = isset($data['fornecedor']) && $data['fornecedor'];
        $data['transportador'] = isset($data['transportador']) && $data['transportador'];
        $data['simples'] = isset($data['simples']) && $data['simples'];
        $data['ativo'] = isset($data['ativo']) && $data['ativo'];
        $data['nfemite'] = isset($data['nfemite']) && $data['nfemite'];
        $data['convenioativo'] = isset($data['convenioativo']) && $data['convenioativo'];
        $data['convenio'] = isset($data['convenio']) && $data['convenio'];
        $data["codigo_convenio"] = empty($data["codigo_convenio"]) ? null : $data["codigo_convenio"];
        $data["rg"] = empty($data["rg"]) ? null : $data["rg"];
        $data['tipopessoa_id'] = str_replace('J', '', $data['tipopessoa_id']);
        $data['tipopessoa_id'] = str_replace('F', '', $data['tipopessoa_id']);
        $data['gasdopovo'] = isset($data['gasdopovo']) && $data['gasdopovo'];



        if (isset($data['setor_id'])) $data['setor_id'] = str_replace('Selecione', '', $data['setor_id']);

        if (starts_with($data["rua_id"], "N")) $this->createRua($data);

        $data["empresa_id"] = $this->empresa_id;
        $data["grupo_id"] = $this->grupo_id;
        $latLong = buscaLatitudeLongitude($data['uf'], $data['cidade_id'], $data['bairro_id'], $data['rua_id'], $data['numero']);

        if (isset($latLong->location->lat) && isset($latLong->location->lng)) {
            $data['latitude'] = $latLong->location->lat;
            $data['longitude'] = $latLong->location->lng;
            $data['locationtype'] = $latLong->location_type;
        } else {
            Session::flash('message_info', "Nao foi possiível encontrar a liatitude e longitude, "
                . "tente novamente em alguns minutos ou contate o suporte");
        }
        $data = emptyToNull($data);

        if (isset($data["alltables"]))
            $this->allTablesView = json_decode($data['alltables']);

        return $data;
    }

    /**
     * @param array $data
     * @param Cliente|Collection $cliente
     * @param bool $editing
     * @throws \Exception
     */
    protected function insertUpdateOthers($data, $cliente, $editing = true)
    {
        try {
            $data = emptyToNull($data);
            $tblPromo = isset($this->allTablesView->tblClientePromocoes) ? $this->allTablesView->tblClientePromocoes : null;
            // ? atualizando promoções
            if (!is_null($tblPromo)) {
                if (isset($data['promocoes']) && (!$editing || $tblPromo->added || $tblPromo->removed)) {
                    $promocoes = json_decode($data['promocoes']);
                    $this->insertUpdatePromocoes(collect($promocoes), $cliente, $tblPromo);
                }
            }

            $tblFone = isset($this->allTablesView->tblFone) ? $this->allTablesView->tblFone : null;
            // ? atualizar telefones
            if (!is_null($tblFone)) {
                if (isset($data["telefones"]) && (!$editing || $tblFone->added || $tblFone->removed)) {
                    $telefones = json_decode($data["telefones"]);
                    $this->insertUpdateTelefones($telefones, $cliente, $tblFone);
                }
            }

            $tblCont = isset($this->allTablesView->tblCont) ? $this->allTablesView->tblCont : null;
            // ? inserindo contato/interações (antigo followUP);
            if (!is_null($tblCont)) {
                if (isset($data["contatos"]) && (!$editing || $tblCont->added || $tblCont->removed)) {
                    $contatos = json_decode($data["contatos"]);
                    $this->insertUpdateContato($contatos, $cliente, $tblCont);
                }
            }

            $tblParentesco = isset($this->allTablesView->tblParentesco) ? $this->allTablesView->tblParentesco : null;
            // ? cliente convenios dependentes
            if (!is_null($tblParentesco)) {
                if (isset($data['parentesco']) && (!$editing || $tblParentesco->added || $tblParentesco->removed)) {
                    $parentes = json_decode($data["parentesco"]);
                    $this->insertUpdateParentesco($parentes, $cliente, $tblParentesco);
                }
            }

            $tblProdutosPrecos = isset($this->allTablesView->tblProdutosPrecos) ? $this->allTablesView->tblProdutosPrecos : null;
            // ? atualizando preços de produtos para clientes
            if (!is_null($tblProdutosPrecos)) {
                if (isset($data["produtos"]) && (!$editing || $tblProdutosPrecos->added || $tblProdutosPrecos->removed)) {
                    $produtos = json_decode($data["produtos"]);
                    $this->insertUpdateProdutos($produtos, $cliente, $tblProdutosPrecos);
                }
            }

            $tblCondPgto = isset($this->allTablesView->tblCondPgto) ? $this->allTablesView->tblCondPgto : null;
            // ? atualizando condições de pagamento para o cliente
            if (!is_null($tblCondPgto)) {
                if (isset($data['condicoespagamento']) && (!$editing || $tblCondPgto->added || $tblCondPgto->removed)) {
                    $condicoesPagamento = json_decode($data['condicoespagamento']);
                    $this->insertUpdateCondicoesPgto($condicoesPagamento, $cliente, $tblCondPgto);
                }
            }
            $tblProdConvenio = isset($this->allTablesView->tblProdConvenio) ? $this->allTablesView->tblProdConvenio : null;
            // ? atualizando condições de pagamento para o cliente
            if (!is_null($tblProdConvenio)) {
                if (isset($data['clienteprodutosconvenios']) && (!$editing || $tblProdConvenio->added || $tblProdConvenio->removed)) {
                    $produtos = json_decode($data['clienteprodutosconvenios']);
                    $this->insertUpdateProdConvenio($produtos, $cliente, $tblProdConvenio);
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }


    /**
     * @param array $produtos
     * @param Cliente $cliente
     * @param object $tbl
     * @throws \Exception
     */
    protected function insertUpdateProdConvenio($produtos, $cliente, $tbl)
    {
        $clienteprodutos = collect([]);
        $implode = [];
        /**
         * <code>
         * $produto = array(
         *   '0', // id da produdo_convenio
         *   '1', // id do produto
         *   '2', // descrição da produto
         *   '3', // preço
         * );
         * </code>
         * @var array $produto
         */
        foreach ($produtos as $produto) {
            if (!$produto[0]) {
                $prod = new Clienteprodutosconvenio();
                $prod["produto_id"] = $produto[1];
                $prod["cliente_id"] = $cliente->id;
                $prod["preco"] = insertNumeroDecimalOracle($produto[3]);
                $clienteprodutos->push($prod);
                $implode[] = "Produto_id: " . $produto[1] . ", Preço: " . $produto[3];
            }
        }

        $toDelete = Clienteprodutosconvenio::whereIn('id', $tbl->removed);
        if ($tbl->removed) {
            $str = "";
            $produtos = $toDelete->get();
            foreach ($produtos as $prod) {
                $str .= "Produto_id: " . $prod->produto_id . ", Preço: " . requestNumeroDecimalOracle($prod->preco) . " | ";
            }
            $this->keepRevisions("Produtos do convênio removidos", $str, "produtoConvenio", $cliente);
        }
        $toDelete->delete();

        $cliente->produtoconvenio()->saveMany($clienteprodutos);
        if (count($clienteprodutos)) {
            $this->keepRevisions(null, implode(' | ', $implode), "produtoConvenio", $cliente);
        }
    }

    /**
     * @param $promocoes
     * @param Cliente $cliente
     * @param $tbl
     * @var Clientepromocao $toDelete
     * @throws \Exception
     */
    protected function insertUpdatePromocoes($promocoes, Cliente $cliente, $tbl)
    {
        $promocoesInsert = [];
        $implode = [];

        /**
         * <code>
         * $promocao = array(
         *   '0', // id da cliente_promocao
         *   '1', // id da promocao
         *   '2', // descrição da promocao
         *   '3', // datainicio
         *   '4', // datafim
         *   '5', // mediadias
         * );
         * </code>
         * @var array $promocao
         */
        foreach ($promocoes as $promocao) {
            if (!$promocao[0]) {
                $promo = new Clientepromocao();
                $promo['cliente_id'] = $cliente->id;
                $promo['datainicio'] = insertDataOracle($promocao[3]);
                $promo['datafim'] = insertDataOracle($promocao[4]);
                $promo['mediadias'] = $promocao[5] !== '' ? $promocao[5] : 0;
                $promo['promocao_id'] = $promocao[1];
                array_push($promocoesInsert, $promo);
                $implode[] = "Promocao: " . $promocao[1] . ", Data Inicio: " .
                    substr($promocao[3], 0, 10) . ", Data Fim: " . substr($promocao[4], 0, 10);
            }
        }

        $toDelete = Clientepromocao::whereIn('id', $tbl->removed);
        if ($tbl->removed) {
            $this->keepRevisions("Promoções removidas", implode('|', $toDelete->get()->pluck('promocao_id')->toArray()), "clientePromocao", $cliente);
        }
        $toDelete->delete();

        $cliente->clientePromocao()->saveMany($promocoesInsert);
        if (count($promocoesInsert)) {
            $this->keepRevisions(null, implode(' | ', $implode), "clientePromocao", $cliente);
        }
    }

    protected function insertUpdateCondicoesPgto($condicoesPagamento, Cliente $cliente, $tbl)
    {
        $condInsert = [];
        $implode = [];
        foreach ($condicoesPagamento as $condicaoPgto) {
            array_push($condInsert, $condicaoPgto[0]);
            $implode[] = $condicaoPgto[0];
        }

        if ($tbl->removed) {
            // FASE 1 (segurança — S1/SQLi): antes concatenava IDs do request
            // direto no DELETE. Agora força inteiros + bindings parametrizados.
            $removedIds = array_map('intval', (array) $tbl->removed);
            $strRemove = implode(', ', $removedIds);
            $this->keepRevisions("Condições removidas", "Condicoes: " . $strRemove, "clientePromocao", $cliente);
            $placeholders = implode(', ', array_fill(0, count($removedIds), '?'));
            $bindings = array_merge([$cliente->id], $removedIds);
            DB::statement(
                "DELETE FROM cliente_condicaopagamento WHERE cliente_id = ? AND condicaopagamento_id IN ($placeholders)",
                $bindings
            );
        }
        $cliente->condicaoPagamento()->attach($condInsert);
        if (count($condInsert)) {
            $this->keepRevisions(null, "Condicoes: " . implode(', ', $implode), "condicaoPagamento", $cliente);
        }
    }

    /**
     * @param $data
     * @param $cliente
     * @return bool
     * @throws \Exception
     */
    protected function insertUpdateConvenio($data, $cliente)
    {
        if (!array_key_exists('convenioativo', $data) || (array_key_exists('convenioativo', $data) && !$data['convenioativo'])) {
            return true;
        }
        if (isset($data['datacontrato'])) {
            if ($data['comissao'] === '') {
                $data['comissao'] = 0;
            }
            if ($data['cpfrepresentante'] == '' || $data['rgrepresentante'] == '' || $data['diafechamento'] == '') {
                return false;
            }
            $clienteConvenio = [
                'cliente_id'        => $cliente,
                'datacontrato'      => insertDataOracle($data['datacontrato']),
                'limitecompra'      => $data['limitecompra'],
                'cpfrepresentante'  => $data['cpfrepresentante'],
                'rgrepresentante'   => $data['rgrepresentante'],
                'nomerepresentante' => $data['nomerepresentante'],
                'diafechamento'     => $data['diafechamento'],
                'diavencimento'     => $data['diavencimento'],
                'comissao'          => insertPercentualOracle($data['comissao']),
                'comissaodestino'   => $data['comissaodestino'],
            ];
            $convenioAtivo = Clienteconvenio::where('cliente_id', $cliente)->get()->first();
            $old = $convenioAtivo;

            if (!is_null($convenioAtivo)) {
                $convenioAtivo->update($clienteConvenio);
                $this->keepRevisionsArr($old, $convenioAtivo, $cliente);
            } else {
                Clienteconvenio::create($clienteConvenio);
            }
        } else {
            throw new \Exception("Por favor, informe todos os campos do convênio!");
        }
        return true;
    }

    /**
     * @param $telefones
     * @param Cliente $cliente
     * @param $tbl
     * @throws \Exception
     */
    protected function insertUpdateTelefones($telefones, Cliente $cliente, $tbl)
    {
        $fones = [];
        $rev = [];

        /**
         * <code>
         * $telefone = array(
         *   '0', // id do cliente_telefones
         *   '1', // telefonetipo_id
         *   '2', // descrição telefonetipo
         *   '3', // telefone
         *   '4', // whatsapp
         *   '5', // btn
         * );
         * </code>
         * @var array $telefone
         */
        foreach ($telefones as $telefone) {
            if (!$telefone[0]) {
                $fone = new Clientetelefone();
                $fone["grupo_id"] = $this->grupo_id;
                $fone["empresa_id"] = $this->empresa_id;
                $fone["telefonetipo_id"] = $telefone[1];
                $fone["telefone"] = $telefone[3];
                $fone["whatsapp"] = $telefone[4] === 'Sim' ? 1 : 0;
                array_push($fones, $fone);
                $rev[] = "Telefone: " . $telefone[3];
            }
        }

        $toDelete = Clientetelefone::whereIn('id', $tbl->removed);
        if ($tbl->removed) {
            $this->keepRevisions("Telefones removidos", implode('|', $toDelete->get()->pluck('telefone')->toArray()), "telefones", $cliente);
        }
        $toDelete->delete();

        $cliente->telefones()->saveMany($fones);
        if (count($fones)) {
            $this->keepRevisions(null, implode(' | ', $rev), "telefones", $cliente);
        }
    }

    /**
     * @param array $contatos
     * @param Cliente $cliente
     * @param $tbl
     * @throws \Exception
     */
    protected function insertUpdateContato($contatos, Cliente $cliente, $tbl)
    {
        $conts = [];
        $implode = [];

        /**
         * <code>
         * $contato = array(
         *   '0', // id do cliente_contatos
         *   '1', // data da interação
         *   '2', // tipo_id
         *   '3', // descrição tipo
         *   '4', // situacao_id
         *   '5', // descrição situação.
         *   '6', // descrição.
         *   '7', // ação.
         *   '7', // btn
         * );
         * </code>
         * @var array $contato
         */
        foreach ($contatos as $contato) {
            if (!$contato[0]) {
                $cont = new Clientecontato();
                $cont["grupo_id"] = $this->grupo_id;
                $cont["empresa_id"] = $this->empresa_id;
                $cont["tipo_id"] = $contato[2];
                $cont["situacao_id"] = $contato[4];
                $cont["datahora"] = insertDataOracle($contato[1]);
                $cont["descricao"] = $contato[6];
                $cont["acao"] = $contato[7];
                array_push($conts, $cont);
                $implode[] = "Contato data: " . $contato[1] . ", Situacao: " . $contato[4];
            }
        }

        $toDelete = Clientecontato::whereIn('id', $tbl->removed);
        if ($tbl->removed) {
            $this->keepRevisions("Interação removida", implode('|', $toDelete->get()->pluck('id')->toArray()), "contatos", $cliente);
        }
        $toDelete->delete();

        $cliente->contatos()->saveMany($conts);
        if (count($conts)) {
            $this->keepRevisions(null, implode(' | ', $implode), "contatos", $cliente);
        }
    }

    /**
     * @param $parentes
     * @param Cliente $cliente
     * @param $tbl
     * @throws \Exception
     */
    protected function insertUpdateParentesco($parentes, Cliente $cliente, $tbl)
    {
        $conveniodependentes = [];
        $implode = [];

        /**
         * <code>
         * $parente = array(
         *   '0', // id do cliente_convenio_dependente
         *   '1', // nome do parente
         *   '2', // parentesco_id
         *   '3', // descrição parentesco
         *   '4', // ativo
         *   '5', // btn
         * );
         * </code>
         * @var array $parente
         */
        foreach ($parentes as $parente) {
            if (!$parente[0]) {
                $conveniodependente = new Clienteconveniodependente();
                $conveniodependente["nome"] = $parente[1];
                $conveniodependente["cliente_id"] = $cliente->id;
                $conveniodependente["parentesco_id"] = $parente[2];
                $conveniodependente["ativo"] = $parente[4] === 'Sim' ? 1 : 0;
                array_push($conveniodependentes, $conveniodependente);
                $implode[] = "Nome: " . $parente[1] . ", Ativo: " . $parente[4] .
                    ", Parentesco: " . $parente[2];
            }
        }

        $toDelete = Clienteconveniodependente::whereIn('id', $tbl->removed);
        if ($tbl->removed) {
            $this->keepRevisions("Convênio dependente removido ", implode('|', $toDelete->get()->pluck('nome')->toArray()), "clienteConvenioDependete", $cliente);
        }
        $toDelete->delete();

        $cliente->clienteConvenioDependete()->saveMany($conveniodependentes);
        if (count($conveniodependentes)) {
            $this->keepRevisions(null, implode(' | ', $implode), "clienteConvenioDependete", $cliente);
        }
    }

    /**
     * @param $produtos
     * @param Cliente $cliente
     * @param $tbl
     * @throws \Exception
     */
    protected function insertUpdateProdutos($produtos, Cliente $cliente, $tbl)
    {
        $clienteprodutos = collect([]);
        $implode = [];
        /**
         * <code>
         * $produto = array(
         *   '0', // id do cliente_produtos
         *   '1', // produto_id
         *   '2', // descricao produto
         *   '3', // preço
         *   '4', // desconto
         *   '5', // tipo
         *   '6', // descontopara
         *   '7', // btn
         * );
         * </code>
         * @var array $produto
         */
        foreach ($produtos as $produto) {
            if (!$produto[0]) {
                $prod = new Clienteproduto();
                $prod["produto_id"] = $produto[1];
                $prod["cliente_id"] = $cliente->id;
                $prod["preco"] = insertNumeroDecimalOracle($produto[3]);
                $desc = $produto[5] == "1" ? insertNumeroDecimalOracle($produto[4]) : insertPercentualOracle($produto[4]);
                $prod["desconto"] = $produto[5] == "1" ? $desc : $desc / 100;
                $prod["tipo"] = $produto[5];
                $prod["descontopara"] = $produto[6];
                $clienteprodutos->push($prod);
                $desconto = $produto[4];
                $implode[] = "Produto_id: " . $produto[1] . ", Preço: " . $produto[3] .
                    ", Desconto: " . $desconto . ", Tipo: " . $produto[5];
            }
        }

        $toDelete = Clienteproduto::whereIn('id', $tbl->removed);
        if ($tbl->removed) {
            $produtos = $toDelete->get();
            $str = "";
            foreach ($produtos as $prod) {
                $desc = $prod->tipo === "1" ? requestNumeroDecimalOracle($prod->desconto) : requestPercentualOracle($prod->desconto);
                $str .= "Produto_id: " . $prod->produto_id . ", Preço: " . requestNumeroDecimalOracle($prod->preco) .
                    ", Desconto: " . $desc . ", Tipo: " . $prod->tipo . " | ";
            }
            $this->keepRevisions("Produto(s) removido(s)", $str, "clienteProduto", $cliente);
        }
        $toDelete->delete();

        $cliente->clienteProduto()->saveMany($clienteprodutos);
        if (count($clienteprodutos)) {
            $this->keepRevisions(null, implode(' | ', $implode), "clienteProduto", $cliente);
        }
    }

    /**
     * @return array
     */
    protected function selectTipoPessoas()
    {
        $tipopessoasdados = Tipopessoa::where('ativo', 1)->where('grupo_id', $this->grupo_id)->orderBy('descricao')->get();
        $tipopessoas = ['' => 'Selecione'];
        foreach ($tipopessoasdados as $tipopessoa) {
            $tipopessoas[$tipopessoa->id . $tipopessoa->tipopessoacadastro] = $tipopessoa->descricao;
        }
        return $tipopessoas;
    }

    /**
     * @param $id
     * @param Cliente $cli
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function contrato($id, Cliente $cli)
    {
        $this->authorize('view', $cli);
        $cliente = Cliente::findOrFail($id);
        $this->authorize('igualdade', $cliente);
        $codigo = $cliente->id;
        $empresa = Empresa::find($cliente->empresa_id);
        $titulo = 'CONTRATO PARTICULAR DE CONVÊNIOS';
        $filtro = '';
        $dataAtual = Carbon::now()->format('d/m/Y');
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView(
            'clientes.gerar.contrato_convenio',
            compact('titulo', 'filtro', 'codigo', 'cliente', 'dataAtual', 'empresa')
        );
        return $pdf->stream('Convênio nº: ' . $id);
    }

    /**
     * @param $id
     * @return mixed
     */
    function buscaClienteTipoPessoa($id)
    {
        $cliente = Cliente::where('clientes.id', $id)->join(
            'tipopessoas',
            'clientes.tipopessoa_id',
            '=',
            'tipopessoas.id'
        )->get();
        if (isset($cliente[0]))
            return $cliente[0];
        return null;
    }

    /**
     * @param $id
     * @return Cliente|Cliente[]|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|mixed|null|string
     */
    function buscaPorId($id)
    {
        $cliente = Cliente::with([
            'cidade',
            'bairro',
            'rua',
            'telefones'
        ])->find($id);
        return is_null($cliente) ? 'ERRO! Cliente não encontrado!' : $cliente;
    }

    /**
     * @return \Illuminate\Database\Query\Builder|Collection|string|\Yajra\Oci8\Query\OracleBuilder
     */
    function buscaClienteEndereco()
    {
        $rua = e(Input::get('rua', null));
        $num = e(Input::get('num', null));
        $complemento = str_encode_to_query(e(Input::get('complemento', null)));
        if ($rua) {
            $clientes = DB::table('clientes as c')
                ->where('grupo_id', Session::get('empresa_padrao')->grupo_id)
                ->whereRaw("empresa_id in (SELECT empresa_id FROM empresa_user where user_id = " . \Auth::user()->id . ")")
                ->where('rua_id', $rua)
                ->whereAtivo(true)
                ->selectRaw("id, nome, numero, complemento");

            if ($complemento) {
                $raw = rawTranslateSpecialChars("complemento") . " LIKE '%" . strtolower($complemento) . "%'";
                $clientes = $clientes->whereRaw($raw);
            }
            if ($num) {
                $clientes = $clientes->where('numero', $num);
            }
            $clientes = $clientes->get();
            if (count($clientes)) {
                return $clientes;
            }
            return 'NOT';
        } else {
            return 'ERR';
        }
    }

    /**
     * busca pelo ajax
     * @param $nome
     * @return Collection
     */
    public function buscaClienteNome($nome)
    {
        $nome = strtolower($nome);
        return Cliente::where([
            "ativo" => 1,
            "nome"  => $nome
        ])->get(['nome', 'id']);
    }

    /**
     * @param $id
     * @return string
     * @throws \Exception
     */
    public function updateCampoCliente($id)
    {
        DB::beginTransaction();
        try {
            $cliente = Cliente::find($id);
            if (isset($cliente->cliente)) {
                $cliente->cliente = 1;
                $cliente->save();
                DB::commit();
                return "OK|";
            }
            return "ERRO! Cliente não encontrado!";
        } catch (\Exception $e) {
            DB::rollback();
            return "ERRO! " . $e;
        }
    }

    /**
     * @param $data
     * @param Cliente|Collection $cliente
     * @throws \Exception
     */
    public function verificaEndereco($data, $cliente = null, $fromApi = false)
    {
        $rawComplemento = "";
        if ($data['complemento'] != '') {
            $rawComplemento = rawTranslateSpecialChars("complemento") . " LIKE '%" . str_encode_to_query($data["complemento"]) . "%' AND ";
        }
        $raw = "cidade_id = " . $data["cidade_id"] . " AND (bairro_id = " . $data["bairro_id"] . " OR bairro_id IS NOT NULL)"
            . " AND " . $rawComplemento . " rua_id = " . $data["rua_id"] . " AND numero = " . $data["numero"]
            . " AND empresa_id = " . Session::get('empresa_padrao')->id  . " limit 1";

        if (!is_null($cliente)) {
            $raw .= " AND id != " . $cliente->id;
        }

        $clienteS = Cliente::whereRaw($raw)->first();
        if (!is_null($clienteS) && !$fromApi) {
            throw new \Exception("Já existe um cliente cadastrado para este endereço: " . $clienteS->nome, 1);
        }

        if ($fromApi) return $clienteS;
    }

    /**
     * @param null $empresa_id
     */
    private function definitions($empresa_id = null)
    {
        //grupo_id
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //empresa_id
        if (is_null($empresa_id))
            $this->empresa_id = Session::get('empresa_padrao')->id;
        else
            $this->empresa_id = $empresa_id;

        $this->cepempresa = Empresa::find($this->empresa_id)->cep;
    }

    /**
     * @param $apartir
     * @param $id
     * @param Cliente $cli
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function imprimirEtiquetasConvenio($apartir, $id, Cliente $cli)
    {
        $this->authorize('view', $cli);
        $cliente = Cliente::find($id);
        $this->authorize('igualdade', $cliente);
        try {

            $clientesConveniados = Cliente::with("clienteConvenioDependente")
                ->where('convenio', 1)
                ->where('convenio_id', $id)
                ->get();

            if ($cliente !== null) {

                $dadosParaImpressao = [];
                if ($apartir > 29)
                    throw new \Exception('O campo "A partir" não deve ser maior que 29.');

                if ($apartir >= 1) {
                    for ($i = 1; $i < $apartir; $i++) {
                        $dados = (object) [];
                        $dados->codigo = '';
                        $dados->nome_conveniado = '';
                        $dados->cpf = '';
                        $dados->empresa = '';
                        $dados->parentesco = '';
                        array_push($dadosParaImpressao, $dados);
                    }
                }
                $nomeEmpresa = $cliente->nome;
                foreach ($clientesConveniados as $conveniado) {
                    if (count($conveniado->clienteConvenioDependente) > 0) {
                        foreach ($conveniado->clienteConvenioDependente as $dependente) {
                            $dados = (object) [];
                            $dados->codigo = $cliente->id;
                            $dados->nome_conveniado = $conveniado->id . ' - ' . $conveniado->nome;
                            $dados->cpf = empty($conveniado->cpf) ? $conveniado->cnpj : $conveniado->cpf;
                            $dados->empresa = $nomeEmpresa;
                            $dados->parentesco = $dependente->nome . ' - ' . $dependente->parentesco->descricao;
                            array_push($dadosParaImpressao, $dados);
                        }
                    }

                    $dados = (object) [];
                    $dados->codigo = $cliente->id;
                    $dados->nome_conveniado = $conveniado->id . ' - ' . $conveniado->nome;
                    $dados->cpf = empty($conveniado->cpf) ? $conveniado->cnpj : $conveniado->cpf;
                    $dados->empresa = $nomeEmpresa;
                    $dados->parentesco = '';

                    array_push($dadosParaImpressao, $dados);
                }
                $data = collect($dadosParaImpressao)->chunk(30);
                $titulo = "Etiquetas Convenio";
                $pdf = \App::make('dompdf.wrapper');
                $pdf->loadView('clientes.gerar.etiquetas_convenio_v2', compact('titulo', 'data'))->setPaper('letter');
                return $pdf->stream('Convenio.pdf');
            } else {
                throw new \Exception('Empresa do convenio não encontrada!');
            }
        } catch (\Exception $e) {
            Session::flash("message_info", $e->getMessage());
            return Redirect::to('cliente/' . $id);
        }
    }

    //Ajax fechamento convenio
    public function fechamentoConvenio($id)
    {
        $cliente = Cliente::find($id);
        $diavencimento = $cliente->clienteConvenio->diavencimento;
        return $diavencimento;
    }

    public function ativaCliente($id)
    {
        try {
            Cliente::find($id)->update(['ativo' => 1]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        return "OK|";
    }

    /**
     * Metodo para salvar revisões manuais
     *
     * @param string $old
     * @param string $new
     * @param string $key => chave('o que ou qual campo representa')
     * @param \App\Cliente $cliente ('model')
     * @param boolean $now ('se deseja salvar agora ou retornar o objeto')
     * @return mixed $rev
     */
    private function keepRevisions($old, $new, $key, $cliente, $now = true)
    {
        $user_id = \Auth::user()->getAuthIdentifier();
        $revision = new Revision();
        $revision->revisionable_type = $cliente->getMorphClass();
        $revision->revisionable_id = $cliente->getKey();
        $revision->key = $key;
        $revision->old_value = $old;
        $revision->new_value = $new;
        $revision->user_id = $user_id;
        $revision->created_at = Carbon::now();
        $revision->updated_at = Carbon::now();
        $revision->identity = $cliente->empresa_id;
        $revision->identityBy = "empresa_id";
        if ($now) {
            $revision->save();
            return true;
        } else {
            return $revision;
        }
    }

    /**
     * @param Collection $old
     * @param $new
     * @param $cliente
     * @return bool
     */
    private function keepRevisionsArr($old, $new, $cliente)
    {
        $revs = new Revision();
        $arr = collect([]);
        $changes = [];
        $changedto = [];
        $dontkeep = ["updated_at"];
        foreach ($old->toArray() as $key => $antigo) {
            if (strpos($key, "data") !== false) {
                $new[$key] = $new[$key] . " 00:00:00";
            }
            if ((!isset($new[$key]) || $old[$key] != $new[$key]) && !in_array($key, $dontkeep)) {
                $changes[$key] = $old[$key];
                $changedto[$key] = $new[$key];
            }
        }
        foreach ($changes as $key => $value) {
            $revision = $this->keepRevisions($changes[$key], $changedto[$key], $key, $cliente, false);
            $arr->push($revision);
        }
        $revs->insert($arr->toArray());
        return true;
    }

    private function createRua(&$data)
    {

        $ruaCon = new RuaController();
        $rua = $ruaCon->createRuaFromOther($data);

        $data["rua_id"] = $rua->id;
    }

    private function getDescontoPara()
    {
        return [
            1 => "Todos",
            2 => "Aplicativo",
            3 => "Disk"
        ];
    }
}
