import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', 'Iowan Old Style', 'Palatino Linotype', 'Georgia', 'serif'],
            },
            colors: {
                canvas: '#F6F8FC',
                surface: '#FFFFFF',
                ink: '#182C48',
                muted: '#596B83',
                line: '#E3E8F1',
                brand: {
                    DEFAULT: '#205BF4',
                    hover: '#1D4ED8',
                },
                success: '#166534',
                warning: {
                    DEFAULT: '#F59E0B',
                    text: '#B45309',
                },
                danger: {
                    DEFAULT: '#DC2626',
                    text: '#B91C1C',
                },
            },
        },
    },
    plugins: [forms],
};
