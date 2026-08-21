package br.inf.qti.movelapp;

import android.app.AlertDialog;
import android.content.DialogInterface;
import android.content.Intent;
import android.os.AsyncTask;
import android.os.Bundle;
//import android.support.design.widget.FloatingActionButton;
//import android.support.design.widget.Snackbar;
//import android.support.v7.app.AppCompatActivity;
//import android.support.v7.widget.Toolbar;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
//import android.widget.Button;
import android.widget.ImageButton;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import org.json.JSONObject;

import java.util.HashMap;
import java.util.Map;

public class MeLigueActivity extends AppCompatActivity {

    public ImageButton btnEnviar;
    private static enviaMeligueTask task;
    private String msg;
    public TextView texto;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_me_ligue);
        //Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        //setSupportActionBar(toolbar);

        //Verificar conexão
        if (!Utils.verificaConexao(this.getApplicationContext())) {
            Toast.makeText(MeLigueActivity.this, "Operação não realizada - sem conexão com Internet", Toast.LENGTH_LONG).show();
            Intent returnIntent = new Intent();
            setResult(RESULT_OK, returnIntent);
            finish();
        }
        texto = (TextView) findViewById(R.id.txtMeligue);
        btnEnviar = (ImageButton) findViewById(R.id.btnEnviarMeligue);
        btnEnviar.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                msg = texto.getText().toString();
                task = new enviaMeligueTask();
                task.execute();
            }
        });

    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {

        // Inflate the menu; this adds items to the action bar if it is present.
        getMenuInflater().inflate(R.menu.meligue, menu);
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

    public class enviaMeligueTask extends AsyncTask<String, String, String> {

        @Override
        protected String doInBackground(String... strings) {

            String retorno = "";
            DataResult res;

            res = enviaMsg();
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
        }

        @Override
        protected void onPostExecute(String result) {
            if(result.length() > 0) {
                AlertDialog.Builder builder = new AlertDialog.Builder(MeLigueActivity.this);
                builder.setTitle("Erro ao enviar mensagem");
                builder.setMessage(result);

                builder.setPositiveButton("OK", new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int which) {
                        dialog.dismiss();
                        Intent returnIntent = new Intent();
                        setResult(RESULT_OK, returnIntent);
                        finish();
                    }
                });
                AlertDialog alert = builder.create();
                alert.show();
            } else {
                Toast.makeText(MeLigueActivity.this, "Mensagem enviada com sucesso", Toast.LENGTH_LONG).show();
                Intent returnIntent = new Intent();
                setResult(RESULT_OK, returnIntent);
                finish();
            }
        }


        @Override
        protected void onProgressUpdate(String... values) {
        }

        public DataResult enviaMsg() {
            try {
                DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                Config config = dbHandler.getConfig();
                Map<String, String> params = new HashMap<String, String>();
                params.put("msg", msg);
                DataResult res = Utils.getDataFromServer("setAndroidMensagem", getApplicationContext(), config, params);
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
