const forms = require('@tailwindcss/forms');
const typography = require('@tailwindcss/typography');
const animate = require('tailwindcss-animate');

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{ts,tsx,vue}',
    ],
    safelist: [
        { pattern: /col-span-.+/, variants: ['lg'] },
        { pattern: /prose.*/, variants: ['dark'] },
    ],
    theme: {
        container: {
            center: true,
            padding: '2rem',
            screens: {
                '2xl': '1400px',
            },
        },
    },
    plugins: [forms, typography, animate],
};
