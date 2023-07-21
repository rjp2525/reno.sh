import svgLoader from 'vite-svg-loader'

export default defineNuxtConfig({
  app: {
    head: {
      htmlAttrs: {
        lang: 'en'
      },
      link: [{ rel: 'icon', type: 'image/x-icon', href: './favicon.ico' }],
      meta: [
        { hid: 'description', name: 'description', 'content': 'Denver-based software engineer & photographer fusing passion with creativity to deliver intuitive web solutions and captivating images. Elevate your digital experience.' },
        { hid: 'og:title', name: 'og:title', content: 'Reno Philibert' },
        { hid: 'og:description', name: 'og:description', content: 'Denver-based software engineer & photographer creating innovative digital solutions & captivating images.' },
        { hid: 'og:image', name: 'og:image', content: 'https://reno.sh/reno.sh/images/og-share-card.jpg' }
      ]
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
