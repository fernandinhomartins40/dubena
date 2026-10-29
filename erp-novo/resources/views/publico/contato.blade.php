<ul>
    @if($p['email'])<li>E-mail: <a href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a></li>@endif
    @if($p['whatsapp'])<li>WhatsApp: {{ $p['whatsapp'] }}</li>@endif
    @if($p['telefone'])<li>Telefone: {{ $p['telefone'] }}</li>@endif
    @if($p['endereco'])<li>Endereço: {{ $p['endereco'] }}</li>@endif
</ul>
