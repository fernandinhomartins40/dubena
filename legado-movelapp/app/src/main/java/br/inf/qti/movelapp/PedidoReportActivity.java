package br.inf.qti.movelapp;

import android.app.DatePickerDialog;
import android.app.ProgressDialog;
import android.content.Context;
import android.content.Intent;
import android.os.AsyncTask;
import android.os.Bundle;
//import android.support.design.widget.FloatingActionButton;
//import android.support.design.widget.Snackbar;
//import android.support.v7.app.AppCompatActivity;
//import android.support.v7.widget.PopupMenu;
//import android.support.v7.widget.Toolbar;
import android.view.MenuItem;
import android.view.View;
import android.widget.DatePicker;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.ListView;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.PopupMenu;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Calendar;
import java.util.HashMap;
import java.util.LinkedList;
import java.util.List;
import java.util.Locale;
import java.util.Map;

public class PedidoReportActivity extends AppCompatActivity implements getReportDataTask.AsyncResponse {

    EditText txtDataInicial, txtDataFinal;
    Calendar myCalendarInicial, myCalendarFinal;
    ImageButton btnAtualizar, btnImprimir;

    @Override
    public void onCreate(Bundle savedInstanceState) {

        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_pedido_report);
        //Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        //setSupportActionBar(toolbar);

        txtDataInicial = (EditText) findViewById(R.id.txtDataInicial);
        txtDataFinal = (EditText) findViewById(R.id.txtDataFinal);
        btnAtualizar = (ImageButton) findViewById(R.id.btnAtualizarReport);
        btnImprimir = (ImageButton) findViewById(R.id.btnPrintReport);

        configureBtnAtualizar();
        configureBtnImprimir();

        myCalendarInicial = Calendar.getInstance();
        myCalendarFinal = Calendar.getInstance();

        updateLabelInicial();
        updateLabelFinal();


        final DatePickerDialog.OnDateSetListener dateInicial = new DatePickerDialog.OnDateSetListener() {

            @Override
            public void onDateSet(DatePicker view, int year, int monthOfYear,
                                  int dayOfMonth) {
                // TODO Auto-generated method stub
                myCalendarInicial.set(Calendar.YEAR, year);
                myCalendarInicial.set(Calendar.MONTH, monthOfYear);
                myCalendarInicial.set(Calendar.DAY_OF_MONTH, dayOfMonth);
                updateLabelInicial();
            }
        };

        final DatePickerDialog.OnDateSetListener dateFinal = new DatePickerDialog.OnDateSetListener() {

            @Override
            public void onDateSet(DatePicker view, int year, int monthOfYear,
                                  int dayOfMonth) {
                // TODO Auto-generated method stub
                myCalendarFinal.set(Calendar.YEAR, year);
                myCalendarFinal.set(Calendar.MONTH, monthOfYear);
                myCalendarFinal.set(Calendar.DAY_OF_MONTH, dayOfMonth);
                updateLabelFinal();
            }
        };

        txtDataInicial.setOnClickListener(new View.OnClickListener() {

            @Override
            public void onClick(View v) {
                // TODO Auto-generated method stub
                new DatePickerDialog(PedidoReportActivity.this, dateInicial, myCalendarInicial
                        .get(Calendar.YEAR), myCalendarInicial.get(Calendar.MONTH),
                        myCalendarInicial.get(Calendar.DAY_OF_MONTH)
                ).show();
            }
        });

