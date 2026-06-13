<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Documento;
use App\Documentotipo;
use App\Colaborador;
use App\Documentoversao;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller {

    protected $msgsValidacao = array(
        'descricao.required' => 'O campo Descrição é obrigatório.',
        'documentotipo_id.required' => 'O campo Tipo de Documento é obrigatório.',
        'colaborador_id.required' => 'O campo Colaborador é obrigatório.',
    );

    protected $msgsValidacaoVersao = array(
        'descricaoversao.required' => 'O campo Descrição é obrigatório.',
        'numeroversao.required' => 'O campo Número da Versão é obrigatório.',
        'emissaoversao.required' => 'O campo Emissão é obrigatório.',
        'vencimentoversao.required' => 'O campo Vencimento é obrigatório.',
    );

    public function index(Documento $vei) {
        $this->authorize('view',$vei);
        $documentos = Documento::where([
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->get();
        return view('documentos.documentos', compact('documentos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Documento $vei) {
        $this->authorize('create',$vei);
        $documentotipos = Documentotipo::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione','');
        $colaboradors = Colaborador::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('nome')->pluck('nome', 'id')->prepend('Selecione','');

        return view('documentos.documento_form', compact('documentotipos', 'colaboradors'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Documento $vei) {
        $this->authorize('create',$vei);
        $this->validate($request, [
            'descricao' => 'required',
            'colaborador_id' => 'required',
            'documentotipo_id' => 'required',
            ], $this->msgsValidacao);
        $data = $request->only('grupo_id', 'empresa_id', 'documentotipo_id',  'colaborador_id', 'descricao');

        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;

        DB::beginTransaction();
        try {
            $documento = Documento::create($data);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/documento/create')
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return \Redirect::route('documento.index')->withMessageSuccess('Documento cadastrado com sucesso!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Documento $vei) {
        $this->authorize('view',$vei);
        $documento = Documento::find($id);
        $this->authorize('igualdade',$documento);

        $documentotipos = Documentotipo::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $colaboradors = Colaborador::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('nome')->pluck('nome', 'id');
        $show = true;
        return view('documentos.documento_form', compact('documento', 'show', 'documentotipos', 'colaboradors'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Documento $vei) {
        $this->authorize('update',$vei);
        $documento = Documento::find($id);
        $this->authorize('igualdade',$documento);
        $documentotipos = Documentotipo::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione','');;
        $colaboradors = Colaborador::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('nome')->pluck('nome', 'id');
        return view('documentos.documento_form', compact('documento', 'documentotipos', 'colaboradors'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Documento $vei) {
        $this->authorize('update',$vei);
        $documento = Documento::findOrFail($id);
        $this->authorize('igualdade',$documento);
        $this->validate($request, [
            'descricao' => 'required',
            'colaborador_id' => 'required',
            'documentotipo_id' => 'required',
            ], $this->msgsValidacao);
        $data = $request->only('grupo_id', 'empresa_id', 'documentotipo_id', 'colaborador_id', 'descricao');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        DB::beginTransaction();
        try {
            $documento->update($data);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/documento/' . $id. "/edit")
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return \Redirect::route('documento.index')->withMessageSuccess('Documento atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Documento $vei) {
        $this->authorize('delete',$vei);
        $documento = Documento::find($id);
        $this->authorize('igualdade',$documento);
        DB::beginTransaction();
        try {
            Documento::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    public function addEditVersao(Request $request){
        try {
            $data = $request->all();
            if($data['operacao']=='ADD'){
                $this->validate($request, [
                    'numeroversao'     => 'required',
                    'emissaoversao'    => 'required',
                    'vencimentoversao' => 'required'
                ], $this->msgsValidacaoVersao);
                throwIf(!$request->hasFile('file'), 'Arquivo da versão não enviado corretamente.');
                $documento = Documento::find($data['documento_id']);
                throwIf(!$documento, 'Documento não encontrado');
                $file = $request->file('file');
                DB::beginTransaction();
                if($data['desativarversao']=="true"){
                    foreach($documento->versoes as $vers){
                        $vers->ativo = false;
                        $vers->save();
                    }
                }
                $versao = new Documentoversao();
                $versao->descricao = $data['descricaoversao'];
                $versao->numeroversao = $data['numeroversao'];
                $versao->documento_id = $data['documento_id'];
                $versao->dataemissao = insertDataOracle($data['emissaoversao']);
                $versao->datavencimento = insertDataOracle($data['vencimentoversao']);
                $versao->ativo = $data['ativoversao']=="true";
                $versao->nomearquivo = $file->getClientOriginalName();
                $versao->save();
                $fileName = $versao->id . '.' . $file->getClientOriginalExtension();
                $directory = 'documentoversao/'; 
                 if (!Storage::disk('local')->exists($directory)) {
                    Storage::disk('local')->makeDirectory($directory);
                }
                Storage::disk('local')->putFileAs(
                    $directory, 
                    $file,
                    $fileName
                );
                DB::commit();
                $documento->refresh();
                return responseSuccess($documento->versoes);
            } elseif($data['operacao']=='DEL'){
                DB::beginTransaction();
                $versao = Documentoversao::find($data['versao_id']);
                throwIf(!$versao, 'Versão não encontrada');
                throwIf($versao->documento_id != $data['documento_id'], 'Documento não encontrado');
                $versao->delete();
                DB::commit();
                $fileName = $versao->id . '.' . pathinfo($versao->nomearquivo)["extension"];
                $directory = 'documentoversao/'.$fileName;
                if(Storage::disk('local')->exists($directory)){
                    Storage::disk('local')->delete($directory);
                }
                $documento = Documento::find($data['documento_id']);
                return responseSuccess($documento->versoes);
            } elseif($data['operacao']=='UPD'){
                $this->validate($request, [
                    'numeroversao'     => 'required',
                    'emissaoversao'    => 'required',
                    'vencimentoversao' => 'required'
                ], $this->msgsValidacaoVersao);

                $documento = Documento::find($data['documento_id']);
                throwIf(!$documento, 'Documento não encontrado');
                DB::beginTransaction();
                $file = null;
                if($request->hasFile('file')){
                    $file = $request->file('file');
                }
                $versao = Documentoversao::find($data['versao_id']);
                throwIf(!$versao, 'Versão não encontrada');
                $versao->descricao = $data['descricaoversao'];
                $versao->numeroversao = $data['numeroversao'];
                $versao->documento_id = $data['documento_id'];
                $versao->dataemissao = insertDataOracle($data['emissaoversao']);
                $versao->datavencimento = insertDataOracle($data['vencimentoversao']);
                $versao->ativo = $data['ativoversao']=="true";
                if($file){
                    $fileName = $versao->id . '.' . pathinfo($versao->nomearquivo)["extension"];
                    $directory = 'documentoversao/'.$fileName;
                    if(Storage::disk('local')->exists($directory)){
                        Storage::disk('local')->delete($directory);
                    }
                    $versao->nomearquivo = $file->getClientOriginalName();
                }
                $versao->save();
                if($request->hasFile('file')){
                    $fileName = $versao->id . '.' . $file->getClientOriginalExtension();
                    $directory = 'documentoversao/'; 
                    if (!Storage::disk('local')->exists($directory)) {
                        Storage::disk('local')->makeDirectory($directory);
                    }
                    Storage::disk('local')->putFileAs(
                        $directory, 
                        $file,
                        $fileName
                    );
                }
                DB::commit();
                $documento->refresh();
                return responseSuccess($documento->versoes);
            }
         } catch (\Exception $e) {
            DB::rollback();
            return responseError('Erro: ' . $e->getMessage());
        }
    }

    public function downloadVersao($id){
        $versao_id = $id; // $request->only('download_id')['download_id'];
        $versao = Documentoversao::find($versao_id);
        throwif(!$versao, 'Versão não encontrada');
        $fileName = $versao->id . '.' . pathinfo($versao->nomearquivo)["extension"];
        $directory = 'documentoversao/'.$fileName;
        if(Storage::disk('local')->exists($directory)){
            $contentType = Storage::mimeType($directory);
            $headers = [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $versao->nomearquivo . '"',
            ];
            return response()->download(storage_path('app/' . $directory), $versao->nomearquivo, $headers);
        } else {
            throw new \Exception('Arquivo não encontrado');
        }
    }
}
