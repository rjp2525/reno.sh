import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import animate from 'tailwindcss-animate';
import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{ts,tsx,vue}',
    ],
    safelist: [{ pattern: /col-span-.+/, variants: ['lg'] }],
    theme: {
        container: {
            center: true,
            padding: '2rem',
            screens: {
                '2xl': '1400px',
            },
        },
        extend: {
            fontFamily: {
                sans: ['Geist Mono', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                navy: '#1E2D3D',
                menu: '#607B96',
                background: '#010C15',
                purple: '#4D5BCE',
                github: {
                    fill: '#071511',
                    text: '#43D9AD',
                },
            },
            backgroundImage: {
                intro: 'linear-gradient(to bottom, rgba(35, 123, 109, 1), rgba(67, 217, 173, 0.13))',
            },
            animation: {
                'octocat-wave': 'octocat-wave 560ms ease-in-out',
                typewriter:
                    'typewriter 3.5s steps(40) 1s 1 normal both, blink-text-cursor 800ms steps(40) infinite normal',
                'accordion-down': 'accordion-down 0.2s ease-out',
                'accordion-up': 'accordion-up 0.2s ease-out',
                'collapsible-down': 'collapsible-down 0.2s ease-in-out',
                'collapsible-up': 'collapsible-up 0.2s ease-in-out',
            },
            keyframes: {
                'octocat-wave': {
                    '0%, 100%': { transform: 'rotate(-3deg)' },
                    '20%, 60%': { transform: 'rotate(-25deg)' },
                    '40%, 80%': { transform: 'rotate(10deg)' },
                },
                typewriter: {
                    from: { width: '0' },
                    to: { width: '100%' },
                },
                'blink-text-cursor': {
                    from: { 'border-right-color': 'rgba(255,255,255,.75)' },
                    to: { 'border-right-color': 'transparent' },
                },
                'accordion-down': {
                    from: { height: 0 },
                    to: { height: 'var(--radix-accordion-content-height)' },
                },
                'accordion-up': {
                    from: { height: 'var(--radix-accordion-content-height)' },
                    to: { height: 0 },
                },
                'collapsible-down': {
                    from: { height: 0 },
                    to: { height: 'var(--radix-collapsible-content-height)' },
                },
                'collapsible-up': {
                    from: { height: 'var(--radix-collapsible-content-height)' },
                    to: { height: 0 },
                },
            },
        },
    },

    plugins: [forms, typography, animate],
};
