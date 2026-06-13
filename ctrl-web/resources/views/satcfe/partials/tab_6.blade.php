<div class="row">
    <div id="tabCadastro" class="col-md-10 col-md-offset-1">
        <div class="box-body">
            <div class="form-group crud_space">
                {!! Form::label('rateiocentrocusto_id', 'Centro Custo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-4">
                    {{Form::hidden('rateiocentrocusto_id',null, ['id'=>'rateiocentrocusto_id'])}}
                    <div class="input-group">
                        {!! Form::text('rateiocentrocusto_descricao','',['id'=>'rateiocentrocusto_descricao', 'class'=>'form-control input-sm', 'readonly']) !!}
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnRateioCcusto">Mudar</button>
                        </span>
                    </div>
                </div>
                {!! Form::label('rateioplanoconta_id', 'Plano Conta:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-4">
                    {{Form::hidden('rateioplanoconta_id',null, ['id'=>'rateioplanoconta_id'])}}
                    <div class="input-group">
                        {!! Form::text('rateioplanoconta_descricao','',['id'=>'rateioplanoconta_descricao', 'class'=>'form-control input-sm', 'readonly']) !!}
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnRateioPconta" >Mudar</button>
                        </span>
                    </div>
                </div>
            </div>
            <div class="form-group crud_space">
                {!! Form::label('rateiovalor', 'Valor:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('rateiovalor',null,['class'=>'form-control input-sm dinheiro']) !!}
                </div>
                <button type="button" class='btn btn-xs btn-nw-buscas' id="addRateio">Adicionar</button>
            </div>
            <div class="form-group crud_space">
                <div class="col-md-10 col-md-offset-1">
                    {{Form::hidden('rateios',"", ['id'=>'rateios'])}}
                    {{Form::hidden('user_change_rateio',"0", ['id'=>'user_change_rateio'])}}
                    <table id="tblRateios" class="table table-bordered table-hover table-condensed">
                        <thead>
                            <tr>
                                <th>Centro de Custo ID</th><!--0-->
                                <th>Chave CC</th><!--1-->
                                <th>Centro de Custo</th><!--2-->
                                <th>Plano de Conta ID</th><!--3-->
                                <th>Chave PC</th><!--4-->
                                <th>Plano de Conta</th><!--5-->
                                <th>Valor</th><!--6-->
                                <th>Operação</th><!--7-->
                            </tr>
                        </thead>
                        <tbody id="tbodyRateiosList" name="tbodyRateiosList">
                            @if(isset($rateios))
                            @foreach ($rateios as $rateio)
                            <tr id="rateio{{$rateio->id}}">
                                <td>{{$rateio->centrocusto_id}}</td><!--0-->
                                <td>{{$rateio->centrocusto_codigo}}</td><!--1-->
                                <td>{{$rateio->centrocusto_descricao}}</td><!--2-->
                                <td>{{$rateio->planoconta_id}}</td><!--3-->
                                <td>{{$rateio->planoconta_codigo}}</td><!--4-->
                                <td>{{$rateio->planoconta_descricao}}</td><!--5-->
                                <td>{{requestNumeroDecimalOracle($rateio->valor)}}</td><!--6-->
                                <td><button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverRateio'>Remover</button></td><!--7-->
                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div><!-- /.box -->
            </div>
        </div>
    </div>
</div>