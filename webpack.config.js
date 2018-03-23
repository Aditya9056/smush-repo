const ETP     = require('extract-text-webpack-plugin');
const path    = require('path');
const webpack = require('webpack');

const config = {
	source:{},
	output:{}
};

// Full path of main files that need to be ran through the bundler.
config.source.scss           = './_src/scss/admin.scss';
config.source.js             = './_src/js/index.js';

// Path where the scss & js should be compiled to.
config.output.scssDirectory  = 'assets/shared-ui-2/css';       // No trailing slash.
config.output.jsDirectory    = 'assets/shared-ui-2/js';        // No trailing slash.

// File names of the compiled scss & js.
config.output.scssFileName   = 'admin.min.css';
config.output.jsFileName     = 'admin.min.js';

// The path where the Shared UI fonts & images should be sent. (relative to config.output.jsFileName)
config.output.imagesDirectory = '../images/';            // Trailing slash required.
config.output.fontsDirectory  = '../fonts/';             // Trailing slash required.

const scssConfig = {
	entry: config.source.scss,
	output: {
		filename: config.output.scssFileName,
		path: path.resolve(__dirname, config.output.scssDirectory)
	},
	module: {
		rules: [
			{
				test: /\.scss$/,
				exclude: /node_modules/,
				use: ETP.extract({
					fallback: 'style-loader',
					use: [
						{
							loader: 'css-loader'
						},
						{
							loader: 'postcss-loader'
						},
						{
							loader: 'resolve-url-loader'
						},
						{
							loader: 'sass-loader',
							options: {
								sourceMap: true
							}
						}
					]
				})
			},
			{
				test: /\.(png|jpg|gif)$/,
				use: {
					loader: 'file-loader', // Instructs webpack to emit the required object as file and to return its public URL.
					options: {
						name: '[name].[ext]',
						outputPath: config.output.imagesDirectory
					}
				}
			},
			{
				test: /\.(woff|woff2|eot|ttf|otf|svg)$/,
				use: {
					loader: 'file-loader', // Instructs webpack to emit the required object as file and to return its public URL.
					options: {
						name: '[name].[ext]',
						outputPath: config.output.fontsDirectory
					}
				}
			}
		]
	},
	plugins: [
		new ETP(config.output.scssFileName)
	]
};

const jsConfig = {
	entry: config.source.js,
	output: {
		filename: config.output.jsFileName,
		path: path.resolve(__dirname, config.output.jsDirectory)
	},
	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: ['env']
					}
				}
			}
		]
	},
	devtool: 'source-map',
	plugins: [
		// Automatically load modules instead of having to import or require them everywhere.
		new webpack.ProvidePlugin({
			ClipboardJS: '@wpmudev/shared-ui/js/clipboard.js',  // Cendor script in Shared UI.
			A11yDialog:  '@wpmudev/shared-ui/js/a11y-dialog.js' // Vendor script in Shared UI.
		})
	]
};

module.exports = [scssConfig, jsConfig];
