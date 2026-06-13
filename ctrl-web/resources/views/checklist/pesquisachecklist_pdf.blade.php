
<HTML>
<HTML>
  <HEAD>
    <title>{{ $titulo }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <STYLE>

        .fonte{font-size:13px;font-family:"Times New Roman",times,arial;}
        .fonte14{font-size:14px;font-family:"Times New Roman",times,arial;}

        .marginleft10{margin-left:10px;}
        .marginleft20{margin-left:20px;}
        .margintop3{margin-top:3px;}

        .check{border: 1px solid black;width: 9px;height: 9px;display: inline-block;margin-right: 4px;margin-top:1.8px;}
        .check.checked:after{content: '';display: block;width: 4px;height: 7px;position:relative;left:2.2px;top:-1.5px;border: solid #000;border-width: 0 2px 2px 0;transform: rotate(45deg);}

    </STYLE>
  </HEAD>
  <BODY>
    <div>
        <hr />
    </div>
    <div>
        <div class="" style="text-align:left;width:50%;">
            <p class="fonte">Formulário: {{$descricao}}</p>
            <p class="fonte">Código Empresa: {{$empresa_id}}</p>
            <p class="fonte">Empresa: {{$empresanome}}</p>
            <p class="fonte">CNPJ: {{$empresacnpj}}</p>
            <p class="fonte">Data Checklist: {{$data}}</p>
        </div>
    </div>
    <img id="imgInicial" style="max-height:70px;position:absolute;top:3%;right:15%;" src="data:image/png;base64,{{Session::get('empresa_padrao')->logo }}" alt="Logotipo"/>
    <div>
        <hr />
    </div>
    <div>
    @if(isset($checklisttopicos))
    @foreach($checklisttopicos as $topico)
    <div>
        <p class="fonte14" style="font-weight:bold;">Tópico - {{$topico->descricao}}</p>
    </div>
    @foreach($topico->perguntas as $pergunta)
        <p class="fonte marginleft10">Pergunta - {{$pergunta->descricao}}</p>
        @foreach($pergunta->respostas as $resposta)
            @if($resposta->tipopergunta == "0")
                @if(isset($resposta->respondido))
                    <label for="{{$resposta->id}}" class="fonte marginleft20">{{$resposta->descricao}}:</label>
                    <span class="fonte marginleft20"><div class="check checked"></div></span>
                @else
                    <label for="{{$resposta->id}}" class="fonte marginleft20">{{$resposta->descricao}}:</label>
                    <input class="fonte margintop3" type="checkbox" name="{{$resposta->id}}" value="{{$resposta->id}}" />
                @endif
            @elseif($resposta->tipopergunta == "1")
                @if(isset($resposta->respondido))
                    <p class="fonte marginleft20">{{$resposta->descricao}}: {{$resposta->respondido}} </p>
                @else
                    <p class="fonte marginleft20">{{$resposta->descricao}}:____________________ </p>
                @endif
            @elseif($resposta->tipopergunta == "2")
                @if(isset($resposta->respondido))
                    <p class="fonte marginleft20">{{$resposta->descricao}}: {{$resposta->respondido}} </p>
                @else
                    <p class="fonte marginleft20">{{$resposta->descricao}}:____________________ </p>
                @endif
            @elseif($resposta->tipopergunta == "3")
                @if(isset($resposta->respondido))
                    <p><span class="fonte marginleft20">{{$resposta->descricao}}: {{$resposta->respondido}}</div></span></p>
                @else
                    <p class="fonte marginleft20">{{$resposta->descricao}}:______/____________/______ </p>
                @endif
            @elseif($resposta->tipopergunta == "4")
                @if(isset($resposta->respondido))
                    <label for="{{$resposta->id}}" class="fonte marginleft20">{{$resposta->descricao}}:</label>
                    <input class="fonte margintop3" type="radio" name="{{$resposta->id}}" value="{{$resposta->id}}" checked />
                @else
                    <label for="{{$resposta->id}}" class="fonte marginleft20">{{$resposta->descricao}}:</label>
                    <input class="fonte margintop3" type="radio" name="{{$resposta->id}}" value="{{$resposta->id}}" />
                @endif
            @endif
        @endforeach
    @endforeach
    <div>
        <br />
    </div>
    @endforeach
    @endif
    </div>
    <div>
        <p class="fonte14">Observacões: {{@$checklist->obsevacoes}}</p>
    </div>
  </BODY>
</HTML>