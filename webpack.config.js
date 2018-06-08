const path    = require('path');
const webpack = require('webpack');
const AP      = require('autoprefixer');
const ETP     = require('mini-css-extract-plugin');

const config = {
	output: {}
};

// The path where the Shared UI fonts & images should be sent. (relative to config.output.jsFileName)
config.output.imagesDirectory = '..//images'; // Trailing slash required.
config.output.fontsDirectory = '../fonts'; // Trailing slash required.

const scssConfig = {
	mode: 'production',

	// Was: entry: config.source.scss,
	entry: {
		'shared-ui': './_src/scss/shared-ui.scss',
		'admin': './_src/scss/admin.scss',
		'resize-detection': './_src/scss/resize-detection.scss'
	},

	output: {
		filename: '[name].min.css',
		path: path.resolve( __dirname, 'assets/css' )
	},

	module: {
		rules: [
			{
				test: /\.scss$/,
				exclude: /node_modules/,
				use: [ ETP.loader,
					{
						loader: 'css-loader'
					},
					{
						loader: 'postcss-loader',
						options: {
							plugins: [
								AP({
									browsers: ['ie > 9', '> 1%']
								})
							],
							sourceMap: true
						}
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
		new ETP( '[name].min.css' )
	]
};

const jsConfig = {
	mode: 'production',

	// Was: entry: config.source.js,
	entry: {
		'shared-ui': './_src/js/shared-ui.js',
		'admin': './_src/js/admin-index.js',
	},

	output: {
		filename: '[name].min.js',
		path: path.resolve( __dirname, 'assets/js' )
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
		new webpack.ProvidePlugin( {
			ClipboardJS: '@wpmudev/shared-ui/js/clipboard.js',  // Cendor script in Shared UI.
			A11yDialog: '@wpmudev/shared-ui/js/a11y-dialog.js' // Vendor script in Shared UI.
		} )
	]
};

const resizeJsConfig = {
	entry: './_src/js/public-resize-detection.js',
	output: {
		filename: 'resize-detection.min.js',
		path: path.resolve( __dirname, 'assets/js' )
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
};

module.exports = [scssConfig, jsConfig, resizeJsConfig];
