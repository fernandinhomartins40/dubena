package br.inf.qti.movelapp;

/**
 * Created by flavio on 11/06/2014.
 */
public class Usuario {
    public int codigo;
    public String usuario;
    public String senha;

    public Usuario(int codigo, String usuario, String senha){
        this.usuario=usuario;
        this.codigo=codigo;
        this.senha=senha;
    }

    public int getId() { return codigo; }

    public String getUsuario() {
        return usuario;
    }
    public String getSenha(){
        return senha;
    }
    public void setSenha(String senha){
        this.senha = senha;
    }
    public void setId(int id){
        this.codigo = id;
    }

}
