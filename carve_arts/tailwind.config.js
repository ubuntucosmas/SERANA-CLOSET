import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import containerQueries from '@tailwindcss/container-queries';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                "tertiary": "#fffcc6",
                "background": "#0c0c1f",
                "on-error-container": "#ffd2c8",
                "on-tertiary-fixed": "#4d4c00",
                "on-tertiary": "#636200",
                "secondary": "#e2e0fc",
                "tertiary-fixed-dim": "#eeeb59",
                "outline-variant": "#46465c",
                "on-surface-variant": "#aaa8c3",
                "surface": "#0c0c1f",
                "surface-container-low": "#111127",
                "surface-container-highest": "#23233f",
                "secondary-fixed-dim": "#d4d2ed",
                "on-secondary": "#505066",
                "on-background": "#e5e3ff",
                "on-error": "#450900",
                "surface-tint": "#99ff33",
                "surface-container-lowest": "#000000",
                "secondary-dim": "#d4d2ed",
                "outline": "#74738b",
                "tertiary-container": "#f4f15e",
                "secondary-container": "#45455b",
                "tertiary-dim": "#eeeb59",
                "on-surface": "#e5e3ff",
                "on-primary-container": "#294f00",
                "error-dim": "#d53d18",
                "inverse-on-surface": "#53536a",
                "error-container": "#b92902",
                "primary": "#99ff33",
                "on-secondary-fixed": "#3e3d53",
                "primary-container": "#83e711",
                "surface-bright": "#292948",
                "on-tertiary-fixed-variant": "#6b6900",
                "primary-dim": "#8bf020",
                "primary-fixed-dim": "#8bf020",
                "on-primary-fixed": "#264a00",
                "surface-variant": "#23233f",
                "primary-fixed": "#99ff33",
                "inverse-surface": "#fcf8ff",
                "secondary-fixed": "#e2e0fc",
                "on-secondary-container": "#d0cee9",
                "inverse-primary": "#396b00",
                "error": "#ff7351",
                "on-primary": "#325e00",
                "surface-dim": "#0c0c1f",
                "tertiary-fixed": "#fcfa66",
                "surface-container": "#17172f",
                "on-secondary-fixed-variant": "#5a5971",
                "on-tertiary-container": "#5b5a00",
                "surface-container-high": "#1d1d37",
                "on-primary-fixed-variant": "#386900"
            },
            fontFamily: {
                "headline": ["Epilogue", ...defaultTheme.fontFamily.sans],
                "body": ["Manrope", ...defaultTheme.fontFamily.sans],
                "label": ["Manrope", ...defaultTheme.fontFamily.sans]
            },
            borderRadius: {
                "DEFAULT": "0.125rem",
                "lg": "0.25rem",
                "xl": "0.5rem",
                "full": "0.75rem"
            },
        },
    },

    plugins: [forms, containerQueries],
};
