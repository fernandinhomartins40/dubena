<?php

namespace App\Models\Geografico;

use Illuminate\Database\Eloquent\Model;

/**
 * Logradouro do cadastro OFICIAL (CNEFE — IBGE, Censo 2022).
 *
 * Referência nacional, sem dono: não confundir com `Rua`, que é o cadastro do
 * GRUPO (o que o operador digitou, com os erros que ele digitou). Esta tabela
 * é aquilo contra o que o cadastro manual é comparado.
 *
 * `nome_busca` é a chave de casamento, produzida por
 * `NormalizadorTexto::logradouro()` — a mesma função que o
 * `scripts/cnefe_importar.py` espelha ao gerar o CSV.
 */
class LogradouroOficial extends Model
{
    protected $table = 'logradouros_oficiais';

    protected $fillable = [
        'cod_ibge', 'tipo', 'nome', 'bairro', 'cep', 'nome_busca',
        'numero_min', 'numero_max', 'enderecos', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'cod_ibge' => 'integer',
            'numero_min' => 'integer',
            'numero_max' => 'integer',
            'enderecos' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** Nome apresentável, com o tipo na frente ("RUA" + "CLAUDIO COUTINHO"). */
    public function getNomeCompletoAttribute(): string
    {
        return trim(($this->tipo ?? '').' '.$this->nome);
    }

    /**
     * O número informado cai na faixa recenseada?
     *
     * NULL quando não há faixa conhecida. A faixa é o que o Censo observou, não
     * a extensão legal da via — serve para ALERTAR sobre número improvável, não
     * para recusar cadastro: construção nova legitimamente fica fora dela.
     */
    public function numeroPlausivel(?int $numero): ?bool
    {
        if ($numero === null || $this->numero_min === null || $this->numero_max === null) {
            return null;
        }

        // Margem de 20%: a via cresce depois do recenseamento.
        $folga = max(50, (int) (($this->numero_max - $this->numero_min) * 0.2));

        return $numero >= max(1, $this->numero_min - $folga)
            && $numero <= $this->numero_max + $folga;
    }
}
