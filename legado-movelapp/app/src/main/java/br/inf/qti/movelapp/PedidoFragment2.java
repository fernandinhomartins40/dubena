package br.inf.qti.movelapp;

/**
 * Created by flavio on 18/06/2014.
 */
import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.os.Bundle;
//import android.support.v4.app.Fragment;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.view.inputmethod.InputMethodManager;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.ListView;
import android.widget.Spinner;
import android.widget.Toast;

import androidx.fragment.app.Fragment;

import java.util.LinkedList;
import java.util.Locale;

public class PedidoFragment2 extends Fragment {
/*
    Spinner produtoSpinner, classeSpinner;
    EditText txtProduto, txtQuantidade, txtValorTotal, txtPreco;
    View viewG;
    ListView lstItens;
    ImageButton btnPesquisa, btnAdd;

    private OnItemSelectedListener listener;
    int produtoId, classeId;
    Produto produto;
    ClasseProduto classe;
    double quantidade;
    int posicao;
    String pedidoId;
    boolean isConsumidorFinal;
    boolean isNotaFiscalOnline = false;


    @Override
    public View onCreateView(LayoutInflater inflater, ViewGroup container,
                             Bundle savedInstanceState) {
        // TODO Auto-generated method stub

        View view = inflater.inflate(R.layout.pedido_fragment2, container, false);
        viewG = view;
        pedidoId = getArguments().getString("pedidoId");

        produtoSpinner = (Spinner) view.findViewById(R.id.spinProdutoPedido);
        classeSpinner = (Spinner) view.findViewById(R.id.spinClasseProdutoPedido);
        txtProduto = (EditText) view.findViewById(R.id.txtProdutoPedido);
        txtValorTotal = (EditText) view.findViewById(R.id.txtValorTotalPedido);
        txtQuantidade = (EditText) view.findViewById(R.id.txtQuantidadePedido);
        txtPreco = (EditText) view.findViewById(R.id.txtPrecoPedido);
        lstItens = (ListView) viewG.findViewById(R.id.listItens);
        btnPesquisa = (ImageButton) view.findViewById(R.id.btnPesquisaProdutoPedido);
        btnAdd = (ImageButton) view.findViewById(R.id.btnAddProdutoPedido);
        carregarClasse(view);
        //carregarProdutos("", view);
        configureBtnPesquisa(view);
        configureBtnAdd(view);
        carregarItens();
        isConsumidorFinal = carregaConsumidorFinal();
        classeSpinner.setFocusableInTouchMode(true);
        classeSpinner.requestFocus();

        produtoSpinner.setEnabled(pedidoId.equals("-1"));
        classeSpinner.setEnabled(pedidoId.equals("-1"));
        txtProduto.setEnabled(pedidoId.equals("-1"));
        txtValorTotal.setEnabled(pedidoId.equals("-1"));
        txtQuantidade.setEnabled(pedidoId.equals("-1"));
        txtPreco.setEnabled(pedidoId.equals("-1"));
        lstItens.setEnabled(true);
        btnPesquisa.setEnabled(pedidoId.equals("-1"));
        btnAdd.setEnabled(pedidoId.equals("-1"));
        return view;
    }

    @Override
    public void onResume() {
        txtValorTotal.setText("Valor total: " + String.format(new Locale("pt", "BR"), "%1$,.2f", valorTotalItens()));
        super.onResume();
    }


    public interface OnItemSelectedListener {
        public void onProdutoSelected(String link);
    }

    @Override
    public void onAttach(Activity activity) {
        super.onAttach(activity);
        if (activity instanceof OnItemSelectedListener) {
            listener = (OnItemSelectedListener) activity;
        } else {
            throw new ClassCastException(activity.toString()
                    + " must implemenet PedidosFragment2.OnItemSelectedListener");
        }
    }

    @Override
    public void onDetach() {
        super.onDetach();
        listener = null;
    }

    private void carregarClasse(View view){
        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
        final LinkedList<ClasseProduto> classes = dbHandler.getAllClassesProdutosEstoque();
        ArrayAdapter<ClasseProduto> dataAdapter = new ArrayAdapter<ClasseProduto>(this.getActivity(),
                android.R.layout.simple_spinner_item, classes);

        dataAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);

        classeSpinner.setAdapter(dataAdapter);

        classeSpinner.setOnItemSelectedListener(
                new AdapterView.OnItemSelectedListener() {
                    public void onItemSelected(
                            AdapterView<?> parent,
                            View view,
                            int position,
                            long id) {
                        classeId = ((ClasseProduto) parent.getItemAtPosition(position)).getId();
                        classe = ((ClasseProduto) parent.getItemAtPosition(position));
                        carregarProdutos("", viewG);
                    }

                    public void onNothingSelected(AdapterView<?> parent) {
                    }
                }
        );
        txtProduto.setText("");
    }

    private void carregarProdutos(String txt, View view){
        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
        final LinkedList<Produto> produtos = dbHandler.getAllProdutosPedido(txt, classeId);
        ArrayAdapter<Produto> dataAdapter = new ArrayAdapter<Produto>(this.getActivity(),
                android.R.layout.simple_spinner_item, produtos);

        dataAdapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);

        produtoSpinner.setAdapter(dataAdapter);

        produtoSpinner.setOnItemSelectedListener(
                new AdapterView.OnItemSelectedListener() {
                    public void onItemSelected(
                            AdapterView<?> parent,
                            View view,
                            int position,
                            long id) {
                        produtoId = ((Produto) parent.getItemAtPosition(position)).getId();
                        produto = ((Produto) parent.getItemAtPosition(position));
                        Double preco = produto.getPreco();
                        txtPreco.setText(String.valueOf(preco).replace(",","").replace(".",","));
                        txtQuantidade.requestFocus();
                    }

                    public void onNothingSelected(AdapterView<?> parent) {
                    }
                }
        );
        txtProduto.setText("");

    }


    private void configureBtnPesquisa(View view){
        ImageButton btn = (ImageButton) view.findViewById(R.id.btnPesquisaProdutoPedido);
        btn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                String txtPesquisa = txtProduto.getText().toString();
                carregarProdutos(txtPesquisa, view);
            }
        });
    }

    private void configureBtnAdd(View view){
        ImageButton btn = (ImageButton) view.findViewById(R.id.btnAddProdutoPedido);
        btn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                quantidade = 0;

                if(produtoId <= 0){
                    Toast.makeText(getActivity().getApplicationContext(), "Produto não selecionado", Toast.LENGTH_SHORT).show();
                } else {
                    if(existeItem(produtoId)) {
                        Toast.makeText(getActivity().getApplicationContext(), "Produto já adicionado ao pedido", Toast.LENGTH_SHORT).show();
                        return;
                    }
                    if(txtQuantidade.getText().toString().equalsIgnoreCase("")){
                        Toast.makeText(getActivity().getApplicationContext(), "Quantidade não informada", Toast.LENGTH_SHORT).show();
                    } else{

                        try {
                            quantidade = Utils.round(Double.valueOf(txtQuantidade.getText().toString().replace(",", ".")),3);
                        } catch(Exception e){
                            quantidade = 0;
                        }
                        if (quantidade <= 0) {
                            Toast.makeText(getActivity().getApplicationContext(), "Quantidade não informada", Toast.LENGTH_SHORT).show();
                        } else {
                            InputMethodManager imm = (InputMethodManager)getActivity().getSystemService(Context.
                                    INPUT_METHOD_SERVICE);
                            imm.hideSoftInputFromWindow(getActivity().getCurrentFocus().getWindowToken(), 0);


                            if(quantidade > produto.getQuantidade()){
                                AlertDialog.Builder builder = new AlertDialog.Builder(getActivity());
                                builder.setTitle("Aviso");
                                builder.setMessage("Não existe estoque disponível para esta quantidade. Deseja continuar?");

                                builder.setPositiveButton("Sim", new DialogInterface.OnClickListener() {
                                    @Override
                                    public void onClick(DialogInterface dialog, int which) {
                                        Double preco = Double.valueOf(txtPreco.getText().toString().replace(",","."));
                                        Double preco_origem = preco;
                                        //Double preco = produto.getPreco();
                                        //Double preco_mva = produto.getPrecoMva();
                                        //if(preco_mva > 0 && !isConsumidorFinal && isNotaFiscalOnline){
                                        //    preco = preco_mva;
                                        //}

                                        //Double preco_mva = 0.0;
                                        //Double aliq_mva = produto.getAliqMva();
                                        //Double aliq_icms = produto.getAliqIcms();
                                        //if(aliq_mva > 0 && !isConsumidorFinal && isNotaFiscalOnline){
                                        //    preco_mva = preco +((((preco * aliq_mva / 100) + preco) * aliq_icms / 100) - (preco * aliq_icms / 100));
                                        //    if(preco_mva > 0){
                                        //        preco = preco_mva;
                                        //    }
                                        //}
                                        //if(aliq_mva > 0 && !isConsumidorFinal && isNotaFiscalOnline){

                                        //    Double base_icms = (preco * quantidade);
                                        //    base_icms = Utils.roundZero((base_icms * aliq_mva / 100) + base_icms,2);
                                        //    Double preValorIcms = Utils.roundEven(base_icms * aliq_icms / 100, 4);
                                        //    Double valorIcms = Utils.roundFloor(preValorIcms * 1000,0) / 1000;
                                        //    valorIcms = valorIcms -((preco * quantidade) * aliq_icms / 100);
                                        //    valorIcms = Utils.roundFloor(valorIcms * 1000,2) / 1000;
                                        //    valorIcms = Utils.roundUpZero(valorIcms, 2);
                                        //    valorIcms = Utils.round(valorIcms, 2);
                                        //    preco_mva = Utils.round((Utils.round(preco * quantidade,2) + valorIcms) / quantidade,3);
                                        //    if(preco_mva > 0){
                                        //        preco = preco_mva;
                                        //    }
                                        //}

                                        //txtPreco.setText(String.valueOf(preco));

                                        PedidoItem item = new PedidoItem(-1, -1, produtoId, quantidade, "", "", Utils.round(preco,3), Utils.round(preco_origem,3), Utils.round(Utils.round(preco,3) * quantidade,2));
                                        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                                        dbHandler.insertItemNovoPedido(item);
                                        carregarItens();
                                        dialog.dismiss();
                                    }
                                });
                                builder.setNegativeButton("Não", new DialogInterface.OnClickListener() {
                                    @Override
                                    public void onClick(DialogInterface dialog, int which) {
                                        carregarItens();
                                        dialog.dismiss();
                                    }
                                });


                                AlertDialog alert = builder.create();
                                alert.show();

                            }
                            else {
                                //Adiciona o produto;
                                //Double preco = produto.getPreco();
                                Double preco = Double.valueOf(txtPreco.getText().toString().replace(",","."));
                                Double preco_origem = preco;
                                //Double preco_mva = produto.getPrecoMva();
                                //if(preco_mva > 0 && !isConsumidorFinal && isNotaFiscalOnline){
                                //    preco = preco_mva;
                                //}
                                //Double preco_mva = 0.0;
                                //Double aliq_mva = produto.getAliqMva();
                                //Double aliq_icms = produto.getAliqIcms();
                                //if(aliq_mva > 0 && !isConsumidorFinal && isNotaFiscalOnline){
                                //    preco_mva = preco +((((preco * aliq_mva / 100) + preco) * aliq_icms / 100) - (preco * aliq_icms / 100));
                                //    if(preco_mva > 0){
                                //        preco = preco_mva;
                                //    }
                                //}
                                //if(aliq_mva > 0 && !isConsumidorFinal && isNotaFiscalOnline){
                                //    Double base_icms = (preco * quantidade);
                                //    base_icms = Utils.roundZero((base_icms * aliq_mva / 100) + base_icms,2);
                                //    Double preValorIcms = Utils.roundEven(base_icms * aliq_icms / 100, 4);
                                //    Double valorIcms = Utils.roundFloor(preValorIcms * 1000,0) / 1000;
                                //    valorIcms = valorIcms -((preco * quantidade) * aliq_icms / 100);
                                //    valorIcms = Utils.roundFloor(valorIcms * 1000,2) / 1000;
                                //    valorIcms = Utils.roundUpZero(valorIcms, 2);
                                //    valorIcms = Utils.round(valorIcms, 2);
                                //    preco_mva = Utils.round((Utils.round(preco * quantidade,2) + valorIcms) / quantidade,3);
                                //    if(preco_mva > 0){
                                //        preco = preco_mva;
                                //    }
                                //}



                                PedidoItem item = new PedidoItem(-1, -1, produtoId, quantidade, "", "", Utils.round(preco,3), Utils.round(preco_origem,3), Utils.round(Utils.round(preco,3) * quantidade,2));
                                DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                                dbHandler.insertItemNovoPedido(item);

                                //final LinkedList<PedidoItem> itens = dbHandler.getAllItens(String.valueOf(item.cod_pedido).toString());
                                //listener.onProdutoSelected(String.valueOf(produtoId));
                                //final ListView lv1 = (ListView) viewG.findViewById(R.id.listItens);
                                //lv1.setAdapter(new CustomListAdapter(getActivity(), itens, "itens"));
                                carregarItens();
                            }
                        }
                    }
                }
            }
        });
    }

    private void carregarItens(){
        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
        final LinkedList<PedidoItem> itens = dbHandler.getAllItens(pedidoId);
        //listener.onProdutoSelected(String.valueOf(produtoId));
        final ListView lv1 = (ListView) viewG.findViewById(R.id.listItens);

        lv1.setAdapter(new CustomListAdapter(getActivity(), itens, "itens"));
        lv1.setOnItemLongClickListener(new AdapterView.OnItemLongClickListener() {

            public boolean onItemLongClick(AdapterView<?> arg0, View arg1,
                                           int pos, long id) {
                if(pedidoId.equals("-1")) {
                    posicao = pos;
                    AlertDialog.Builder builder = new AlertDialog.Builder(getActivity());
                    builder.setTitle("Questão");
                    builder.setMessage("Deseja remover o produto selecionado?");

                    builder.setPositiveButton("Sim", new DialogInterface.OnClickListener() {
                        @Override
                        public void onClick(DialogInterface dialog, int which) {
                            final ListView lv1 = (ListView) viewG.findViewById(R.id.listItens);
                            final CustomListAdapter adapter = (CustomListAdapter) lv1.getAdapter();
                            DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                            dbHandler.deleteItemNovoPedido(String.valueOf(((PedidoItem) adapter.getItem(posicao)).getCodProduto()).toString());
                            carregarItens();
                            dialog.dismiss();
                        }
                    });
                    builder.setNegativeButton("Não", new DialogInterface.OnClickListener() {
                        @Override
                        public void onClick(DialogInterface dialog, int which) {
                            carregarItens();
                            dialog.dismiss();
                        }
                    });


                    AlertDialog alert = builder.create();
                    alert.show();
                }
                return true;
            }
        });
        txtQuantidade.setText("");
        txtPreco.setText("");
        produtoSpinner.setSelection(0);
        txtValorTotal.setText("Valor total: " + String.format(new Locale("pt", "BR"), "%1$,.2f", valorTotalItens()));
    }
    private boolean existeItem(int codProduto) {

        final ListView lv1 = (ListView) viewG.findViewById(R.id.listItens);
        final CustomListAdapter adapter = (CustomListAdapter)lv1.getAdapter();
        for(int i=0; i< adapter.getCount(); i++){
            if(((PedidoItem)adapter.getItem(i)).getCodProduto() == codProduto)
                return true;
        }
        return false;
    }
    private double valorTotalItens() {
        double valor = 0;
        final ListView lv1 = (ListView) viewG.findViewById(R.id.listItens);
        final CustomListAdapter adapter = (CustomListAdapter)lv1.getAdapter();
        if(adapter != null) {
            for (int i = 0; i < adapter.getCount(); i++) {
                valor += ((PedidoItem) adapter.getItem(i)).getValorTotal();
            }
        }
        return Utils.round(valor,2);
    }
    private boolean carregaConsumidorFinal(){

        boolean ret = false;
        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
        Pedido pedido = dbHandler.getPedido(pedidoId);
        isNotaFiscalOnline = (pedido.getGerarNF() == 1);
        final LinkedList<Cliente> clientes = dbHandler.getCliente(String.valueOf(String.valueOf(pedido.getCodCliente()).toString()));
        if(clientes.size() > 0) {
            ret = (clientes.get(0).getConsumidorFinal() == 1);
        }
        return ret;
    }
   */
}