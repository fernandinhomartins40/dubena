<?php

namespace App\Domain\Cliente;

use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exportação personalizada da base de clientes (CSV / XLSX / PDF).
 *
 * Por que um serviço próprio e não mais um método no RelatorioService: aqui a
 * FORMA do arquivo é escolhida por quem exporta (colunas e filtros vêm da
 * requisição), então o catálogo de campos precisa ser um contrato explícito —
 * é ele que impede a requisição de virar `select` livre e vazar coluna que
 * ninguém autorizou.
 *
 * O endereço sai DECOMPOSTO (logradouro, número, complemento, bairro, cidade…)
 * porque o pedido é filtrar e agrupar por rua/bairro na planilha. A base já
 * guarda assim: `rua_id`/`bairro_id`/`cidade_id` são FKs para o catálogo
 * geográfico. ⚠️ A coluna `clientes.endereco` está NULL em 100% da base (0 de
 * 55.453 medidos em produção) — quem lê ela direto exporta só o número. O
 * logradouro real vem da FK. Ver o accessor em Cliente::getEnderecoCompleto.
 */
class ClienteExportacaoService
{
    /**
     * Teto do PDF. O dompdf monta a tabela HTML inteira em memória antes de
     * paginar, e um relatório de 5 mil linhas já passa de 100 páginas — acima
     * disso o formato deixa de servir a quem vai LER.
     */
    public const LIMITE_PDF = 5000;

    /**
     * A partir daqui o XLSX fica lento e o CSV é o caminho melhor.
     *
     * Não é teto: é aviso. Medido com 55.000 linhas × 13 colunas — XLSX leva
     * ~100s e 604 MB (51s montando a árvore de células do PhpSpreadsheet, 66s
     * gravando), enquanto o MESMO conteúdo em CSV sai em 0,9s. O custo é
     * intrínseco à biblioteca, não algo que dê para otimizar aqui.
     *
     * O usuário continua livre para escolher XLSX com qualquer volume; a tela
     * só avisa que vai demorar e que o CSV abre no Excel do mesmo jeito.
     */
    public const XLSX_LENTO_ACIMA_DE = 20000;

    /**
     * CSV e XLSX NÃO têm teto de linhas.
     *
     * Havia um de 50.000 aqui, escolhido sem medir nada. Ele caía logo abaixo
     * dos 51.793 clientes da base e transformava a operação mais óbvia do
     * recurso — "exportar todos os clientes" — num erro. Nenhum limite real o
     * justificava: o XLSX comporta 1.048.576 linhas (20x a base) e o CSV não
     * tem limite algum. Era invenção do código, não restrição do formato nem
     * do plano do tenant.
     *
     * Se um dia o volume pesar, a resposta é exportação assíncrona — gerar em
     * fila e entregar o arquivo depois —, nunca recusar o que o usuário pediu.
     */
    public const SEM_LIMITE = PHP_INT_MAX;

    /**
     * Registros por lote do `chunk`. Ajustável só para o teste conseguir
     * exercitar a FRONTEIRA entre lotes com poucos registros — com o valor de
     * produção, 25 clientes caberiam num lote só e o teste de paginação
     * passaria sem testar paginação nenhuma.
     */
    public static int $tamanhoDoLote = 2000;

