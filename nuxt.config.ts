export default defineNuxtConfig({
  app: {
    head: {
      link: [{ rel: 'icon', type: 'image/x-icon', href: '/favicon.ico'}]
    }
  },
  ssr: true,
  devtools: {
    enabled: true,
  },
  modules: [
    '@nuxtjs/google-fonts',
    '@nuxtjs/tailwindcss'
  ],
  googleFonts: {
    families: {
      'Poppins': true,
      'Cormorant Garamond': true
    }
  },
  tailwindcss: {
    cssPath: '~/src/styles/reno.scss'
  }
});
