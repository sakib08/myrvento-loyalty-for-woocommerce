const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

const babelOptions = {
  presets: [
    ["@babel/preset-env", { targets: "defaults" }],
    ["@babel/preset-react", { runtime: "automatic" }],
  ],
};

const cssPipeline = (configFile) => [
  MiniCssExtractPlugin.loader,
  "css-loader",
  {
    loader: "postcss-loader",
    options: {
      postcssOptions: {
        config: path.resolve(__dirname, configFile),
      },
    },
  },
];

module.exports = [
  {
    name: "admin",
    entry: path.resolve(__dirname, "admin/src/main.jsx"),
    output: {
      path: path.resolve(__dirname, "assets/admin"),
      filename: "ciwp-admin.js",
      clean: true,
    },
    resolve: {
      extensions: [".js", ".jsx"],
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: { loader: "babel-loader", options: babelOptions },
        },
        {
          test: /\.css$/,
          use: cssPipeline("postcss.admin.config.js"),
        },
      ],
    },
    plugins: [new MiniCssExtractPlugin({ filename: "ciwp-admin.css" })],
    mode: process.env.NODE_ENV === "development" ? "development" : "production",
    devtool: process.env.NODE_ENV === "development" ? "source-map" : false,
    stats: "minimal",
  },
  {
    name: "frontend",
    entry: path.resolve(__dirname, "frontend/src/main.js"),
    output: {
      path: path.resolve(__dirname, "assets/frontend"),
      filename: "ciwp.js",
      clean: true,
    },
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: { loader: "babel-loader", options: babelOptions },
        },
        {
          test: /\.css$/,
          use: cssPipeline("postcss.frontend.config.js"),
        },
      ],
    },
    plugins: [new MiniCssExtractPlugin({ filename: "ciwp.css" })],
    mode: process.env.NODE_ENV === "development" ? "development" : "production",
    devtool: process.env.NODE_ENV === "development" ? "source-map" : false,
    stats: "minimal",
  },
];
