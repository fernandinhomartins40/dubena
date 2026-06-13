@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Mala Direta</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Aniversariantes</a></li>
                            <li><a href="#tab_2" data-toggle="tab">Endereço</a></li>
                            <li><a href="#tab_3" data-toggle="tab">Não compram a X dias</a></li>
                        </ul>
                        <div class="tab-content">
                            @include('reports.clientes.maladireta.tab_1')
                            @include('reports.clientes.maladireta.tab_2')
                            @include('reports.clientes.maladireta.tab_3')
                            <div class="row">
                                <div id="tabCadastro" class="col-sm-12">
                                    <div class="box-body">
                                        <div class="form-group crud_space margTop_15" style="margin-left: 3%">
                                            <div class="col-sm-3 col-sm-offset-5">
                                                <button disabled id="btnFiltro" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Clientes"><span class="fa fa-search fa-lg"></span></button>
                                                <button disabled id="btnMail" type="button" class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Enviar E-mail"><span class="fa fa-envelope fa-lg"></span></button>
                                                <button disabled type="button" id='btnCsv' class="btn btn-excel btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Exportar CSV"><span class="fa fa-file-text-o fa-lg" aria-hidden="true"></span></button>
                                                <button disabled id="btnEtiquetas" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Imprimir Etiquetas"><span class="fa fa-print fa-lg"></span></button>
                                                <button disabled type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                            </div>
                                        </div> 
                                        <div class="form-group crud_space margTop_15">
                                            <div class="col-sm-12" style="margin-left: 1%">
                                                <table id="tblClientes" class="table table-bordered table-striped table-hover table-condensed no-select">
                                                    <thead>
                                                        <tr>
                                                            <th>Cód.</th>
                                                            <th>Cliente</th>
                                                            <th>Endereço</th>
                                                            <th>Nascimento</th>
                                                            <th>Celular</th>
                                                            <th>E-Mail</th>
                                                            <th>Setor</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if(isset($clientes))
                                                            @foreach($clientes as $cliente)
                                                                <tr>
                                                                    <td>{{$cliente->id}}</td>
                                                                    <td>{{$cliente->nome}}</td>
                                                                    <td>{{$cliente->endereco}}</td>
                                                                    <td>{{requestDataOracle($cliente->datanascimento, false)}}</td>
                                                                    <td>{{$cliente->telefone}}</td>
                                                                    <td>{{$cliente->email}}</td>
                                                                    <td>{{$cliente->setor}}</td>
                                                                </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            <i>
                                                <div class="col-sm-2 margTop_15">
                                                    <div>
                                                        @if(isset($clientes))
                                                            {{count($clientes)}} registros encontrados.
                                                        @endif    
                                                    </div>
                                                </div>
                                                <div class="col-sm-2 col-sm-offset-7 fright margTop_15">
                                                    <div id="totalSelecionados">
                                                        0 de 90 clientes selecionados.
                                                    </div>    
                                                </div>
                                            </i>
                                        </div>
                                        <div class="form-group crud_space">
                                            <div class="col-sm-4 margTop_25">
                                                 <i>
                                                    Para selecionar mais de um cliente, pressione a tecla "Ctrl". <br /> 
                                                    Para selecionar vários de uma vez, pressione a tecla "Shift"
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /.panel-default -->
            </div>
        </div>
    </div>
</div>

@include('general.modal_report_iframe')

{{ Form::open(['id'=>'fmCsv',  'class' => 'form-horizontal', 'files' => true]) }}
<input type="hidden" id="clientes" name="clientes">
{{Form::close()}}
@include('general.modal_report_iframe')
@include('reports.clientes.maladireta.js')
@endsection