        txtDataFinal.setOnClickListener(new View.OnClickListener() {

            @Override
            public void onClick(View v) {
                // TODO Auto-generated method stub
                new DatePickerDialog(PedidoReportActivity.this, dateFinal, myCalendarFinal
                        .get(Calendar.YEAR), myCalendarFinal.get(Calendar.MONTH),
                        myCalendarFinal.get(Calendar.DAY_OF_MONTH)
                ).show();
            }
        });
    }

    @Override
    public void getReportDataFinish(DataResult output){
        try{
        double valor = 0;
        double quantidade = 0;
        LinkedList<Pedido> pedidos = new LinkedList<Pedido>();
        JSONArray dados = output.getDados();
        for (int i = 0; i < dados.length(); i++) {
            JSONObject c = dados.getJSONObject(i);
            String codigo = c.getString("id");
            String situacao = c.getString("sitdescricao");
            String condicao = c.getString("condicao");
            Double valorvenda = c.getDouble("valorvenda");
            String endereco = c.getString("ruadescricao") + ", " + c.getString("entreganumero") + "-" + c.getString("bairro");;
            Double qtde = c.getDouble("qtde");
            Pedido pedido = new Pedido(Integer.parseInt(codigo), "data_pedido", endereco, condicao, valorvenda, "rua", "num", "comp", "obs", "bairro", "ref", 0, situacao);
            pedido.setQuantidade(qtde);
            pedidos.add(pedido);

            valor += pedido.getValor_venda();
            quantidade += (pedidos.get(i)).getQuantidade();
        }

        final TextView txtQtde = (TextView) findViewById(R.id.txtTotalQtdeReport);
        final TextView txtValor = (TextView) findViewById(R.id.txtTotalValorReport);

        txtQtde.setText("Qtde: " + String.format(new Locale("pt", "BR"), "%1$,.2f",quantidade).replace(".", "").replace(".", ","));
        txtValor.setText("Valor: " + String.format(new Locale("pt", "BR"), "%1$,.2f",valor).replace(".", "").replace(".", ","));


        final ListView lv1 = (ListView) findViewById(R.id.listItensReportVenda);
        lv1.setAdapter(new CustomListAdapter(PedidoReportActivity.this, pedidos, "reportVendas"));
        } catch (JSONException e) {
            e.printStackTrace();
        } catch(Exception e1){
            e1.printStackTrace();
        }
    }

    private void updateLabelInicial() {

        String myFormat = "dd/MM/yyyy"; //In which you need put here
        SimpleDateFormat sdf = new SimpleDateFormat(myFormat, new Locale("pt", "BR"));

        txtDataInicial.setText(sdf.format(myCalendarInicial.getTime()));
    }

    private void updateLabelFinal() {

        String myFormat = "dd/MM/yyyy"; //In which you need put here
        SimpleDateFormat sdf = new SimpleDateFormat(myFormat, new Locale("pt", "BR"));

        txtDataFinal.setText(sdf.format(myCalendarFinal.getTime()));
    }


    private void atualizarReport(){

        new getReportDataTask(this, getApplicationContext(), txtDataInicial.getText().toString(), txtDataFinal.getText().toString()).execute();
    }
    private void configureBtnAtualizar() {
        btnAtualizar.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                atualizarReport();
            }
        });
    }
    private void configureBtnImprimir() {
        btnImprimir.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                PopupMenu popupMenu = new PopupMenu(PedidoReportActivity.this, btnImprimir);
                popupMenu.getMenuInflater().inflate(R.menu.report_print, popupMenu.getMenu());
                popupMenu.setOnMenuItemClickListener(new PopupMenu.OnMenuItemClickListener() {
                    public boolean onMenuItemClick(MenuItem item) {
                        if (item.getTitle().equals("Imprimir")) {
                            Intent i = new Intent(getApplicationContext(), ReportImpressaoActivity.class);
                            i.putExtra("tipo", "print");
                            i.putExtra("codReport", "1");
                            i.putExtra("dataInicial", txtDataInicial.getText().toString());
                            i.putExtra("dataFinal", txtDataFinal.getText().toString());
                            startActivity(i);
                            return true;
                        } else if (item.getTitle().equals("Visualizar")) {
                            Intent i = new Intent(getApplicationContext(), ReportImpressaoActivity.class);
                            i.putExtra("tipo", "view");
                            i.putExtra("codReport", "1");
                            i.putExtra("dataInicial", txtDataInicial.getText().toString());
                            i.putExtra("dataFinal", txtDataFinal.getText().toString());
                            startActivity(i);
                            return true;
                        }
                        return true;
                    }
                });
                popupMenu.show();//showing popup menu
            }
        });
    }

}

class getReportDataTask extends AsyncTask<String, String, DataResult> {

    public Context context;
    public String dataInicial;
    public String dataFinal;

    public interface AsyncResponse {
        void getReportDataFinish(DataResult output);
    }

    public AsyncResponse delegate = null;

    public getReportDataTask(AsyncResponse delegate, Context cont, String dataInicial, String dataFinal){
        this.delegate = delegate;
        this.context = cont;
        this.dataInicial = dataInicial;
        this.dataFinal = dataFinal;
    }

    @Override
    protected DataResult doInBackground(String... strings) {
        try {
            return LoadData();

        } catch (Exception e) {
            e.printStackTrace();
            return null;
        }
    }

    @Override
    protected void onPreExecute() {
        super.onPreExecute();
    }

    @Override
    protected void onPostExecute(DataResult result) {
        delegate.getReportDataFinish(result);
    }

    public DataResult LoadData() {
        try {
            Map<String, String> params = new HashMap<String, String>();

            params.put("data_inicial", dataInicial);
            params.put("data_final", dataFinal);

            DataBaseHandler dbHandler = new DataBaseHandler(context);
            Config config = dbHandler.getConfig();
            DataResult res = Utils.getDataFromServer("getPedidosReport", this.context, config, params);
            if (res.getStatus()) {
                JSONObject json = new JSONObject(res.getMsg());
                JSONArray dados = json.getJSONArray("dados");
                res.setDados(dados);
                return res;
            }
            return res;

        } catch (JSONException e) {
            e.printStackTrace();
            return new DataResult(false, e.getMessage());
        } catch(Exception e1){
            e1.printStackTrace();
            return new DataResult(false, e1.getMessage());
        }
    }

}
