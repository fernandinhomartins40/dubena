package br.inf.qti.movelapp;

import android.app.AlertDialog;
import android.content.DialogInterface;
import android.content.Intent;
import android.os.AsyncTask;
import android.os.Bundle;
//import android.support.v7.app.AppCompatActivity;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.widget.ArrayAdapter;
import android.widget.ImageButton;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import org.json.JSONObject;

import java.util.HashMap;
import java.util.LinkedList;
import java.util.Map;

public class VeiculoActivity extends AppCompatActivity {

    public ImageButton btnEnviar;
    private static enviaVeiculoTask task;
    public TextView txtTitulo;
    Spinner veiculoSpinner;
    public int veiculoId;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_veiculo);
        //Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        //setSupportActionBar(toolbar);

        //Verificar conexão
        if (!Utils.verificaConexao(this.getApplicationContext())) {
            Toast.makeText(VeiculoActivity.this, "Operação não realizada - sem conexão com Internet", Toast.LENGTH_LONG).show();
            Intent returnIntent = new Intent();
            setResult(RESULT_OK, returnIntent);
            finish();
        }
        veiculoSpinner = (Spinner) findViewById(R.id.spinVeiculo);
        txtTitulo = (TextView) findViewById((R.id.txtTituloPlaca));
        DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
        Veiculo veiculo = dbHandler.getVeiculoAtivo();
        txtTitulo.setText(veiculo.getPlaca()  + " "  + veiculo.toString());
        carregarVeiculos();
        veiculoSpinner.requestFocus();

        btnEnviar = (ImageButton) findViewById(R.id.btnEnviarPlaca);
        btnEnviar.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                if(((Veiculo) veiculoSpinner.getSelectedItem()).codigo>0) {
                    veiculoId = ((Veiculo) veiculoSpinner.getSelectedItem()).codigo;
                    DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                    if(dbHandler.atualizaVeiculoAtivo(veiculoId)) {
                        task = new enviaVeiculoTask();
                        task.execute();
                    }
                }
            }
        });

    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {

        // Inflate the menu; this adds items to the action bar if it is present.
        getMenuInflater().inflate(R.menu.veiculo, menu);
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

    private void carregarVeiculos() {
        DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());

        final LinkedList<Veiculo> veiculos = dbHandler.getVeiculos();
        ArrayAdapter<Veiculo> dataAdapter = new ArrayAdapter<Veiculo>(this,
                android.R.layout.simple_spinner_item, veiculos);

        dataAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);

        veiculoSpinner.setAdapter(dataAdapter);
    }


    public class enviaVeiculoTask extends AsyncTask<String, String, String> {

        @Override
        protected String doInBackground(String... strings) {

            String retorno = "";
            DataResult res;

            res = enviaVeiculo();
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
                AlertDialog.Builder builder = new AlertDialog.Builder(VeiculoActivity.this);
                builder.setTitle("Erro ao atualizar veículo");
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
                Toast.makeText(VeiculoActivity.this, "Veículo atualizado com sucesso", Toast.LENGTH_LONG).show();
                Intent returnIntent = new Intent();
                setResult(RESULT_OK, returnIntent);
                finish();
            }
        }


        @Override
        protected void onProgressUpdate(String... values) {
        }

        public DataResult enviaVeiculo() {
            try {
                DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                Config config = dbHandler.getConfig();
                Map<String, String> params = new HashMap<String, String>();
                params.put("veiculo_id", String.valueOf(veiculoId));
                DataResult res = Utils.getDataFromServer("setVeiculoAtivo", getApplicationContext(), config, params);
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
