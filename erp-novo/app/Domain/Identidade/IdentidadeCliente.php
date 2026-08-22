<?php

namespace App\Domain\Identidade;

use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteIdentidade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extração e comparação de traços de identidade do cliente.
 *
 * Responde a UMA pergunta: dados nome/telefone/endereço/documento, qual cliente
 * já existente é provavelmente esta mesma pessoa — e com quanta confiança.
 */
class IdentidadeCliente
{
    /**
     * Sincroniza os traços de um cliente na tabela de identidades.
     *
     * Idempotente: chamado a cada criação/edição, refaz o conjunto de traços do
     * cliente. Sem isto, um cliente que corrige o telefone continuaria sendo
     * encontrado pelo número antigo.
     */
    public function sincronizar(Cliente $cliente, ?string $origem = null): void
    {
        $tracos = $this->tracosDoCliente($cliente);

        DB::transaction(function () use ($cliente, $tracos, $origem) {
            ClienteIdentidade::query()->where('cliente_id', $cliente->id)->delete();

            foreach ($tracos as $tipo => $valores) {
                foreach ((array) $valores as $valor) {
                    if ($valor === '' || $valor === null) {
                        continue;
                    }
                    ClienteIdentidade::query()->create([
                        'empresa_id' => $cliente->empresa_id,
                        'cliente_id' => $cliente->id,
                        'tipo' => $tipo,
                        'valor' => $valor,
                        'origem' => $origem,
                        // Telefone que veio do app já passou por SMS do Firebase.
                        'verificado' => $tipo === 'telefone' && $origem === 'app',
                    ]);
                }
            }
        });
    }

    /**
     * Traços extraídos de um cliente já persistido.
     *
     * @return array<string, list<string>>
     */
    public function tracosDoCliente(Cliente $cliente): array
    {
        $telefones = $cliente->relationLoaded('telefones')
            ? $cliente->telefones
            : $cliente->telefones()->get();

        return $this->tracos([
            'nome' => $cliente->nome,
            'cpf' => $cliente->cpf,
            'cnpj' => $cliente->cnpj,
            'email' => $cliente->email,
            'endereco' => $cliente->endereco,
            'numero' => $cliente->numero,
            'cidade_id' => $cliente->cidade_id,
            'telefones' => $telefones->pluck('telefone')->all(),
        ]);
    }

    /**
     * O cliente no formato de PAYLOAD, para poder ser comparado com os demais.
     *
     * `candidatos()` recebe o payload que chega na porta de cadastro; para
     * comparar um cliente já persistido contra a base (varredura do passivo),
     * ele precisa primeiro virar payload.
     *
     * @return array<string, mixed>
     */
    public function tracosParaConsulta(Cliente $cliente): array
    {
        $telefones = $cliente->relationLoaded('telefones')
            ? $cliente->telefones
            : $cliente->telefones()->get();

        return [
            'nome' => $cliente->nome,
            'cpf' => $cliente->cpf,
            'cnpj' => $cliente->cnpj,
            'email' => $cliente->email,
            'endereco' => $cliente->endereco,
            'numero' => $cliente->numero,
            'cidade_id' => $cliente->cidade_id,
            'telefones' => $telefones->pluck('telefone')->all(),
        ];
    }