    /**
     * Catálogo de campos exportáveis: chave => [rótulo, grupo, sensivel?].
     *
     * `sensivel` marca o que a LGPD trata como dado que não deveria sair da
     * tela sem intenção explícita — não bloqueia (a exportação inteira já é
     * privilégio de dono), mas o modal avisa e nenhum deles vem pré-marcado.
     *
     * @var array<string, array{rotulo: string, grupo: string, sensivel?: bool}>
     */
    public const CAMPOS = [
        // ── Identificação ──
        'id' => ['rotulo' => 'Código', 'grupo' => 'Identificação'],
        'nome' => ['rotulo' => 'Nome / Razão social', 'grupo' => 'Identificação'],
        'fantasia' => ['rotulo' => 'Nome fantasia', 'grupo' => 'Identificação'],
        'tipopessoa' => ['rotulo' => 'Tipo de pessoa', 'grupo' => 'Identificação'],
        'segmento' => ['rotulo' => 'Segmento', 'grupo' => 'Identificação'],
        'sexo' => ['rotulo' => 'Sexo', 'grupo' => 'Identificação'],
        'datanascimento' => ['rotulo' => 'Data de nascimento', 'grupo' => 'Identificação', 'sensivel' => true],
        'observacoes' => ['rotulo' => 'Observações', 'grupo' => 'Identificação'],

        // ── Documentos ──
        'cpf' => ['rotulo' => 'CPF', 'grupo' => 'Documentos', 'sensivel' => true],
        'rg' => ['rotulo' => 'RG', 'grupo' => 'Documentos', 'sensivel' => true],
        'cnpj' => ['rotulo' => 'CNPJ', 'grupo' => 'Documentos'],
        'inscricao_estadual' => ['rotulo' => 'Inscrição estadual', 'grupo' => 'Documentos'],
        'indicador_ie' => ['rotulo' => 'Indicador de IE', 'grupo' => 'Documentos'],
        'suframa' => ['rotulo' => 'SUFRAMA', 'grupo' => 'Documentos'],

        // ── Endereço (decomposto — é o que permite filtrar por rua/bairro) ──
        'logradouro' => ['rotulo' => 'Logradouro', 'grupo' => 'Endereço'],
        'numero' => ['rotulo' => 'Número', 'grupo' => 'Endereço'],
        'complemento' => ['rotulo' => 'Complemento', 'grupo' => 'Endereço'],
        'ponto_referencia' => ['rotulo' => 'Ponto de referência', 'grupo' => 'Endereço'],
        'bairro' => ['rotulo' => 'Bairro', 'grupo' => 'Endereço'],
        'cidade' => ['rotulo' => 'Cidade', 'grupo' => 'Endereço'],
        'uf' => ['rotulo' => 'UF', 'grupo' => 'Endereço'],
        'cep' => ['rotulo' => 'CEP', 'grupo' => 'Endereço'],
        'latitude' => ['rotulo' => 'Latitude', 'grupo' => 'Endereço', 'sensivel' => true],
        'longitude' => ['rotulo' => 'Longitude', 'grupo' => 'Endereço', 'sensivel' => true],
        'location_type' => ['rotulo' => 'Precisão da geolocalização', 'grupo' => 'Endereço'],
        'endereco_completo' => ['rotulo' => 'Endereço completo (linha única)', 'grupo' => 'Endereço'],

        // ── Contato ──
        'email' => ['rotulo' => 'E-mail', 'grupo' => 'Contato', 'sensivel' => true],
        'telefones' => ['rotulo' => 'Telefones', 'grupo' => 'Contato', 'sensivel' => true],
        'telefone_principal' => ['rotulo' => 'Telefone principal', 'grupo' => 'Contato', 'sensivel' => true],
        'whatsapp' => ['rotulo' => 'WhatsApp', 'grupo' => 'Contato', 'sensivel' => true],

        // ── Papéis e flags ──
        'cliente' => ['rotulo' => 'É cliente', 'grupo' => 'Papéis'],
        'fornecedor' => ['rotulo' => 'É fornecedor', 'grupo' => 'Papéis'],
        'transportador' => ['rotulo' => 'É transportador', 'grupo' => 'Papéis'],
        'simples' => ['rotulo' => 'Simples Nacional', 'grupo' => 'Papéis'],
        'nfemite' => ['rotulo' => 'Emite NF-e', 'grupo' => 'Papéis'],
        'gasdopovo' => ['rotulo' => 'Gás do Povo', 'grupo' => 'Papéis'],

        // ── Convênio e crédito (campos com gate field-level próprio) ──
        'convenio' => ['rotulo' => 'Tem convênio', 'grupo' => 'Convênio e crédito'],
        'convenio_ativo' => ['rotulo' => 'Convênio ativo', 'grupo' => 'Convênio e crédito'],
        'convenio_titular' => ['rotulo' => 'Titular do convênio', 'grupo' => 'Convênio e crédito'],
        'convenio_limite' => ['rotulo' => 'Limite do convênio', 'grupo' => 'Convênio e crédito', 'sensivel' => true],
        'credito_limite' => ['rotulo' => 'Limite de crédito', 'grupo' => 'Convênio e crédito', 'sensivel' => true],
        'credito_saldo' => ['rotulo' => 'Saldo de crédito', 'grupo' => 'Convênio e crédito', 'sensivel' => true],

        // ── Situação e trilha ──
        'ativo' => ['rotulo' => 'Ativo', 'grupo' => 'Situação'],
        'data_ultima_compra' => ['rotulo' => 'Última compra', 'grupo' => 'Situação'],
        'desativado_em' => ['rotulo' => 'Desativado em', 'grupo' => 'Situação'],
        'desativado_por' => ['rotulo' => 'Desativado por', 'grupo' => 'Situação'],
        'motivo_desativacao' => ['rotulo' => 'Motivo da desativação', 'grupo' => 'Situação'],
        'created_at' => ['rotulo' => 'Cadastrado em', 'grupo' => 'Situação'],
        'updated_at' => ['rotulo' => 'Atualizado em', 'grupo' => 'Situação'],
    ];

