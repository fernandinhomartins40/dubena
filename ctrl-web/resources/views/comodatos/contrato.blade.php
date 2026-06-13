@extends('layouts.contrato')

@section('content')

<div class="fontSize_14">
    <div style="color:#1D1D1F; margin-top: 1%">
        <div style="position: absolute">
            <div class="fontSize_15 negrito">
                {{ $empresa->razao_social }}
            </div>
            <div class="fontSize_13 negrito">
                {{ $empresa->rua->descricao }}, {{ $empresa->numero }}
                {{ $empresa->bairro->descricao }} -
                {{ $empresa->cidade->descricao }}/{{ $empresa->uf }}
            </div>
            <div class="fontSize_13 negrito">CNPJ: {{ $empresa->cnpj }} I.E.: {{ $empresa->inscricao_estadual }}</div>
        </div>
        <div style="position: absolute; float:right;">
            @if($empresa->logo != null)
            <img id="imgInicial" class="img-circle" style="max-height:50px; padding-top: -20px" src="data:image/png;base64,{{ $empresa->logo }}" alt="Logotipo"/>
            @else
            <img id="imgInicial" class="img-circle"  style="max-height:50px;" src="{{URL::to('dist/img/userdefault.png')}}" alt="Logotipo"/>
            @endif
        </div>
        <br />
        <br />
        <br />
        <div class="margTop_20" style="border:1px solid black; padding-top: 10px;"></div>
        <div style="text-align: center; margin-bottom: 1.5%" class="fontSize_14 margLeft_45 text-justify margTop_5 negrito">CONTRATO DE FIEL DEPOSITÁRIO
        </div>

        @if(@$comodato->tipo == 0)
        <div class="margLeft_20 text-justify">
            Pelo presente instrumento <span class="maiusculo">{{ @$comodato->cliente->nome }}</span>, pessoa jurídica de direito privado,
            inscrita no CNPJ sob o nº {{ @$comodato->cliente->cnpj }}, e no CAD-ICMS sob nº {{ @$comodato->cliente->inscricao_estadual }}
            com sede na
            {{@$comodato->cliente->rua->descricao}} - {{@$comodato->cliente->numero}},
            {{ @$comodato->cliente->bairro->descricao }}, {{ @$comodato->cliente->cidade->descricao }}/{{  @$comodato->cliente->uf }},
            neste ato representada
            por <span class="maiusculo">{{@$comodato->nomerepresentante}}</span> portador(a) do RG nº {{@$comodato->rgrepresentante}} CPF nº
            {{@$comodato->cpfrepresentante}} recebeu em COMODATO da empresa
            <span class="maiusculo">{!!@$empresa->razao_social!!}</span>, estabelecida na rua {!!@$empresa->rua->descricao!!} -
            {!!@$empresa->numero!!}, {!!@$empresa->bairro->descricao!!}, {!!@$empresa->cidade->descricao!!}/{!!@$empresa->estado->uf!!},
            os seguintes itens:
        </div>
        @elseif(@$comodato->tipo == 1)
        <div class="margLeft_20 text-justify">
            Pelo presente instrumento <span class="maiusculo">{{ @$comodato->cliente->nome }}</span>, inscrito(a) no CPF sob o nº
            {{ is_null($comodato->cliente->cpf) ? $comodato->cliente->cnpj : $comodato->cliente->cpf }},
            RG nº {{ @$comodato->cliente->rg }} estabelecido(a) na {{@$comodato->cliente->rua->descricao}} - {{@$comodato->cliente->numero}},
            {{ @$comodato->cliente->bairro->descricao }}, {{ @$comodato->cliente->cidade->descricao }}/{{  @$comodato->cliente->uf }},
            recebeu em COMODATO da empresa <span class="maiusculo">{!!@$empresa->razao_social!!}</span>,
            estabelecida na {!!@$empresa->rua->descricao!!} - {!!@$empresa->numero!!}, {!!@$empresa->bairro->descricao!!},
            {!!@$empresa->cidade->descricao!!}/{!!@$empresa->estado->uf!!}, os seguintes itens:
        </div>
        @else
        <div class="margLeft_20 text-justify">
            Declaro para os fins de direito que,  <span class="maiusculo">{!!@$empresa->razao_social!!}</span>,
            estabelecida na rua {!!@$empresa->rua->descricao!!} -
            {!!@$empresa->numero!!}, {!!@$empresa->bairro->descricao!!}, {!!@$empresa->cidade->descricao!!}/{!!@$empresa->estado->uf!!}
            inscrito no CNPJ/CPF sob nº

            @if($comodato->cliente->cpf !== null)
                {{$comodato->cliente->cpf}},
            @elseif($comodato->cliente->cnpj !== null)
                {{$comodato->cliente->cnpj}},
            @endif

            ora Depositário, neste ato representada por seu representante legal
            Sr(a). <span class="maiusculo">{{ $empresa->contratonome }}</span>, portador da cédula de identidade RG
            nº {{ $empresa->contratorg }} e do CPF nº {{ $empresa->contratocpf }},
            recebeu em depósito da empresa  <span class="maiusculo">{{ @$comodato->cliente->nome }}</span>
            estabelecido na rua {{@$comodato->cliente->rua->descricao}} - {{@$comodato->cliente->numero}},
            {{ @$comodato->cliente->bairro->descricao }}, {{ @$comodato->cliente->cidade->descricao }}/{{  @$comodato->cliente->uf }},
            ora Depositante, neste ato representada por seu representante legal
            Sr(a). <span class="maiusculo">{{@$comodato->nomerepresentante}}</span>, portador da
            cédula de identidade RG nº {{@$comodato->rgrepresentante}} e do CPF nº {{@$comodato->cpfrepresentante}},
            os seguintes itens:
        </div>
        @endif

        <br />
        <table id="tblProdutos" style="width: 80%; margin-bottom: 18px">
            <thead>
                <tr>
                    <th class='text-center'>Produto</th>
                    <th class='text-center'>Quantidade</th>
                </tr>
            </thead>
            <!-- {{$count = 0}} -->
            @if(isset($comodatoprodutos))
            @foreach ($comodatoprodutos as $produto)
            <tbody>
                <tr>
                    <td class='text-center'>{{ $produto->produto->descricao }}</td>
                    <td class='text-center'>{{ $produto->quantidade }}</td>
                </tr>
            </tbody>
            <!-- {{$count++}} -->
            @endforeach
            @endif
        </table>

        <div class="margLeft_20 text-justify">Pelo presente contrato a guarda e conservação das mercadorias e equipamentos,
            ficam confiados ao depositário a título gratuito, conforme disposto pelos arts. 579 a 585 da Lei nº 10.406, Código Civil de 2002.
        </div>

        <br />

        <div class="margLeft_20 text-justify">A COMODATÁRIA investida na função de FIEL DEPOSITÁRIA obriga-se a manter os equipamentos e
            mercadorias em perfeito estado de conservação, tal como foram entregues, podendo os mesmos serem utilizados para os fins a que se
            destinam, devendo ser restituídos no prazo legal de 03 (três) dias, contadas da data de recebimento da notificação extrajudicial da
            depositante.
        </div>

        <br />

        <div class="margLeft_20 text-justify">A COMODATARIA, em razão do presente contrato de comodato, deverá adquirir com exclusividade da
            COMODANTE as quantidades de Gás GLP a que se referem os vasilhames em comodato, que necessitar para as suas operações.
        </div>

        <br />

        <div class="margLeft_20 text-justify">As partes estipulam o prazo de 02 (dois anos) de vigência do contrato desde a sua assinatura,
            devendo o COMODATÁRIO, ao término da vigência, devolver os bens nas mesmas condições em que os recebeu. Fica ainda pactuado
            entre as partes que o referido contrato pode ser renovado por igual período. A rescisão do presente Contrato se dará por iniciativa de
            qualquer das partes, mediante comunicação prévia.
        </div>

        <br />

        <div class="margLeft_20 text-justify">Havendo rescisão do presente Contrato, por quaisquer das partes, será de inteira
            responsabilidade da COMODANTE retirar os equipamentos comodatados, no prazo máximo de 03 (três) dias, a contar da rescisão.
        </div>

        <br />

        <div class="margLeft_20 text-justify">E, por estarem livremente justos e contratados assinam o presente instrumento em 2 (duas)
            vias de igual teor e forma, na presença de 02 (duas) testemunhas.
        </div>

        <br />

        <div align="center" class="margTop_5">Guarapuava, {{ requestDataOracle($comodato->datacontrato, false) }}</div>
        @if($count > 2 && $count < 12)
        <div class="page-break ">
        </div>
        <br />
        <br />
        <br />
        @endif
        <div class="margLeft_20" style="position: absolute;text-align:left;padding-top:40px;">
            <div class="fleft" style="border-top: 1px solid black; width:40%">
                <span class="maiusculo" id="contratComodatoNome_2"></span>
                <span class="maiusculo">{{ @$comodato->cliente->nome }}</span>
                <div>Fiel Depositário</div>
            </div>
        </div>
        <div class="margLeft_20" style="position: absolute;text-align:left;padding-top:150px;">
            <div class="fleft" style="border-top: 1px solid black; width:40%">
                <div>{{ $empresa->razao_social }}</div>
                <div>{{ $empresa->cnpj }}</div>
            </div>
        </div>
        <div class="margLeft_20" style=" text-align:left;padding-top:40px;">
            <div class="fright" style="border-top: 1px solid black; width:40%">
                Responsável pela abertura
                <div class="margTop_30">
                    Testemunhas
                </div>
            </div>
        </div>

        <div class="margLeft_20" style="text-align:left;padding-top:150px;">
            <div class="fright" style="border-top: 1px solid black; width:40%">
                Nome:<br />
                RG:
            </div>
        </div>

        <div class="margLeft_20" style="text-align:left;padding-top:220px;">

            <div class="fright" style="border-top: 1px solid black; width:40%">
                Nome:<br />
                RG:
            </div>
        </div>
    </div>
</div>
@endsection
