import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Color de marca del candidato elegido. Las vistas usan `indigo-*`; aquí
// `indigo` lee variables CSS que <x-marca> redefine por candidato
// (App\Support\Marca). Los valores por defecto (el índigo de siempre) están
// en resources/css/app.css.
const marca = (tono) => `rgb(var(--marca-${tono}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                indigo: Object.fromEntries(
                    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((t) => [t, marca(t)]),
                ),
                // Color secundario del candidato (franjas, cabeceras) y el
                // texto que se lee encima de él
                secundario: {
                    DEFAULT: 'rgb(var(--marca-sec) / <alpha-value>)',
                    texto: 'rgb(var(--marca-sec-texto) / <alpha-value>)',
                },
            },
        },
    },

    plugins: [forms],
};
