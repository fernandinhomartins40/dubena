package br.inf.qti.movelapp;

/**
 * Created by fl_on on 02/06/2017.
 */

public class CoordenadaResult {
    private boolean achou;
    private String msg;
    private double latitude;
    private double longitude;

    public CoordenadaResult(boolean achou, double latitude, double longitude, String msg) {
        this.achou = achou;
        this.latitude = latitude;
        this.longitude = longitude;
        this.msg = msg;
    }

    public boolean getAchou() {
        return achou;
    }

    public void setAchou(boolean achou) {
        this.achou = achou;
    }

    public String getMsg() {
        return msg;
    }

    public void setMsg(String msg) {
        this.msg = msg;
    }

    public double getLatitude() {
        return latitude;
    }

    public void setLatitude(double latitude) {
        this.latitude = latitude;
    }

    public double getLongitude() {
        return longitude;
    }

    public void setLongitude(int longitude) {
        this.longitude = longitude;
    }
}
