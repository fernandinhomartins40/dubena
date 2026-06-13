@extends('layouts.mainmenu') 
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            @if(isset($credito))
                {{ Form::model($credito, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('spedcreditos.update', $credito->id))) }}
            @else 
                {{ Form::open(['id'=>'fmCadastro','route' => 'spedcreditos.store', 'class' => 'form-horizontal', 'files' => true]) }} 
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Sped Contribuições - Créditos</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Informações Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="col-md-12">
                                                <div class="form-group crud_space">
                                                    <!-- Campo 01 -->
                                                    {{ Form::label('registro', 'Registro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::select('registro', $registros, null, ['id'=>'registro', 'class' => 'form-control selectChosen']) }}
                                                    </div>
                                                    <!-- Campo 02 -->
                                                    {{ Form::label('per_apu_cred', 'Apuração:', ['class' => 'input-sm control-label col-sm-1']) }}
                                                    <div class="col-sm-2">
                                                        <div class="input-group mesAno">
                                                            {{ Form::text('per_apu_cred', null, ['id' => 'per_apu_cred', 'class' => 'input-sm form-control mesAno']) }}
                                                            <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <!-- Campo 03 -->
                                                    {{ Form::label('orig_cred', 'Origem:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        {{ Form::select('orig_cred', $origem, null, ['id'=>'orig_cred', 'class' => 'form-control selectChosen']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <!-- Campo 04 -->
                                                    {{ Form::label('cnpj_suc', 'CNPJ Cedente:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::text('cnpj_suc', null, ['id'=>'cnpj_suc', 'class' => 'form-control input-sm cnpj', 'readonly']) }}
                                                    </div>
                                                    <!-- Campo 05 -->
                                                    {{ Form::label('cod_cred', 'Tipo Crédito:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-6">
                                                        {{ Form::select('cod_cred', $cred, null, ['id'=>'cod_cred', 'class' => 'form-control selectChosen','readonly']) }}
                                                    </div>
                                                </div>
                                                <br />
                                                <div class="form-group crud_space">
                                                    <!-- Campo 06 -->
                                                    {{ Form::label('vl_cred_apu', 'Valor Total do Crédito (EFD ou DACON):', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_apu', null, ['id'=>'vl_cred_apu', 'class' => 'form-control input-sm dinheiro camp08']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <!-- Campo 07 -->
                                                    {{ Form::label('vl_cred_ext_apu', 'Valor de Crédito Extemporâneo Apurado:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_ext_apu', null, ['id'=>'vl_cred_ext_apu', 'class' => 'form-control input-sm dinheiro camp08']) }}
                                                    </div>
                                                    <!-- Campo 08 -->
                                                    {{ Form::label('vl_tot_cred_apu', 'Valor Total do Crédito Apurado:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_tot_cred_apu', null, ['id'=>'vl_tot_cred_apu', 'class' => 'form-control input-sm dinheiro','readonly']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <!-- Campo 09 -->
                                                    {{ Form::label('vl_cred_desc_pa_ant', 'Valor do Crédito Mediante Desconto:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_desc_pa_ant', null, ['id'=>'vl_cred_desc_pa_ant', 'class' => 'form-control input-sm dinheiro camp12']) }}
                                                    </div>
                                                    <!-- Campo 10 -->
                                                    {{ Form::label('vl_cred_per_pa_ant', 'Valor do Crédito Mediante Pedido de Ressarcimento:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_per_pa_ant', null, ['id'=>'vl_cred_per_pa_ant', 'class' => 'form-control input-sm dinheiro camp12','readonly']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <!-- Campo 11 -->
                                                    {{ Form::label('vl_cred_dcomp_pa_ant', 'Valor do Crédito Mediante Declaração de Compensação:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_dcomp_pa_ant', null, ['id'=>'vl_cred_dcomp_pa_ant', 'class' => 'form-control input-sm dinheiro camp12', 'readonly']) }}
                                                    </div>
                                                    <!-- Campo 12 -->
                                                    {{ Form::label('sd_cred_disp_efd', 'Saldo Crédito Disponivel (P. Escrituração):', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('sd_cred_disp_efd', null, ['id'=>'sd_cred_disp_efd', 'class' => 'form-control input-sm','readonly']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <!-- Campo 13 -->
                                                    {{ Form::label('vl_cred_desc_efd', 'Valor do Crédito Descontado (P. Escrituração):', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_desc_efd', null, ['id'=>'vl_cred_desc_efd', 'class' => 'form-control input-sm dinheiro camp18']) }}
                                                    </div>
                                                    <!-- Campo 14 -->
                                                    {{ Form::label('vl_cred_per_efd', 'Valor do Crédito do Pedido de Ressarcimento (P. Escrituração):', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_per_efd', null, ['id'=>'vl_cred_per_efd', 'class' => 'form-control input-sm dinheiro camp18', 'readonly']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <!-- Campo 15 -->
                                                    {{ Form::label('vl_cred_dcomp_efd', 'Valor do Crédito Mediante Declaração de Compensação (P. Escrituração):', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_dcomp_efd', null, ['id'=>'vl_cred_dcomp_efd', 'class' => 'form-control input-sm dinheiro camp18', 'readonly']) }}
                                                    </div>
                                                    <!-- Campo 16 -->
                                                    {{ Form::label('vl_cred_trans', 'Valor do Crédito Transferido em Cisão, Fusão ou Incorporação:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_trans', null, ['id'=>'vl_cred_trans', 'class' => 'form-control input-sm dinheiro camp18']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <!-- Campo 17 -->
                                                    {{ Form::label('vl_cred_out', 'Valor do Crédito Utilizado Outras Formas:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('vl_cred_out', null, ['id'=>'vl_cred_out', 'class' => 'form-control input-sm dinheiro camp18']) }}
                                                    </div>
                                                    <!-- Campo 18 -->
                                                    {{ Form::label('sld_cred_fim', 'Saldo de Crédito a Utilizar (P. Apuração):', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('sld_cred_fim', null, ['id'=>'sld_cred_fim', 'class' => 'form-control input-sm','readonly']) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['id'=>'btnGravar','class' => 'btn btn-nw-registro']) !!}
                                <a type="button" href="{{url('spedcreditos')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
            {{Form::close()}}
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/spedcredito.js')}}"></script>
<script type="text/javascript">
    @if(isset($show))
        desativarInputs();
    @endif
</script>
@endsection