package br.inf.qti.movelapp;

import android.app.Activity;
import android.bluetooth.BluetoothDevice;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Typeface;

import java.io.IOException;
import java.util.Arrays;
import java.util.HashMap;
import java.util.LinkedList;
import java.util.Locale;
import java.util.Map;
import java.util.Set;

//import inputservice.NfePrinter.ReceiptPrinterA7;

/**
 * Created by flavio on 20/09/2014.
 */
public class ReportImpressao {
    private String codReport;
    //private static ReceiptPrinterA7 receiptprinter;
    private static boolean connected = false;
    private Activity atividade;
    //Para impressão em Bitmap
    Bitmap mBitmap = null;
    static Bitmap mBitmapLogo = null;
    Bluetooth mBth = new Bluetooth();
    Integer mDensity = 8;
    String alerta;

    public ReportImpressao(String codReport, Activity atividade){
        this.codReport=codReport;
        this.atividade=atividade;
    }

    public String imprimirReport1Bmp(String dataInicial, String dataFinal) {

        try {
            try {
                mBitmapLogo = BitmapFactory.decodeStream(atividade.getApplicationContext().getAssets().open("logo_empresa.png"));
            } catch (IOException e) {
                e.printStackTrace();
            }

            mBitmap = createReport1Bmp(dataInicial, dataFinal);


            if (!checkBth())
                return "9" + alerta;
            if (mBitmap != null) {
                ESCP.ImageToEsc(mBitmap, mBth.Ostream, 8, mDensity);
            }
            closeBth();
            return "2";

        } catch (Exception e) {
            return "9Erro impressão Relatório BMP: " + e.getMessage();
        }
    }

    public Bitmap gerarReport1Bmp(String dataInicial, String dataFinal) {

        try {
            try {
                mBitmapLogo = BitmapFactory.decodeStream(atividade.getApplicationContext().getAssets().open("logo_empresa.png"));
            } catch (IOException e) {
                e.printStackTrace();
            }

            mBitmap = createReport1Bmp(dataInicial, dataFinal);

            return mBitmap;

        } catch (Exception e) {
            return null;
        }
    }


