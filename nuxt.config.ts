export default defineNuxtConfig({
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
    cssPath: '~/src/styles/reno.css'
  }
});
