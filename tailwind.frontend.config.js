/** @type {import('tailwindcss').Config} */
export default {
  content: ["./frontend/src/**/*.{js,jsx}", "./templates/**/*.php"],
  prefix: "myrvento-",
  corePlugins: {
    preflight: false,
  },
  theme: {
    extend: {
      colors: {
        brand: {
          500: "#14b8a6",
          600: "#0d9488",
          700: "#0f766e",
        },
      },
    },
  },
};
