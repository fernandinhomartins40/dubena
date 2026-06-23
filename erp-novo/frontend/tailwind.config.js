/** @type {import('tailwindcss').Config} */
// Design system Dubena (IMPL_UI). Tokens semânticos via CSS vars (index.css) +
// paleta de marca legada mantida durante a migração das telas antigas.
import animate from 'tailwindcss-animate'

const hsl = (v) => `hsl(var(${v}) / <alpha-value>)`

export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    container: { center: true, padding: '1.5rem' },
    extend: {
      colors: {
        border: hsl('--border'),
        input: hsl('--input'),
        ring: hsl('--ring'),
        background: hsl('--background'),
        foreground: hsl('--foreground'),
        primary: { DEFAULT: hsl('--primary'), foreground: hsl('--primary-foreground') },
        secondary: { DEFAULT: hsl('--secondary'), foreground: hsl('--secondary-foreground') },
        muted: { DEFAULT: hsl('--muted'), foreground: hsl('--muted-foreground') },
        accent: { DEFAULT: hsl('--accent'), foreground: hsl('--accent-foreground') },
        destaque: { DEFAULT: hsl('--destaque'), foreground: hsl('--destaque-foreground') },
        destructive: { DEFAULT: hsl('--destructive'), foreground: hsl('--destructive-foreground') },
        success: { DEFAULT: hsl('--success'), foreground: hsl('--success-foreground') },
        card: { DEFAULT: hsl('--card'), foreground: hsl('--card-foreground') },
        popover: { DEFAULT: hsl('--popover'), foreground: hsl('--popover-foreground') },
        sidebar: {
          DEFAULT: hsl('--sidebar'),
          foreground: hsl('--sidebar-foreground'),
          accent: hsl('--sidebar-accent'),
        },
        // Paleta de marca (laranja puro #FF6200). Telas legadas usam marca-600 etc.
        marca: {
          50: '#fff3ea', 100: '#ffe0cc', 200: '#ffbe99', 300: '#ff9b66',
          400: '#ff7e33', 500: '#ff6200', 600: '#e05600', 700: '#b84600',
          800: '#8f3700', 900: '#662700',
        },
        // Lime de destaque (energia) — usar pontualmente.
        lime: {
          DEFAULT: '#dbfb3b', 400: '#e3fc63', 500: '#dbfb3b', 600: '#c2e520',
        },
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
      },
      keyframes: {
        'accordion-down': { from: { height: '0' }, to: { height: 'var(--radix-accordion-content-height)' } },
        'accordion-up': { from: { height: 'var(--radix-accordion-content-height)' }, to: { height: '0' } },
      },
    },
  },
  plugins: [animate],
}
