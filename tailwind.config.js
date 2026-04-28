import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    // Safelist for z-index values used across the application
    // This ensures dynamic or conditional z-index classes are generated
    safelist: [
        'z-10',
        'z-20',
        'z-30',
        'z-40',
        'z-50',
        'z-100',
        'z-110',
        'z-120',
        'z-130',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                shake: {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '10%, 30%, 50%, 70%, 90%': { transform: 'translateX(-4px)' },
                    '20%, 40%, 60%, 80%': { transform: 'translateX(4px)' },
                },
                blob: {
                    '0%': { transform: 'scale(1)' },
                    '33%': { transform: 'scale(1.1)' },
                    '66%': { transform: 'scale(1.05)' },
                    '100%': { transform: 'scale(1)' },
                },
            },
            animation: {
                shake: 'shake 0.5s ease-in-out',
                blob: 'blob 7s infinite ease-in-out',
            },
            animationDelay: {
                '2000': '2000ms',
                '4000': '4000ms',
            },
        },
    },

    plugins: [forms],
};
