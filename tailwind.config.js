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
                "background": "var(--background)",
                "surface": "var(--surface)",
                "on-surface": "var(--on-surface)",
                "primary": "var(--primary)",
                "outline-variant": "var(--outline-variant)",
                "on-background": "var(--on-background)",
                "surface-container": "var(--surface-container)",
                "on-surface-variant": "var(--on-surface-variant)",
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

    plugins: [forms, containerQueries],
};
