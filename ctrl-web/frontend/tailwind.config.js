/** @type {import('tailwindcss').Config} */
// Paleta da marca Dubena (extraída dos logos): azul primário, amarelo destaque, roxo accent.
export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        // Azul da marca (primary)
        marca: {
          50: '#eef3fb', 100: '#d6e2f5', 200: '#aec5ea', 300: '#7ea2db',
          400: '#4f7ccb', 500: '#2a54ad', 600: '#244a98', 700: '#1f3c7c',
          800: '#2a3b85', 900: '#1b2a5e',
        },
        // Amarelo da marca (destaque/atenção)
        destaque: { DEFAULT: '#e7eb13', 600: '#c4c70f' },
        // Roxo da identidade (accent / header legado)
        accent: { DEFAULT: '#672290', 500: '#7b2aa8', 600: '#605ca8' },
        // Azul claro
        info: { DEFAULT: '#137bc9' },
      },
    },
  },
  plugins: [],
}
