<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Menu
 *
 * @mixin \Eloquent
 * @property int $id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property int|null $parent_id
 * @property string $titulo
 * @property string|null $descricao
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereDescricao($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereTitulo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Menu whereUpdatedAt($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Menu[] $children
 */
class Menu extends Model
{
    protected $table = "menus";

    protected $fillable = ['titulo', 'descricao', 'parent_id', 'ordem'];


    public function children()
    {
        return $this->hasMany('App\Menu', 'parent_id', 'id')->orderBy('id');
    }

    public static function tree()
    {
        return static::with(implode('.', array_fill(0, 100, 'children')))->where('parent_id', '=', NULL)->get();
    }

    private static function makeHtmlMenu($menu, $hasChildrens, $submenu)
    {
        if (! $hasChildrens) {
            return '<a href="' . url($menu->descricao) . '" class="dropdown-item">' . $menu->titulo . '</a>';
        } else {
            return '<a href="#" class="nav-link dropdown-toggle ' . ($submenu ? 'submenu' : '') .  '" id="menu-' . $menu->id .
                '" data-toggle="dropdown" aria-expanded="false">' .
                $menu->titulo . '<span class="caret"></span></a>';
        }
    }

    public static function menus($menus = null)
    {
        $menuHtml = '';
        $type = "submenu";
        if ($menus === null) {
            $type = "menu";
            $childrens = implode('.', array_fill(0, 10, 'children'));
            $menus = static::with($childrens)->orderBy('id')
                ->where('parent_id', '=', NULL)->get();
        }

        foreach ($menus as $menu) {
            $hasChildrens = count($menu->children) > 0;

            if ($type === "menu") {
                $menuHtml .= '<li class="nav-item dropdown">';
            } elseif ($type === "submenu" && $hasChildrens) {
                $menuHtml .= '<li class="dropdown-submenu">';
            }

            $menuHtml .= static::makeHtmlMenu($menu, $hasChildrens, $type === "submenu");

            if ($hasChildrens) {
                $menuHtml .= '<ul class="dropdown-menu ' .
                    '"  aria-labelledby="menu-' . $menu->id . '">' . static::menus($menu->children) . '</ul>';
            }

            if ($type === "menu" || $hasChildrens) {
                $menuHtml .= '</li>';
            }
        }

        return $menuHtml;
    }
}
