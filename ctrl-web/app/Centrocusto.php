<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Session;

/**
 * App\Centrocusto
 *
 * @property string|null $ATIVO
 * @property string $CODIGO
 * @property string|null $CREATED_AT
 * @property string $DESCRICAO
 * @property int $EMPRESA_ID
 * @property string $FINALIZADOR
 * @property int $GRUPO_ID
 * @property int $ID
 * @property int $NIVEL
 * @property int|null $PAICENTROCUSTO_ID
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Centrocusto[] $children
 * @property-read \App\Empresa $empresa
 * @property-read \App\EmpresasGrupo $empresasGrupo
 * @property-read \App\Centrocusto $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|\Venturecraft\Revisionable\Revision[] $revisionHistory
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereEMPRESAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereFINALIZADOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereGRUPOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereNIVEL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto wherePAICENTROCUSTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Centrocusto whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Centrocusto extends Model
{

    use \App\Services\RevisionsTraitService;

    protected $identity = "empresa_id";

    protected $fillable = ['grupo_id', 'empresa_id', 'descricao', 'ativo', 'codigo', 'nivel', 'finalizador', 'paicentrocusto_id'];

    public function empresasGrupo()
    {
        return $this->belongsTo('App\EmpresasGrupo');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Empresa');
    }

    public function parent()
    {
        return $this->belongsTo('App\Centrocusto', 'id', 'paicentrocusto_id');
    }

    public function children()
    {
        return $this->hasMany('App\Centrocusto', 'paicentrocusto_id', 'id')->orderBy('codigo');
    }

    public static function tree()
    {
        return static::with(implode('.', array_fill(0, 100, 'children')))->where('paicentrocusto_id', '=', NULL)->get();
    }

    public static function menusinner($paicentrocusto_id)
    {
        $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('codigo')->where('paicentrocusto_id', '=', $paicentrocusto_id)->get();

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
        $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('codigo')->where('paicentrocusto_id', '=', NULL)->get();
        $menuHtml = array();
        $firstSubMenu = true;
        //dd($menus);
        foreach ($menus as $menu) {
            if (count($menu->users->where('id', \Auth::user()->id)) > 0) {
                array_push($menuHtml, '<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown">' . $menu->titulo . '<span class="caret"></span></a><ul class="dropdown-menu" role="menu">');
                foreach (Centrocusto::menusinner($menu->id) as $child) {
                    array_push($menuHtml, $child);
                }
                array_push($menuHtml, '</ul></li>');
            }
        }

        return($menuHtml);
    }

    public static function menuscheckinner($menus, $nivel)
    {
        // $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('id')
        //     ->where('paicentrocusto_id', '=', $paicentrocusto_id)->where('ativo', true)->get();

        $menuHtml = array();
        $firstSubMenu = true;
        //dd($menus);
        foreach ($menus as $menu) {
            $menu['nivel'] = $nivel;
            array_push($menuHtml, $menu);
            if (count($menu->children) > 0) {
                foreach (Centrocusto::menuscheckinner($menu->children, $nivel + 1) as $child) {
                    array_push($menuHtml, $child);
                }
            }
        }
        return($menuHtml);
    }

    public static function menuscheck($ativo,$passar)
    {
        $menus = static::with(implode('.', array_fill(0, 100, 'children')))
                        ->orderBy('codigo')
                        ->where('paicentrocusto_id', '=', NULL)
                        ->where('empresa_id', Session::get('empresa_padrao')->id);
        if($ativo)
            $menus = $menus->where('ativo', true);

        $menus = $menus->get();
        $menuHtml = array();
        $firstSubMenu = true;
        foreach ($menus as $menu) {
            array_push($menuHtml, $menu);
            if($passar){
                foreach (Centrocusto::menuscheckinner($menu->children, 1) as $child) {
                    array_push($menuHtml, $child);
                }
            }
        }

        return($menuHtml);
    }

    public static function menuspermissoes($user_id)
    {
        $menus = array();
        foreach (Centrocusto::menuscheck(true,true) as $menu) {
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

    public static function menuspermissoesAll($ativo = true, $passar = true)
    {
        $menus = array();
        foreach (Centrocusto::menuscheck($ativo,$passar) as $menu) {
            //$menus[$menu->id] = '|'.str_repeat('-', $menu->nivel*5).$menu->titulo.'!'.$menu->permitido;
            $menu['disabled'] = ($menu->finalizador == 1 ? 'F' : 'T');
            array_push($menus, $menu);
        }
        return $menus;
    }

    public static function centroCustoFinalizador0()
    {
        $menus = static::orderBy('codigo')
                        ->where('empresa_id', Session::get('empresa_padrao')->id)
                        ->where('finalizador', 0)->get();

        return($menus);
    }

}
