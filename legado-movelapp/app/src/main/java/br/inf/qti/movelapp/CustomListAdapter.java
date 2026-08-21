package br.inf.qti.movelapp;

/**
 * Created by fl_on on 02/06/2017.
 */

import android.content.Context;
import android.graphics.Color;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.BaseAdapter;
import android.widget.ImageView;
import android.widget.TextView;

import java.util.LinkedList;
import java.util.Locale;

/**
 * Created by flavio on 16/06/2014.
 */
public class CustomListAdapter extends BaseAdapter {

    private LinkedList<?> listData;
    private LayoutInflater layoutInflater;
    public String tipo;

    public CustomListAdapter(Context context, LinkedList<?> listData, String tipo) {
        this.listData = listData;
        layoutInflater = LayoutInflater.from(context);
        this.tipo=tipo;
    }

    @Override
    public int getCount() {
        return listData.size();
    }

    @Override
    public Object getItem(int position) {
        return listData.get(position);
    }

    @Override
    public long getItemId(int position) {
        return position;
    }

    public View getView(int position, View convertView, ViewGroup parent) {
        ViewHolder holder;
        if(tipo.equalsIgnoreCase("itens")) {
            if (convertView == null) {
                convertView = layoutInflater.inflate(R.layout.list_row_item_pedido_layout, null);
                holder = new ViewHolder();

                holder.produtoView = (TextView) convertView.findViewById(R.id.txtProdutoItem);
                holder.quantidadeView = (TextView) convertView.findViewById(R.id.txtQuantidadeItem);
                holder.valorView = (TextView) convertView.findViewById(R.id.txtValorItem);
                holder.precoView = (TextView) convertView.findViewById(R.id.txtPrecoItem);

                convertView.setTag(holder);
            } else {
                holder = (ViewHolder) convertView.getTag();
            }

            holder.produtoView.setText(((PedidoItem) listData.get(position)).getProduto());
            holder.quantidadeView.setText(String.format(new Locale("pt", "BR"), "%1$,.3f", ((PedidoItem) listData.get(position)).getQuantidade()));
            holder.valorView.setText("Valor: " + String.format(new Locale("pt", "BR"), "%1$,.2f", ((PedidoItem) listData.get(position)).getValor_total()));
            holder.precoView.setText("Preço: " + String.format(new Locale("pt", "BR"), "%1$,.3f", ((PedidoItem) listData.get(position)).getPreco()));
        } else
        if(tipo.equalsIgnoreCase("pedidos")) {
            if (convertView == null) {

                convertView = layoutInflater.inflate(R.layout.list_row_pedidos, null);
                holder = new ViewHolder();

                holder.vencimentoView = (TextView) convertView.findViewById(R.id.txtVencimento);
                holder.valorView = (TextView) convertView.findViewById(R.id.txtValor);
                holder.documentoView = (TextView) convertView.findViewById(R.id.txtDocto);
                holder.precoView = (TextView) convertView.findViewById(R.id.txtDocto);
                holder.urgenteView = (ImageView) convertView.findViewById(R.id.imgUrgente);
                convertView.setTag(holder);
            } else {
                holder = (ViewHolder) convertView.getTag();
            }
            try {
                if(((Pedido) listData.get(position)).getApp().equalsIgnoreCase("S")){
                    holder.vencimentoView.setTextColor(Color.parseColor("#AB24B7"));
                } else {
                    holder.vencimentoView.setTextColor(Color.parseColor("#000000"));
                }
                if(((Pedido) listData.get(position)).getUrgente().equalsIgnoreCase("S")){
                    holder.urgenteView.setVisibility(View.VISIBLE);
                } else {
                    holder.urgenteView.setVisibility(View.INVISIBLE);
                }
            } catch (Exception e) {
                e.printStackTrace();
            }
            holder.documentoView.setText(((Pedido) listData.get(position)).getDataPedidoTexto());
            holder.valorView.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", ((Pedido) listData.get(position)).getValor_venda()));
            holder.vencimentoView.setText("Pedido " + ((Pedido) listData.get(position)).getId());
        } else
        if(tipo.equalsIgnoreCase("reportVendas")) {
            if (convertView == null) {
                convertView = layoutInflater.inflate(R.layout.list_row_report_vendas, null);
                holder = new ViewHolder();

                holder.pedidoView = (TextView) convertView.findViewById(R.id.txtPedidoReport);
                holder.clienteView = (TextView) convertView.findViewById(R.id.txtClienteReport);
                holder.condicaoView = (TextView) convertView.findViewById(R.id.txtCondicaoReport);
                holder.quantidadeView = (TextView) convertView.findViewById(R.id.txtQtuantidadeReport);
                holder.valorView = (TextView) convertView.findViewById(R.id.txtValorReport);

                convertView.setTag(holder);
            } else {
                holder = (ViewHolder) convertView.getTag();
            }

            holder.pedidoView.setText(String.valueOf(((Pedido) listData.get(position)).getId()));
            holder.clienteView.setText(((Pedido) listData.get(position)).getCliente());
            holder.condicaoView.setText(((Pedido) listData.get(position)).getCondicao());
            holder.quantidadeView.setText(String.format(new Locale("pt", "BR"), "%1$,.3f", ((Pedido) listData.get(position)).getQuantidade()));
            holder.valorView.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", ((Pedido) listData.get(position)).getValor_venda()));
        }
        return convertView;
    }

    static class ViewHolder {
        TextView pedidoView;
        TextView clienteView;
        TextView condicaoView;
        TextView vencimentoView;
        TextView valorView;
        TextView documentoView;
        TextView produtoView;
        TextView quantidadeView;
        TextView precoView;
        ImageView urgenteView;
    }

}