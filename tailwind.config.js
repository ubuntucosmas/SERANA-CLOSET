import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
                // Channel form so opacity modifiers (bg-primary/10, text-on-surface-variant/70) work.
                "background": "rgb(var(--background-rgb) / <alpha-value>)",
                "surface": "rgb(var(--surface-rgb) / <alpha-value>)",
                "on-surface": "rgb(var(--on-surface-rgb) / <alpha-value>)",
                "primary": "rgb(var(--primary-rgb) / <alpha-value>)",
                "outline-variant": "rgb(var(--outline-variant-rgb) / <alpha-value>)",
                "on-background": "rgb(var(--on-background-rgb) / <alpha-value>)",
                "surface-container": "rgb(var(--surface-container-rgb) / <alpha-value>)",
                "on-surface-variant": "rgb(var(--on-surface-variant-rgb) / <alpha-value>)",
                // New Curator specific tokens
                "curator-indigo": "#B9C3FF",
                "curator-blue": "#8A9DFF",
                "curator-surface": "#0E0E0E",
                "curator-border": "#1C1B1B",
                "curator-muted": "#454652"
            },
            fontFamily: {
                "headline": ["Space Grotesk", ...defaultTheme.fontFamily.sans],
                "body": ["Plus Jakarta Sans", ...defaultTheme.fontFamily.sans],
                // Labels/UI now use the slim sans (no more terminal monospace).
                "label": ["Plus Jakarta Sans", ...defaultTheme.fontFamily.sans],
                "mono": ["Plus Jakarta Sans", ...defaultTheme.fontFamily.sans]
            },
            borderRadius: {
                "DEFAULT": "8px",
                "lg": "16px",
                "xl": "24px",
                "full": "9999px"
            },
        },
    },

    plugins: [forms],
};
