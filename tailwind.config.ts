import type { Config } from "tailwindcss";

export default <Partial<Config>>{
  important: true,
  content: [
    './*.{vue,js,ts}',
    './app.vue',
    './assets/**/*.css',
    './components/*.{vue,js}',
    './components/**/*.{vue,js}',
    './nuxt.config.{js,ts}',
    './pages/*.vue',
    './pages/**/*.vue',
    './plugins/**/*.{js,ts}',
    './src/**/*.{html,vue,js,ts}'
  ],
  theme: {
    extend: {
      animation: {
        'load': 'loading 2s infinite ease-in-out'
      },
      backgroundImage: {
        'nav-gradient': 'linear-gradient(135deg, rgba(0, 0, 0, 0.5) 0%, rgba(0, 0, 0, 0) 100%)',
        'home-bg': "url('@/images/bg-tetons.jpg')"
      },
      boxShadow: {
        'special-button': '0 0 5px #ff7940, 0 0 25px #ff7940, 0 0 50px #ff7940, 0 0 100px #ff7940'
      },
      colors: {
        'accent': '#f24900',
        'accent-light': '#ff7940'
      },
      keyframes: {
        loading: {
          '0%': { height: '0', top: '0', bottom: 'auto' },
          '50%': { height: '100%', top: '0', bottom: 'auto' },
          '51%': { height: '100%', top: '0', bottom: 'auto' },
          '52%': { height: '100%', top: 'auto', bottom: '0' },
          '100%': { height: '0', top: 'auto', bottom: '0' },
        }
      },
      transitionTimingFunction: {
        'mobile-nav': 'cubic-bezier(0.4, 0, 0.2, 1)'
      }
    },
    fontFamily: {
      poppins: ['Poppins', "sans-serif"],
      cormorant: ['Cormorant Garamond', "sans-serif"],
    }
  }
};
