<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') — {{ $p['app_nome'] }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.6;
               max-width: 760px; margin: 0 auto; padding: 24px 16px 64px; color: #1f2328; background: #fff; }
        h1 { color: #FF6200; font-size: 1.6rem; margin-bottom: .25rem; }
        h2 { font-size: 1.15rem; margin-top: 2rem; }
        .vig { color: #57606a; font-size: .9rem; margin-top: 0; }
        .caixa { background: #fff4ec; border-left: 4px solid #FF6200; padding: 12px 16px; border-radius: 6px; }
        a { color: #c24a00; }
        footer { margin-top: 3rem; font-size: .85rem; color: #57606a; }
        @media (prefers-color-scheme: dark) {
            body { background: #0d1117; color: #e6edf3; }
            .caixa { background: #2a1a0f; }
            .vig, footer { color: #8b949e; }
            a { color: #ff9a57; }
        }
    </style>
</head>
<body>
@yield('conteudo')
<footer>
    {{ $p['razao_social'] }}@if($p['cnpj']) — CNPJ {{ $p['cnpj'] }}@endif
    @if($p['endereco'])<br>{{ $p['endereco'] }}@endif
</footer>
</body>
</html>
