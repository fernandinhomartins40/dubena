<!-- form start -->
<div class="row">
    <div id="tabCadastro" class="col-sm-12">
        <div class="box-body">
            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
            <div class="form-group crud_space">
                {{ Form::label('tipo', 'Tipo de filtro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('tipo',[0=>"Produto",1=>"Segmento",2=>"Setor"],null, 
                    ['id' => 'tipo','class'=>'form-control input-sm selectChosen', 'onchange' => 'mudaTipoFiltro($(this).val())']) }}
                </div>
                {{ Form::label('produto_id', 'Produto:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('produto_id',$produtos,null,['id' => 'produto_id','class'=>'form-control input-sm selectChosen']) }}
                </div>
                {{ Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('segmento_id',$segmentos,null,['id' => 'segmento_id_tab_2','class'=>'form-control input-sm selectChosen']) }}
                </div>
                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::select('setor_id',$setores,null,['id' => 'setor_id','class'=>'form-control input-sm selectChosen']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="input-group generalDatePicker">
                        {{Form::text('datainicio', null, ['id' => 'datainicio_tab_2', 'class' => 'input-sm form-control generalDatePicker'])}}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar"></span>
                        </span>
                    </div>
                </div>  
                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    <div class="input-group generalDatePicker">
                        {{Form::text('datafim', null, (['id' => 'datafim_tab_2', 'class' => 'input-sm form-control generalDatePicker']))}}
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-calendar"></span>
                        </span>
                    </div>
                </div>
                <div class="col-sm-2 col-sm-offset-1">
                    <button type="button" id='btnLimpar-tab_2' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                    <!-- <button type="button" id='btnGerarPDF' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
                     --><button id="btnIframe-tab_2" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                </div>
            </div> 
            {{ Form::close() }}
        </div>
    </div>
</div>
<script type="text/javascript">
    $("#btnLimpar-tab_2").on('click', function() {
        $("#tab_2 > .selectChosen").val('');
        $("#tab_2 > input.generalDatePicker").val(dataAtual());
        $("#tab_2 > #tipo").val('0');
        mudaTipoFiltro(0);
        $(".selectChosen").trigger('chosen:updated');
    });
    $("#btnIframe-tab_2").on('click', function () {
        setUrlTab_2(function (url) {
            $("#popup_relatorio").modal('show');
            $("#iFrameReport").attr('src',url);
        }, false)
    });
    function setUrlTab_2(callback, pdf) {
        if(isEmpty($("#datainicio_tab_2").val())) {
            bootbox.alert('O campo Data Início é obrigatório');
            return;
        }
        if(isEmpty($("#datafim_tab_2").val())) {
            bootbox.alert('O campo Data Fim é obrigatório');
            return;
        }
        var url = root + '/report.segSetorProd';
        if(typeof pdf != 'undefined' && pdf)
            url += '.pdf';
        url += '?produto_id=:produto_id&segmento_id=:segmento_id&setor_id=:setor_id&tipo=:tipo&datainicio=:datainicio&datafim=:datafim';

        url = url.replace(':produto_id', $("#produto_id").val());
        url = url.replace(':segmento_id', $("#segmento_id_tab_2").val());
        url = url.replace(':setor_id', $("#setor_id").val());
        url = url.replace(':tipo', $("#tipo").val());
        url = url.replace(':datainicio', $("#datainicio_tab_2").val());
        url = url.replace(':datafim', $("#datafim_tab_2").val());


        callback(url);
    }
    function mudaTipoFiltro(tipo) {
        if(tipo == 0) {
            $("#produto_id").prop('disabled', false);
            $("#segmento_id_tab_2, #setor_id").prop('disabled', true);
            $("#produto_id_chosen").addClass('chosen-container-active');
        } else if (tipo == 1) {
            $("#segmento_id_tab_2").prop('disabled', false);
            $("#produto_id, #setor_id").prop('disabled', true);
            $("#segmento_id_tab_2_chosen").addClass('chosen-container-active');
        } else {
            $("#setor_id").prop('disabled', false);
            $("#segmento_id_tab_2, #produto_id").prop('disabled', true);
            $("#setor_id_chosen").addClass('chosen-container-active');
        }
        $(".selectChosen").trigger('chosen:updated');
    }
    $(document).ready(function () {
        mudaTipoFiltro(0);
    });
</script>