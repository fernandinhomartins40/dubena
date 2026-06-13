<?php

namespace App;

use Session;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Planoconta
 *
 * @property string|null $ATIVO
 * @property string $CODIGO
 * @property string|null $CREATED_AT
 * @property string $CUSTOSVARIAVEIS
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property string $FINALIZADOR
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $INSUMO_VALOR
 * @property string $INVESTIMENTO
 * @property string|null $NATUREZASPED
 * @property int $NIVEL
 * @property string $PAGARRECEBER
 * @property int|null $PAIPLANOCONTA_ID
 * @property string $PROVISAO
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Planoconta[] $children
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Planoconta $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereCUSTOSVARIAVEIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereFINALIZADOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereINSUMOVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereINVESTIMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereNATUREZASPED($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereNIVEL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta wherePAGARRECEBER($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta wherePAIPLANOCONTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta wherePROVISAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Planoconta whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Planoconta extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "grupo_id";

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'ativo', 'insumo_valor',
        'provisao', 'investimento', 'pagarreceber', 'codigo', 'nivel', 'finalizador', 'paiplanoconta_id', 
        'custosvariaveis', 'naturezasped'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function children()
    {
        return $this->hasMany('App\Planoconta', 'paiplanoconta_id', 'id');
    }

    public static function tree()
    {
        return static::with(implode('.', array_fill(0, 100, 'children')))->where('paiplanoconta_id', '=', NULL)->get();
    }

    public static function menusinner($paiplanoconta_id)
    {
        $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('codigo')->where('paiplanoconta_id', '=', $paiplanoconta_id)->get();

        $menuHtml = array();

        //dd($menus);
        foreach ($menus as $menu) {
            if (count($menu->users->where('id', \Auth::user()->id)) > 0) {
                if (count($menu->children) > 0) {
                    array_push($menuHtml, '<li class="dropdown-submenu"><a tabindex="-1" href="#">' . $menu->titulo . '</a>');
                    array_push($menuHtml, '<ul class="dropdown-menu">');
                    foreach (Menu::menusinner($menu->id) as $child) {
                        array_push($menuHtml, $child);
                    }
                    array_push($menuHtml, '</ul>');
                    array_push($menuHtml, '</li>');
                } else {
                    if (substr($menu->descricao, 0, 6) == 'report') {
                        array_push($menuHtml, "<li>" . link_to_route($menu->descricao, $menu->titulo, '', array('target' => '_blank')) . "</li>");
                    } else {
                        array_push($menuHtml, "<li>" . link_to_route($menu->descricao, $menu->titulo) . "</li>");
                    }
                }
            }
        }
        return($menuHtml);
    }

    public static function menus()
    {
        $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('codigo')->where('paiplanoconta_id', '=', NULL)->get();
        $menuHtml = array();
        $firstSubMenu = true;
        //dd($menus);
        foreach ($menus as $menu) {
            if (count($menu->users->where('id', \Auth::user()->id)) > 0) {
                array_push($menuHtml, '<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown">' . $menu->titulo . '<span class="caret"></span></a><ul class="dropdown-menu" role="menu">');
                foreach (Planoconta::menusinner($menu->id) as $child) {
                    array_push($menuHtml, $child);
                }
                array_push($menuHtml, '</ul></li>');
            }
        }

        return($menuHtml);
    }

    public static function menuscheckinner($menus, $nivel)
    {
        // $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('codigo')->where('ativo', true)->where('paiplanoconta_id', '=', $paiplanoconta_id)->get();

        $menuHtml = array();
        $firstSubMenu = true;
        //dd($menus);
        foreach ($menus as $menu) {
            $menu['nivel'] = $nivel;
            array_push($menuHtml, $menu);
            if (count($menu->children) > 0) {
                foreach (Planoconta::menuscheckinner($menu->children, $nivel + 1) as $child) {
                    array_push($menuHtml, $child);
                }
            }
        }
        return($menuHtml);
    }

    public static function menuscheck($pagarreceber = 'A')
    {
        if($pagarreceber == 'A'){
            $menus = static::with(implode('.', array_fill(0, 100, 'children')))
            ->orderBy('codigo')
            ->where('ativo', 1)
            ->where('paiplanoconta_id', '=', NULL)
            ->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->get();
        }else if($pagarreceber == 'R'){
            $menus = static::with(implode('.', array_fill(0, 100, 'children')))
            ->orderBy('codigo')
            ->where('ativo', 1)
            ->whereIn('pagarreceber', ['R','A'])
            ->where('paiplanoconta_id', '=', NULL)
            ->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->get();
        }else if($pagarreceber == 'D'){
            $menus = static::with(implode('.', array_fill(0, 100, 'children')))
            ->orderBy('codigo')
            ->where('ativo', 1)
            ->whereIn('pagarreceber', ['D','A'])
            ->where('paiplanoconta_id', '=', NULL)
            ->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->get();
        }
        

        $menuHtml = array();
        $firstSubMenu = true;
        foreach ($menus as $menu) {
            array_push($menuHtml, $menu);
            if(count($menu->children) > 0){
                foreach (Planoconta::menuscheckinner($menu->children, 1) as $child) {
                    array_push($menuHtml, $child);
                }
            }
        }

        return($menuHtml);
    }

    public static function planoContasFinalizador0()
    {
        $pc = static::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('ativo', 1)->get();
        return($pc);
    }

    public static function menuspermissoes($user_id)
    {
        $menus = array();
        foreach (Planoconta::menuscheck() as $menu) {
            $menu['permitido'] = 'F';
            if (count($menu->users->where('id', intval($user_id))) > 0) {
                $menu['permitido'] = 'T';
            }
            //$menus[$menu->id] = '|'.str_repeat('-', $menu->nivel*5).$menu->titulo.'!'.$menu->permitido;
            //$menu['titulo'] =
            array_push($menus, $menu);
        }
        return $menus;
    }

    public static function menuspermissoesAll($pagarreceber = 'A')
    {
        $menus = array();
        foreach (Planoconta::menuscheck($pagarreceber) as $menu) {
            //$menus[$menu->id] = '|'.str_repeat('-', $menu->nivel*5).$menu->titulo.'!'.$menu->permitido;
            $menu['disabled'] = ($menu->finalizador == 1 ? 'F' : 'T');
            array_push($menus, $menu);
        }
        return $menus;
    }

    public static function receitas($ativo = true, $passar = true)
    {
        $menus = array();
        foreach (Planoconta::planos('R',$ativo,$passar) as $menu) {
            //$menus[$menu->id] = '|'.str_repeat('-', $menu->nivel*5).$menu->titulo.'!'.$menu->permitido;
            $menu['disabled'] = ($menu->finalizador == 1 ? 'F' : 'T');
            array_push($menus, $menu);
        }
        return $menus;
    }

    public static function despesas($ativo = true, $passar = true)
    {
        $menus = array();
        foreach (Planoconta::planos('D',$ativo,$passar) as $menu) {
            //$menus[$menu->id] = '|'.str_repeat('-', $menu->nivel*5).$menu->titulo.'!'.$menu->permitido;
            $menu['disabled'] = ($menu->finalizador == 1 ? 'F' : 'T');
            array_push($menus, $menu);
        }
        return $menus;
    }

    public static function planos($pagarreceber,$ativo = true, $passar = true)
    {
        $menus = static::with(implode('.', array_fill(0, 100, 'children')))
                        ->orderBy('codigo')
                        ->where('paiplanoconta_id', '=', NULL)
                        ->where('grupo_id', Session::get('empresa_padrao')->grupo_id);
        if($ativo)
            $menus = $menus->where('ativo',1);
            
        $menus = $menus->whereIn('pagarreceber', [$pagarreceber, "A"])->get();
        $menuHtml = array();

        $firstSubMenu = true;
        foreach ($menus as $menu) {
            array_push($menuHtml, $menu);
            if($passar){
                if(count($menu->children) > 0){
                    foreach (Planoconta::menuscheckinner($menu->children, 1) as $child) {
                        if($child->pagarreceber==$pagarreceber||$child->pagarreceber=="A"){
                            array_push($menuHtml, $child);
                        }
                    }
                }
            }
        }

        return($menuHtml);
    }

    public function parent()
    {
    return $this->belongsTo('App\Planoconta', 'id', 'paiplanoconta_id');
    }

}
