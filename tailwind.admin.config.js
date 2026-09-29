/** @type {import('tailwindcss').Config} */
export default {
  content: ["./admin/src/**/*.{js,jsx}"],
  prefix: "ciwp-",
  corePlugins: {
    preflight: false,
  },
  theme: {
    extend: {
      colors: {
        brand: {
          50: "#f0fdfa",
          100: "#ccfbf1",
          200: "#99f6e4",
          400: "#2dd4bf",
          500: "#14b8a6",
          600: "#0d9488",
          700: "#0f766e",
        },
      },
    },
  },
};
