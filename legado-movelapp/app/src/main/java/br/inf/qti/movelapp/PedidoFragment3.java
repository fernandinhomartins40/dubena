package br.inf.qti.movelapp;

/**
 * Created by flavio on 18/06/2014.
 */
import android.annotation.TargetApi;
import android.app.Activity;
import android.app.AlertDialog;
import android.app.DatePickerDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.graphics.Color;
import android.os.Build;
import android.os.Bundle;
//import android.support.v4.app.Fragment;
import android.view.LayoutInflater;
import android.view.MenuItem;
import android.view.View;
import android.view.ViewGroup;
import android.view.inputmethod.InputMethodManager;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.CheckBox;
import android.widget.CompoundButton;
import android.widget.DatePicker;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.LinearLayout;
import android.widget.ListView;
import android.widget.PopupMenu;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import androidx.fragment.app.Fragment;

import java.text.DateFormat;
import java.text.SimpleDateFormat;
import java.util.Calendar;
import java.util.Date;
import java.util.LinkedList;
import java.util.Locale;

public class PedidoFragment3 extends Fragment {
/*
    Spinner condicoesSpinner;
    EditText txtQuantidadeParcelas, txtDataParcela, txtValorParcela, txtValorDesconto, txtNumeroParcela;
    TextView txtValorTotal, txtValorLiquido, txtTotalParcelas, txtValorTroca, txtValorMva;
    CheckBox chkPrazo, chkNF;
    ImageButton btnParcelas, btnCalculaParcelas, btnGravarPedido;
    ListView lvParcelas;
    View viewG;
    private OnItemSelectedListener listener;
    int posicao;
    int condicaoId;
    Condicoes condicao;
    Calendar myCalendar;
    LinearLayout layout1;
    LinearLayout layout2;
    Pedido pedido;
    String pedidoId;
    int codPedidoImpressao;
    public static Pedido pedidoImpressao;
    public static String codPedidoNF;
    public static String tipoImpressaoNF = "print";
    private static final int REQUEST_SEND = 1;
    private static final int REQUEST_PRINT_PEDIDO = 2;
    private static final int REQUEST_PRINT_NF = 3;

    @Override
    public View onCreateView(LayoutInflater inflater, ViewGroup container,
                             Bundle savedInstanceState) {
        // TODO Auto-generated method stub

        View view = inflater.inflate(R.layout.pedido_fragment3, container, false);
        viewG = view;
        pedidoId = getArguments().getString("pedidoId");

        condicoesSpinner = (Spinner) view.findViewById(R.id.spinCondicaoFechamento);
        txtValorTotal = (TextView) view.findViewById(R.id.txtValorTotalFechamento);
        txtValorTroca = (TextView) view.findViewById(R.id.txtValorTrocaFechamento);
        txtQuantidadeParcelas = (EditText) view.findViewById(R.id.txtQuantidadeParcelasFechamento);
        txtDataParcela = (EditText) view.findViewById(R.id.txtDataParcelaFechamento);
        txtNumeroParcela = (EditText) view.findViewById(R.id.txtNumeroParcelaFechamento);
        txtValorParcela = (EditText) view.findViewById(R.id.txtValorParcelaFechamento);
        txtValorDesconto = (EditText) view.findViewById(R.id.txtValorDescontoFechamento);
        txtValorLiquido = (TextView) view.findViewById(R.id.txtValorLiquidoFechamento);
        txtValorMva = (TextView) view.findViewById(R.id.txtValorMvaFechamento);
        txtTotalParcelas = (TextView) view.findViewById(R.id.txtValorTotalParcelasFechamento);
        btnParcelas = (ImageButton) view.findViewById(R.id.btnAddParcelas);
        btnCalculaParcelas = (ImageButton) view.findViewById(R.id.btnCalculaParcelas);
        chkPrazo = (CheckBox) view.findViewById(R.id.chkPrazoFechamento);
        chkNF = (CheckBox) view.findViewById(R.id.chkNFFechamento);
        layout1 = (LinearLayout) view.findViewById(R.id.linearLayoutParcela);
        layout2 = (LinearLayout) view.findViewById(R.id.linearLayoutListaParcelas);
        btnGravarPedido = (ImageButton) view.findViewById(R.id.btnGravarPedidoFechamento);
        lvParcelas = (ListView) viewG.findViewById(R.id.listParcelasFechamento);

        condicoesSpinner.setEnabled(pedidoId.equals("-1"));
        txtValorTotal.setEnabled(pedidoId.equals("-1"));
        txtValorTroca.setEnabled(pedidoId.equals("-1"));
        txtQuantidadeParcelas.setEnabled(pedidoId.equals("-1"));
        txtDataParcela.setEnabled(pedidoId.equals("-1"));
        txtNumeroParcela.setEnabled(pedidoId.equals("-1"));
        txtValorParcela.setEnabled(pedidoId.equals("-1"));
        txtValorDesconto.setEnabled(pedidoId.equals("-1"));
        txtValorLiquido.setEnabled(pedidoId.equals("-1"));
        txtValorMva.setEnabled(pedidoId.equals("-1"));
        txtTotalParcelas.setEnabled(pedidoId.equals("-1"));
        btnParcelas.setEnabled(pedidoId.equals("-1"));
        btnCalculaParcelas.setEnabled(pedidoId.equals("-1"));
        chkPrazo.setEnabled(pedidoId.equals("-1"));
        lvParcelas.setEnabled(pedidoId.equals("-1"));
        chkNF.setEnabled(pedidoId.equals("-1"));
        chkNF.setVisibility(View.INVISIBLE);

        txtNumeroParcela.setEnabled(false);
        txtValorDesconto.setText("0");
        layout1.setVisibility(View.GONE);

        //btnGravarPedido.setEnabled();
        btnGravarPedido.setImageResource(R.drawable.imprimir);
        if(pedidoId.equals("-1")) {
            btnGravarPedido.setImageResource(R.drawable.gravar);
        }
        carregarCondicoes("", view);
        condicoesSpinner.setFocusableInTouchMode(true);
        condicoesSpinner.requestFocus();


        chkPrazo.setOnCheckedChangeListener(new CompoundButton.OnCheckedChangeListener() {
            public void onCheckedChanged(CompoundButton buttonView, boolean isChecked) {
                habilitaControles(isChecked);
                if(isChecked)
                  pedido.setPrazo(1);
                else
                  pedido.setPrazo(0);

                pedido.setValorTroca(Utils.round(Double.parseDouble(txtValorTroca.getText().toString().replace(".", "").replace(",", ".")),2));
                pedido.setValorDesconto(Utils.round(Double.parseDouble(txtValorDesconto.getText().toString().replace(".","").replace(",", ".")),2));
                pedido.setValorVenda(Utils.round(Double.parseDouble(txtValorTotal.getText().toString().replace(".","").replace(",", ".")),2));
                pedido.setValorMva(Utils.round(Double.parseDouble(txtValorMva.getText().toString().replace(".","").replace(",", ".")),2));

                DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                dbHandler.updateNovoPedido(pedido);
            }
        });

        chkNF.setOnCheckedChangeListener(new CompoundButton.OnCheckedChangeListener() {
            public void onCheckedChanged(CompoundButton buttonView, boolean isChecked) {
                if(isChecked)
                    pedido.setGerarNF(1);
                else
                    pedido.setGerarNF(0);
                DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                dbHandler.updateNovoPedido(pedido);
            }
        });

        myCalendar = Calendar.getInstance();
        final DatePickerDialog.OnDateSetListener date = new DatePickerDialog.OnDateSetListener() {

            @Override
            public void onDateSet(DatePicker view, int year, int monthOfYear,
                                  int dayOfMonth) {
                // TODO Auto-generated method stub
                myCalendar.set(Calendar.YEAR, year);
                myCalendar.set(Calendar.MONTH, monthOfYear);
                myCalendar.set(Calendar.DAY_OF_MONTH, dayOfMonth);
                updateLabel();
            }
        };

        txtDataParcela.setOnClickListener(new View.OnClickListener() {

            @Override
            public void onClick(View v) {
                // TODO Auto-generated method stub
                new DatePickerDialog(getActivity(), date, myCalendar
                        .get(Calendar.YEAR), myCalendar.get(Calendar.MONTH),
                        myCalendar.get(Calendar.DAY_OF_MONTH)
                ).show();
            }
        });

        txtValorDesconto.setOnFocusChangeListener(new View.OnFocusChangeListener() {
            @Override
            public void onFocusChange(View v, boolean hasFocus) {
                if (!hasFocus) {
                    pedido.setValorTroca(Utils.round(Double.parseDouble(txtValorTroca.getText().toString().replace(".", "").replace(",", ".")),2));
                    pedido.setValorDesconto(Utils.round(Double.parseDouble(txtValorDesconto.getText().toString().replace(",", ".")),2));
                    pedido.setValorVenda(Utils.round(Double.parseDouble(txtValorTotal.getText().toString().replace(".","").replace(",", ".")),2));
                    if(pedidoId.equals("-1"))
                      pedido.setValorMva(calcularMva());
                    txtValorMva.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", pedido.getValorMva()));
                    txtValorLiquido.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", Utils.round(Double.parseDouble(txtValorTotal.getText().toString().replace(".","").replace(",",".")),2) - Utils.round(Double.parseDouble(txtValorTroca.getText().toString().replace(".","").replace(",",".")),2) - Utils.round(Double.parseDouble(txtValorDesconto.getText().toString().replace(".","").replace(",",".")),2) + Utils.round(Double.parseDouble(txtValorMva.getText().toString().replace(".","").replace(",",".")),2)));
                    DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                    dbHandler.updateNovoPedido(pedido);


                }
            }
        });

        carregarParcelas();
        configureBtnCalculaParcelas(view);
        configureBtnAlteraParcela(view);
        configureBtnGravarPedido(view);
        //habilitaControles(false);
        return view;
    }

    private void habilitaControles(boolean isChecked){
        txtQuantidadeParcelas.setEnabled(isChecked && pedidoId.equals("-1"));
        txtDataParcela.setEnabled(isChecked && pedidoId.equals("-1"));
        txtValorParcela.setEnabled(isChecked && pedidoId.equals("-1"));
        btnParcelas.setEnabled(isChecked && pedidoId.equals("-1"));
        btnCalculaParcelas.setEnabled(isChecked && pedidoId.equals("-1"));
        if (!isChecked) {
            txtQuantidadeParcelas.setVisibility(View.INVISIBLE);
            txtQuantidadeParcelas.setText("");
            //layout1.setVisibility(View.INVISIBLE);
            layout2.setVisibility(View.INVISIBLE);
            btnCalculaParcelas.setVisibility(View.INVISIBLE);
        } else {
            txtQuantidadeParcelas.setVisibility(View.VISIBLE);
            //layout1.setVisibility(View.VISIBLE);
            layout2.setVisibility(View.VISIBLE);
            btnCalculaParcelas.setVisibility(View.VISIBLE);
        }
    }

    private void updateLabel() {

        String myFormat = "dd/MM/yyyy"; //In which you need put here
        SimpleDateFormat sdf = new SimpleDateFormat(myFormat, new Locale("pt", "BR"));

        txtDataParcela.setText(sdf.format(myCalendar.getTime()));
    }

    public interface OnItemSelectedListener {
        public void onProdutoSelected1(String link);
    }

    @Override
    public void onAttach(Activity activity) {
        super.onAttach(activity);
        if (activity instanceof OnItemSelectedListener) {
            listener = (OnItemSelectedListener) activity;
        } else {
            throw new ClassCastException(activity.toString()
                    + " must implement PedidosFragment3.OnItemSelectedListener");
        }
    }

    @Override
    public void onDetach() {
        super.onDetach();
        listener = null;
    }

    private void carregarCondicoes(String txt, View view){
        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
        final LinkedList<Condicoes> condicoes = dbHandler.getAllCondicoes();
        ArrayAdapter<Condicoes> dataAdapter = new ArrayAdapter<Condicoes>(this.getActivity(),
                android.R.layout.simple_spinner_item, condicoes);

        dataAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);

        condicoesSpinner.setAdapter(dataAdapter);

        pedido = dbHandler.getPedido(pedidoId);
        if(pedido.getId() == -1 || pedido.getId() >= 0) {
            carregaPedido();

        } else if(pedido.getId() == -2) {
            Calendar c = Calendar.getInstance();
            SimpleDateFormat df = new SimpleDateFormat("yyyy-MM-dd");
            String formattedDate = df.format(c.getTime());
            pedido.setCodCliente(0);
            pedido.setDataEntrega(formattedDate);
            pedido.setDataPedido(formattedDate);
            pedido.setCondicao(0);
            pedido.setValorDesconto(0);
            pedido.setValorVenda(0);
            pedido.setValorTroca(0);
            pedido.setTransmitido(0);
            pedido.setPrazo(0);
            pedido.setCodigoNovo(0);
            pedido.setGerarNF(0);
            pedido.setValorMva(0);
            dbHandler.insertNovoPedido(pedido);
        }
        condicoesSpinner.setOnItemSelectedListener(
                new AdapterView.OnItemSelectedListener() {
                    public void onItemSelected(
                            AdapterView<?> parent,
                            View view,
                            int position,
                            long id) {
                        condicaoId = ((Condicoes) parent.getItemAtPosition(position)).getId();
                        condicao = ((Condicoes) parent.getItemAtPosition(position));
                        pedido.setCondicao(condicaoId);
                        pedido.setValorDesconto(Utils.round(Double.parseDouble(txtValorDesconto.getText().toString().replace(".","").replace(",", ".")),2));
                        pedido.setValorTroca(Utils.round(Double.parseDouble(txtValorTroca.getText().toString().replace(".","").replace(",", ".")),2));
                        pedido.setValorVenda(Utils.round(Double.parseDouble(txtValorTotal.getText().toString().replace(".","").replace(",", ".")),2));
                        pedido.setValorMva(Utils.round(Double.parseDouble(txtValorMva.getText().toString().replace(".","").replace(",", ".")),2));
                        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                        dbHandler.updateNovoPedido(pedido);

                    }

                    public void onNothingSelected(AdapterView<?> parent) {
                    }
                }
        );
        txtValorTroca.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", dbHandler.getValorTotalTrocasPedido(pedidoId)).replace(".", "").replace(".", ","));
        txtValorTotal.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", dbHandler.getValorTotalItensPedido(pedidoId)));
        if(pedidoId.equals("-1"))
            pedido.setValorMva(calcularMva());
        txtValorMva.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", pedido.getValorMva()).replace(".", "").replace(".", ","));
        txtValorLiquido.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", dbHandler.getValorTotalItensPedido(pedidoId) - dbHandler.getValorTotalTrocasPedido(pedidoId) - Double.parseDouble(txtValorDesconto.getText().toString().replace(".","").replace(",",".")) + pedido.getValorMva()));
    }

    private void carregarParcelas(){
        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());

        final LinkedList<PedidoParcela> itens = dbHandler.getAllParcelasPedido(pedidoId);
        final ListView lv1 = (ListView) viewG.findViewById(R.id.listParcelasFechamento);

        double total = 0;
        for(int i=0; i<itens.size(); i++){
            total += itens.get(i).getValor();
        }
        txtTotalParcelas.setText("Parcelas: " + String.format(new Locale("pt", "BR"), "%1$,.2f", total));
        double totalliq = Utils.round((Double.parseDouble(txtValorLiquido.getText().toString().replace(".","").replace(",", "."))),2);
        if(total != totalliq){
            txtTotalParcelas.setTextColor(Color.RED);
        } else {
            txtTotalParcelas.setTextColor(Color.BLUE);
        }

        lv1.setAdapter(new CustomListAdapter(getActivity(), itens, "parcelaspedido"));

        lv1.setOnItemLongClickListener(new AdapterView.OnItemLongClickListener() {

            public boolean onItemLongClick(AdapterView<?> arg0, View arg1,
                                           int pos, long id) {
                final ListView lv2 = (ListView) viewG.findViewById(R.id.listParcelasFechamento);
                final CustomListAdapter adapter = (CustomListAdapter) lv2.getAdapter();
                txtDataParcela.setText(String.valueOf(((PedidoParcela) adapter.getItem(pos)).getVencimentoTexto()).toString());
                txtValorParcela.setText(String.valueOf(((PedidoParcela) adapter.getItem(pos)).getValor()).toString().replace(".",",").replace(".", ","));
                txtNumeroParcela.setText(String.valueOf(((PedidoParcela) adapter.getItem(pos)).getId()).toString());
                layout1.setVisibility(View.VISIBLE);
                carregarParcelas();
                return true;
            }
        });


    }

    private void carregaPedido() {
        final ArrayAdapter<Condicoes> adapter = (ArrayAdapter<Condicoes>)condicoesSpinner.getAdapter();
        for(int i=0; i< adapter.getCount(); i++){
            if(((Condicoes)adapter.getItem(i)).getId() == pedido.getCodCondicao()) {
                condicoesSpinner.setSelection(i);
            }
        }
        if(pedidoId.equals("-1"))
            pedido.setValorMva(calcularMva());
        txtValorDesconto.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", pedido.getValorDesconto()).replace(".","").replace(".", ","));
        txtValorTroca.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", pedido.getValorTroca()).replace(".","").replace(".", ","));
        txtValorMva.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", pedido.getValorMva()).replace(".","").replace(".", ","));
        chkPrazo.setChecked(pedido.getPrazo()==1);
        chkNF.setChecked(pedido.getGerarNF()==1);
        habilitaControles(pedido.getPrazo() == 1);
    }

    private void configureBtnCalculaParcelas(View view) {

        btnCalculaParcelas.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {

                InputMethodManager imm = (InputMethodManager)getActivity().getSystemService(Context.
                        INPUT_METHOD_SERVICE);
                imm.hideSoftInputFromWindow(getActivity().getCurrentFocus().getWindowToken(), 0);

                if (txtQuantidadeParcelas.getText().equals("")) {
                    Toast.makeText(getActivity().getApplicationContext(), "Informe a quantidade de parcelas", Toast.LENGTH_SHORT).show();
                } else {

                    DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                    if (dbHandler.countParcelasPedido(String.valueOf(pedido.getId()).toString()) > 0) {
                        AlertDialog.Builder builder = new AlertDialog.Builder(getActivity());
                        builder.setTitle("Aviso");
                        builder.setMessage("Já existem parcelas para o pedido que serão removidas. Deseja continuar?");

                        builder.setPositiveButton("Sim", new DialogInterface.OnClickListener() {
                            @Override
                            public void onClick(DialogInterface dialog, int which) {

                                inserirParcelas(true);
                                carregarParcelas();
                                dialog.dismiss();
                            }
                        });
                        builder.setNegativeButton("Não", new DialogInterface.OnClickListener() {
                            @Override
                            public void onClick(DialogInterface dialog, int which) {
                                carregarParcelas();
                                dialog.dismiss();
                            }
                        });


                        AlertDialog alert = builder.create();
                        alert.show();

                    } else {
                        inserirParcelas(false);
                        carregarParcelas();
                    }
                }
            }
        });
    }

    private void inserirParcelas(boolean remover){
        int qt = Integer.parseInt(txtQuantidadeParcelas.getText().toString());
        PedidoParcela parcela;
        DataBaseHandler dbHandlerParcela = new DataBaseHandler(getActivity().getApplicationContext());
        if(remover){
            dbHandlerParcela.deleteParcelasPedido("-1");
        }
        double total = (Double.parseDouble(txtValorLiquido.getText().toString().replace(".","").replace(",", ".")));
        double valorParcela = 0;
        if(qt > 0)
            valorParcela = total / qt;



        //total = Double.valueOf(new DecimalFormat("#.##").format(total));
        total = Math.round(total*1e2)/1e2;
        //Toast.makeText(getActivity().getApplicationContext(), "novo: " + String.valueOf(total).toString(), Toast.LENGTH_SHORT).show();
        //valorParcela = Double.valueOf(new DecimalFormat("#.##").format(valorParcela));
        valorParcela = Math.round(valorParcela*1e2)/1e2;

        Calendar cal = Calendar.getInstance();
        SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd");
        for(int i=0; i<qt; i++) {
            cal.add(Calendar.MONTH, 1);
            String dt = sdf.format(cal.getTime());

            if(i==(qt-1)) {
                parcela = new PedidoParcela(0,-1,dt,total);
                dbHandlerParcela.insertParcelaNovoPedido(parcela);
            } else {
                parcela = new PedidoParcela(0,-1,dt,valorParcela);
                dbHandlerParcela.insertParcelaNovoPedido(parcela);
                total -= valorParcela;
            }
            total = Math.round(total*1e2)/1e2;
        }
    }

    private void configureBtnAlteraParcela(View view) {

        btnParcelas.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {

                InputMethodManager imm = (InputMethodManager)getActivity().getSystemService(Context.
                        INPUT_METHOD_SERVICE);
                imm.hideSoftInputFromWindow(getActivity().getCurrentFocus().getWindowToken(), 0);

                if (txtDataParcela.getText().equals("")) {
                    Toast.makeText(getActivity().getApplicationContext(), "Informe a data de vencimento", Toast.LENGTH_SHORT).show();
                } else if (txtValorParcela.getText().equals("")) {
                    Toast.makeText(getActivity().getApplicationContext(), "Informe o valor da parcela", Toast.LENGTH_SHORT).show();
                } else {
                    int numParcela = Integer.parseInt(txtNumeroParcela.getText().toString());
                    String dt = String.valueOf(txtDataParcela.getText()).toString();

                    DateFormat df = new SimpleDateFormat("dd/MM/yyyy");
                    DateFormat df1 = new SimpleDateFormat("yyyy/MM/dd");
                    Date d;
                    try {
                      d = df.parse(dt);
                    } catch (java.text.ParseException e) {
                        Toast.makeText(getActivity().getApplicationContext(), "Data de Vencimento Inválida", Toast.LENGTH_SHORT).show();
                        e.printStackTrace();
                        return;
                    }
                    dt = df1.format(d);

                    double valorParcela = (Double.parseDouble(txtValorParcela.getText().toString().replace(".","").replace(",", ".")));
                    //valorParcela = Double.valueOf(new DecimalFormat("#.##").format(valorParcela));
                    valorParcela = Math.round(valorParcela*1e2)/1e2;

                    PedidoParcela parcela = new PedidoParcela(numParcela,-1,dt,valorParcela);

                    DataBaseHandler dbHandlerParcela = new DataBaseHandler(getActivity().getApplicationContext());
                    dbHandlerParcela.updateParcelaPedido(parcela);
                    layout1.setVisibility(View.GONE);
                    carregarParcelas();
                }
            }
        });
    }

    private void configureBtnGravarPedido(View view) {

        btnGravarPedido.setOnClickListener(new View.OnClickListener() {
            @TargetApi(Build.VERSION_CODES.HONEYCOMB)
            @Override
            public void onClick(View view) {
                InputMethodManager imm = (InputMethodManager) getActivity().getSystemService(Context.
                        INPUT_METHOD_SERVICE);
                imm.hideSoftInputFromWindow(getActivity().getCurrentFocus().getWindowToken(), 0);

                if (pedidoId.equals("-1")) {
                    if (Double.parseDouble(txtValorLiquido.getText().toString().replace(".", "").replace(",", ".")) < 0) {
                        Toast.makeText(getActivity().getApplicationContext(), "Total líquido não pode ser menor que zero", Toast.LENGTH_SHORT).show();
                        return;
                    }

                    if (chkPrazo.isChecked() && Double.parseDouble(txtTotalParcelas.getText().toString().substring(9).replace(".", "").replace(",", ".")) != Double.parseDouble(txtValorLiquido.getText().toString().replace(".", "").replace(",", "."))) {
                        Toast.makeText(getActivity().getApplicationContext(), "Total de Parcelas não é igual ao total da venda", Toast.LENGTH_SHORT).show();
                        return;
                    }

                    final ListView lv1 = (ListView) viewG.findViewById(R.id.listParcelasFechamento);
                    if (chkPrazo.isChecked() && lv1.getCount() == 0) {
                        Toast.makeText(getActivity().getApplicationContext(), "Venda a prazo: informe pelo menos uma parcela", Toast.LENGTH_SHORT).show();
                        return;
                    }

                    DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                    final LinkedList<PedidoItem> itens = dbHandler.getAllItens(pedidoId);
                    if (itens.size() <= 0) {
                        Toast.makeText(getActivity().getApplicationContext(), "Adicione pelo menos um produto", Toast.LENGTH_SHORT).show();
                        return;
                    }

                    int cod = dbHandler.gravaNovoPedido();
                    pedidoId = String.valueOf(cod).toString();
                    if (cod == -2)
                        Toast.makeText(getActivity().getApplicationContext(), "Erro ao gravar pedido", Toast.LENGTH_SHORT).show();
                    else {
                        Toast.makeText(getActivity().getApplicationContext(), "Pedido gravado com sucesso: número " + String.valueOf(cod).toString(), Toast.LENGTH_SHORT).show();
                        codPedidoImpressao = cod;
                        dialogTransmitir();
                    }
                } else {
                    DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                    final LinkedList<Pedido> pedidos = dbHandler.getAllPedidosImprimir(pedidoId);
                    for (int i = 0; i < pedidos.size(); i++) {
                        codPedidoImpressao = i;
                        PopupMenu popupMenu = new PopupMenu(getActivity(), btnGravarPedido);
                        if (pedidos.get(i).getCodigoNovo() <= 0) {
                            popupMenu.getMenuInflater().inflate(R.menu.pedido_print_1, popupMenu.getMenu());
                        } else {
                            popupMenu.getMenuInflater().inflate(R.menu.pedido_print, popupMenu.getMenu());
                        }
                        popupMenu.setOnMenuItemClickListener(new PopupMenu.OnMenuItemClickListener() {
                            public boolean onMenuItemClick(MenuItem item) {
                                if (item.getTitle().equals("Pedido")) {
                                    //PedidoImpressao imp = new PedidoImpressao(pedidos.get(codPedidoImpressao), getActivity());
                                    //imp.imprimirPedido();
                                    Intent i = new Intent(getActivity().getApplicationContext(), PedidoImpressaoActivity.class);
                                    tipoImpressaoNF = "print";
                                    i.putExtra("tipo", tipoImpressaoNF);
                                    codPedidoNF = String.valueOf(pedidos.get(codPedidoImpressao).getId());
                                    startActivity(i);
                                    return true;
                                } else if (item.getTitle().equals("Ver Pedido")) {
                                    Intent i = new Intent(getActivity().getApplicationContext(), PedidoImpressaoActivity.class);
                                    tipoImpressaoNF = "view";
                                    i.putExtra("tipo", tipoImpressaoNF);
                                    pedidoImpressao = pedidos.get(codPedidoImpressao);
                                    startActivity(i);
                                    return true;
                                } else if (item.getTitle().equals("Nota Fiscal")) {
                                    Intent i = new Intent(getActivity().getApplicationContext(), NotaFiscalImpressaoActivity.class);
                                    tipoImpressaoNF = "print";
                                    i.putExtra("tipo", tipoImpressaoNF);
                                    codPedidoNF = String.valueOf(pedidos.get(codPedidoImpressao).getCodigoNovo());
                                    startActivity(i);
                                    return true;
                                } else {
                                    Intent i = new Intent(getActivity().getApplicationContext(), NotaFiscalImpressaoActivity.class);
                                    tipoImpressaoNF = "view";
                                    i.putExtra("tipo", tipoImpressaoNF);
                                    codPedidoNF = String.valueOf(pedidos.get(codPedidoImpressao).getCodigoNovo());
                                    startActivity(i);
                                    return true;
                                }
                            }
                        });
                        popupMenu.show();//showing popup menu
                    }
                }

            }
        });
    }

    private void dialogTransmitir(){
                Intent intent = new Intent(getActivity().getApplicationContext(), PedidoRecepcaoActivity.class);
                intent.putExtra("pedidoId", String.valueOf(pedidoId));
                                                                                                                                                                                                                                                                                                                                                                                                                                                         startActivityForResult(intent, REQUEST_SEND);
    }

    private void dialogImprimirNF(){
        AlertDialog.Builder builder1 = new AlertDialog.Builder(getActivity());
        builder1.setTitle("Questão");
        builder1.setMessage("Deseja imprimir Nota Fiscal?");

        builder1.setPositiveButton("Sim", new DialogInterface.OnClickListener() {
            @Override
            public void onClick(DialogInterface dialog, int which) {
                dialog.dismiss();
                DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                final LinkedList<Pedido> pedidos = dbHandler.getAllPedidosImprimir(pedidoId);
                for (int i = 0; i < pedidos.size(); i++) {
                    Intent intent = new Intent(getActivity().getApplicationContext(), NotaFiscalImpressaoActivity.class);
                    tipoImpressaoNF = "print";
                    intent.putExtra("tipo", tipoImpressaoNF);
                    codPedidoNF = String.valueOf(pedidos.get(i).getCodigoNovo());
                    startActivity(intent);
                }
                getActivity().finish();
            }
        });
        builder1.setNegativeButton("Não", new DialogInterface.OnClickListener() {
            @Override
            public void onClick(DialogInterface dialog, int which) {
                dialog.dismiss();
                getActivity().finish();
            }
        });
        AlertDialog alert1 = builder1.create();
        alert1.show();
    }
    public void onActivityResult(int requestCode, int resultCode,
                                    Intent data) {
        if (requestCode == REQUEST_SEND) {
            AlertDialog.Builder builder = new AlertDialog.Builder(getActivity());
            builder.setTitle("Questão");
            builder.setMessage("Deseja imprimir o pedido?");

            builder.setPositiveButton("Sim", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface dialog, int which) {
                    dialog.dismiss();
                    DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                    final LinkedList<Pedido> pedidos = dbHandler.getAllPedidosImprimir(pedidoId);
                    for (int i = 0; i < pedidos.size(); i++) {
                        Intent intent = new Intent(getActivity().getApplicationContext(), PedidoImpressaoActivity.class);
                        tipoImpressaoNF = "print";
                        codPedidoNF = String.valueOf(pedidos.get(i).getId());
                        pedidoImpressao = pedidos.get(i);
                        intent.putExtra("tipo", tipoImpressaoNF);
                        startActivityForResult(intent, REQUEST_PRINT_PEDIDO);
                    }
                }
            });
            builder.setNegativeButton("Não", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface dialog, int which) {
                    dialog.dismiss();
                    if (chkNF.isChecked()) {
                        dialogImprimirNF();
                    } else {
                        getActivity().finish();
                    }
                }
            });
            AlertDialog alert = builder.create();
            alert.show();
        }
        else if(requestCode == REQUEST_PRINT_PEDIDO){
            if (chkNF.isChecked()) {
                dialogImprimirNF();
            } else {
                getActivity().finish();
            }
        }
    }
    public Double calcularMva(){
        Double totalMva = 0.0;
        Double totalMvaAnterior = pedido.getValorMva();
        if(pedido.getGerarNF() == 0){
            return 0.0;
        }
        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
        final LinkedList<Cliente> clientes = dbHandler.getCliente(String.valueOf(String.valueOf(pedido.getCodCliente()).toString()));
        if(clientes.size() > 0) {
            if(clientes.get(0).getConsumidorFinal() == 1) {
                return 0.0;
            }
        }
        else return 0.0;
        Double totalDesconto = pedido.getValorDesconto() + pedido.getValorTroca();
        //if(totalDesconto <= 0)
        //    return;
        Double total_nf = dbHandler.getValorLiquidoItens(pedidoId);
        //boolean altera = false;
        final LinkedList<PedidoItem> itens = dbHandler.getAllItens(pedidoId);
        for(int i=0;i<itens.size();i++){
            Produto produto = dbHandler.getProduto(itens.get(i).getCodProduto());
            //Double precoAnterior = itens.get(i).getPreco();
            //Double preco = itens.get(i).getPreco();
            Double preco_mva = 0.0;
            Double aliq_mva = produto.getAliqMva();
            Double aliq_icms = produto.getAliqIcms();

            if(aliq_mva > 0){
                Double descontoProduto = totalDesconto / total_nf;
                //descontoProduto = Utils.roundFloor(descontoProduto * 100,0) / 100;
                //descontoProduto = Utils.roundZero(descontoProduto, 3);
                Double base_icms = (itens.get(i).getPrecoOrigem() * itens.get(i).getQuantidade()) * (1 - descontoProduto);
                base_icms = Utils.roundZero((base_icms * aliq_mva / 100) + base_icms,2);
                Double preValorIcms = Utils.roundEven(base_icms * aliq_icms / 100, 4);
                Double valorIcms = Utils.roundFloor(preValorIcms * 1000,0) / 1000;
                valorIcms = valorIcms -((itens.get(i).getPrecoOrigem() * itens.get(i).getQuantidade() * ( 1 - descontoProduto)) * aliq_icms / 100);
                valorIcms = Utils.roundFloor(valorIcms * 1000,2) / 1000;
                valorIcms = Utils.roundUpZero(valorIcms, 2);
                valorIcms = Utils.round(valorIcms, 2);
                Double valorMva = valorIcms;

                totalMva += valorMva;

                //if(preco_mva > 0){
                //    preco = preco_mva;
                //}
                //if(!preco.equals(precoAnterior)){
                //    PedidoItem item = itens.get(i);
                //    item.preco = preco_mva;
                //    item.valor_total = preco_mva * item.getQuantidade();
                //    itens.set(i, item);
                //    dbHandler.updatePrecoItemPedido(item);
                //    altera = true;
                //}
               //Utils.round(preco_origem,3)
               //Utils.round(Utils.round(preco,3) * quantidade,2));
            }
        }
        if(!totalMva.equals(totalMvaAnterior)){
            //pedido.setValorVenda(dbHandler.getValorTotalItensPedido(pedidoId));
            //txtValorTotal.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", pedido.getValorVenda()));
            //txtValorLiquido.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", Utils.round(Double.parseDouble(txtValorTotal.getText().toString().replace(".","").replace(",",".")),2) - Utils.round(Double.parseDouble(txtValorTroca.getText().toString().replace(".","").replace(",",".")),2) - Utils.round(Double.parseDouble(txtValorDesconto.getText().toString().replace(".","").replace(",",".")),2) + totalMva));
            //pedido.setValorTroca(Utils.round(Double.parseDouble(txtValorTroca.getText().toString().replace(".", "").replace(",", ".")),2));
            //pedido.setValorDesconto(Utils.round(Double.parseDouble(txtValorDesconto.getText().toString().replace(",", ".")),2));
            //pedido.setValorMva(totalMva);
            //dbHandler.updateNovoPedido(pedido);

            AlertDialog.Builder builder = new AlertDialog.Builder(getActivity());
            builder.setTitle("Atenção");
            builder.setMessage("O valor do ICMS ST foi recalculado. ");

            builder.setPositiveButton("Ok", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface dialog, int which) {
                    dialog.dismiss();
                }
            });
            AlertDialog alert = builder.create();
            alert.show();

        }
        return totalMva;
    }
*/
}