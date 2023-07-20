import type { Config } from "tailwindcss";

export default <Partial<Config>>{
  important: true,
  content: [
    './pages/**/*.{html,vue}',
    './src/**/*.{html,vue,js,ts}'
  ],
  theme: {
    extend: {
      animation: {
        'load': 'loading 2s infinite ease-in-out'
      },
      backgroundImage: {
        'nav-gradient': 'linear-gradient(135deg, rgba(0, 0, 0, 0.5) 0%, rgba(0, 0, 0, 0) 100%)'
      },
      colors: {
        accent: 'var(--accent-color)'
      },
      keyframes: {
        loading: {
          '0%': { height: '0', top: '0', bottom: 'auto' },
          '50%': { height: '100%', top: '0', bottom: 'auto' },
          '51%': { height: '100%', top: '0', bottom: 'auto' },
          '52%': { height: '100%', top: 'auto', bottom: '0' },
          '100%': { height: '0', top: 'auto', bottom: '0' },
        }
      }
    },
    fontFamily: {
      poppins: ['Poppins', "sans-serif"],
      cormorant: ['Cormorant Garamond', "sans-serif"],
    }
  }
};
