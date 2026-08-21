package br.inf.qti.movelapp;

import java.io.ByteArrayOutputStream;

import android.graphics.Bitmap;
import android.graphics.Bitmap.Config;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Paint.Style;
import android.graphics.Rect;

public class BarI25 {
	private ByteArrayOutputStream _bmp = new ByteArrayOutputStream();
	
	public BarI25()
	{
		byte [] head = {
				//Bitmap file header
				0x42, 0x4D, //0|2 signature, must be 4D42 hex
				0x72, 0x00, 0x00, 0x00, //2|4 size of BMP file in bytes (unreliable)
				0x00, 0x00, //6|2 reserved, must be zero
				0x00, 0x00, //8|2 reserved, must be zero
				0x3E, 0x00, 0x00, 0x00, //10|4 offset to start of image data in bytes
				//DIB header (bitmap information header)
				0x28, 0x00, 0x00, 0x00, //14|4 size of BITMAPINFOHEADER structure, must be 40
				(byte)0x96, 0x01, 0x00, 0x00, //18|4 image width in pixels
				0x01, 0x00, 0x00, 0x00, //22|4 image height in pixels
				0x01, 0x00, //26|2 number of planes in the image, must be 1
				0x01, 0x00, //28|2 number of bits per pixel (1, 4, 8, or 24)
				0x00, 0x00, 0x00, 0x00, //30|4 compression type (0=none, 1=RLE-8, 2=RLE-4)
				0x34, 0x00, 0x00, 0x00, //34|4 size of image data in bytes (including padding), This is the size of the raw bitmap data (see below), and should not be confused with the file size.
				0x61, 0x0F, 0x00, 0x00, //38|4 horizontal resolution in pixels per meter (unreliable) (pixel per meter, signed integer)
				0x61, 0x0F, 0x00, 0x00, //42|4 vertical resolution in pixels per meter (unreliable) (pixel per meter, signed integer)
				0x02, 0x00, 0x00, 0x00, //46|4 number of colors in image, or zero
				0x00, 0x00, 0x00, 0x00, //50|4 number of important colors, or zero
				//Color Table (4 * numcolors bytes) repeat number of colors from BITMAPINFOHEADER
				0x00, 0x00, 0x00, 0x00, //color RGBA 1
				(byte)0xFF, (byte)0xFF, (byte)0xFF, 0x00 //color 2
			};
			 this._bmp.write(head, 0, head.length); 		
	}

	public int size(){return 114;};
	public byte [] buff(){return this._bmp.toByteArray();}

	public boolean draw(String code)
	{	
		byte [] bar = new byte[256];
		byte [] map = new byte[512];

		if (!this._encI25(code,bar)) 
			return false;
		int pbar=0; 
		int pmap=0;
		boolean B=false;
		while(bar[pbar]!=(byte)0x00)
		{
			switch(bar[pbar])
			{
			case '0': 
				map[pmap] = (byte)(B?'1':'0');
				pmap++;
				break;
			case '1': 
				map[pmap] = (byte)(B?'1':'0');
				map[pmap+1] = map[pmap];
				map[pmap+2] = map[pmap];
				pmap+=3;
				break;
			}		
			pbar++;
			B=!B;
		}
		int maplen = pmap+19;
		while(pmap < maplen)
		{
			map[pmap]='1';
			pmap++;
		}

		byte [] bite= {0,0,0,0,0,0,0,0,0,0};
		for (int i=0; i<424; i+=8)
		{
			for(int b=0; b<8; b++)
			{
				bite[b]=map[i+b];
			}
			this._bmp.write(this._bin2dec(bite));
		}
		return true;		
	}	
	private boolean _encI25(String s, byte [] e)
	{
		if ((s.length() == 0) || ((s.length() % 2) != 0)) 
			return false;
		String cbi = "0000";
		String cbf = "100";
		String cbn[]={"00110", "10001", "01001", "11000", "00101", "10100", "01100", "00011", "10010", "01010"};
		int enc=0;
		String d1, d2;
		int i = 0; 
		char i1=s.charAt(0), i2=s.charAt(i+1);
		int n;
		for (n=0; n<4; n++) {e[enc]=(byte)cbi.charAt(n); enc++;}
		while(i1!='\0' && i2!='\0')
		{
			if (!Character.isDigit(i1) || !Character.isDigit(i2)) 
				return false;
			d1 = cbn[((int)i1)-48];
			d2 = cbn[((int)i2)-48];		
			for (n=0; n<5; n++) {
				e[enc] = (byte)d1.charAt(n); enc++;
				e[enc] = (byte)d2.charAt(n); enc++;
			}
			i+=2;
			if (i>=s.length())
				break;
			i1=s.charAt(i);
			i2=s.charAt(i+1);
		}
		for (n=0; n<3; n++) {e[enc]=(byte)cbf.charAt(n); enc++;}
		return true;			
	}


	private int _bin2dec(byte [] bin)
	{
	     int ptr=0; byte ptr_char;
	     int sum = 0;
	     int value;
	     int power = -1;
	 
	     while(bin[ptr] != (char)0x00)
	     {
	          ptr++;
	          power++;
	     }
	     ptr=0;
	     ptr_char = bin[ptr];
	     while(ptr_char != (char)0x00)
	     {
	          if(ptr_char == '1')
	               value = 1;
	          else if(ptr_char == '0')
	               value = 0;
	          else
	          {
					return 0;
	          }
	          sum += (int)Math.pow((double)2.0,(int)power) * value;
	          power--;
	          ptr++;
	          ptr_char = bin[ptr];
	     }
		 return sum;		
	}
	
	public Bitmap createI25(String code)
	{
		byte [] bar = new byte[256];
		byte [] map = new byte[512];

		if (!_encI25(code,bar)) 
			return null;		
		
		Bitmap im = Bitmap.createBitmap(1000, 200, Config.RGB_565);
		Canvas g = new Canvas(im);
		g.drawColor(Color.WHITE);
		
		Paint p = new Paint(Color.BLACK);
		p.setStyle(Style.FILL);
		p.setStrokeWidth(0);
		
		int x=0, lowB=2, lowW=3, higB=5, higW=6;
		
		int pbar=0;
		int pmap=0;
		boolean B=false;
		while(bar[pbar]!=(byte)0x00)
		{
			switch(bar[pbar])
			{
			case '0': 
				if (!B)	
				{
					g.drawRect(new Rect(x,0,x+lowB,im.getHeight()), p);
					x+=lowB;
				}else{
					x+=lowW;
				}
				
				map[pmap] = (byte)(B?'1':'0');
				pmap++;
				break;
			case '1': 
				if (!B)
				{
					g.drawRect(new Rect(x,0,x+higB,im.getHeight()), p);
					x+=higB;
				}else{
					x+=higW;
				}
				
				map[pmap] = (byte)(B?'1':'0');
				map[pmap+1] = map[pmap];
				map[pmap+2] = map[pmap];
				pmap+=3;
				break;
			}		
			pbar++;
			B=!B;
		}
		int maplen = pmap+19;
		while(pmap < maplen)
		{
			map[pmap]='1';
			pmap++;
		}

		byte [] bite= {0,0,0,0,0,0,0,0,0,0};
		for (int i=0; i<424; i+=8)
		{
			for(int b=0; b<8; b++)
			{
				bite[b]=map[i+b];
			}
			
			this._bmp.write(this._bin2dec(bite));
		}		
		
		return im;
	}
	
}