    public Bitmap createReport1Bmp(String dataInicial, String dataFinal)
    {
        int x=0, y=0, w=576, h=0;
        int size_text=32, size_legend=16, size_chave=22, row_width=55, row_height=20, size_legend_title=14, row_height_title=17;
        int witdthProd = 10;
        DataBaseHandler dbHandler = new DataBaseHandler(atividade.getApplicationContext());
        final LinkedList<Pedido> pedidos = dbHandler.listVendasPeriodo(dataInicial.toString(), dataFinal.toString());

        //CALCULO DO TAMANHO DO BITMAP
        //============================
        h+=10*row_height; //Cabeçalho + totais
        //produtos
        h+=3*row_height;
        for(int i=0; i < pedidos.size(); i++){
            Pedido pedido = pedidos.get(i);
            int witdthField1_1 = witdthProd - 5;
            int quantprod = 1;
            if(pedido.getCliente().length() > (witdthField1_1 - 1)){
                quantprod = 2;
            }

            h+=quantprod*row_height;
        }


        Paint fontTitleBold = new Paint(Color.BLACK);
        fontTitleBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        fontTitleBold.setTextSize((int)(size_text*1.2));

        Paint fontText = new Paint(Color.BLACK);
        fontText.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.NORMAL));
        fontText.setTextSize((int)(size_text));

        Paint fontTextBold = new Paint(Color.BLACK);
        fontTextBold.setTypeface(Typeface.create(Typeface.MONOSPACE, Typeface.BOLD));
        fontTextBold.setTextSize((int)(size_text));

        Paint fontLegend = new Paint(Color.BLACK);
        fontLegend.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(),"fonts/unispace rg.ttf"));
        fontLegend.setTextSize(size_legend);

        Paint fontLegendBold = new Paint(Color.BLACK);
        fontLegendBold.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(),"fonts/unispace bd.ttf"));
        fontLegendBold.setTextSize(size_legend);

        Paint fontLegendTitle = new Paint(Color.BLACK);
        fontLegendTitle.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(),"fonts/unispace rg.ttf"));
        fontLegendTitle.setTextSize(size_legend_title);

        Paint fontLegendTitleBold = new Paint(Color.BLACK);
        fontLegendTitleBold.setTypeface(Typeface.createFromAsset(atividade.getApplicationContext().getAssets(),"fonts/unispace bd.ttf"));
        fontLegendTitleBold.setTextSize(size_legend_title);

        Paint fontChave = new Paint(Color.BLACK);
        fontChave.setTypeface(Typeface.create(Typeface.SANS_SERIF,Typeface.NORMAL));
        fontChave.setTextSize(size_chave);

        Paint fontChaveBold = new Paint(Color.BLACK);
        fontChaveBold.setTypeface(Typeface.create(Typeface.SANS_SERIF,Typeface.BOLD));
        fontChaveBold.setTextSize(size_chave);

        Paint p = new Paint(Color.RED);
        p.setStyle(Paint.Style.STROKE);
        p.setStrokeWidth(2);


        Bitmap BitmapDanfe = Bitmap.createBitmap(w, h, Bitmap.Config.RGB_565);

        Canvas g = new Canvas(BitmapDanfe);
        g.drawColor(Color.WHITE);

        //CABEÇALHO
        if (mBitmapLogo!=null)
        {
            x+=5;
            y+=5;
            g.drawBitmap(mBitmapLogo, x, y, p);
        }
        x += mBitmapLogo.getWidth()+15;

        //DESCRIÇÃO DO CABECALHO
        //==========================================================================
        String campo1 = "";
        String campo2 = "Relatório de Pedidos";
        String campo3 = "Período: " + dataInicial + " a " + dataFinal;
        String campo4 = "";

        //char[] chars = new char[row_width];
        //Arrays.fill(chars, ' ');
        y+=row_height_title;
        //x=10;
        g.drawText(campo1, x, y, fontLegendTitleBold);
        y+=row_height_title;
        g.drawText(campo2, x, y, fontLegendTitleBold);
        y+=row_height_title;
        g.drawText(campo3, x, y, fontLegendTitle);
        y+=row_height_title;
        g.drawText(campo4, x, y, fontLegendTitle);
        y+=row_height_title;

        //y+=(1*row_height_title);
        g.drawRect(1, 1, w, y+10, p);

        double perc = w / row_width;
        int pos=0,somapos=0;
        int witdthField1=witdthProd, witdthField4=30,witdthField5=8,witdthField6=10;
        y+=10;
        //Retangulo
        int y_1 = y;
        //Cabeçalho
        pos=0;
        somapos=0;
        pos = (int)(Utils.round(witdthField1*perc,0));
        g.drawRect(somapos,y,pos,y+row_height+5,p);
        somapos += pos;
        pos = (int)(Utils.round(witdthField4*perc,0));
        g.drawRect(somapos,y,pos+somapos,y+row_height+5,p);
        somapos += pos;
        pos = (int)(Utils.round(witdthField5*perc,0));
        g.drawRect(somapos,y,pos+somapos,y+row_height+5,p);
        somapos += pos;
        pos = (int)(Utils.round(witdthField6*perc,0));
        g.drawRect(somapos,y,pos+somapos,y+row_height+5,p);

        x=5;
        y+=row_height;

        campo1 = "Pedido";
        char[] chars = new char[witdthField1 - campo1.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars);
        campo2 = "Cliente";
        chars = new char[witdthField4 - campo2.length()-1];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + campo2 + new String(chars);
        campo2 = "Qtde";
        chars = new char[witdthField5 - campo2.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars) + campo2;
        campo2 = "Valor";
        chars = new char[witdthField6 - campo2.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars) + campo2;
        g.drawText(campo1, x, y, fontLegend);
        //Itens
        x=5;
        y+=5;
        double valor = 0;
        double quantidade = 0;

        for(int i=0; i < pedidos.size(); i++){
            y+=row_height;
            Pedido pedido = pedidos.get(i);
            valor += pedido.getValor_venda();
            quantidade += pedido.getQuantidade();
            pos=0; somapos=0;
            campo1 = String.valueOf(pedido.getId());
            //Descrição maior que o tamanho do campo
            //=======================================
            int witdthField1_1 = witdthField1 - 5;
            //==================================================
            chars = new char[witdthField1 - campo1.length()];
            Arrays.fill(chars, ' ');
            campo1 = campo1 + new String(chars);
            //campo2 = "";
            //chars = new char[witdthField3 - campo2.length()];
            //Arrays.fill(chars, ' ');
            //campo1 = campo1 + campo2 + new String(chars);

            campo2 = pedido.getCliente();
            if(campo2.length() > 20)
                campo2 = campo2.substring(0,19);
            chars = new char[witdthField4 - campo2.length() - 1];
            Arrays.fill(chars, ' ');
            campo1 = campo1 + campo2 + new String(chars);
            campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f",pedido.getQuantidade()).replace(".",",");  //trocas
            chars = new char[witdthField5 - campo2.length()];
            Arrays.fill(chars, ' ');
            campo1 = campo1 + new String(chars) + campo2;
            campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f",pedido.getValor_venda()).replace(".",",");
            chars = new char[witdthField6 - campo2.length()];
            Arrays.fill(chars, ' ');
            campo1 = campo1 + new String(chars) + campo2;
            g.drawText(campo1, x, y, fontLegend);

            //=================================================
        }
        //Rodapé
        pos=0;
        somapos=0;
        pos = (int)(Utils.round(witdthField1*perc,0));
        g.drawRect(somapos,y+5,pos,y+row_height+15,p);
        somapos += pos;
        pos = (int)(Utils.round(witdthField4*perc,0));
        g.drawRect(somapos,y+5,pos+somapos,y+row_height+15,p);
        somapos += pos;
        pos = (int)(Utils.round(witdthField5*perc,0));
        g.drawRect(somapos,y+5,pos+somapos,y+row_height+15,p);
        somapos += pos;
        pos = (int)(Utils.round(witdthField6*perc,0));
        g.drawRect(somapos,y+5,pos+somapos,y+row_height+15,p);

        y+=row_height+5;
        campo1 = "Total";
        chars = new char[witdthField1 - campo1.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars);
        campo2 = "";
        chars = new char[witdthField4 - campo2.length() - 1];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars) + campo2;
        campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f",quantidade).replace(".",",");
        chars = new char[witdthField5 - campo2.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars) + campo2;
        campo2 = String.format(new Locale("pt", "BR"), "%1$,.2f",valor).replace(".",",");
        chars = new char[witdthField6 - campo2.length()];
        Arrays.fill(chars, ' ');
        campo1 = campo1 + new String(chars) + campo2;
        g.drawText(campo1, x, y, fontLegendBold);

        g.drawRect(0, y_1, w, y+10, p);

        somapos=0;
        pos = (int)(Utils.round(witdthField1*perc,0));
        g.drawRect(somapos,y_1,pos,y+10,p);
        somapos += pos;
        pos = (int)(Utils.round(witdthField4*perc,0));
        g.drawRect(somapos,y_1,pos+somapos,y+10,p);
        somapos += pos;
        pos = (int)(Utils.round(witdthField5*perc,0));
        g.drawRect(somapos,y_1,pos+somapos,y+10,p);
        somapos += pos;
        pos = (int)(Utils.round(witdthField6*perc,0));
        g.drawRect(somapos,y_1,pos+somapos,y+10,p);



        y+=row_height+5;
        g.drawText("Desenvolvido por QTI - www.qti.inf.br", x, y, fontLegendTitle);
        Bitmap BitmapReturn = Bitmap.createBitmap(BitmapDanfe.getWidth(), h, Bitmap.Config.RGB_565);
        Canvas g3 = new Canvas(BitmapReturn);

        g3.drawBitmap(BitmapDanfe, 0, 0, p);
        return BitmapReturn;

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
