package br.inf.qti.movelapp;

/**
 * Created by fl_on on 31/05/2017.
 */

public class Config {
    public int codigo;
    public String secret;
    public String token;
    public String usuario;
    public String url;
    public String cliente_id;
    public String revenda_id;

    public Config(int codigo, String secret, String token, String usuario, String url, String cliente_id, String revenda_id){
        this.codigo = codigo;
        this.secret = secret;
        this.token = token;
        this.usuario = usuario;
        this.url = url;
        this.cliente_id = cliente_id;
        this.revenda_id = revenda_id;
    }

    public Config(int codigo){
        this.codigo=codigo;
    }

    public int getId() { return codigo; }

    public void setId(int id){
        this.codigo = id;
    }

    public String getSecret() {
        return secret;
    }

    public void setSecret(String secret) {
        this.secret = secret;
    }

    public String getToken() {
        return token;
    }

    public void setToken(String token) {
        this.token = token;
    }

    public String getUsuario() {
        return usuario;
    }

    public void setUsuario(String usuario) {
        this.usuario = usuario;
    }

    public String getUrl() {
        return url;
    }

    public void setUrl(String url) {
        this.url = url;
    }

    public String getCliente_id() {
        return cliente_id;
    }

    public void setCliente_id(String cliente_id) {
        this.cliente_id = cliente_id;
    }

    public String getRevenda_id() {
        return revenda_id;
    }

    public void setRevenda_id(String revenda_id) {
        this.revenda_id = revenda_id;
    }
}
