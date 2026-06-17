<?php

namespace App;

use DB;
use Session;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Menu
 *
 * @property string|null $CREATED_AT
 * @property string|null $DESCRICAO
 * @property int $ID
 * @property int|null $ORDEM
 * @property int|null $PARENT_ID
 * @property string $TITULO
 * @property string|null $UPDATED_AT
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Menu[] $children
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Menuuser[] $menuuser
 * @property-read \App\Menu $parent
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereDESCRICAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereORDEM($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu wherePARENTID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereTITULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Menu extends Model
{

    protected $fillable = ['titulo', 'descricao', 'parent_id', 'ordem'];

    public function menuuser()
    {
        return $this->hasMany('App\Menuuser');
    }

    public function parent()
    {
        return $this->belongsTo('App\Menu', 'id', 'parent_id');
    }

    public function children()
    {
        return $this->hasMany('App\Menu', 'parent_id', 'id')->orderBy('id');
    }

    public static function tree()
    {
        return static::with(implode('.', array_fill(0, 100, 'children')))->where('parent_id', '=', NULL)->get();
    }

    public static function menusinner($menus, $ids)
    {
        // $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('ordem')->where('parent_id', '=', $parent_id)->whereIn('id',$ids)->get();

        $menuHtml = array();
        // dd(in_array('36',$ids));
        foreach ($menus as $menu) {
            if (in_array($menu->id, $ids)) {
                if (count($menu->children) > 0) {
                    array_push($menuHtml, '<li class="dropdown-submenu"><a tabindex="-1" href="#">' . $menu->titulo . '</a>');
                    array_push($menuHtml, '<ul class="dropdown-menu">');
                    foreach (Menu::menusinner($menu->children, $ids) as $child) {
                        array_push($menuHtml, $child);
                    }
                    array_push($menuHtml, '</ul>');
                    array_push($menuHtml, '</li>');
                } else {
                    // (legado) os dois ramos report/não-report eram idênticos — simplificado.
                    array_push($menuHtml, "<li>" . link_to_route($menu->descricao, $menu->titulo) . "</li>");
                }
            }
        }
        return ($menuHtml);
    }

    public static function menus()
    {
        $ids = DB::table('menuusers')->where([
            ['user_id', \Auth::user()->id],
            ['empresa_id', Session::get('empresa_padrao')->id]
        ])->pluck('menu_id')->toArray();

        $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('id')->where('parent_id', '=', NULL)->whereIn('id', $ids)->get();

        $menuHtml = array();
        $firstSubMenu = true;

        foreach ($menus as $menu) {
            if (in_array($menu->id, $ids)) {
                array_push($menuHtml, '<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown">' . $menu->titulo . '<span class="caret"></span></a><ul class="dropdown-menu" role="menu">');
                foreach (Menu::menusinner($menu->children, $ids) as $child) {
                    array_push($menuHtml, $child);
                }
                array_push($menuHtml, '</ul></li>');
            }
        }

        return ($menuHtml);
    }

    // REMOVIDO (F4 Bloco A / PRD 11 §6 — código morto): menuscheck()/menuscheckinner()
    // estavam comentados e menuspermissoes()/menuspermissoesAll() os chamavam, além de
    // usarem $menu->users (relação inexistente neste Model) → fatal se invocados. Não
    // havia chamadores no ERP (os usos de Menu::menuspermissoes* são do módulo Monitora,
    // que tem seu PRÓPRIO App\Monitora\Models\Menu, intacto).
}
