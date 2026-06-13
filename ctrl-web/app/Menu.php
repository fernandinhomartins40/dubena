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
                    if (substr($menu->descricao, 0, 6) == 'report') {
                        array_push($menuHtml, "<li>" . link_to_route($menu->descricao, $menu->titulo) . "</li>");
                    } else {
                        array_push($menuHtml, "<li>" . link_to_route($menu->descricao, $menu->titulo) . "</li>");
                    }
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

    // public static function menuscheckinner($parent_id, $nivel) {
    //   $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('titulo')->where('parent_id', '=', $parent_id)->get();

    //   $menuHtml = array();
    //   $firstSubMenu = true;
    //   foreach($menus as $menu){
    //     $menu['nivel']=$nivel;
    //     array_push($menuHtml, $menu);
    //     if(count($menu->children)>0){
    //       foreach(Menu::menuscheckinner($menu->id, $nivel+1) as $child){
    //           array_push($menuHtml, $child);
    //       }
    //     }
    //   }
    //   return($menuHtml);
    // }

    // public static function menuscheck($passar = true) {
    //   $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('ordem')->where('parent_id', '=', NULL)->get();

    //   $menuHtml = array();
    //   $firstSubMenu = true;
    //   foreach($menus as $menu){
    //     array_push($menuHtml, $menu);
    //     if($passar){
    //       foreach(Menu::menuscheckinner($menu->id, 1) as $child){
    //           array_push($menuHtml, $child);
    //       }
    //     }
    //   }

    //   return($menuHtml);
    // }

    public static function menuspermissoes($user_id)
    {
        $menus = array();
        foreach (Menu::menuscheck() as $menu) {
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

    public static function menuspermissoesAll($passar = true)
    {
        $menus = array();
        foreach (Menu::menuscheck($passar) as $menu) {
            $menu['permitido'] = 'F';
            //$menus[$menu->id] = '|'.str_repeat('-', $menu->nivel*5).$menu->titulo.'!'.$menu->permitido;
            array_push($menus, $menu);
        }
        return $menus;
    }
}