    /** Colunas oferecidas quando o usuário abre o modal pela primeira vez. */
    public const PADRAO = [
        'id', 'nome', 'cpf', 'cnpj', 'telefone_principal',
        'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'ativo',
    ];

    /**
     * Campos que dependem de relação — declarados aqui para o eager loading
     * carregar SÓ o necessário. Sem isso, exportar 50 mil clientes com bairro
     * viraria 50 mil consultas (N+1) e o pedido morreria no timeout.
     *
     * @var array<string, string>
     */
    private const RELACOES = [
        'logradouro' => 'rua', 'bairro' => 'bairro', 'cidade' => 'cidade',
        'endereco_completo' => 'rua,bairro,cidade',
        'tipopessoa' => 'tipopessoa', 'segmento' => 'segmento',
        'telefones' => 'telefones', 'telefone_principal' => 'telefones', 'whatsapp' => 'telefones',
        'desativado_por' => 'desativadoPor', 'convenio_titular' => 'titularConvenio',
    ];

    /**
     * Monta as linhas da exportação.
     *
     * @param  list<string>  $campos  chaves do catálogo, na ordem escolhida
     * @param  array<string,mixed>  $filtros
     * @return list<array<string,mixed>>
     */
    public function linhas(array $campos, array $filtros, int $limite = self::SEM_LIMITE): array
    {
        $campos = $this->camposValidos($campos);

        $query = Cliente::query()->with($this->relacoesNecessarias($campos));
        $this->aplicarFiltros($query, $filtros);
        $linhas = [];
        // Em lotes, e não `get()`: sem teto de linhas, hidratar 51 mil models
        // Eloquent de uma vez é o maior custo de memória do fluxo. O PHP libera
        // cada lote antes do próximo, e o array de saída (arrays simples) pesa
        // uma fração do que pesariam os models vivos.
        //
        // `chunkById` e não `chunk`: o `chunk` pagina com OFFSET, que só é
        // confiável se a ordenação for única — e ordenar por `nome` numa base
        // cheia de homônimos ("MARIA APARECIDA" aparece dezenas de vezes) não
        // é. Um empate resolvido de formas diferentes entre duas consultas faz
        // o OFFSET pular e repetir registros: a exportação sairia com linha
        // faltando e ninguém notaria. `chunkById` pagina por `id > último`,
        // imune ao problema por construção — e o preço é a ordenação sair por
        // id, então quem quiser por nome ordena na planilha (o XLSX já vai com
        // filtro automático).
        $query->chunkById(self::$tamanhoDoLote, function ($lote) use (&$linhas, $campos, $limite) {
            foreach ($lote as $cliente) {
                if (count($linhas) >= $limite) {
                    return false; // interrompe o chunk (só o PDF usa teto)
                }
                $linhas[] = $this->linha($cliente, $campos);
            }

            return true;
        });

        return $linhas;
    }

    /** Quantos clientes o filtro atinge — o modal mostra ANTES de exportar. */
    public function contar(array $filtros): int
    {
        $query = Cliente::query();
        $this->aplicarFiltros($query, $filtros);

        return $query->count();
    }

    /**
     * Filtra as chaves recebidas contra o catálogo, preservando a ORDEM
     * escolhida pelo usuário (as colunas da planilha saem nessa ordem).
     *
     * @param  list<string>  $campos
     * @return list<string>
     */
    public function camposValidos(array $campos): array
    {
        $validos = array_values(array_filter(
            array_unique($campos),
            fn ($c) => is_string($c) && isset(self::CAMPOS[$c]),
        ));

        // Exportação sem coluna nenhuma geraria arquivo vazio sem dizer por quê.
        return $validos === [] ? self::PADRAO : $validos;
    }

