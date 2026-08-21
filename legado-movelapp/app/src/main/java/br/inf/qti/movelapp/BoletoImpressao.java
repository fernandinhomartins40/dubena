package br.inf.qti.movelapp;

import android.app.Activity;
import android.bluetooth.BluetoothDevice;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Bitmap.Config;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.DashPathEffect;
import android.graphics.Paint;
import android.graphics.Typeface;
import android.widget.Toast;

import java.io.IOException;
import java.text.DecimalFormat;
import java.util.Arrays;
import java.util.Formatter;
import java.util.LinkedList;
import java.util.Locale;
import java.util.Set;

/**
 * Created by flavio on 20/09/2014.
 */
public class BoletoImpressao {
    private static boolean connected = false;
    private Activity atividade;
    public Boleto boleto;
    //Para impressão em Bitmap
    Bitmap mBitmap = null;
    static Bitmap mBitmapLogo = null;
    static Bitmap mBitmapBanco = null;
    Bluetooth mBth = new Bluetooth();
    Integer mDensity = 8;
    String alerta;

    public BoletoImpressao(Boleto boleto, Activity atividade) {
        this.boleto = boleto;
        this.atividade = atividade;
    }


    public String imprimirBoletoBmp() {

        try {
            try {
                mBitmapLogo = BitmapFactory.decodeStream(atividade.getApplicationContext().getAssets().open("logo_empresa.png"));
            } catch (IOException e) {
                e.printStackTrace();
            }

            mBitmap = createBoletoBmp();


            if (!checkBth())
                return "9" + alerta;
            if (mBitmap != null) {
                ESCP.ImageToEsc(mBitmap, mBth.Ostream, 8, mDensity);
            }
            closeBth();
            return "2";

        } catch (Exception e) {
            return "9Erro impressão Boleto BMP: " + e.getMessage() + e.getStackTrace()[0].getLineNumber();
        }
    }

    public Bitmap gerarBoletoBmp() {

        try {
            try {
                mBitmapLogo = BitmapFactory.decodeStream(atividade.getApplicationContext().getAssets().open("logo_empresa.png"));
                mBitmapBanco = BitmapFactory.decodeStream(atividade.getApplicationContext().getAssets().open(this.boleto.getBanco() + ".png"));
            } catch (IOException e) {
                e.printStackTrace();
            }

            mBitmap = createBoletoBmp();

            return mBitmap;

        } catch (Exception e) {
            return null;
        }
    }


