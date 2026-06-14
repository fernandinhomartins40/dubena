<?php

namespace App\Monitora\Models;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    
  protected $fillable = ['titulo', 'descricao', 'parent_id', 'ordem' ];

  public function users(){
    return $this->belongsToMany('App\User')->withTimestamps();
  }

  public function parent()
  {
      return $this->belongsTo('App\Menu', 'id', 'parent_id');
  }
  public function children()
  {
      return $this->hasMany('App\Menu', 'parent_id', 'id');
  }
  public static function tree() {
    return static::with(implode('.', array_fill(0, 100, 'children')))->where('parent_id', '=', NULL)->get();
  }
  public static function menusinner($parent_id) {
    $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('ordem')->where('parent_id', '=', $parent_id)->get();

    $menuHtml = array();

    //dd($menus);
    foreach($menus as $menu){
      if(count($menu->users->where('id', \Auth::guard('monitora')->user()->id))>0){
        if(count($menu->children)>0){
          array_push($menuHtml, '<li class="dropdown-submenu"><a tabindex="-1" href="#">'.$menu->titulo.'</a>');
          array_push($menuHtml, '<ul class="dropdown-menu">');
          foreach(Menu::menusinner($menu->id) as $child){
              array_push($menuHtml, $child);
          }
          array_push($menuHtml, '</ul>');
          array_push($menuHtml, '</li>');
        } else {
          if(substr($menu->descricao,0,6) =='report'){
            array_push($menuHtml, "<li>".link_to_route($menu->descricao, $menu->titulo)."</li>");
          } else {
            array_push($menuHtml, "<li>".link_to_route($menu->descricao, $menu->titulo)."</li>");
          }
        }
      }
    }
    return($menuHtml);
  }

  public static function menus() {
    $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('ordem')->where('parent_id', '=', NULL)->get();
    $menuHtml = array();
    $firstSubMenu = true;
    //dd($menus);
    foreach($menus as $menu){
      if(count($menu->users->where('id', \Auth::guard('monitora')->user()->id))>0){
        array_push($menuHtml, '<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown">'.$menu->titulo.'<span class="caret"></span></a><ul class="dropdown-menu" role="menu">');
        foreach(Menu::menusinner($menu->id) as $child){
            array_push($menuHtml, $child);
        }
        array_push($menuHtml, '</ul></li>');
      }
    }

    return($menuHtml);
  }
  public static function menuscheckinner($parent_id, $nivel) {
    $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('titulo')->where('parent_id', '=', $parent_id)->get();

    $menuHtml = array();
    $firstSubMenu = true;
    //dd($menus);
    foreach($menus as $menu){
      $menu['nivel']=$nivel;
      array_push($menuHtml, $menu);
      if(count($menu->children)>0){
        foreach(Menu::menuscheckinner($menu->id, $nivel+1) as $child){
            array_push($menuHtml, $child);
        }
      }
    }
    return($menuHtml);
  }

  public static function menuscheck() {
    $menus = static::with(implode('.', array_fill(0, 100, 'children')))->orderBy('ordem')->where('parent_id', '=', NULL)->get();

    $menuHtml = array();
    $firstSubMenu = true;
    foreach($menus as $menu){
      array_push($menuHtml, $menu);
      foreach(Menu::menuscheckinner($menu->id, 1) as $child){
          array_push($menuHtml, $child);
      }
    }

    return($menuHtml);
  }
  public static function menuspermissoes($user_id) {
    $menus = array();
    foreach(Menu::menuscheck() as $menu){
      $menu['permitido']='F';
      if(count($menu->users->where('id', intval($user_id)))>0){
        $menu['permitido']='T';
      }
      //$menus[$menu->id] = '|'.str_repeat('-', $menu->nivel*5).$menu->titulo.'!'.$menu->permitido;
      //$menu['titulo'] =
      array_push($menus, $menu);
    }
    return $menus;
  }
  public static function menuspermissoesAll() {
    $menus = array();
    foreach(Menu::menuscheck() as $menu){
      $menu['permitido']='F';
      //$menus[$menu->id] = '|'.str_repeat('-', $menu->nivel*5).$menu->titulo.'!'.$menu->permitido;
      array_push($menus, $menu);
    }
    return $menus;
  }
}
