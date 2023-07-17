export default defineNuxtConfig({
  ssr: true,
  devtools: {
    enabled: true,
  },
  modules: [
    ['@nuxtjs/tailwindcss', {
      cssPath: '~/assets/css/reno.css'
    }]
  ]
});