    /**
     * Traços a partir de um payload cru (o que chega na porta de cadastro).
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, list<string>>
     */
    public function tracos(array $dados): array
    {
        $tracos = [];

        if ($nome = NormalizadorTexto::nomeFonetico($dados['nome'] ?? null)) {
            $tracos['nome_fonetico'] = [$nome];
        }

        foreach (['cpf' => 11, 'cnpj' => 14] as $doc => $tamanho) {
            // `documento()` restaura o zero a esquerda que planilha e campo
            // numerico comem — sem isso o mesmo CPF vira duas pessoas.
            $v = NormalizadorTexto::documento($dados[$doc] ?? null, $tamanho);
            // Fora do tamanho exato e lixo de digitacao e casaria errado.
            if (strlen($v) === $tamanho) {
                $tracos[$doc] = [$v];
            }
        }

        if ($email = NormalizadorTexto::basico($dados['email'] ?? null)) {
            $tracos['email'] = [$email];
        }

        // Telefones: aceita string única ou lista.
        $fones = [];
        foreach ((array) ($dados['telefones'] ?? []) as $fone) {
            if ($f = NormalizadorTexto::telefone(is_array($fone) ? ($fone['telefone'] ?? null) : $fone)) {
                $fones[] = $f;
            }
        }
        if ($f = NormalizadorTexto::telefone($dados['telefone'] ?? null)) {
            $fones[] = $f;
        }
        if ($fones !== []) {
            $tracos['telefone'] = array_values(array_unique($fones));
        }

        // Endereço só vale com cidade: "rua das flores 100" existe em toda
        // cidade do país, e sem o município casaria clientes de lugares
        // diferentes.
        $endereco = NormalizadorTexto::endereco($dados['endereco'] ?? null, $dados['numero'] ?? null);
        if ($endereco !== '' && ! empty($dados['cidade_id'])) {
            $tracos['endereco'] = [$dados['cidade_id'].'|'.$endereco];
        }

        return $tracos;
    }

    /**
     * Candidatos a "mesma pessoa", com escore, para um payload de cadastro.
     *
     * @param  array<string, mixed>  $dados
     * @return Collection<int, ResultadoIdentidade>
     */
    public function candidatos(int $empresaId, array $dados, ?int $ignorarClienteId = null): Collection
    {
        $tracos = $this->tracos($dados);
        if ($tracos === []) {
            return collect();
        }

        // 1) Busca larga: todo cliente que compartilhe QUALQUER traço exato.
        //    O índice (empresa_id, tipo, valor) sustenta esta consulta.
        $condicoes = [];
        foreach ($tracos as $tipo => $valores) {
            foreach ($valores as $valor) {
                $condicoes[] = [$tipo, $valor];
            }
        }

        $encontrados = ClienteIdentidade::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($condicoes) {
                foreach ($condicoes as [$tipo, $valor]) {
                    $q->orWhere(fn ($w) => $w->where('tipo', $tipo)->where('valor', $valor));
                }
            })
            ->when($ignorarClienteId, fn ($q) => $q->where('cliente_id', '<>', $ignorarClienteId))
            ->get();

        if ($encontrados->isEmpty()) {
            return collect();
        }

        // 2) Pontua cada cliente candidato somando os traços que casaram.
        $clientes = Cliente::query()
            ->whereIn('id', $encontrados->pluck('cliente_id')->unique())
            // Um cadastro já absorvido por outro não é candidato: o vencedor é
            // que deve aparecer, senão a consolidação encadearia.
            ->whereNotIn('id', fn ($q) => $q->select('cliente_id')->from('cliente_vinculos'))
            ->get()
            ->keyBy('id');

