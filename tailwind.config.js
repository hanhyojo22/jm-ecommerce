import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                // Headings share the UI face so the whole app reads in one typeface.
                display: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            // One type scale for the whole app (the Seller Center scale): 12 small · 14 body ·
            // 15 section title · 24 figure · 26 page title. Larger steps collapse onto the title.
            fontSize: {
                xs: ['12px', '16px'],
                // 13 meta: secondary lines inside dense lists, between the 12 small and 14 body steps.
                meta: ['13px', '20px'],
                sm: ['14px', '20px'],
                base: ['14px', '22px'],
                lg: ['15px', '22px'],
                xl: ['15px', '22px'],
                '2xl': ['24px', '30px'],
                '3xl': ['26px', '32px'],
                '4xl': ['26px', '32px'],
                '5xl': ['26px', '32px'],
                '6xl': ['26px', '32px'],
            },
            colors: {
                canvas: '#F6F8FC',
                surface: '#FFFFFF',
                ink: '#182C48',
                muted: '#596B83',
                line: {
                    DEFAULT: '#E3E8F1',
                    soft: '#EEF1F5',
                },
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
