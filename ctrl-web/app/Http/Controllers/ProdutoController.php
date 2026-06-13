<?php

namespace App\Http\Controllers;

use App\Estado;
use DB;
use Session;
use Redirect;
use App\NFcest;
use App\Produto;
use App\Estoquesetor;
use App\Produtoclasse;
use App\Repository\SelectRepository;
use App\Http\Requests\ProdutoRequest;
use App\Produtoorigem;

class ProdutoController extends Controller
{

    protected $nfipi;
    protected $nfcest;
    protected $produto;
    protected $grupo_id;
    protected $vasilhame;
    protected $empresa_id;
    protected $repositorio;
    protected $tipo_classe;
    protected $nfetipoitem;
    protected $empresa_sped;
    protected $unidademedida;
    protected $produtoclasses;
    protected $produtovasilhame;

    public function __construct(SelectRepository $repositorio)
    {
        $this->repositorio = $repositorio;
    }

    public function index(Produto $pro)
    {
        $this->authorize('view', $pro);
        $this->definition();
        $produto = Produto::where('empresa_id', $this->empresa_id)->get();
        return view('produtos.produtos')->with(compact('produto'));
    }

    public function create(Produto $pro)
    {
        $this->authorize('create', $pro);
        $this->definition();
        $produtoclasses = $this->repositorio->getProdutoClasse()->prepend("Selecione", "");
        $vasilhame = $this->getProdutoVasilhame() != null ? $this->getProdutoVasilhame()->prepend('Selecione', '') : [];
        $unidademedida = $this->repositorio->getUnidadeMedida()->prepend("Selecione", "");
        $nfgrupofiscal_id = $this->repositorio->getNfGrupoFiscal()->prepend("Selecione", "");
        $nfetipoitem = $this->repositorio->getSpedTipoItem()->prepend('Selecione', '');
        $nfipi = nfMake($this->repositorio->getIpi());
        $sped = Session::get('empresa_padrao')->spedemite;
        $classeglp = Produtoclasse::where(['grupo_id' => $this->grupo_id, 'tipo' => 'G', 'ativo' => 1])->pluck('id');
        $tipoglp = self::tipoGlp();
        $ressarcimento = Produto::join('produtoclasses class', 'produtos.produtoclasse_id', 'class.id')
            ->where([
                ['produtos.empresa_id', $this->empresa_id],
                ['class.tipo', 'R'],
                ['produtos.ativo', 1]
            ])->orderBy('produtos.descricao')
            ->pluck('produtos.descricao', 'produtos.id')->prepend('Selecione', '');

        $generos = self::getGeneros();
        $lst = self::getLst();
        $estados = Estado::select("cod_ibge", "descricao")
            ->orderBy("descricao")
            ->pluck("descricao", "cod_ibge")
            ->prepend('Selecione', '');

        return view(
            'produtos.produtos_form',
            compact(
                'produtoclasses',
                'unidademedida',
                'nfgrupofiscal_id',
                'nfetipoitem',
                'nfipi',
                'sped',
                'vasilhame',
                'tipoglp',
                'classeglp',
                'ressarcimento',
                'generos',
                'lst',
                'estados'
            )
        );
    }

    public function store(ProdutoRequest $request, Produto $pro)
    {
        $this->authorize('create', $pro);

        $this->definition();

        DB::beginTransaction();
        try {
            $data = $request->all();
            $dadosextras = $this->dadosExtras($data);
            $data = array_merge($data, $dadosextras);

            $produto = Produto::create($data);

            if (isset($data["origensList"]) && !empty($data["origensList"])) $this->createOrigens($produto, $data["origensList"]);
        } catch (\Exception $ex) {
            DB::rollback();
            return Redirect::to('/produto/create')
                ->withErrors($ex->getMessage())
                ->withInput();
        }
        DB::commit();

        return Redirect::to('produto')->withMessageSuccess("Produto cadastrado com sucesso!");
    }

    public function show($id, Produto $pro)
    {
        $this->authorize('view', $pro);
        return $this->showEdit(true, $id);
    }

    public function edit($id, Produto $pro)
    {
        $this->authorize('update', $pro);
        return $this->showEdit(false, $id);
    }

    public function update(ProdutoRequest $request, $id)
    {
        $produto = Produto::findOrFail($id);
        $this->authorize('igualdade', $produto);

        $this->definition();

        DB::beginTransaction();
        try {
            $data = $request->all();
            $dadosExtras = $this->dadosExtras($data);
            $data = array_merge($data, $dadosExtras);

            if ($data['ativo'] === 0) {
                $estoquesetor = Estoquesetor::where('produto_id', $id)->get();
                foreach ($estoquesetor as $estoque) {
                    if ($estoque->quantidade != 0)
                        throw new \Exception("Este produto não pode ser inativado pois está no estoque de algum setor!");
                }
            }

            $produto->update($data);

            $produto->origens()->delete();

            if (isset($data["origensList"]) && !empty($data["origensList"])) $this->createOrigens($produto, $data["origensList"]);
        } catch (\Exception $ex) {
            DB::rollback();
            return Redirect::to('produto/' . $id . '/edit/')->withErrors($ex->getMessage())->withInput();
        }
        DB::commit();
        return Redirect::to('produto')->withMessageSuccess('Produto atualizado com sucesso!');
    }

    private function dadosExtras($data)
    {
        $origens = isset($data["origensList"]) ? json_decode($data["origensList"]) : [];
        $data["precovenda"] = insertNumeroDecimalOracle($data["precovenda"]);
        $data["precogasdopovo"] = insertNumeroDecimalOracle($data["precogasdopovo"]);
        $data["customedio"] = insertNumeroDecimalOracle($data["customedio"]);
        $data["precovendaminimo"] = insertNumeroDecimalOracle($data["precovendaminimo"]);
        $data["pesoliquido"] = conversaoPeso($data["pesoliquido"]);
        $data["pesobruto"] = conversaoPeso($data["pesobruto"]);
        if (isset($data["nfepermite"])) {
            $data["nfealiqipi"] = converterBaseCalcOracle($data["nfealiqipi"]);
            $data["nfebcipi"] = converterBaseCalcOracle($data["nfebcipi"]);
            $data["nfeqbcprod"] = converterBaseCalcOracle($data["nfeqbcprod"]);
            $data["nfevaliqprod"] = converterBaseCalcOracle($data["nfevaliqprod"]);
            $data["nfevcide"] = converterBaseCalcOracle($data["nfevcide"]);
        } else {
            $data["nfepermite"] = 0;
        }
        $data['pgni'] = insertPercentualOracle($data['pgni']);
        $data['pgnn'] = insertPercentualOracle($data['pgnn']);
        $data['pglp'] = insertPercentualOracle($data['pglp']);

        $sum = $data['pglp'] + $data['pgnn'] + $data['pgni'];
        if ($sum < 100 && $sum > 0) {
            throw new \Exception("A soma dos percentuais de GLP precisa ser 100 ou 0.");
        }

        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["produtoretornavel_id"] = !isset($data["produtoretornavel_id"]) ? null : $data["produtoretornavel_id"];
        if (!isset($data["ativo"])) {
            $data["ativo"] = 0;
        }
        if (!isset($data["enviaappnf"])) {
            $data["enviaappnf"] = 0;
        }

        if (!empty($origens)) {
            $total = 0;
            foreach ($origens as $origem) {
                $origem[5] = insertPercentualOracle($origem[5]);

                $total += $origem[5];
            }

            if ($total != 100) {
                throw new \Exception("A soma dos percentuais de Origem do Combustível precisa dar 100 %.");
            }

            $data["origensList"] = $origens;
        }

        return emptyToNull($data);
    }