        return $encontrados
            ->groupBy('cliente_id')
            ->map(function (Collection $tracosDoCandidato, $clienteId) use ($clientes, $dados) {
                $candidato = $clientes->get($clienteId);
                if ($candidato === null) {
                    return null;
                }

                return $this->pontuar($candidato, $tracosDoCandidato, $dados);
            })
            ->filter()
            ->sortByDesc(fn (ResultadoIdentidade $r) => $r->escore)
            ->values();
    }

    /**
     * Os dois lados têm documento preenchido e eles são DIFERENTES?
     *
     * Só conta quando ambos têm o documento: ausência não é divergência — 90,5%
     * da base não tem CPF, e tratar o vazio como conflito vetaria quase tudo.
     *
     * @param  array<string, mixed>  $dados
     */
    private function documentosConflitam(Cliente $candidato, array $dados): bool
    {
        foreach (['cpf' => 11, 'cnpj' => 14] as $campo => $tamanho) {
            $meu = NormalizadorTexto::documento($dados[$campo] ?? null, $tamanho);
            $dele = NormalizadorTexto::documento($candidato->{$campo}, $tamanho);

            if (strlen($meu) === $tamanho && strlen($dele) === $tamanho && $meu !== $dele) {
                return true;
            }
        }

        return false;
    }

    /**
     * Escore de um candidato: soma dos pesos dos traços que casaram.
     *
     * @param  Collection<int, ClienteIdentidade>  $casados
     * @param  array<string, mixed>  $dados
     */
    private function pontuar(Cliente $candidato, Collection $casados, array $dados): ResultadoIdentidade
    {
        $escore = 0;
        $motivos = [];
        $tipos = $casados->pluck('tipo')->unique();

        if ($tipos->contains('cpf')) {
            $escore += PesoTraco::CPF;
            $motivos[] = 'CPF idêntico';
        }

        if ($tipos->contains('cnpj')) {
            $escore += PesoTraco::CNPJ;
            $motivos[] = 'CNPJ idêntico';
        }

        if ($tipos->contains('telefone')) {
            $verificado = $casados->firstWhere('tipo', 'telefone')?->verificado;
            $escore += $verificado ? PesoTraco::TELEFONE_VERIFICADO : PesoTraco::TELEFONE;
            $motivos[] = $verificado ? 'Telefone verificado idêntico' : 'Telefone idêntico';
        }

        if ($tipos->contains('email')) {
            $escore += PesoTraco::EMAIL;
            $motivos[] = 'E-mail idêntico';
        }

        if ($tipos->contains('endereco')) {
            $escore += PesoTraco::ENDERECO;
            $motivos[] = 'Mesmo endereço';
        }

        // Nome entra por SIMILARIDADE, não por igualdade: é o que pega o erro
        // de digitação e o nome parcial, que a busca exata acima não alcança.
        //
        // Três degraus, porque a força da evidência é bem diferente:
        //   idêntico letra a letra > foneticamente muito parecido > parecido.
        $similaridade = NormalizadorTexto::similaridadeNome($dados['nome'] ?? null, $candidato->nome);
        $nomeExato = NormalizadorTexto::nome($dados['nome'] ?? null) !== ''
            && NormalizadorTexto::nome($dados['nome'] ?? null) === NormalizadorTexto::nome($candidato->nome);

        if ($nomeExato) {
            $escore += PesoTraco::NOME_EXATO;
            $motivos[] = 'Nome idêntico';
        } elseif ($similaridade >= PesoTraco::SIMILARIDADE_FORTE) {
            $escore += PesoTraco::NOME_FORTE;
            $motivos[] = sprintf('Nome muito parecido (%d%%)', (int) round($similaridade * 100));
        } elseif ($similaridade >= PesoTraco::SIMILARIDADE_MINIMA) {
            $escore += PesoTraco::NOME_FRACO;
            $motivos[] = sprintf('Nome parecido (%d%%)', (int) round($similaridade * 100));
        }

        // NOME SOZINHO NÃO IDENTIFICA NINGUÉM.
        //
        // Medido ao calibrar: com nome idêntico valendo 75, a varredura da base
        // devolveu 73.893 pares cujo ÚNICO traço em comum era o nome — todos os
        // homônimos de uma base com milhares de "MARIA SILVA". Isso afogaria a
        // fila de revisão e a tornaria inútil.
        //
        // O nome é um QUALIFICADOR: ele confirma um candidato que já apareceu
        // por telefone, documento, e-mail ou endereço. Sem essa corroboração,
        // não há caso a analisar.
        $temOutroTraco = $tipos->intersect(['cpf', 'cnpj', 'telefone', 'email', 'endereco'])->isNotEmpty();
        if (! $temOutroTraco) {
            $escore = 0;
            $motivos = [];
        }

        // DOCUMENTO DIFERENTE VETA a fusão automática.
        //
        // Dois CPFs distintos são a evidência mais forte de que são pessoas
        // diferentes — mais forte que qualquer coincidência de nome, telefone
        // ou endereço (pai e filho homônimos na mesma casa, com o mesmo
        // telefone, existem). O par ainda vai para revisão, onde uma pessoa
        // olha; o que não pode é fundir sozinho.
        if ($this->documentosConflitam($candidato, $dados)) {
            $escore = min($escore, PesoTraco::LIMIAR_AUTOMATICO - 1);
            $motivos[] = 'ATENÇÃO: documentos diferentes';
        }

        return new ResultadoIdentidade(
            cliente: $candidato,
            escore: $escore,
            motivos: $motivos,
            similaridadeNome: $similaridade,
        );
    }
}
