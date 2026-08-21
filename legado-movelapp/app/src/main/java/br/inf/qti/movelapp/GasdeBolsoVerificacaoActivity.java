package br.inf.qti.movelapp;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.net.ConnectivityManager;
import android.os.AsyncTask;
import android.os.Bundle;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import java.util.HashMap;
import java.util.Map;


public class GasdeBolsoVerificacaoActivity extends Activity {

    private static verificaGasdeBolsoTask task;
    public String gasdebolso;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_gasdebolso_verificacao);
        gasdebolso = getIntent().getStringExtra("gasdebolso");

        //Verificar conexão
        if (!Utils.verificaConexao(this.getApplicationContext())) {
            Toast.makeText(GasdeBolsoVerificacaoActivity.this, "Operação não realizada - sem conexão com Internet", Toast.LENGTH_LONG).show();
            Intent returnIntent = new Intent();
            setResult(RESULT_OK, returnIntent);
            finish();

        } else {
            task = new verificaGasdeBolsoTask();
            task.execute();
        }
    }


    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        
        // Inflate the menu; this adds items to the action bar if it is present.
        getMenuInflater().inflate(R.menu.gasde_bolso_verificacao, menu);
        return true;
    }

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        // Handle action bar item clicks here. The action bar will
        // automatically handle clicks on the Home/Up button, so long
        // as you specify a parent activity in AndroidManifest.xml.
        int id = item.getItemId();
        if (id == R.id.action_settings) {
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    public class verificaGasdeBolsoTask extends AsyncTask<String, String, String> {

        @Override
        protected String doInBackground(String... strings) {

            String retorno = "";
            DataResult res;

            res = confirmaGasdeBolso();
            if(!res.getStatus())
                retorno = res.getMsg();
            else {
                retorno = "";
            }
            return retorno;
        }

        @Override
        protected void onPreExecute() {
            super.onPreExecute();
            ProgressBar prog = (ProgressBar) findViewById(R.id.import_progress);
            prog.setVisibility(View.VISIBLE);
        }

        @Override
        protected void onPostExecute(String result) {
            if(result.length() > 0) {
                AlertDialog.Builder builder = new AlertDialog.Builder(GasdeBolsoVerificacaoActivity.this);
                builder.setTitle("Erro ao consultar vale gás " + gasdebolso);
                builder.setMessage(result);

                builder.setPositiveButton("OK", new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int which) {
                        dialog.dismiss();
                        Intent returnIntent = new Intent();
                        setResult(RESULT_CANCELED, returnIntent);
                        finish();
                    }
                });
                AlertDialog alert = builder.create();
                alert.show();
                //Toast.makeText(PedidoRecepcaoActivity.this, result, Toast.LENGTH_LONG).show();
            } else {
                Intent returnIntent = new Intent();
                setResult(RESULT_OK, returnIntent);
                finish();
            }
        }


        @Override
        protected void onProgressUpdate(String... values) {
            TextView txtProgresso = (TextView) findViewById(R.id.txtImportProgress);
            txtProgresso.setText(String.valueOf(values[1]));
            if (values[0].equals("0")) {
            } else if (values[0].equals("1")) {
            } else if (values[0].equals("2")) {
            }
        }

        public DataResult confirmaGasdeBolso() {
            try {
                DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                Config config = dbHandler.getConfig();
                Map<String, String> params = new HashMap<String, String>();
                params.put("valegas", gasdebolso);
                DataResult res = Utils.getDataFromServer("getValeGas", getApplicationContext(), config, params);
                if(res.getStatus()) {
                    JSONObject json = new JSONObject(res.getMsg());
                    String status = json.getString("status");
                    String msg = json.getString("dados");
                    if (status.equals("OK")) {
                        res.setStatus(true);
                        res.setMsg("");
                    } else {
                        res.setStatus(false);
                        res.setMsg(msg);
                    }
                }else {
                    res.setStatus(false);
                }
                return res;
            } catch (Throwable t) {
                return new DataResult(false, t.toString());
            }
        }

    }

}
