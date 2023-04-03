const path = require('path');
const webpack = require('webpack');

// PrestaShop folders, we use process.env.PWD instead of __dirname in case the module is symlinked
// Note: when building, your admin folder needs to be named admin-dev
const psRootDir = path.resolve(process.env.PWD, '../../../../');
const psJsDir = path.resolve(psRootDir, 'admin-dev/themes/new-theme/js');
const psComponentsDir = path.resolve(psJsDir, 'components');
const psAppDir = path.resolve(psRootDir, 'admin-dev/themes/new-theme/js/app');

module.exports = {
  externals: {
    jquery: 'jQuery',
  },
  entry: {
    vg_postnord: './vg_postnord',
    vg_postnord_form: './vg_postnord/form',
  },
  output: {
    path: path.resolve(__dirname, '../'),
    filename: '[name].bundle.js',
    libraryTarget: 'window',
    library: '[name]',
  },
  resolve: {
    extensions: ['.js', '.vue', '.json'],
    alias: {
      '@components': psComponentsDir,
      '@app': psAppDir,
    },
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        include: path.resolve(__dirname, '../vg_postnord'),
        use: [{
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', { modules: false }],
            ],
          },
        }],
      },
      {
        test: /\.js$/,
        include: psJsDir,
        use: [{
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', { modules: false }],
            ],
          },
        }],
      },
      // FILES
      {
        test: /.(jpg|png|woff2?|eot|otf|ttf|svg|gif)$/,
        type: 'asset/resource',
      },
    ],
  },
  plugins: [
    new webpack.ProvidePlugin({
      $: 'jquery', // needed for jquery-ui
      jQuery: 'jquery',
    }),
  ],
};
