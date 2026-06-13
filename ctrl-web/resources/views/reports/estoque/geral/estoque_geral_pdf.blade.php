@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
    <!-- {{$count = 0}} -->
    @if(isset($geral) && count($geral) > 0)
        @foreach($sector as $setor)
            <p class="fontSize15 negrito">Setor: {{$setor->setor}}</p>
            <table id="tblCadastro" class="table500">
                <thead>
                    <tr class="bordered">
                        <th class="bordered destaque" style="width:50%">Produto</th>
                        <th class="bordered destaque" style="width:50%">Quantidade</th>
                    </tr>
                </thead>
                    <tbody id="repor-list" name="report-list">
                    @foreach($geral as $estoque)
                        @if($estoque->setor_id == $setor->setor_id)                                                                    
                            <tr class="bordered">
                                <td class="bordered" style="width:50%">{{$estoque->produto}}</td>
                                <td class="bordered" style="width:50%">{{$estoque->quantidade}}</td>
                            </tr>
                        @endif
                    @endforeach
                        <tr class="bordered">
                            <th class="bordered destaque" style="width:50%">Total</th>
                            <th class="bordered destaque" style="width:50%">{{$setor->total}}</th>
                        </tr>
                    <!-- {{$count++}} -->
                    @if($count == count($sector) && count($sector) > 1)
                        <tr>
                            <td colspan="2"></td>
                        </tr>
                        <tr class="bordered">
                            <th class="bordered destaque" style="width:50%">Total Geral</th>
                            <th class="bordered destaque" style="width:50%">{{$total}}</th>
                        </tr>                        
                    @endif
                </tbody>
            </table>
            <br />
            <hr />
            <br />
        @endforeach
    @else
        <p class="negrito" style="text-align:center">Nenhum resultado encontrado para estes filtros!</p>
    @endif
</div>

@endsection