    /**
     * Aplica os filtros de seleção de QUAIS clientes entram.
     *
     * Todo filtro é opcional e ausente = não restringe. A única exceção é
     * `situacao`, que default para 'ativos' — espelha a aba da tela, onde o
     * operador vê ativos por padrão e se surpreenderia com desativados no
     * arquivo.
     *
     * @param  Builder<Cliente>  $q
     * @param  array<string,mixed>  $f
     */
    private function aplicarFiltros(Builder $q, array $f): void
    {
        // ── Situação ──
        match ($f['situacao'] ?? 'ativos') {
            'inativos' => $q->where('ativo', false),
            'todos' => null,
            default => $q->where('ativo', true),
        };

        // ── Geografia: o caso de uso central (só clientes da rua/bairro X) ──
        foreach (['rua_id' => 'rua_ids', 'bairro_id' => 'bairro_ids', 'cidade_id' => 'cidade_ids'] as $coluna => $chave) {
            if (! empty($f[$chave]) && is_array($f[$chave])) {
                $q->whereIn($coluna, array_map('intval', $f[$chave]));
            }
        }
        if (filled($f['uf'] ?? null)) {
            $q->where('uf', strtoupper((string) $f['uf']));
        }
        if (filled($f['cep'] ?? null)) {
            // Prefixo: '85070' pega o CEP inteiro de uma região da cidade.
            $q->where('cep', 'like', preg_replace('/\D/', '', (string) $f['cep']).'%');
        }
        // "Sem geolocalização": a lista de quem precisa de recadastro.
        // O OR vai DENTRO de um grupo — solto, ele anularia todos os demais
        // filtros por precedência e o arquivo sairia com a base inteira.
        if (filter_var($f['sem_geolocalizacao'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $q->where(fn (Builder $w) => $w->whereNull('latitude')->orWhereNull('longitude'));
        }

        // ── Papéis e classificação ──
        foreach (['cliente', 'fornecedor', 'transportador', 'convenio', 'convenio_ativo', 'gasdopovo', 'nfemite', 'simples'] as $flag) {
            if (isset($f[$flag]) && $f[$flag] !== null && $f[$flag] !== '') {
                $q->where($flag, filter_var($f[$flag], FILTER_VALIDATE_BOOLEAN));
            }
        }
        foreach (['segmento_id' => 'segmento_ids', 'tipopessoa_id' => 'tipopessoa_ids'] as $coluna => $chave) {
            if (! empty($f[$chave]) && is_array($f[$chave])) {
                $q->whereIn($coluna, array_map('intval', $f[$chave]));
            }
        }

        // ── Período e atividade ──
        // whereDate, não whereBetween: em coluna datetime a comparação de
        // string descarta o último dia do intervalo.
        if (filled($f['cadastro_inicio'] ?? null)) {
            $q->whereDate('created_at', '>=', $f['cadastro_inicio']);
        }
        if (filled($f['cadastro_fim'] ?? null)) {
            $q->whereDate('created_at', '<=', $f['cadastro_fim']);
        }
        if (filled($f['ultima_compra_inicio'] ?? null)) {
            $q->whereDate('data_ultima_compra', '>=', $f['ultima_compra_inicio']);
        }
        if (filled($f['ultima_compra_fim'] ?? null)) {
            $q->whereDate('data_ultima_compra', '<=', $f['ultima_compra_fim']);
        }
        // Inclui quem NUNCA comprou: sem isso, "clientes parados há 90 dias"
        // esconderia justamente o cadastro que nunca gerou pedido.
        if (filled($f['sem_compra_dias'] ?? null)) {
            $limite = now()->subDays((int) $f['sem_compra_dias'])->toDateString();
            $q->where(fn (Builder $w) => $w->whereNull('data_ultima_compra')->orWhereDate('data_ultima_compra', '<', $limite));
        }
        if (filled($f['aniversario_mes'] ?? null)) {
            $q->whereNotNull('datanascimento')
                ->whereMonth('datanascimento', (int) $f['aniversario_mes']);
        }

        // ── Crédito ──
        if (filter_var($f['com_credito'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $q->where('credito_limite', '>', 0);
        }
        if (filter_var($f['com_saldo_devedor'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $q->where('credito_saldo', '<', 0);
        }

        // ── Busca livre (mesmo alcance da barra de busca da tela) ──
        if (filled($f['q'] ?? null)) {
            $termo = '%'.trim((string) $f['q']).'%';
            $q->where(fn (Builder $w) => $w
                ->where('nome', 'ilike', $termo)
                ->orWhere('fantasia', 'ilike', $termo)
                ->orWhere('cpf', 'ilike', $termo)
                ->orWhere('cnpj', 'ilike', $termo));
        }
    }

    /**
     * @param  list<string>  $campos
     * @return list<string>
     */
    private function relacoesNecessarias(array $campos): array
    {
        $relacoes = [];
        foreach ($campos as $campo) {
            foreach (explode(',', self::RELACOES[$campo] ?? '') as $rel) {
                if ($rel !== '') {
                    $relacoes[$rel] = true;
                }
            }
        }

        return array_keys($relacoes);
    }

    /**
     * Uma linha do arquivo, com o RÓTULO como chave — é o cabeçalho que o
     * dono lê na planilha, não o nome da coluna do banco.
     *
     * @param  list<string>  $campos
     * @return array<string,mixed>
     */
    private function linha(Cliente $c, array $campos): array
    {
        $linha = [];
        foreach ($campos as $campo) {
            $linha[self::CAMPOS[$campo]['rotulo']] = $this->valor($c, $campo);
        }

        return $linha;
    }

    private function valor(Cliente $c, string $campo): string|int|float|null
    {
        return match ($campo) {
            // Endereço: o logradouro vem da FK; a coluna de texto é o fallback
            // para o cadastro antigo que porventura a tenha preenchido.
            'logradouro' => $c->endereco ?: $c->rua?->descricao,
            'bairro' => $c->bairro?->descricao,
            'cidade' => $c->cidade?->descricao,
            'endereco_completo' => $c->endereco_completo,

            'tipopessoa' => $c->tipopessoa?->descricao,
            'segmento' => $c->segmento?->descricao,
            'desativado_por' => $c->desativadoPor?->name,
            'convenio_titular' => $c->titularConvenio?->nome,

            'telefones' => $c->telefones->pluck('telefone')->implode(' / ') ?: null,
            'telefone_principal' => $c->telefones->first()?->telefone,
            'whatsapp' => $c->telefones->firstWhere('whatsapp', true)?->telefone,

            'datanascimento', 'data_ultima_compra' => $c->{$campo}?->format('d/m/Y'),
            'desativado_em' => $c->desativado_em?->format('d/m/Y H:i'),
            'created_at', 'updated_at' => $c->{$campo}?->format('d/m/Y H:i'),

            'indicador_ie' => match ($c->indicador_ie) {
                1 => '1 - Contribuinte',
                2 => '2 - Isento',
                9 => '9 - Não contribuinte',
                default => null,
            },
            'sexo' => match ($c->sexo) { 'M' => 'Masculino', 'F' => 'Feminino', default => $c->sexo },

            'cliente', 'fornecedor', 'transportador', 'simples', 'nfemite',
            'gasdopovo', 'ativo', 'convenio', 'convenio_ativo' => $c->{$campo} ? 'Sim' : 'Não',

            'convenio_limite', 'credito_limite', 'credito_saldo' => $c->{$campo} !== null ? (float) $c->{$campo} : null,
            'latitude', 'longitude' => $c->{$campo} !== null ? (float) $c->{$campo} : null,

            default => $c->{$campo},
        };
    }

    // ───────────────────────────── Formatos ─────────────────────────────

    /**
     * Neutraliza texto que o Excel leria como FÓRMULA ao abrir o CSV.
     *
     * Mesmo problema do XLSX, por outro caminho: `=`, `+`, `-` ou `@` no
     * início de uma célula viram fórmula na abertura. No XLSX resolvemos
     * gravando como texto explícito; no CSV não existe tipo, então a defesa é
     * prefixar com apóstrofo — o Excel o consome como "isto é texto" e ele não
     * aparece na célula.
     *
     * Feito aqui e não no `RelatorioService::csv()` de propósito: aquele método
     * é compartilhado por dezenas de relatórios e mudar o formato de todos por
     * causa deste seria alterar o que já funciona.
     *
     * @param  list<array<string,mixed>>  $linhas
     * @return list<array<string,mixed>>
     */
    public function semFormulas(array $linhas): array
    {
        foreach ($linhas as &$linha) {
            foreach ($linha as &$valor) {
                if (is_string($valor) && preg_match('/^[=+\-@]/', $valor) === 1) {
                    $valor = "'".$valor;
                }
            }
        }

        return $linhas;
    }

    /**
     * XLSX real (PhpSpreadsheet): cabeçalho congelado, filtro automático e
     * largura por coluna. É o que permite ao dono ordenar por bairro e somar
     * limites de crédito na própria planilha — que é o motivo de existir XLSX
     * em vez de só CSV.
     *
     * @param  list<array<string,mixed>>  $linhas
     */
    public function xlsx(array $linhas, string $titulo): string
    {
        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet();
        // O Excel recusa aba com >31 chars ou com : \ / ? * [ ]
        $aba->setTitle(mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '', $titulo) ?: 'Clientes', 0, 31));

        $colunas = $linhas === [] ? [] : array_keys($linhas[0]);

        foreach ($colunas as $i => $rotulo) {
            $aba->setCellValue([$i + 1, 1], $rotulo);
        }
        foreach ($linhas as $l => $linha) {
            foreach ($colunas as $i => $rotulo) {
                $valor = $linha[$rotulo] ?? null;
                // setCellValueExplicit como TEXTO em dois casos:
                //
                // 1. zero à esquerda (CPF/CEP/telefone) — o Excel converteria
                //    para número e "07..." viraria "7...";
                //
                // 2. texto começando com = + - @, que o Excel (e o
                //    PhpSpreadsheet ao gravar) trata como FÓRMULA. Não é
                //    hipótese: a base tem 6 complementos assim ('=', '+',
                //    '=======', '=-') e a exportação inteira morria com 500
                //    — "Formula Error: Unexpected operator '='" —, derrubando
                //    o relatório por causa de um cadastro digitado errado.
                //    É também o vetor de CSV/formula injection: um cadastro
                //    com `=HYPERLINK(...)` viraria fórmula ativa na máquina de
                //    quem abrisse a planilha.
                $texto = is_string($valor)
                    && (preg_match('/^0\d+$/', $valor) === 1 || preg_match('/^[=+\-@]/', $valor) === 1);

                if ($texto) {
                    $aba->setCellValueExplicit([$i + 1, $l + 2], $valor, DataType::TYPE_STRING);
                } else {
                    $aba->setCellValue([$i + 1, $l + 2], $valor);
                }
            }
        }

        if ($colunas !== []) {
            // getCellByColumnAndRow foi removido no PhpSpreadsheet 5; a
            // conversão índice→letra é o caminho suportado.
            $ultima = Coordinate::stringFromColumnIndex(count($colunas));
            $cabecalho = $aba->getStyle("A1:{$ultima}1");
            $cabecalho->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $cabecalho->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2A54AD');
            $cabecalho->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $aba->getRowDimension(1)->setRowHeight(22);
            $aba->freezePane('A2');
            $aba->setAutoFilter("A1:{$ultima}".max(1, count($linhas) + 1));
            // Largura a partir do RÓTULO, não autoSize: o autoSize percorre
            // todas as células para medir — em 51 mil linhas isso custa tempo
            // e memória, e era ele que AVALIAVA o texto como fórmula. A largura
            // pelo cabeçalho dá quase o mesmo resultado visual de graça.
            foreach ($colunas as $i => $rotulo) {
                $aba->getColumnDimensionByColumn($i + 1)
                    ->setWidth(min(45, max(12, mb_strlen((string) $rotulo) + 4)));
            }
        }

        // O writer só escreve em arquivo/stream; php://temp evita disco.
        $stream = fopen('php://temp', 'r+');
        $writer = new Xlsx($planilha);
        // Segunda barreira contra o defeito que derrubou a exportação em
        // produção: sem pré-cálculo, o writer NÃO avalia nada como fórmula.
        // A primeira barreira é gravar esses valores como texto explícito
        // (acima); esta garante que, mesmo se um caso escapar da regex, o
        // arquivo sai em vez de estourar 500 em cima do usuário.
        // De quebra é mais rápido: não há motor de cálculo rodando.
        $writer->setPreCalculateFormulas(false);
        $writer->save($stream);
        rewind($stream);
        $bytes = (string) stream_get_contents($stream);
        fclose($stream);
        $planilha->disconnectWorksheets();

        return $bytes;
    }
}
