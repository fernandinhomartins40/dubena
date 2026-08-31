<?php

namespace App\Domain\Contrato;

use Illuminate\Support\Facades\Route;

/**
 * F2-01 — captura o schema REAL de request/response por rota, em runtime.
 *
 * O manifesto de API já dizia quais endpoints existem. Isso pega rota removida,
 * mas não pega o que quebra a SPA e os apps com muito mais frequência: um campo
 * que some do payload, um obrigatório que aparece, um tipo que muda. O contrato
 * era de EXISTÊNCIA, não de forma.
 *
 * ## Por que em runtime, e não lendo o código
 *
 * Só 5 rotas usam `FormRequest`; as outras 217 validam inline com
 * `$request->validate([...])`, muitas com regras montadas em tempo de execução
 * (`Rule::exists` com escopo de empresa, obrigatoriedade condicional). Um
 * extrator estático leria a primeira forma e erraria a segunda — e um contrato
 * que descreve mal metade do sistema é pior que nenhum, porque dá confiança
 * onde não deve.
 *
 * Capturar durante os testes registra o que a aplicação **de fato** exige e
 * devolve. A cobertura vira uma consequência honesta da suíte: rota exercitada
 * entra no contrato, rota não exercitada fica de fora — e isso é uma informação
 * útil por si só.
 *
 * ## O que se registra
 *
 * Do request: os NOMES dos campos e se são obrigatórios. Não os valores, nem as
 * regras completas — o alvo é a forma do contrato, e gravar `min:8` faria o
 * arquivo mudar a cada ajuste de validação sem que o contrato tivesse mudado.
 *
 * Da response: os caminhos das chaves de `data`, com o tipo. Um array de objetos
 * vira um caminho só (`data[].nome`): o contrato é a forma do item, não quantos
 * vieram.
 */
class ColetorDeSchema
{
    /** Onde a suíte deposita o que capturou, para `api:schema` consolidar. */
    public const CAMINHO_CAPTURA = 'storage/app/api-schema-capturado.json';

    /** @var array<string, array{request: array<string,bool>, response: array<string,string>, status: list<int>}> */
    private static array $observado = [];

    private static bool $ligado = false;

    /**
     * @param  bool  $zerar  false acumula entre testes — é o modo do `api:schema`,
     *                       onde o contrato é a soma do que a suíte inteira
     *                       exercita. `true` isola um teste do anterior.
     */
    public static function ligar(bool $zerar = true): void
    {
        self::$ligado = true;

        if ($zerar) {
            self::$observado = [];
        }
    }

    public static function desligar(): void
    {
        self::$ligado = false;
    }

    public static function ligado(): bool
    {
        return self::$ligado;
    }

    /**
     * Registra as regras de validação aplicadas na rota corrente.
     *
     * @param  array<string, mixed>  $regras
     */
    public static function registrarRequest(array $regras): void
    {
        $chave = self::rotaCorrente();
        if ($chave === null) {
            return;
        }

        self::$observado[$chave] ??= ['request' => [], 'response' => [], 'status' => []];

        foreach ($regras as $campo => $regra) {
            $texto = is_array($regra)
                ? implode('|', array_map(fn ($r) => is_string($r) ? $r : '', $regra))
                : (string) $regra;

            // `required` decide sozinho: um campo que já foi visto como
            // obrigatório continua obrigatório no contrato, mesmo que outra
            // chamada o valide sob condição.
            $obrigatorio = str_contains($texto, 'required');

            self::$observado[$chave]['request'][$campo] =
                (self::$observado[$chave]['request'][$campo] ?? false) || $obrigatorio;
        }
    }

    /**
     * Registra a forma da resposta.
     *
     * @param  array<string, mixed>  $corpo
     */
    public static function registrarResponse(array $corpo, int $status): void
    {
        $chave = self::rotaCorrente();
        if ($chave === null) {
            return;
        }

        self::$observado[$chave] ??= ['request' => [], 'response' => [], 'status' => []];

        if (! in_array($status, self::$observado[$chave]['status'], true)) {
            self::$observado[$chave]['status'][] = $status;
        }

        // Só respostas de sucesso descrevem o contrato: o corpo de um 422 é a
        // forma do ERRO, e misturá-lo faria o contrato prometer campos que só
        // existem quando algo deu errado.
        if ($status >= 300 || ! array_key_exists('data', $corpo)) {
            return;
        }

        foreach (self::achatar($corpo['data'], 'data') as $caminho => $tipo) {
            self::$observado[$chave]['response'][$caminho] = $tipo;
        }
    }

    /**
     * Achata uma estrutura em caminho => tipo.
     *
     * @return array<string, string>
     */
    private static function achatar(mixed $valor, string $prefixo, int $profundidade = 0): array
    {
        // Teto de profundidade: uma árvore muito funda (pedido → itens →
        // produto → …) geraria centenas de caminhos e faria o contrato virar
        // ruído. Três níveis descrevem a forma que a SPA consome.
        if ($profundidade > 3) {
            return [];
        }

        if (! is_array($valor)) {
            return [$prefixo => get_debug_type($valor)];
        }

        if ($valor === []) {
            return [$prefixo => 'array'];
        }

        // Lista: descreve o PRIMEIRO item. O contrato é a forma do item, não a
        // quantidade — e itens de tipos diferentes na mesma lista seriam um
        // defeito, não um contrato a documentar.
        if (array_is_list($valor)) {
            return self::achatar($valor[0], $prefixo.'[]', $profundidade + 1);
        }

        $saida = [];
        foreach ($valor as $chave => $item) {
            $saida += self::achatar($item, $prefixo.'.'.$chave, $profundidade + 1);
        }

        return $saida;
    }

    /** "MÉTODO uri" da rota atual, ou null fora de uma requisição roteada. */
    private static function rotaCorrente(): ?string
    {
        $rota = Route::current();
        if ($rota === null) {
            return null;
        }

        $uri = $rota->uri();
        if (! str_starts_with($uri, 'api/')) {
            return null;
        }

        $metodo = collect($rota->methods())
            ->reject(fn (string $m) => in_array($m, ['HEAD', 'OPTIONS'], true))
            ->first();

        return $metodo === null ? null : "{$metodo} {$uri}";
    }

    /**
     * O que foi observado, ordenado para o arquivo ter diff estável.
     *
     * @return array<string, array{request: array<string,bool>, response: array<string,string>, status: list<int>}>
     */
    public static function coletado(): array
    {
        $saida = self::$observado;
        ksort($saida);

        foreach ($saida as &$rota) {
            ksort($rota['request']);
            ksort($rota['response']);
            sort($rota['status']);
        }

        return $saida;
    }
}