    public Bitmap createBoletoBmp()
    {
        //Tamanho do Bitmap
        //-----------------
        Boleto bol = this.boleto;
        int size_text=20, size_legend=22, size_text2=18;

        Paint fontTitleBold = new Paint(Color.BLACK);
        fontTitleBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        fontTitleBold.setTextSize((int)(size_text*1.2));

        Paint fontText = new Paint(Color.BLACK);
        fontText.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.NORMAL));
        fontText.setTextSize((int)(size_text));

        Paint fontText2 = new Paint(Color.BLACK);
        fontText2.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.NORMAL));
        fontText2.setTextSize((int)(size_text2));

        Paint fontTextBold = new Paint(Color.BLACK);
        fontTextBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        fontTextBold.setTextSize((int)(size_text));

        Paint fontLegend = new Paint(Color.BLACK);
        fontLegend.setTypeface(Typeface.create(Typeface.MONOSPACE,Typeface.NORMAL));
        fontLegend.setTextSize(size_legend);

        Paint fontLegendBold = new Paint(Color.BLACK);
        fontLegendBold.setTypeface(Typeface.create(Typeface.MONOSPACE,Typeface.BOLD));
        fontLegendBold.setTextSize(size_legend);

        Paint p = new Paint(Color.RED);
        p.setStyle(Paint.Style.STROKE);
        p.setStrokeWidth(2);
        int x=0, y=0, w=576, h=w*5;
        int ay = y;
        //------------------------------------------------
        //Bitmap rotacionado usado para Boletos
        //------------------------------------------------
        int W=1700, H=576;
        Bitmap BitmapBoleto = Bitmap.createBitmap(H, W, Config.RGB_565);
        Canvas g2 = new Canvas(BitmapBoleto);
        g2.drawColor(Color.WHITE);

        g2.rotate(90,H/2,H/2);
        //g2.rotate(90);

        //x=0; y=0;
        if (mBitmapBanco!=null)
        {
            g2.drawBitmap(mBitmapBanco, x, y, p);
        }

        //g2.drawRect(0, 50, W, H-100, p);

        String linha1, linha2;
        int tamL = 23, nlinha = 50, espaco = 4;
        //Linha1 - banco + linha digitável
        linha1 =  "  " + bol.getBanco() + "-" + bol.getBancoDv()  + "     " + bol.getLinhaDigitavel();
        g2.drawText(linha1, 250, 40, fontTextBold);
        g2.drawLine(250, 0, 250, nlinha, p);
        g2.drawLine(370, 0, 370, nlinha, p);
        //Linha2/3 - local de pagamento
        linha1 = getLinha("Local de Pagamento    " + bol.getLocalPagamento().get(0), 100, "L") + repeat(" ", 7) + repeat(" ", 5) + getLinha("Vencimento", 20, "L");
        linha2 = getLinha("                      " + (bol.getLocalPagamento().size()>1?bol.getLocalPagamento().get(1):""), 100, "L") + repeat(" ", 7) + repeat(" ", 5) + getLinha(bol.getVencimento(), 34 , "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha2, 20, nlinha, fontText2);
        g2.drawLine(0, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha que separa todas na vertical
        g2.drawLine(1200, 50+espaco, 1200, 50+(tamL*13)+espaco, p);
        //Linha 4/5 cedente
        linha1 = getLinha("Beneficiário", 100, "L") + repeat(" ", 7) + repeat(" ", 5) + "Agência/Código Beneficiário";
        linha2 = getLinha(bol.getBenefNome() + " / CNPJ: " + bol.getBenefDocumento(), 100, "L") + repeat(" ", 7) + repeat(" ", 5) + getLinha(bol.getAgencia() + "/" + bol.getCedente(), 34, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha2, 20, nlinha, fontText2);
        g2.drawLine(x, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 6/7
        linha1 = getLinha("Data do Documento", 27, "C") + getLinha("Nº do Documento", 20, "C") + getLinha("Espécie Doc", 16, "C") + getLinha("Aceite", 10, "C") + getLinha("Data de Processamento", 27, "C") + repeat(" ", 7) + repeat(" ", 5) + "Carteira/Nosso Número";
        linha2 = getLinha(bol.getDataDocumento(), 27, "C") + getLinha(bol.getDocumento(), 20, "C") + getLinha(bol.getEspecie(), 16, "C") + getLinha(bol.getAceite(), 10, "C") + getLinha(bol.getDataDocumento(), 27, "C") + repeat(" ", 7) + repeat(" ", 5) + getLinha(bol.getNossoNumero() + "-" + bol.getDvNossoNumero(), 34, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha2, 20, nlinha, fontText2);
        g2.drawLine(0, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linhas de separação vertical
        g2.drawLine(315, nlinha-(tamL*2)+espaco, 315, nlinha+espaco, p);
        g2.drawLine(540, nlinha-(tamL*2)+espaco, 540, nlinha+espaco, p);
        g2.drawLine(700, nlinha-(tamL*2)+espaco, 700, nlinha+espaco, p);
        g2.drawLine(820, nlinha-(tamL*2)+espaco, 820, nlinha+espaco, p);
        //Linha 8/9
        Formatter f1 = new Formatter();
        String valor = String.valueOf(f1.format("%1.2f", Utils.round(bol.getValor(), 2))).replace(".", ",");
        linha1 = getLinha("Uso do Banco", 27, "C") +      getLinha("Carteira", 15, "C") + getLinha("Espécie", 14, "C") + getLinha("Quantidade", 16, "C") + getLinha("(x) Valor", 28, "C") + repeat(" ", 7) + repeat(" ", 5) + "(=) Valor Documento";
        linha2 = getLinha("", 27, "C") + getLinha(bol.getCarteira(), 15, "C") + getLinha("R$", 14, "C") + getLinha("", 16, "C") + getLinha("", 28, "R") + repeat(" ", 7) + repeat(" ", 5) + getLinha(valor, 34,"R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha2, 20, nlinha, fontText2);
        g2.drawLine(x, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linhas de separação vertical
        g2.drawLine(315, nlinha-(tamL*2)+espaco, 315, nlinha+espaco, p);
        g2.drawLine(490, nlinha-(tamL*2)+espaco, 490, nlinha+espaco, p);
        g2.drawLine(630, nlinha-(tamL*2)+espaco, 630, nlinha+espaco, p);
        g2.drawLine(820, nlinha-(tamL*2)+espaco, 820, nlinha+espaco, p);
        //Linha 1 das instruções
        linha1 = getLinha("Instruções de Responsabilidade do beneficiário. Qualquer dúvida sobre esse boleto, contacte o beneficiário", 107, "L") + repeat(" ", 5) + getLinha("(-) Desconto/Abatimentos", 24, "L") + getLinha("0,00", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(1200, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 2 das instruções
        linha1 = getLinha("", 107, "L") + repeat(" ", 5) + getLinha("(-) Outras Deduções", 24, "L") + getLinha("0,00", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(1200, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 3 das instruções
        String inst = "";
        if(bol.getInstrucoes().size() > 0){
            inst = bol.getInstrucoes().get(0);
        }
        linha1 = getLinha(inst, 107, "L") + repeat(" ", 5) + getLinha("(+) Mora/Multa", 24, "L") + getLinha("0,00", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(1200, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 4 das instruções
        inst = "";
        if(bol.getInstrucoes().size() > 1){
            inst = bol.getInstrucoes().get(1);
        }
        linha1 = getLinha(inst, 107, "L") + repeat(" ", 5) + getLinha("(+) Outros Acréscimos", 24, "L") + getLinha("0,00", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(1200, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linha 4 das instruções
        inst = "";
        if(bol.getInstrucoes().size() > 2){
            inst = bol.getInstrucoes().get(2);
        }
        linha1 = getLinha(inst, 107, "L") + repeat(" ", 5) + getLinha("(=) Valor Cobrado", 24, "L") + getLinha("0,00", 10, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        g2.drawLine(20, nlinha+espaco, W-10, nlinha+espaco, p);
        //Linhas do Pagador (sacado)
        linha1 = bol.getPagadorNome();
        if(bol.getPagadorDocumento() != "" && bol.getPagadorDocumento() != null && bol.getPagadorDocumento() != "null"){
            linha1 += " / " + bol.getPagadorDocumento();
        }
        nlinha += tamL;
        g2.drawText("Pagador", 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(bol.getPagadorEndereco() + " - CEP " + bol.getPagadorCep(), 20, nlinha, fontText2);
        nlinha += tamL;
        g2.drawText(bol.getPagadorBairro() + " - " + bol.getPagadorCidade() + "/" + bol.getPagadorUf(), 20, nlinha, fontText2);
        g2.drawLine(x, nlinha+espaco, W-10, nlinha+espaco, p);
        linha1 = getLinha("Sacador/Avalista", 99, "L") + repeat(" ", 5) + getLinha("Autenticação Mecânica-Ficha de Compensação", 42, "R");
        nlinha += tamL;
        g2.drawText(linha1, 20, nlinha, fontText2);
        BarI25 b25 = new BarI25();
        Bitmap i25 = b25.createI25(bol.getCodigoBarras());
        g2.drawBitmap(i25, 20, H-97, p);

        x=0; y=ay;

        x=0; y+=2000;

        h = (int)(y / 64);
        h = ((h+10) * 64);

        Bitmap BitmapReturn = Bitmap.createBitmap(BitmapBoleto.getWidth(), W, Config.RGB_565);
        Canvas g3 = new Canvas(BitmapReturn);

        g3.drawBitmap(BitmapBoleto, 0, 0, p);

        return BitmapReturn;

    }

    public String getLinha(String linha, int tamanho, String align){
        String retorno = linha;
        if(linha.length() > tamanho){
            retorno = linha.substring(0,tamanho);
        } else {
            String espacos = repeat(" ", tamanho - linha.length());
            if(align == "R"){
                retorno = espacos + linha;
            } else if(align == "L"){
                retorno = linha + espacos;
            } else {
                int metade = Math.round(espacos.length()/2);
                retorno = repeat(" ", metade) + linha + repeat(" ", espacos.length() - metade);
            }
        }
        return retorno;
    }

    public String repeat(String val, int count){
        StringBuilder buf = new StringBuilder(val.length() * count);
        while (count-- > 0) {
            buf.append(val);
        }
        return buf.toString();
    }

    public String[][] itensArray(LinkedList<NotafiscalItem> itens){
        String[][] ret = new String[itens.size()][5];
        for(int i=0; i < itens.size(); i++){
            NotafiscalItem item = itens.get(i);
            ret[i][0] = item.getDescricao();
            ret[i][1] = item.getUnidade();
            ret[i][2] = String.format(new Locale("pt", "BR"), "%1$,.0f",item.getQuantidade()).replace(".",",");
            ret[i][3] = String.format(new Locale("pt", "BR"), "%1$,.2f",item.getPreco()).replace(".",",");
            ret[i][4] = String.format(new Locale("pt", "BR"), "%1$,.2f",item.getValorTotal()).replace(".",",");
        }
        return ret;
    }

    public String[] splitInParts(String s, int partLength)
    {
        int len = s.length();

        // Number of parts
        int nparts = (len + partLength - 1) / partLength;
        String parts[] = new String[nparts];

        // Break into parts
        int offset= 0;
        int i = 0;
        while (i < nparts)
        {
            parts[i] = s.substring(offset, Math.min(offset + partLength, len));
            offset += partLength;
            i++;
        }

        return parts;
    }

    public boolean closeBth()
    {
        if (mBth.isConnected())
        {
            return mBth.Close();
        }
        return false;
    }

    public boolean checkBth()
    {
        if (!mBth.isConnected())
        {
            if (!mBth.Enable())
            {
                alerta = "Nao foi possivel habilitar bluetooth, tente habilitar manualmente.";
                return false;
            }
            String mac=null;
            Set<BluetoothDevice> devices = mBth.GetBondedDevices();
            for (BluetoothDevice device : devices)
            {
                if ("MPT-III".equals(device.getName()))
                {
                    mac = device.getAddress();
                }
            }
            if (mac==null)
            {
                alerta = "Nao foi encontrada impressora MPT-III\n\nFaça o pareamento com o disposivo e tente novamente.";
                return false;
            }
            if (!mBth.Open(mac))
            {
                alerta = "Nao foi possivel conectar ao dispositivo ["+mac+"]\n\nLigue ou conecte o dispositivo e tente novamente.";
                return false;
            }
        }
        return true;
    }
}
