<?php

namespace App\Policies;

use Session;
use App\User;
use App\Segmento;
use Illuminate\Auth\Access\HandlesAuthorization;

class SegmentoPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }
    /**
     * Determine whether the user can view the segmento.
     *
     * @param  \App\User  $user
     * @param  \App\Segmento  $segmento
     * @return mixed
     */
    public function view(User $user, Segmento $segmento)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->visualizar == 1;
    }
    
    /**
    * Determine whether the user can create segmentos.
    *
    * @param  \App\User  $user
    * @return mixed
    */
    public function create(User $user)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->criar == 1;
    }
    
    /**
    * Determine whether the user can update the segmento.
    *
    * @param  \App\User  $user
    * @param  \App\Segmento  $segmento
    * @return mixed
    */
    public function update(User $user, Segmento $segmento)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->editar == 1;
    }
    
    /**
    * Determine whether the user can delete the segmento.
    *
    * @param  \App\User  $user
    * @param  \App\Segmento  $segmento
    * @return mixed
    */
    public function delete(User $user, Segmento $segmento)
    {
        if(is_null($this->getPermission()))
            return false;
            
        return $this->getPermission()->deletar == 1;
    }

    private function getPermission()
    {
        return $this->permissoes->where('descricao','segmento.index')->first();
    }
}
