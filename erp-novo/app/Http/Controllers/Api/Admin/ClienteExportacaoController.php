<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Cliente\ClienteExportacaoService;
use App\Domain\Relatorio\RelatorioService;
use App\Domain\Seguranca\AuditoriaSeguranca;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exportação personalizada da base de clientes (CSV / XLSX / PDF).
 *
 * Gated por `cliente.export.completo`, que NÃO é a mesma coisa que
 * `cliente.export`: aquele libera o CSV resumido de conferência; este extrai a
 * base cadastral inteira — nome, documento, endereço e contato de todo mundo.
 * Sob a LGPD isso é ato do controlador dos dados, então nasce só no papel do
 * dono da rede (ver RbacSeeder) e TODA execução vai para a trilha de segurança
 * com o que foi levado: sem registro de quem exportou o quê, a revenda não tem
 * como responder a um pedido de titular nem a uma fiscalização.
 */
class ClienteExportacaoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(
        private ClienteExportacaoService $service,
        private RelatorioService $relatorio,
        private AuditoriaSeguranca $auditoria,
    ) {}

    /**
     * GET /clientes/exportacao/opcoes — o que o modal oferece.
     *
     * O catálogo de campos vem do backend, não hardcoded na SPA: é o mesmo
     * array que autoriza a exportação, então tela e enforcement não podem
     * divergir.
     */
    public function opcoes(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.export.completo');

        $campos = [];
        foreach (ClienteExportacaoService::CAMPOS as $chave => $meta) {
            $campos[] = [
                'chave' => $chave,
                'rotulo' => $meta['rotulo'],
                'grupo' => $meta['grupo'],
                'sensivel' => $meta['sensivel'] ?? false,
            ];
        }

        return response()->json(['data' => [
            'campos' => $campos,
            'padrao' => ClienteExportacaoService::PADRAO,
            'limite_pdf' => ClienteExportacaoService::LIMITE_PDF,
            'limite_linhas' => ClienteExportacaoService::LIMITE_LINHAS,
        ]]);
    }

    /**
     * GET /clientes/exportacao/previa — quantos clientes o filtro atinge.
     *
     * Existe para o dono ver o tamanho ANTES de gerar: um PDF de 40 mil linhas
     * só se descobre grande demais depois de esperar por ele.
     */
    public function previa(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.export.completo');

        $filtros = $this->validarFiltros($request);
        $total = $this->service->contar($filtros);

        return response()->json(['data' => [
            'total' => $total,
            'excede_pdf' => $total > ClienteExportacaoService::LIMITE_PDF,
            'excede_limite' => $total > ClienteExportacaoService::LIMITE_LINHAS,
        ]]);
    }

    /** POST /clientes/exportacao — gera o arquivo no formato escolhido. */
    public function exportar(Request $request): Response
    {
        $this->autorizar($request, 'cliente.export.completo');

        $dados = $request->validate([
            'formato' => 'required|in:csv,xlsx,pdf',
            'campos' => 'required|array|min:1',
            'campos.*' => 'string',
        ]);

        $formato = $dados['formato'];
        $campos = $this->service->camposValidos($dados['campos']);
        $filtros = $this->validarFiltros($request);

        // O PDF é o único formato com teto próprio: dompdf monta a tabela
        // inteira em memória antes de paginar. Recusar com o número na mão é
        // mais útil que estourar a memória e devolver 500.
        $limite = $formato === 'pdf'
            ? ClienteExportacaoService::LIMITE_PDF
            : ClienteExportacaoService::LIMITE_LINHAS;

        $total = $this->service->contar($filtros);
        if ($total > $limite) {
            abort(422, $formato === 'pdf'
                ? "O filtro atinge {$total} clientes e o PDF comporta ".ClienteExportacaoService::LIMITE_PDF.'. Restrinja o filtro ou exporte em Excel/CSV.'
                : "O filtro atinge {$total} clientes, acima do limite de {$limite} por arquivo. Restrinja o filtro.");
        }

        $linhas = $this->service->linhas($campos, $filtros, $limite);

        // Trilha ANTES de devolver: se a geração falhar no meio, o que importa
        // registrar é a INTENÇÃO de extrair — e ela já aconteceu aqui.
        $this->auditoria->registrar('cliente.exportacao', 'clientes', [
            'formato' => $formato,
            'campos' => $campos,
            'filtros' => array_filter($filtros, fn ($v) => $v !== null && $v !== '' && $v !== []),
            'linhas' => count($linhas),
            'campos_sensiveis' => array_values(array_filter(
                $campos,
                fn (string $c) => ClienteExportacaoService::CAMPOS[$c]['sensivel'] ?? false,
            )),
        ]);

        $nome = 'clientes-'.now()->format('Y-m-d-His');
        $titulo = 'Relação de clientes';

        [$conteudo, $mime, $arquivo] = match ($formato) {
            'xlsx' => [
                $this->service->xlsx($linhas, 'Clientes'),
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "{$nome}.xlsx",
            ],
            'pdf' => [
                $this->relatorio->pdf($linhas, $titulo),
                'application/pdf',
                "{$nome}.pdf",
            ],
            // BOM UTF-8: sem ele o Excel abre o CSV em ANSI e "João" vira
            // "JoÃ£o" — o mesmo defeito de acentuação que já mordeu no PDF.
            default => [
                "\u{FEFF}".$this->relatorio->csv($linhas),
                'text/csv; charset=UTF-8',
                "{$nome}.csv",
            ],
        };

        return response($conteudo, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "attachment; filename=\"{$arquivo}\"",
            // O Bearer viaja no header, então a SPA baixa via blob; sem expor
            // o header ela não consegue ler o nome do arquivo.
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

    /**
     * Filtros aceitos. Validar aqui (e não no serviço) mantém a mensagem de
     * erro no formato que a SPA já trata.
     *
     * @return array<string,mixed>
     */
    private function validarFiltros(Request $request): array
    {
        return $request->validate([
            'situacao' => 'nullable|in:ativos,inativos,todos',
            'q' => 'nullable|string|max:200',

            'rua_ids' => 'nullable|array', 'rua_ids.*' => 'integer',
            'bairro_ids' => 'nullable|array', 'bairro_ids.*' => 'integer',
            'cidade_ids' => 'nullable|array', 'cidade_ids.*' => 'integer',
            'uf' => 'nullable|string|size:2',
            'cep' => 'nullable|string|max:10',
            'sem_geolocalizacao' => 'nullable|boolean',

            'cliente' => 'nullable|boolean',
            'fornecedor' => 'nullable|boolean',
            'transportador' => 'nullable|boolean',
            'convenio' => 'nullable|boolean',
            'convenio_ativo' => 'nullable|boolean',
            'gasdopovo' => 'nullable|boolean',
            'nfemite' => 'nullable|boolean',
            'simples' => 'nullable|boolean',

            'segmento_ids' => 'nullable|array', 'segmento_ids.*' => 'integer',
            'tipopessoa_ids' => 'nullable|array', 'tipopessoa_ids.*' => 'integer',

            'cadastro_inicio' => 'nullable|date',
            'cadastro_fim' => 'nullable|date|after_or_equal:cadastro_inicio',
            'ultima_compra_inicio' => 'nullable|date',
            'ultima_compra_fim' => 'nullable|date|after_or_equal:ultima_compra_inicio',
            'sem_compra_dias' => 'nullable|integer|min:1|max:3650',
            'aniversario_mes' => 'nullable|integer|min:1|max:12',

            'com_credito' => 'nullable|boolean',
            'com_saldo_devedor' => 'nullable|boolean',
        ]);
    }
}
