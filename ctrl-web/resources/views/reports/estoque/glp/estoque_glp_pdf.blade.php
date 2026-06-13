@extends('layouts.reportNoBootstrap')

@section('content')
<div class="fontSize_12">
<!-- {{$count = 0}} -->
    @if(isset($sector) && count($sector) > 0)
        @foreach($sector as $setor)
            <p class="fontSize15 negrito">Setor: {{$setor->setor}}</p>
            <table id="tblCadastro" class="table500">
                <thead>
                    <tr class="bordered">
                        <th class="bordered destaque">Produto</th>
                        <th class="bordered destaque">Cheios</th>
                        <th class="bordered destaque">Vazios</th>
                        <th class="bordered destaque">Vasilhas</th>
                    </tr>
                </thead>
                <tbody id="repor-list" name="report-list">
                @foreach($glps as $glp)
                    @if($glp->setor_id == $setor->setor_id)
                        <tr class="bordered">
                            <td class="bordered">{{$glp->produto}}</td>
                            <td class="bordered">{{$glp->cheios}}</td>
                            <td class="bordered">{{$glp->vazios}}</td>
                            <td class="bordered">{{$glp->total}}</td>
                        </tr>
                    @endif
                @endforeach
                    <tr class="bordered">
                        <th class="bordered destaque">Total</th>
                        <th class="bordered destaque">{{$setor->cheios}}</th>
                        <th class="bordered destaque">{{$setor->vazios}}</th>
                        <th class="bordered destaque">{{$setor->total}}</th>
                    </tr>
                    <!-- {{$count++}} -->
                    @if($count == count($sector) && count($sector) > 1)
                        <tr>
                            <td colspan="4"></td>
                        </tr>
                        <tr class="bordered destaque">
                            <th class="bordered">Total Geral</th>
                            <th class="bordered">{{$cheios}}</th>
                            <th class="bordered">{{$vazios}}</th>
                            <th class="bordered">{{$total}}</th>
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