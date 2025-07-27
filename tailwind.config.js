// tailwind.config.js
/** @type {import('tailwindcss').Config} */
module.exports = {
  // Configure Tailwind to scan your HTML and PHP files for class names
  content: [
    "./public/**/*.php", // Scan all PHP files in the public directory
    "./**/*.html",      // Scan all HTML files (if any outside public)
    // Add any other paths where you will use Tailwind classes
  ],
  theme: {
    extend: {
      // Define your custom color palette to match the earthy green theme
      colors: {
        'nyanza': '#E0FFE0',
        'tea-green': '#D0F0C0',
        'olivine': '#9ABF88',
        'olivine-2': '#80A86E',
        'moss-green': '#6B8E23',
        'reseda-green': '#5D7A1D',
      },
      fontFamily: {
        // Use 'Inter' as the primary font
        sans: ['Inter', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