    public function destroy($id, Produto $pro)
    {
        $this->authorize('delete', $pro);
        $produto = Produto::find($id);
        $this->authorize('igualdade', $produto);
        DB::beginTransaction();
        try {
            $produto->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        return "OK|";
    }

    public static function tipoGlp()
    {
        return [
            ""  => "Selecione",
            "1" => "GLP P02",
            "2" => "GLP P08",
            "3" => "GLP P13",
            "4" => "GLP P20",
            "5" => "GLP P45",
            "6" => "GLP P90"
        ];
    }

    private function correcoesProduto($produto)
    {
        $produto->precovenda = requestNumeroDecimalOracle($produto->precovenda);
        $produto->custofrete = requestNumeroDecimalOracle($produto->custofrete);
        $produto->customedio = requestNumeroDecimalOracle($produto->customedio);
        $produto->precovendaminimo = requestNumeroDecimalOracle($produto->precovendaminimo);
        $produto->precogasdopovo = requestNumeroDecimalOracle($produto->precogasdopovo);
        $produto->pesobruto = number_format($produto->pesobruto, 3, ',', '.') . ' Kg';
        $produto->pesoliquido = number_format($produto->pesoliquido, 3, ',', '.') . ' Kg';
        $produto->nfealiqipi = requestBaseCalcNfOracle($produto->nfealiqipi);
        $produto->nfebcipi = requestBaseCalcNfOracle($produto->nfebcipi);
        $produto->nfeqbcprod = requestBaseCalcNfOracle($produto->nfeqbcprod);
        $produto->nfevaliqprod = requestBaseCalcNfOracle($produto->nfevaliqprod);
        $produto->nfevcide = requestBaseCalcNfOracle($produto->nfevcide);
        return $produto;
    }

    private function getProdutoVasilhame()
    {
        $classevasilhame = ProdutoClasse::where(['ativo' => 1, 'grupo_id' => $this->grupo_id, 'tipo' => 'V'])->pluck('id')->toArray();
        if (!empty($classevasilhame)) {
            $vasilhame = Produto::where(['empresa_id' => $this->empresa_id, 'ativo' => 1])->whereIn(
                'produtoclasse_id',
                $classevasilhame
            )->pluck('descricao', 'id');
            return count($vasilhame) > 0 ? $vasilhame : null;
        } else {
            return;
        }
    }

    private function showEdit($show, $id)
    {
        $produto = Produto::find($id);
        $this->authorize('igualdade', $produto);
        $this->definition();
        $produto = $this->correcoesProduto($produto);
        $produto->load("origens", "origens.uf");
        $produtoclasses = $this->repositorio->getProdutoClasse()->prepend("Selecione", "");
        $vasilhame = $this->getProdutoVasilhame() != null ? $this->getProdutoVasilhame()->prepend('Selecione', '') : [];
        $unidademedida = $this->repositorio->getUnidadeMedida()->prepend("Selecione", "");
        $nfgrupofiscal_id = $this->repositorio->getNfGrupoFiscal()->prepend("Selecione", "");
        $nfetipoitem = $this->repositorio->getSpedTipoItem()->prepend('Selecione', '');
        $nfipi = nfMake($this->repositorio->getIpi());
        $sped = Session::get('empresa_padrao')->spedemite;
        $nfcest = $produto->ncm == null ? null : makeCest($this->repositorio->getNfcest($produto->nfcest, $produto->ncm));
        $classeglp = Produtoclasse::where(['grupo_id' => $this->grupo_id, 'tipo' => 'G', 'ativo' => 1])->pluck('id');
        $tipoglp = self::tipoGlp();
        $generos = self::getGeneros();
        $lst = self::getLst();
        $ressarcimento = Produto::join('produtoclasses class', 'produtos.produtoclasse_id', 'class.id')
            ->where([
                ['produtos.empresa_id', $this->empresa_id],
                ['class.tipo', 'R'],
                ['produtos.ativo', 1]
            ])->orderBy('produtos.descricao')
            ->pluck('produtos.descricao', 'produtos.id')->prepend('Selecione', '');
        $estados = Estado::select("cod_ibge", "descricao")
            ->orderBy("descricao")
            ->pluck("descricao", "cod_ibge")
            ->prepend('Selecione', '');

        $listOrigens = $produto->origens->map(function ($it) {
            $it->porig = requestNumeroDecimal4DigitosOracle($it->porig);
            $it->estado = $it->uf->descricao;
            return $it;
        });

        if ($show) {
            return view('produtos.produtos_form')->with(compact(
                'produto',
                'produtoclasses',
                'unidademedida',
                'nfgrupofiscal_id',
                'nfetipoitem',
                'nfipi',
                'sped',
                'show',
                'nfcest',
                'vasilhame',
                'classeglp',
                'tipoglp',
                'ressarcimento',
                'lst',
                'generos',
                'listOrigens',
                'estados'
            ));
        }

        return view('produtos.produtos_form')->with(compact(
            'produto',
            'produtoclasses',
            'unidademedida',
            'nfgrupofiscal_id',
            'nfetipoitem',
            'nfipi',
            'sped',
            'nfcest',
            'vasilhame',
            'classeglp',
            'tipoglp',
            'ressarcimento',
            'lst',
            'generos',
            'listOrigens',
            'estados'
        ));
    }

    function ajaxNcmCest($ncm)
    {
        return Nfcest::where('ncm', $ncm)->get(['descricao', 'cest', 'id']);
    }

    function ajax($id)
    {
        return Produto::where('id', $id)->with('unidademedida', 'grupoFiscal')->get();
    }

    public function buscaPorSetor($id)
    {
        $produtos = Estoquesetor::join('produtos', 'estoquesetors.produto_id', '=', 'produtos.id')
            ->join('setors', 'estoquesetors.setor_id', '=', 'setors.id')
            ->leftJoin('produtos vas', 'produtos.produtoretornavel_id', 'vas.id')
            ->where([
                ['estoquesetors.setor_id', $id],
                ['setors.ativo', 1],
                ['produtos.ativo', 1]
            ])
            ->select(
                'produtos.descricao',
                'produtos.id',
                'produtos.precovenda',
                'vas.id as retornavel_id',
                'vas.descricao as retornavel',
                'produtos.pesoliquido',
                'produtos.pesobruto',
                'produtos.pgni',
                'produtos.pgnn',
                'produtos.pglp'
            )
            ->orderBy('produtos.descricao')
            ->get();
        return $produtos;
    }

    public function buscaPorSetorNF($id)
    {
        $estoque = Estoquesetor::join('produtos', 'estoquesetors.produto_id', 'produtos.id')
            ->join('setors', 'estoquesetors.setor_id', 'setors.id')
            ->where([
                ['estoquesetors.setor_id', $id],
                ['setors.ativo', 1],
                ['produtos.ativo', 1],
                ['produtos.nfepermite', 1]
            ])
            ->orderBy('produtos.descricao')
            ->select([
                'produtos.descricao',
                'produtos.id',
                'produtos.precovenda',
                'produtos.pesoliquido',
                'produtos.pesobruto',
                'produtos.pgni',
                'produtos.pgnn',
                'produtos.pglp'
            ])->get();
        return $estoque;
    }

    public function buscaPorSetorNFEntrada($id)
    {
        return Produto::where([['ativo', 1], ['nfepermite', 1]])
            ->where('empresa_id', Session::get('empresa_padrao')->id)
            ->orderBy('produtos.descricao')
            ->select([
                'descricao',
                'id',
                'customedio',
                'produtos.pesoliquido',
                'produtos.pesobruto',
                'produtos.precovenda',
                'produtos.precovendaminimo',
                'produtos.pgni',
                'produtos.pgnn',
                'produtos.pglp',
                'produtos.tipo_glp',
                'produtos.nfecprodanp as cprodanp'
            ])->get();
    }

    public function buscaPorClasse($classe_id)
    {
        return Produto::where('ativo', 1)->where('empresa_id', Session::get('empresa_padrao')->id)->where(
            'produtoclasse_id',
            $classe_id
        )->get();
    }

    public function buscarPrecoAjax($id)
    {
        $produto = Produto::findOrFail($id);
        if ($produto !== null) {
            return $produto->precovenda;
        } else {
            return 'ERRO';
        }
    }

    private function definition()
    {
        //Busca id da empresa padrão
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //Busca id do grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //Set Empresa ID
        $this->repositorio->setEmpresa($this->empresa_id);
        //Set grupo id
        $this->repositorio->setGrupo($this->grupo_id);
    }

    public static function getGeneros()
    {
        $gen = [
            ""   => "Selecione",
            "00" => "00 - Serviço",
            "01" => "01 - Animais vivos",
            "02" => "02 - Carnes e miudezas, comestíveis",
            "03" => "03 - Peixes e crustáceos, moluscos e os outros invertebrados aquáticos",
            "04" => "04 - Leite e laticínios; ovos de aves; mel natural; produtos comestíveis de origem animal, não especificadosnem compreendidos em outros Capítulos da TIPI",
            "05" => "05 - Outros produtos de origem animal, não especificados nem compreendidos em outros Capítulos da TIPI",
            "06" => "06 - Plantas vivas e produtos de floricultura",
            "07" => "07 - Produtos hortícolas, plantas, raízes e tubérculos, comestíveis",
            "08" => "08 - Frutas; cascas de cítricos e de melões",
            "09" => "09 - Café, chá, mate e especiarias",
            "10" => "10 - Cereais",
            "11" => "11 - Produtos da indústria de moagem; malte; amidos e féculas; inulina; glúten de trigo",
            "12" => "12 - Sementes e frutos oleaginosos; grãos, sementes e frutos diversos; plantas industriais ou medicinais; palha e forragem",
            "13" => "13 - Gomas, resinas e outros sucos e extratos vegetais",
            "14" => "14 - Matérias para entrançar e outros produtos de origem vegetal, não especificadas nem compreendidas em outros Capítulos da NCM",
            "15" => "15 - Gorduras e óleos animais ou vegetais; produtos da sua dissociação; gorduras alimentares elaboradas; ceras de origem animal ou vegetal",
            "16" => "16 - Preparações de carne, de peixes ou de crustáceos, de moluscos ou de outros invertebrados aquáticos",
            "17" => "17 - Açúcares e produtos de confeitaria",
            "18" => "18 - Cacau e suas preparações",
            "19" => "19 - Preparações à base de cereais, farinhas, amidos, féculas ou de leite; produtos de pastelaria",
            "20" => "20 - Preparações de produtos hortícolas, de frutas ou de outras partes de plantas",
            "21" => "21 - Preparações alimentícias diversas",
            "22" => "22 - Bebidas, líquidos alcoólicos e vinagres",
            "23" => "23 - Resíduos e desperdícios das indústrias alimentares; alimentos preparados para animais",
            "24" => "24 - Fumo (tabaco) e seus sucedâneos, manufaturados",
            "25" => "25 - Sal; enxofre; terras e pedras; gesso, cal e cimento",
            "26" => "26 - Minérios, escórias e cinzas",
            "27" => "27 - Combustíveis minerais, óleos minerais e produtos de sua destilação; matérias betuminosas; ceras minerais",
            "28" => "28 - Produtos químicos inorgânicos; compostos inorgânicos ou orgânicos de metais preciosos, de elementos radioativos, de metais das terras raras ou de isótopos",
            "29" => "29 - Produtos químicos orgânicos",
            "30" => "30 - Produtos farmacêuticos",
            "31" => "31 - Adubos ou fertilizantes",
            "32" => "32 - Extratos tanantes e tintoriais; taninos e seus derivados; pigmentos e outras matérias corantes, tintas e vernizes, mástiques; tintas de escrever",
            "33" => "33 - Óleos essenciais e resinóides; produtos de perfumaria ou de toucador preparados e preparações cosméticas",
            "34" => "34 - Sabões, agentes orgânicos de superfície, preparações para lavagem, preparações lubrificantes, ceras artificiais, ceras preparadas, produtos de conservação e limpeza, velas e artigos semelhantes, massas ou pastas para modelar, 'ceras para dentistas' e composições para dentistas à base de gesso",
            "35" => "35 - Matérias albuminóides; produtos à base de amidos ou de féculas modificados; colas; enzimas",
            "36" => "36 - Pólvoras e explosivos; artigos de pirotecnia; fósforos; ligas pirofóricas; matérias inflamáveis",
            "37" => "37 - Produtos para fotografia e cinematografia",
            "38" => "38 - Produtos diversos das indústrias químicas",
            "39" => "39 - Plásticos e suas obras",
            "40" => "40 - Borracha e suas obras",
            "41" => "41 - Peles, exceto a peleteria (peles com pêlo*), e couros",
            "42" => "42 - Obras de couro; artigos de correeiro ou de seleiro; artigos de viagem, bolsas e artefatos semelhantes; obras de tripa",
            "43" => "43 - Peleteria (peles com pêlo*) e suas obras; peleteria (peles com pêlo*) artificial",
            "44" => "44 - Madeira, carvão vegetal e obras de madeira",
            "45" => "45 - Cortiça e suas obras",
            "46" => "46 - Obras de espartaria ou de cestaria",
            "47" => "47 - Pastas de madeira ou de outras matérias fibrosas celulósicas; papel ou cartão de reciclar (desperdícios e aparas)",
            "48" => "48 - Papel e cartão; obras de pasta de celulose, de papel ou de cartão",
            "49" => "49 - Livros, jornais, gravuras e outros produtos das indústrias gráficas; textos manuscritos ou datilografados, planos e plantas",
            "50" => "50 - Seda",
            "51" => "51 - Lã e pêlos finos ou grosseiros; fios e tecidos de crina",
            "52" => "52 - Algodão",
            "53" => "53 - Outras fibras têxteis vegetais; fios de papel e tecido de fios de papel",
            "54" => "54 - Filamentos sintéticos ou artificiais",
            "55" => "55 - Fibras sintéticas ou artificiais, descontínuas",
            "56" => "56 - Pastas ('ouates'), feltros e falsos tecidos; fios especiais; cordéis, cordas e cabos; artigos de cordoaria",
            "57" => "57 - Tapetes e outros revestimentos para pavimentos, de matérias têxteis",
            "58" => "58 - Tecidos especiais; tecidos tufados; rendas; tapeçarias; passamanarias; bordados",
            "59" => "59 - Tecidos impregnados, revestidos, recobertos ou estratificados; artigos para usos técnicos de matérias têxteis",
            "60" => "60 - Tecidos de malha",
            "61" => "61 - Vestuário e seus acessórios, de malha",
            "62" => "62 - Vestuário e seus acessórios, exceto de malha",
            "63" => "63 - Outros artefatos têxteis confeccionados; sortidos; artefatos de matérias têxteis, calçados, chapéus e artefatos de uso semelhante, usados; trapos",
            "64" => "64 - Calçados, polainas e artefatos semelhantes, e suas partes",
            "65" => "65 - Chapéus e artefatos de uso semelhante, e suas partes",
            "66" => "66 - Guarda-chuvas, sombrinhas, guarda-sóis, bengalas, bengalas-assentos, chicotes, e suas partes",
            "67" => "67 - Penas e penugem preparadas, e suas obras; flores artificiais; obras de cabelo",
            "68" => "68 - Obras de pedra, gesso, cimento, amianto, mica ou de matérias semelhantes",
            "69" => "69 - Produtos cerâmicos",
            "70" => "70 - Vidro e suas obras",
            "71" => "71 - Pérolas naturais ou cultivadas, pedras preciosas ou semipreciosas e semelhantes, metais preciosos, metais folheados ou chapeados de metais preciosos, e suas obras; bijuterias; moedas",
            "72" => "72 - Ferro fundido, ferro e aço",
            "73" => "73 - Obras de ferro fundido, ferro ou aço",
            "74" => "74 - Cobre e suas obras",
            "75" => "75 - Níquel e suas obras",
            "76" => "76 - Alumínio e suas obras",
            "77" => "77 - (Reservado para uma eventual utilização futura no SH)",
            "78" => "78 - Chumbo e suas obras",
            "79" => "79 - Zinco e suas obras",
            "80" => "80 - Estanho e suas obras",
            "81" => "81 - Outros metais comuns; ceramais ('cermets'); obras dessas matérias",
            "82" => "82 - Ferramentas, artefatos de cutelaria e talheres, e suas partes, de metais comuns",
            "83" => "83 - Obras diversas de metais comuns",
            "84" => "84 - Reatores nucleares, caldeiras, máquinas, aparelhos e instrumentos mecânicos, e suas partes",
            "85" => "85 - Máquinas, aparelhos e materiais elétricos, e suas partes; aparelhos de gravação ou de reprodução de som, aparelhos de gravação ou de reprodução de imagens e de som em televisão, e suas partes e acessórios",
            "86" => "86 - Veículos e material para vias férreas ou semelhantes, e suas partes; aparelhos mecânicos (incluídos os eletromecânicos) de sinalização para vias de comunicação",
            "87" => "87 - Veículos automóveis, tratores, ciclos e outros veículos terrestres, suas partes e acessórios",
            "88" => "88 - Aeronaves e aparelhos espaciais, e suas partes",
            "89" => "89 - Embarcações e estruturas flutuantes",
            "90" => "90 - Instrumentos e aparelhos de óptica, fotografia ou cinematografia, medida, controle ou de precisão; instrumentos e aparelhos médico-cirúrgicos; suas partes e acessórios",
            "91" => "91 - Aparelhos de relojoaria e suas partes",
            "92" => "92 - Instrumentos musicais, suas partes e acessórios",
            "93" => "93 - Armas e munições; suas partes e acessórios",
            "94" => "94 - Móveis, mobiliário médico-cirúrgico; colchões; iluminação e construção pré-fabricadas",
            "95" => "95 - Brinquedos, jogos, artigos para divertimento ou para esporte; suas partes e acessórios",
            "96" => "96 - Obras diversas",
            "97" => "97 - Objetos de arte, de coleção e antiguidades",
            "98" => "98 - (Reservado para usos especiais pelas Partes Contratantes)",
            "99" => "99 - Operações especiais (utilizado exclusivamente pelo Brasil para classificar operações especiais na exportação)",
        ];
        return $gen;
    }

    public static function getLst()
    {
        $lst = [
            ""      => "Selecione",
            // 1 => Serviços de informática e congêneres.
            "1.01"  => "1.01 - Análise e desenvolvimento de sistemas.",
            "1.02"  => "1.02 - Programação.",
            "1.03"  => "1.03 - Processamento de dados e congêneres.",
            "1.04"  => "1.04 - Elaboração de programas de computadores, inclusive de jogos eletrônicos.",
            "1.05"  => "1.05 - Licenciamento ou cessão de direito de uso de programas de computação.",
            "1.06"  => "1.06 - Assessoria e consultoria em informática.",
            "1.07"  => "1.07 - Suporte técnico em informática, inclusive instalação, configuração e manutenção de programas de computação e bancos de dados.",
            "1.08"  => "1.08 - Planejamento, confecção, manutenção e atualização de páginas eletrônicas.",
            // 2 => Serviços de pesquisas e desenvolvimento de qualquer natureza.
            "2.01"  => "2.01 - Serviços de pesquisas e desenvolvimento de qualquer natureza.",
            //  3 => Serviços prestados mediante locação, cessão de direito de uso e congêneres.
            "3.01"  => "3.01 - (VETADO) 3.02 => Cessão de direito de uso de marcas e de sinais de propaganda.",
            "3.03"  => "3.03 - Exploração de salões de festas, centro de convenções, escritórios virtuais, stands, quadras esportivas, estádios, ginásios, auditórios, casas de espetáculos, parques de diversões, canchas e congêneres, para realização de eventos ou negócios de qualquer natureza.",
            "3.04"  => "3.04 - Locação, sublocação, arrendamento, direito de passagem ou permissão de uso, compartilhado ou não, de ferrovia, rodovia, postes, cabos, dutos e condutos de qualquer natureza.",
            "3.05"  => "3.05 - Cessão de andaimes, palcos, coberturas e outras estruturas de uso temporário.",
            //  4 => Serviços de saúde, assistência médica e congêneres.
            "4.01"  => "4.01 - Medicina e biomedicina.",
            "4.02"  => "4.02 - Análises clínicas, patologia, eletricidade médica, radioterapia, quimioterapia, ultra-sonografia, ressonância magnética, radiologia, tomografia e congêneres.",
            "4.03"  => "4.03 - Hospitais, clínicas, laboratórios, sanatórios, manicômios, casas de saúde, prontos-socorros, ambulatórios e congêneres.",
            "4.04"  => "4.04 - Instrumentação cirúrgica.",
            "4.05"  => "4.05 - Acupuntura.",
            "4.06"  => "4.06 - Enfermagem, inclusive serviços auxiliares.",
            "4.07"  => "4.07 - Serviços farmacêuticos.",
            "4.08"  => "4.08 - Terapia ocupacional, fisioterapia e fonoaudiologia.",
            "4.09"  => "4.09 - Terapias de qualquer espécie destinadas ao tratamento físico, orgânico e mental.",
            "4.10"  => "4.10 - Nutrição.",
            "4.11"  => "4.11 - Obstetrícia.",
            "4.12"  => "4.12 - Odontologia.",
            "4.13"  => "4.13 - Ortóptica.",
            "4.14"  => "4.14 - Próteses sob encomenda.",
            "4.15"  => "4.15 - Psicanálise.",
            "4.16"  => "4.16 - Psicologia.",
            "4.17"  => "4.17 - Casas de repouso e de recuperação, creches, asilos e congêneres.",
            "4.18"  => "4.18 - Inseminação artificial, fertilização in vitro e congêneres.",
            "4.19"  => "4.19 - Bancos de sangue, leite, pele, olhos, óvulos, sêmen e congêneres.",
            "4.20"  => "4.20 - Coleta de sangue, leite, tecidos, sêmen, órgãos e materiais biológicos de qualquer espécie.",
            "4.21"  => "4.21 - Unidade de atendimento, assistência ou tratamento móvel e congêneres.",
            "4.22"  => "4.22 - Planos de medicina de grupo ou individual e convênios para prestação de assistência médica, hospitalar, odontológica e congêneres.",
            "4.23"  => "4.23 - Outros planos de saúde que se cumpram através de serviços de terceiros contratados, credenciados, cooperados ou apenas pagos pelo operador do plano mediante indicação do beneficiário.",
            //  5 => Serviços de medicina e assistência veterinária e congêneres.
            "5.01"  => "5.01 - Medicina veterinária e zootecnia.",
            "5.02"  => "5.02 - Hospitais, clínicas, ambulatórios, prontos-socorros e congêneres, na área veterinária.",
            "5.03"  => "5.03 - Laboratórios de análise na área veterinária.",
            "5.04"  => "5.04 - Inseminação artificial, fertilização in vitro e congêneres.",
            "5.05"  => "5.05 - Bancos de sangue e de órgãos e congêneres.",
            "5.06"  => "5.06 - Coleta de sangue, leite, tecidos, sêmen, órgãos e materiais biológicos de qualquer espécie.",
            "5.07"  => "5.07 - Unidade de atendimento, assistência ou tratamento móvel e congêneres.",
            "5.08"  => "5.08 - Guarda, tratamento, amestramento, embelezamento, alojamento e congêneres.",
            "5.09"  => "5.09 - Planos de atendimento e assistência médico-veterinária.",
            //  6 => Serviços de cuidados pessoais, estética, atividades físicas e congêneres.
            "6.01"  => "6.01 - Barbearia, cabeleireiros, manicuros, pedicuros e congêneres.",
            "6.02"  => "6.02 - Esteticistas, tratamento de pele, depilação e congêneres.",
            "6.03"  => "6.03 - Banhos, duchas, sauna, massagens e congêneres.",
            "6.04"  => "6.04 - Ginástica, dança, esportes, natação, artes marciais e demais atividades físicas.",
            "6.05"  => "6.05 - Centros de emagrecimento, spa e congêneres.",
            //  7 => Serviços relativos a engenharia, arquitetura, geologia, urbanismo, construção civil, manutenção, limpeza, meio ambiente, saneamento e congêneres.
            "7.01"  => "7.01 - Engenharia, agronomia, agrimensura, arquitetura, geologia, urbanismo, paisagismo e congêneres.",
            "7.02"  => "7.02 - Execução, por administração, empreitada ou subempreitada, de obras de construção civil, hidráulica ou elétrica e de outras obras semelhantes, inclusive sondagem, perfuração de poços, escavação, drenagem e irrigação, terraplanagem, pavimentação, concretagem e a instalação e montagem de produtos, peças e equipamentos (exceto o fornecimento de mercadorias produzidas pelo prestador de serviços fora do local da prestação dos serviços, que fica sujeito ao ICMS).",
            "7.03"  => "7.03 - Elaboração de planos diretores, estudos de viabilidade, estudos organizacionais e outros, relacionados com obras e serviços de engenharia; elaboração de anteprojetos, projetos básicos e projetos executivos para trabalhos de engenharia.",
            "7.04"  => "7.04 - Demolição.",
            "7.05"  => "7.05 - Reparação, conservação e reforma de edifícios, estradas, pontes, portos e congêneres (exceto o fornecimento de mercadorias produzidas pelo prestador dos serviços, fora do local da prestação dos serviços, que fica sujeito ao ICMS).",
            "7.06"  => "7.06 - Colocação e instalação de tapetes, carpetes, assoalhos, cortinas, revestimentos de parede, vidros, divisórias, placas de gesso e congêneres, com material fornecido pelo tomador do serviço.",
            "7.07"  => "7.07 - Recuperação, raspagem, polimento e lustração de pisos e congêneres.",
            "7.08"  => "7.08 - Calafetação.",
            "7.09"  => "7.09 - Varrição, coleta, remoção, incineração, tratamento, reciclagem, separação e destinação final de lixo, rejeitos e outros resíduos quaisquer.",
            "7.10"  => "7.10 - Limpeza, manutenção e conservação de vias e logradouros públicos, imóveis, chaminés, piscinas, parques, jardins e congêneres.",
            "7.11"  => "7.11 - Decoração e jardinagem, inclusive corte e poda de árvores.",
            "7.12"  => "7.12 - Controle e tratamento de efluentes de qualquer natureza e de agentes físicos, químicos e biológicos.",
            "7.13"  => "7.13 - Dedetização, desinfecção, desinsetização, imunização, higienização, desratização, pulverização e congêneres.",
            "7.14"  => "7.14 - (VETADO) 7.15 => (VETADO) 7.16 => Florestamento, reflorestamento, semeadura, adubação e congêneres.",
            "7.17"  => "7.17 - Escoramento, contenção de encostas e serviços congêneres.",
            "7.18"  => "7.18 - Limpeza e dragagem de rios, portos, canais, baías, lagos, lagoas, represas, açudes e congêneres.",
            "7.19"  => "7.19 - Acompanhamento e fiscalização da execução de obras de engenharia, arquitetura e urbanismo.",
            "7.20"  => "7.20 - Aerofotogrametria (inclusive interpretação), cartografia, mapeamento, levantamentos topográficos, batimétricos, geográficos, geodésicos, geológicos, geofísicos e congêneres.",
            "7.21"  => "7.21 - Pesquisa, perfuração, cimentação, mergulho, perfilagem, concretação, testemunhagem, pescaria, estimulação e outros serviços relacionados com a exploração e explotação de petróleo, gás natural e de outros recursos minerais.",
            "7.22"  => "7.22 - Nucleação e bombardeamento de nuvens e congêneres.",
            //  8 => Serviços de educação, ensino, orientação pedagógica e educacional, instrução, treinamento e avaliação pessoal de qualquer grau ou natureza.
            "8.01"  => "8.01 - Ensino regular pré-escolar, fundamental, médio e superior.",
            "8.02"  => "8.02 - Instrução, treinamento, orientação pedagógica e educacional, avaliação de conhecimentos de qualquer natureza.",
            //  9 => Serviços relativos a hospedagem, turismo, viagens e congêneres.
            "9.01"  => "9.01 - Hospedagem de qualquer natureza em hotéis, apart-service condominiais, flat, apart-hotéis, hotéis residência, residence-service, suite service, hotelaria marítima, motéis, pensões e congêneres; ocupação por temporada com fornecimento de serviço (o valor da alimentação e gorjeta, quando incluído no preço da diária, fica sujeito ao Imposto Sobre Serviços).",
            "9.02"  => "9.02 - Agenciamento, organização, promoção, intermediação e execução de programas de turismo, passeios, viagens, excursões, hospedagens e congêneres.",
            "9.03"  => "9.03 - Guias de turismo.",
            //  10 => Serviços de intermediação e congêneres.
            "10.01" => "10.01 - Agenciamento, corretagem ou intermediação de câmbio, de seguros, de cartões de crédito, de planos de saúde e de planos de previdência privada.",
            "10.02" => "10.02 - Agenciamento, corretagem ou intermediação de títulos em geral, valores mobiliários e contratos quaisquer.",
            "10.03" => "10.03 - Agenciamento, corretagem ou intermediação de direitos de propriedade industrial, artística ou literária.",
            "10.04" => "10.04 - Agenciamento, corretagem ou intermediação de contratos de arrendamento mercantil (leasing), de franquia (franchising) e de faturização (factoring).",
            "10.05" => "10.05 - Agenciamento, corretagem ou intermediação de bens móveis ou imóveis, não abrangidos em outros itens ou subitens, inclusive aqueles realizados no âmbito de Bolsas de Mercadorias e Futuros, por quaisquer meios.",
            "10.06" => "10.06 - Agenciamento marítimo.",
            "10.07" => "10.07 - Agenciamento de notícias.",
            "10.08" => "10.08 - Agenciamento de publicidade e propaganda, inclusive o agenciamento de veiculação por quaisquer meios.",
            "10.09" => "10.09 - Representação de qualquer natureza, inclusive comercial.",
            "10.10" => "10.10 - Distribuição de bens de terceiros.",
            //  11 => Serviços de guarda, estacionamento, armazenamento, vigilância e congêneres.
            "11.01" => "11.01 - Guarda e estacionamento de veículos terrestres automotores, de aeronaves e de embarcações.",
            "11.02" => "11.02 - Vigilância, segurança ou monitoramento de bens e pessoas.",
            "11.03" => "11.03 - Escolta, inclusive de veículos e cargas.",
            "11.04" => "11.04 - Armazenamento, depósito, carga, descarga, arrumação e guarda de bens de qualquer espécie.",
            //  12 => Serviços de diversões, lazer, entretenimento e congêneres.
            "12.01" => "12.01 - Espetáculos teatrais.",
            "12.02" => "12.02 - Exibições cinematográficas.",
            "12.03" => "12.03 - Espetáculos circenses.",
            "12.04" => "12.04 - Programas de auditório.",
            "12.05" => "12.05 - Parques de diversões, centros de lazer e congêneres.",
            "12.06" => "12.06 - Boates, taxi-dancing e congêneres.",
            "12.07" => "12.07 - Shows, ballet, danças, desfiles, bailes, óperas, concertos, recitais, festivais e congêneres.",
            "12.08" => "12.08 - Feiras, exposições, congressos e congêneres.",
            "12.09" => "12.09 - Bilhares, boliches e diversões eletrônicas ou não.",
            "12.10" => "12.10 - Corridas e competições de animais.",
            "12.11" => "12.11 - Competições esportivas ou de destreza física ou intelectual, com ou sem a participação do espectador.",
            "12.12" => "12.12 - Execução de música.",
            "12.13" => "12.13 - Produção, mediante ou sem encomenda prévia, de eventos, espetáculos, entrevistas, shows, ballet, danças, desfiles, bailes, teatros, óperas, concertos, recitais, festivais e congêneres.",
            "12.14" => "12.14 - Fornecimento de música para ambientes fechados ou não, mediante transmissão por qualquer processo.",
            "12.15" => "12.15 - Desfiles de blocos carnavalescos ou folclóricos, trios elétricos e congêneres.",
            "12.16" => "12.16 - Exibição de filmes, entrevistas, musicais, espetáculos, shows, concertos, desfiles, óperas, competições esportivas, de destreza intelectual ou congêneres.",
            "12.17" => "12.17 - Recreação e animação, inclusive em festas e eventos de qualquer natureza.",
            //  13 => Serviços relativos a fonografia, fotografia, cinematografia e reprografia.
            "13.01" => "13.01 - (VETADO) 13.02 => Fonografia ou gravação de sons, inclusive trucagem, dublagem, mixagem e congêneres.",
            "13.03" => "13.03 - Fotografia e cinematografia, inclusive revelação, ampliação, cópia, reprodução, trucagem e congêneres.",
            "13.04" => "13.04 - Reprografia, microfilmagem e digitalização.",
            "13.05" => "13.05 - Composição gráfica, fotocomposição, clicheria, zincografia, litografia, fotolitografia.",
            //  14 => Serviços relativos a bens de terceiros.
            "14.01" => "14.01 - Lubrificação, limpeza, lustração, revisão, carga e recarga, conserto, restauração, blindagem, manutenção e conservação de máquinas, veículos, aparelhos, equipamentos, motores, elevadores ou de qualquer objeto (exceto peças e partes empregadas, que ficam sujeitas ao ICMS).",
            "14.02" => "14.02 - Assistência técnica.",
            "14.03" => "14.03 - Recondicionamento de motores (exceto peças e partes empregadas, que ficam sujeitas ao ICMS).",
            "14.04" => "14.04 - Recauchutagem ou regeneração de pneus.",
            "14.05" => "14.05 - Restauração, recondicionamento, acondicionamento, pintura, beneficiamento, lavagem, secagem, tingimento, galvanoplastia, anodização, corte, recorte, polimento, plastificação e congêneres, de objetos quaisquer.",
            "14.06" => "14.06 - Instalação e montagem de aparelhos, máquinas e equipamentos, inclusive montagem industrial, prestados ao usuário final, exclusivamente com material por ele fornecido.",
            "14.07" => "14.07 - Colocação de molduras e congêneres.",
            "14.08" => "14.08 - Encadernação, gravação e douração de livros, revistas e congêneres.",
            "14.09" => "14.09 - Alfaiataria e costura, quando o material for fornecido pelo usuário final, exceto aviamento.",
            "14.10" => "14.10 - Tinturaria e lavanderia.",
            "14.11" => "14.11 - Tapeçaria e reforma de estofamentos em geral.",
            "14.12" => "14.12 - Funilaria e lanternagem.",
            "14.13" => "14.13 - Carpintaria e serralheria.",
            //  15 => Serviços relacionados ao setor bancário ou financeiro, inclusive aqueles prestados por instituições financeiras autorizadas a funcionar pela União ou por quem de direito.
            "15.01" => "15.01 - Administração de fundos quaisquer, de consórcio, de cartão de crédito ou débito e congêneres, de carteira de clientes, de cheques pré-datados e congêneres.",
            "15.02" => "15.02 - Abertura de contas em geral, inclusive conta-corrente, conta de investimentos e aplicação e caderneta de poupança, no País e no exterior, bem como a manutenção das referidas contas ativas e inativas.",
            "15.03" => "15.03 - Locação e manutenção de cofres particulares, de terminais eletrônicos, de terminais de atendimento e de bens e equipamentos em geral.",
            "15.04" => "15.04 - Fornecimento ou emissão de atestados em geral, inclusive atestado de idoneidade, atestado de capacidade financeira e congêneres.",
            "15.05" => "15.05 - Cadastro, elaboração de ficha cadastral, renovação cadastral e congêneres, inclusão ou exclusão no Cadastro de Emitentes de Cheques sem Fundos => CCF ou em quaisquer outros bancos cadastrais.",
            "15.06" => "15.06 - Emissão, reemissão e fornecimento de avisos, comprovantes e documentos em geral; abono de firmas; coleta e entrega de documentos, bens e valores; comunicação com outra agência ou com a administração central; licenciamento eletrônico de veículos; transferência de veículos; agenciamento fiduciário ou depositário; devolução de bens em custódia.",
            "15.07" => "15.07 - Acesso, movimentação, atendimento e consulta a contas em geral, por qualquer meio ou processo, inclusive por telefone, fac-símile, internet e telex, acesso a terminais de atendimento, inclusive vinte e quatro horas; acesso a outro banco e a rede compartilhada; fornecimento de saldo, extrato e demais informações relativas a contas em geral, por qualquer meio ou processo.",
            "15.08" => "15.08 - Emissão, reemissão, alteração, cessão, substituição, cancelamento e registro de contrato de crédito; estudo, análise e avaliação de operações de crédito; emissão, concessão, alteração ou contratação de aval, fiança, anuência e congêneres; serviços relativos a abertura de crédito, para quaisquer fins.",
            "15.09" => "15.09 - Arrendamento mercantil (leasing) de quaisquer bens, inclusive cessão de direitos e obrigações, substituição de garantia, alteração, cancelamento e registro de contrato, e demais serviços relacionados ao arrendamento mercantil (leasing).",
            "15.10" => "15.10 - Serviços relacionados a cobranças, recebimentos ou pagamentos em geral, de títulos quaisquer, de contas ou carnês, de câmbio, de tributos e por conta de terceiros, inclusive os efetuados por meio eletrônico, automático ou por máquinas de atendimento; fornecimento de posição de cobrança, recebimento ou pagamento; emissão de carnês, fichas de compensação, impressos e documentos em geral.",
            "15.11" => "15.11 - Devolução de títulos, protesto de títulos, sustação de protesto, manutenção de títulos, reapresentação de títulos, e demais serviços a eles relacionados.",
            "15.12" => "15.12 - Custódia em geral, inclusive de títulos e valores mobiliários.",
            "15.13" => "15.13 - Serviços relacionados a operações de câmbio em geral, edição, alteração, prorrogação, cancelamento e baixa de contrato de câmbio; emissão de registro de exportação ou de crédito; cobrança ou depósito no exterior; emissão, fornecimento e cancelamento de cheques de viagem; fornecimento, transferência, cancelamento e demais serviços relativos a carta de crédito de importação, exportação e garantias recebidas; envio e recebimento de mensagens em geral relacionadas a operações de câmbio.",
            "15.14" => "15.14 - Fornecimento, emissão, reemissão, renovação e manutenção de cartão magnético, cartão de crédito, cartão de débito, cartão salário e congêneres.",
            "15.15" => "15.15 - Compensação de cheques e títulos quaisquer; serviços relacionados a depósito, inclusive depósito identificado, a saque de contas quaisquer, por qualquer meio ou processo, inclusive em terminais eletrônicos e de atendimento.",
            "15.16" => "15.16 - Emissão, reemissão, liquidação, alteração, cancelamento e baixa de ordens de pagamento, ordens de crédito e similares, por qualquer meio ou processo; serviços relacionados à transferência de valores, dados, fundos, pagamentos e similares, inclusive entre contas em geral.",
            "15.17" => "15.17 - Emissão, fornecimento, devolução, sustação, cancelamento e oposição de cheques quaisquer, avulso ou por talão.",
            "15.18" => "15.18 - Serviços relacionados a crédito imobiliário, avaliação e vistoria de imóvel ou obra, análise técnica e jurídica, emissão, reemissão, alteração, transferência e renegociação de contrato, emissão e reemissão do termo de quitação e demais serviços relacionados a crédito imobiliário.",
            //  16 => Serviços de transporte de natureza municipal.
            "16.01" => "16.01 - Serviços de transporte de natureza municipal.",
            //  17 => Serviços de apoio técnico, administrativo, jurídico, contábil, comercial e congêneres.
            "17.01" => "17.01 - Assessoria ou consultoria de qualquer natureza, não contida em outros itens desta lista; análise, exame, pesquisa, coleta, compilação e fornecimento de dados e informações de qualquer natureza, inclusive cadastro e similares.",
            "17.02" => "17.02 - Datilografia, digitação, estenografia, expediente, secretaria em geral, resposta audível, redação, edição, interpretação, revisão, tradução, apoio e infra-estrutura administrativa e congêneres.",
            "17.03" => "17.03 - Planejamento, coordenação, programação ou organização técnica, financeira ou administrativa.",
            "17.04" => "17.04 - Recrutamento, agenciamento, seleção e colocação de mão-de-obra.",
            "17.05" => "17.05 - Fornecimento de mão-de-obra, mesmo em caráter temporário, inclusive de empregados ou trabalhadores, avulsos ou temporários, contratados pelo prestador de serviço.",
            "17.06" => "17.06 - Propaganda e publicidade, inclusive promoção de vendas, planejamento de campanhas ou sistemas de publicidade, elaboração de desenhos, textos e demais materiais publicitários.",
            "17.07" => "17.07 - (VETADO) 17.08 => Franquia (franchising).",
            "17.09" => "17.09 - Perícias, laudos, exames técnicos e análises técnicas.",
            "17.10" => "17.10 - Planejamento, organização e administração de feiras, exposições, congressos e congêneres.",
            "17.11" => "17.11 - Organização de festas e recepções; bufê (exceto o fornecimento de alimentação e bebidas, que fica sujeito ao ICMS).",
            "17.12" => "17.12 - Administração em geral, inclusive de bens e negócios de terceiros.",
            "17.13" => "17.13 - Leilão e congêneres.",
            "17.14" => "17.14 - Advocacia.",
            "17.15" => "17.15 - Arbitragem de qualquer espécie, inclusive jurídica.",
            "17.16" => "17.16 - Auditoria.",
            "17.17" => "17.17 - Análise de Organização e Métodos.",
            "17.18" => "17.18 - Atuária e cálculos técnicos de qualquer natureza.",
            "17.19" => "17.19 - Contabilidade, inclusive serviços técnicos e auxiliares.",
            "17.20" => "17.20 - Consultoria e assessoria econômica ou financeira.",
            "17.21" => "17.21 - Estatística.",
            "17.22" => "17.22 - Cobrança em geral.",
            "17.23" => "17.23 - Assessoria, análise, avaliação, atendimento, consulta, cadastro, seleção, gerenciamento de informações, administração de contas a receber ou a pagar e em geral, relacionados a operações de faturização (factoring).",
            "17.24" => "17.24 - Apresentação de palestras, conferências, seminários e congêneres.",
            //  18 => Serviços de regulação de sinistros vinculados a contratos de seguros; inspeção e avaliação de riscos para cobertura de contratos de seguros; prevenção e gerência de riscos seguráveis e congêneres.
            "18.01" => "18.01 - Serviços de regulação de sinistros vinculados a contratos de seguros; inspeção e avaliação de riscos para cobertura de contratos de seguros; prevenção e gerência de riscos seguráveis e congêneres.",
            //  19 => Serviços de distribuição e venda de bilhetes e demais produtos de loteria, bingos, cartões, pules ou cupons de apostas, sorteios, prêmios, inclusive os decorrentes de títulos de capitalização e congêneres.
            "19.01" => "19.01 - Serviços de distribuição e venda de bilhetes e demais produtos de loteria, bingos, cartões, pules ou cupons de apostas, sorteios, prêmios, inclusive os decorrentes de títulos de capitalização e congêneres.",
            //  20 => Serviços portuários, aeroportuários, ferroportuários, de terminais rodoviários, ferroviários e metroviários.
            "20.01" => "20.01 - Serviços portuários, ferroportuários, utilização de porto, movimentação de passageiros, reboque de embarcações, rebocador escoteiro, atracação, desatracação, serviços de praticagem, capatazia, armazenagem de qualquer natureza, serviços acessórios, movimentação de mercadorias, serviços de apoio marítimo, de movimentação ao largo, serviços de armadores, estiva, conferência, logística e congêneres.",
            "20.02" => "20.02 - Serviços aeroportuários, utilização de aeroporto, movimentação de passageiros, armazenagem de qualquer natureza, capatazia, movimentação de aeronaves, serviços de apoio aeroportuários, serviços acessórios, movimentação de mercadorias, logística e congêneres.",
            "20.03" => "20.03 - Serviços de terminais rodoviários, ferroviários, metroviários, movimentação de passageiros, mercadorias, inclusive suas operações, logística e congêneres.",
            //  21 => Serviços de registros públicos, cartorários e notariais.
            "21.01" => "21.01 - Serviços de registros públicos, cartorários e notariais.",
            //  22 => Serviços de exploração de rodovia.
            "22.01" => "22.01 - Serviços de exploração de rodovia mediante cobrança de preço ou pedágio dos usuários, envolvendo execução de serviços de conservação, manutenção, melhoramentos para adequação de capacidade e segurança de trânsito, operação, monitoração, assistência aos usuários e outros serviços definidos em contratos, atos de concessão ou de permissão ou em normas oficiais.",
            //  23 => Serviços de programação e comunicação visual, desenho industrial e congêneres.
            "23.01" => "23.01 - Serviços de programação e comunicação visual, desenho industrial e congêneres.",
            //  24 => Serviços de chaveiros, confecção de carimbos, placas, sinalização visual, banners, adesivos e congêneres.
            "24.01" => "24.01 - Serviços de chaveiros, confecção de carimbos, placas, sinalização visual, banners, adesivos e congêneres.",
            //  25 - Serviços funerários.
            "25.01" => "25.01 - Funerais, inclusive fornecimento de caixão, urna ou esquifes; aluguel de capela; transporte do corpo cadavérico; fornecimento de flores, coroas e outros paramentos; desembaraço de certidão de óbito; fornecimento de véu, essa e outros adornos; embalsamento, embelezamento, conservação ou restauração de cadáveres.",
            "25.02" => "25.02 - Cremação de corpos e partes de corpos cadavéricos.",
            "25.03" => "25.03 - Planos ou convênio funerários.",
            "25.04" => "25.04 - Manutenção e conservação de jazigos e cemitérios.",
            //  26 => Serviços de coleta, remessa ou entrega de correspondências, documentos, objetos, bens ou valores, inclusive pelos correios e suas agências franqueadas; courrier e congêneres.
            "26.01" => "26.01 - Serviços de coleta, remessa ou entrega de correspondências, documentos, objetos, bens ou valores, inclusive pelos correios e suas agências franqueadas; courrier e congêneres.",
            //  27 => Serviços de assistência social.
            "27.01" => "27.01 - Serviços de assistência social.",
            //  28 => Serviços de avaliação de bens e serviços de qualquer natureza.
            "28.01" => "28.01 - Serviços de avaliação de bens e serviços de qualquer natureza.",
            //  29 => Serviços de biblioteconomia.
            "29.01" => "29.01 - Serviços de biblioteconomia.",
            //  30 => Serviços de biologia, biotecnologia e química.
            "30.01" => "30.01 - Serviços de biologia, biotecnologia e química.",
            //  31 => Serviços técnicos em edificações, eletrônica, eletrotécnica, mecânica, telecomunicações e congêneres.
            "31.01" => "31.01 - Serviços técnicos em edificações, eletrônica, eletrotécnica, mecânica, telecomunicações e congêneres.",
            //  32 => Serviços de desenhos técnicos.
            "32.01" => "32.01 - Serviços de desenhos técnicos.",
            //  33 => Serviços de desembaraço aduaneiro, comissários, despachantes e congêneres.
            "33.01" => "33.01 - Serviços de desembaraço aduaneiro, comissários, despachantes e congêneres.",
            //  34 => Serviços de investigações particulares, detetives e congêneres.
            "34.01" => "34.01 - Serviços de investigações particulares, detetives e congêneres.",
            //  35 => Serviços de reportagem, assessoria de imprensa, jornalismo e relações públicas.
            "35.01" => "35.01 - Serviços de reportagem, assessoria de imprensa, jornalismo e relações públicas.",
            //  36 => Serviços de meteorologia.
            "36.01" => "36.01 - Serviços de meteorologia.",
            //  37 => Serviços de artistas, atletas, modelos e manequins.
            "37.01" => "37.01 - Serviços de artistas, atletas, modelos e manequins.",
            //  38 => Serviços de museologia.
            "38.01" => "38.01 - Serviços de museologia.",
            //  39 => Serviços de ourivesaria e lapidação.
            "39.01" => "39.01 - Serviços de ourivesaria e lapidação (quando o material for fornecido pelo tomador do serviço).",
            //  40 => Serviços relativos a obras de arte sob encomenda.
            "40.01" => "40.01 - Obras de arte sob encomenda.",
        ];

        return $lst;
    }

    private function createOrigens($produto, $origens)
    {
        $toBeInserted = [];
        foreach ($origens as $origem) {
            $orig = [];
            $orig["produto_id"] = $produto->id;
            $orig["indimport"] = $origem[1];
            $orig["cuforig"] = $origem[3];
            $orig["porig"] = insertPercentualOracle($origem[5]);
            $orig["created_at"] = now();
            $orig["updated_at"] = now();

            array_push($toBeInserted, $orig);
        }

        Produtoorigem::insert($toBeInserted);
    }
}
