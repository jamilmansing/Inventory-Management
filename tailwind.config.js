/** @type {import('tailwindcss').Config} */
export default {
  content: ["./resources/**/*.blade.php", "./resources/**/*.js", "./resources/**/*.vue", "*.{js,ts,jsx,tsx,mdx}"],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Figtree', 'sans-serif'],
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
}