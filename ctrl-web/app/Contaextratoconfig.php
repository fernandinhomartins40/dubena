<?php

namespace App;

use App\Enums\ContaextratoAcao;
use Illuminate\Database\Eloquent\Model;

class Contaextratoconfig extends Model
{
    protected $fillable = ['descricao', 'contamovimentotipo_id', 'conta_id', 'condicaopagamento_id',
        'planoconta_id', 'centrocusto_id', 'cliente_id', 'acao'];

    public function conta()
    {
        return $this->belongsTo('App\Conta');
    }

    public function contaMovimentoTipo()
    {
        return $this->belongsTo('App\Contamovimentotipo', 'contamovimentotipo_id');
    }

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function condicaoPagamento()
    {
        return $this->belongsTo('App\Condicaopagamento', 'condicaopagamento_id');
    }

    public function planoConta()
    {
        return $this->belongsTo('App\Planoconta', 'planoconta_id');
    }

    public function centroCusto()
    {
        return $this->belongsTo('App\Centrocusto', 'centrocusto_id');
    }

    public function contaOrigem()
    {
        return $this->belongsTo('App\Conta', 'contaorigem_id');
    }

    public function acaodescricao()
    {
         return ContaextratoAcao::from($this->acao)->getDesc();
    }
}

