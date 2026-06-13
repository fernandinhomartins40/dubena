<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\ClienteImportacao
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\ClienteEndereco[] $enderecos
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string $nome
 * @property string|null $cpf
 * @property string|null $cnpj
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereCnpj($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereCpf($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereNome($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereUpdatedAt($value)
 * @property string|null $email
 * @property int $ativo
 * @property int|null $user_id
 * @property string|null $datanascimento
 * @property int|null $enderecopadrao_id
 * @property string|null $sexo
 * @property string|null $pushregistration_id
 * @property string $primeironome
 * @property string $appbuildnumber
 * @property int $acessadonovodispositivo
 * @property-read \App\ClienteEndereco|null $favoriteAddress
 * @property-read \App\Pedido $pedido
 * @property-read \App\ClienteTelefone $phone
 * @property-read \App\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereAcessadonovodispositivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereAtivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereDatanascimento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereEnderecopadraoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao wherePrimeironome($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao wherePushregistrationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereSexo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\ClienteImportacao whereUserId($value)
 */
class ClienteImportacao extends Model
{

    protected $hidden = [
        "pushregistration_id"
    ];

    protected $table = 'clienteimportacoes';

    protected $fillable = [
        'nome',
        'cpf',
        'user_id',
        'datanascimento',
        "enderecopadrao_id",
        "sexo",
        "ativo",
        "pushregistration_id",
        "primeironome",
        "acessadonovodispositivo",
        "telefoneantigo",
        "appbuildnumber",
        "conveniado",
        "gasdopovo"
    ];

    public function enderecos()
    {
        return $this->hasMany(ClienteEndereco::class)->whereAtivo(true);
    }

    public function favoriteAddress()
    {
        return $this->belongsTo(ClienteEndereco::class, 'enderecopadrao_id');
    }

    public function phone()
    {
        return $this->hasOne(ClienteTelefone::class, "cliente_id");
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}
