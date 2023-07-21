export default defineNuxtConfig({
  app: {
    baseURL: '',
    buildAssetsDir: './_nuxt/',
    head: {
      // This is not needed now that baseURL fixed the asset loading
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
  css: ['@/src/styles/reno.scss']
});
