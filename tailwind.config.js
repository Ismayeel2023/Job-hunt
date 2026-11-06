import forms from '@tailwindcss/forms';
import defaultTheme from 'tailwindcss/defaultTheme';

const token = (name) => `rgb(var(--${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Instrument Sans Variable"', ...defaultTheme.fontFamily.sans],
                display: ['"Bricolage Grotesque Variable"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono Variable"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                canvas: token('canvas'),
                surface: token('surface'),
                sunken: token('sunken'),
                ink: token('ink'),
                muted: token('muted'),
                line: token('line'),
                accent: {
                    DEFAULT: token('accent'),
                    ink: token('accent-ink'),
                    soft: token('accent-soft'),
                },
                strong: { bg: token('strong-bg'), fg: token('strong-fg') },
                good: { bg: token('good-bg'), fg: token('good-fg') },
                fair: { bg: token('fair-bg'), fg: token('fair-fg') },
                low: { bg: token('low-bg'), fg: token('low-fg') },
            },
        },
    },

    plugins: [forms],
};
