<?php

namespace App\Monitora\Models;
use Illuminate\Database\Eloquent\Model;

class Veiculotipo extends MonitoraModel
{
    protected $fillable = ['id', 'descricao', 'imagem_parado', 'imagem_movimento', 'imagem_acima', 'velocidade_maxima'];
}
