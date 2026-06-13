<?php

namespace App\Http\Controllers;

use Symfony\Component\Finder\Finder;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\CarbonCustom as Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Session;
use App\Processors\EstoqueProcessor;
use App\Estoquesetor;
use App\Estoquesetorhistorico;
use App\Estoquefechamento;
use DB;

class TesteestoqueController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $estoquesetor = Estoquesetor::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->get();
        return view('testes.estoque', compact('estoquesetor'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->only('operacao');
        $ret = "";
        switch ($data["operacao"]) {
            case 'movimentarEstoque':
                $ret = $this->movimentarEstoque();
                break;
            case 'fecharEstoque':
                $ret = $this->fecharEstoque();
                break;
            case 'abrirEstoque':
                $ret = $this->abrirEstoque();
                break;
        }
        return 'Processou|' . $ret;
    }

    private function movimentarEstoque()
    {
        $estoqueProcessor = new EstoqueProcessor();
        try {
            $estoquesetorhistoricos = [];

            $estoquesetorhistorico = new Estoquesetorhistorico();
            $estoquesetorhistorico->user_id = \Auth::user()->id;
            $estoquesetorhistorico->setor_id = 1;
            $estoquesetorhistorico->produto_id = 1;
            $estoquesetorhistorico->movimentacao = 'ENTRADA';
            $estoquesetorhistorico->quantidade = 10;
            $estoquesetorhistorico->motivo = 'Compra Teste';
            $estoquesetorhistorico->datahora = Carbon::now();
            $estoquesetorhistorico->datahoracompetencia = Carbon::now();
            $estoquesetorhistorico->entidade = 'Nfrecebida'; //nome exato da model 
            $estoquesetorhistorico->entidade_id = rand(1, 100); //chave primária 
            $estoquesetorhistorico->grupo_id = Session::get('empresa_padrao')->grupo_id;
            $estoquesetorhistorico->empresa_id = Session::get('empresa_padrao')->id;
            array_push($estoquesetorhistoricos, $estoquesetorhistorico);

            $estoquesetorhistorico = new Estoquesetorhistorico();
            $estoquesetorhistorico->user_id = \Auth::user()->id;
            $estoquesetorhistorico->setor_id = 2;
            $estoquesetorhistorico->produto_id = 1;
            $estoquesetorhistorico->movimentacao = 'ENTRADA';
            $estoquesetorhistorico->quantidade = 10;
            $estoquesetorhistorico->motivo = 'Compra Vasilhame Teste';
            $estoquesetorhistorico->datahora = Carbon::now();
            $estoquesetorhistorico->datahoracompetencia = Carbon::now();
            $estoquesetorhistorico->entidade = 'Nfrecebida';
            $estoquesetorhistorico->entidade_id = rand(1, 100);
            $estoquesetorhistorico->grupo_id = Session::get('empresa_padrao')->grupo_id;
            $estoquesetorhistorico->empresa_id = Session::get('empresa_padrao')->id;
            array_push($estoquesetorhistoricos, $estoquesetorhistorico);

            $estoquesetorhistorico = new Estoquesetorhistorico();
            $estoquesetorhistorico->user_id = \Auth::user()->id;
            $estoquesetorhistorico->setor_id = 1;
            $estoquesetorhistorico->produto_id = 1;
            $estoquesetorhistorico->movimentacao = 'SAIDA';
            $estoquesetorhistorico->quantidade = 2;
            $estoquesetorhistorico->motivo = 'Venda Teste';
            $estoquesetorhistorico->datahora = Carbon::now();
            $estoquesetorhistorico->datahoracompetencia = Carbon::now();
            $estoquesetorhistorico->entidade = 'Nfrecebida'; //nome exato da model 
            $estoquesetorhistorico->entidade_id = rand(1, 100); //chave primária 
            $estoquesetorhistorico->grupo_id = Session::get('empresa_padrao')->grupo_id;
            $estoquesetorhistorico->empresa_id = Session::get('empresa_padrao')->id;
            array_push($estoquesetorhistoricos, $estoquesetorhistorico);

            if ($estoqueProcessor->movimentarEstoque($estoquesetorhistoricos)) {
                
            } else {
                $erros = "";
                $getErros = $estoqueProcessor->getErrors();
                foreach ($getErros as $erro) {
                    $erros .= $erro;
                }
                return $erros;
            }
        } catch (\Exception $e) {
            $erros = "";
            foreach ($estoqueProcessor->getErrors() as $erro) {
                $erros .= $erro;
            }
            return $erros;
        }
    }

    private function fecharEstoque()
    {
        $estoqueProcessor = new EstoqueProcessor();
        try {
            $estoqueFechamento = new Estoquefechamento;

            $estoqueFechamento->grupo_id = Session::get('empresa_padrao')->grupo_id;
            $estoqueFechamento->empresa_id = Session::get('empresa_padrao')->id;
            $estoqueFechamento->fechamentomensal = false;
            $estoqueFechamento->datahorafechamento = Carbon::now();
//            $estoqueFechamento->reaberto = false;
//            $estoqueFechamento->reabertouser_id = \Auth::user()->id;
//            $estoqueFechamento->reabertomotivo = "";

            if ($estoqueProcessor->fecharEstoque($estoqueFechamento)) {
                
            } else {
                $erros = "";
                $getErros = $estoqueProcessor->getErrors();
                foreach ($getErros as $erro) {
                    $erros .= $erro;
                }
                return $erros;
            }
        } catch (\Exception $e) {
            $erros = "";
            foreach ($estoqueProcessor->getErrors() as $erro) {
                $erros .= $erro;
            }
            return $erros;
        }
    }

    private function abrirEstoque()
    {
        $estoqueProcessor = new EstoqueProcessor();
        try {
            $estoqueFechamento = new Estoquefechamento;

            $estoqueFechamento->grupo_id = Session::get('empresa_padrao')->grupo_id;
            $estoqueFechamento->empresa_id = Session::get('empresa_padrao')->id;
            $estoqueFechamento->fechamentomensal = false;
            $estoqueFechamento->datahorafechamento = Carbon::create(2017, 2, 23, 7, 0, 0);
            $estoqueFechamento->reaberto = true;
            $estoqueFechamento->reabertouser_id = \Auth::user()->id;
            $estoqueFechamento->reabertomotivo = "Testes de abrir estoque.";

            if ($estoqueProcessor->abrirEstoque($estoqueFechamento)) {
                
            } else {
                $erros = "";
                $getErros = $estoqueProcessor->getErrors();
                foreach ($getErros as $erro) {
                    $erros .= $erro;
                }
                return $erros;
            }
        } catch (\Exception $e) {
            $erros = "";
            foreach ($estoqueProcessor->getErrors() as $erro) {
                $erros .= $erro;
            }
            return $erros;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function uml()
    {
        $graph = new \Fhaculty\Graph\Graph();
        $builder = new \Fhaculty\Graph\Uml\ClassDiagramBuilder($graph);
        $exclude = [
            // 'Auth',
            // 'Auth/Console',
            'Auth/Console/stubs',
            // 'Auth/Reminders',
            'Cache',
            // 'Cache/Console',
            // 'Config',
            'Console',
            // 'Container',
            // 'Cookie',
            // 'Database',
            // 'Database/Capsule',
            // 'Database/Connectors',
            // 'Database/Console',
            // 'Database/Console/Migrations',
            // 'Database/Eloquent',
            'Database/Eloquent/Relations',
            // 'Database/Migrations',
            'Database/Migrations/stubs',
            // 'Database/Query',
            // 'Database/Query/Grammars',
            'Database/Query/Processors',
            // 'Database/Schema',
            // 'Database/Schema/Grammars',
            // 'Encryption',
            // 'Events',
            // 'Exception',
            'Exception/resources',
            // 'Filesystem',
            'Foundation',
            // 'Foundation/Console',
            // 'Foundation/Console/Optimize',
            'Foundation/Console/stubs',
            // 'Foundation/Providers',
            // 'Foundation/Testing',
            // 'Hashing',
            // 'Html',
            // 'Http',
            // 'Log',
            // 'Mail',
            'Pagination',
            // 'Pagination/views',
            'Queue',
            // 'Queue/Connectors',
            // 'Queue/Console',
            'Queue/Console/stubs',
            // 'Queue/Failed',
            // 'Queue/Jobs',
            // 'Redis',
            // 'Remote',
            // 'Routing',
            // 'Routing/Console',
            // 'Routing/Generators',
            // 'Routing/Generators/stubs',
            // 'Routing/Matching',
            // 'Session',
            // 'Session/Console',
            // 'Session/Console/stubs',
            'Support',
            // 'Support/Contracts',
            // 'Support/Facades',
            // 'Translation',
            // 'Validation',
            // 'View',
            'View/Compilers',
            'View/Engines',
            // 'Workbench',
            // 'Workbench/Console',
            'Workbench/stubs'
        ];
        
        $finder = new Finder();
        $finder
                ->files()
                ->in(base_path('vendor' .DIRECTORY_SEPARATOR. 'laravel' .DIRECTORY_SEPARATOR. 'framework' .DIRECTORY_SEPARATOR. 'src' .DIRECTORY_SEPARATOR. 'Illuminate' .DIRECTORY_SEPARATOR. 'Routing'))
                ->exclude($exclude)
                ->name('*.php')
                ->notName('*Interface.php')
                ->sortByName();
        foreach ($finder as $key => $file) {
            $pathname = preg_replace("/\.php/", "", $file->getRelativePathname());
            $pathname = preg_replace("/\//", "\\", $pathname);
            $builder->createVertexClass('Illuminate\\Routing\\' . $pathname);
        }
        echo $builder->getNumberOfComponents();
        $graphviz = new \Fhaculty\Graph\GraphViz($graph);
        $graphviz->setFormat('svg');
        $graphviz->createImageFile();
        $graphviz->display();
    }

}
