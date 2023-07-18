import type { Config } from "tailwindcss";

export default <Partial<Config>>{
  content: [
    './pages/**/*.{html,vue}',
    './src/**/*.{html,vue,js,ts}'
  ],
  theme: {
    fontFamily: {
      poppins: ['Poppins', "sans-serif"],
      cormorant: ['Cormorant Garamond', "sans-serif"],
    }
  }
};
