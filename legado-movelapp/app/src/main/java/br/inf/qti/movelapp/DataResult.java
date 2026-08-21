package br.inf.qti.movelapp;

import org.json.JSONArray;

/**
 * Created by fl_on on 02/06/2017.
 */

public class DataResult {
    private boolean status;
    private String msg;
    private JSONArray dados;

    public DataResult(boolean status, String msg) {
        this.status = status;
        this.msg = msg;
    }

    public boolean getStatus() {
        return status;
    }

    public String getMsg() {
        return msg;
    }
    public void setStatus(boolean status){
        this.status = status;
    }
    public void setMsg(String msg){
        this.msg = msg;
    }
    public JSONArray getDados() { return dados; }
    public void setDados(JSONArray dados) { this.dados = dados; }
}
