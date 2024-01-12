const path = require('path');
const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const DependencyExtractionWebpackPlugin = require("@wordpress/dependency-extraction-webpack-plugin");

module.exports = {
  ...defaultConfig,
  entry: {
    main: path.resolve(__dirname, 'src/wpseoai.ts'),
    wpseoai_gutenberg: path.resolve(__dirname, 'src/wpseoai_gutenberg.tsx'),
    style: path.resolve(__dirname, 'src/wpseoai.scss'),
  },
  mode: 'production',
  target: 'node',
  output: {
    // filename: 'wpseoai.js',
    path: path.resolve(__dirname, 'dist'),
    library: {
      type: 'window'
    }
  },
  plugins: [
    new DependencyExtractionWebpackPlugin({ injectPolyfill: true })
  ],
  resolve: {
    extensions: ['.tsx', '.ts', '.js'],
  },
  module: {
    rules: [
      {
        test: /\.ts(x)?$/,
        use: 'ts-loader',
        exclude: /node_modules/,
      },
      {
        test: /\.scss$/,
        use: [
          {
            loader: 'file-loader',
            options: {
              name: '[name].css',
            }
          },
          {
            loader: 'extract-loader'
          },
          {
            loader: 'css-loader'
          },
          {
            loader: 'sass-loader'
          }
        ],
        exclude: /node_modules/,
      },
    ],
  },
};