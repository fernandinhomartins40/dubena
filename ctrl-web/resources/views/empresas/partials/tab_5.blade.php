
<!-- form start -->
<div class="row">
    <div id="tabCadastro" class="col-md-10">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('contratonome', 'Representante Legal:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-5">
                    {{ Form::text('contratonome',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('contratocpf', 'CPF:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('contratocpf',null,['class'=>'form-control input-sm cpf','maxlength' => 14]) }}
                </div>
                {{ Form::label('contratorg', 'RG:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('contratorg',null,['class'=>'form-control input-sm rg','maxlength' => 18]) }}
                </div>
            </div>
        </div>
    </div><!-- /.box-body -->
</div>