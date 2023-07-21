import svgLoader from 'vite-svg-loader'

export default defineNuxtConfig({
  app: {
    head: {
      link: [{ rel: 'icon', type: 'image/x-icon', href: './favicon.ico'}]
    }
  },
  ssr: true,
  devtools: {
    enabled: true,
  },
  postcss: {
    plugins: {
      tailwindcss: {},
      autoprefixer: {},
    },
  },
  modules: [
    '@nuxtjs/google-fonts',
    //'@nuxtjs/tailwindcss'
  ],
  googleFonts: {
    families: {
      'Poppins': [200, 400, 600],
      'Cormorant Garamond': true
    }
  },
  css: ['@/src/styles/reno.scss'],
  vite: {
    plugins: [svgLoader()]
  }
});
