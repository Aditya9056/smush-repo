const ETP = require( 'extract-text-webpack-plugin' );
const path = require( 'path' );
const webpack = require( 'webpack' );

const config = {
	source: {},
	output: {}
};

// Full path of main files that need to be ran through the bundler.
config.source.adminscss = './_src/scss/admin.scss';
config.source.resizescss = './_src/scss/resize-detection.scss';
config.source.adminjs = './_src/js/admin.js';
config.source.resizejs = './_src/js/resize-detection.js';

// Path where the scss & js should be compiled to.
config.output.scssDirectory = 'assets/shared-ui-2/css';       // No trailing slash.
config.output.jsDirectory = 'assets/shared-ui-2/js';        // No trailing slash.

// File names of the compiled scss & js.
config.output.adminScssFileName = 'admin.min.css';
config.output.resizeScssFileName = 'resize-detection.min.css';
config.output.adminJsFileName = 'admin.min.js';
config.output.resizeJsFileName = 'resize-detection.min.js';

// The path where the Shared UI fonts & images should be sent. (relative to config.output.jsFileName)
config.output.imagesDirectory = '../images/';            // Trailing slash required.
config.output.fontsDirectory = '../fonts/';             // Trailing slash required.

const adminScssConfig = {
	entry: config.source.adminscss,
	output: {
		filename: config.output.adminScssFileName,
		path: path.resolve( __dirname, config.output.scssDirectory )
	},
	module: {
		rules: [
			{
				test: /\.scss$/,
				exclude: /node_modules/,
				use: ETP.extract( {
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
				} )
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
		new ETP( config.output.adminScssFileName )
	]
};

const resizeScssConfig = {
	entry: config.source.resizescss,
	output: {
		filename: config.output.resizeScssFileName,
		path: path.resolve( __dirname, config.output.scssDirectory )
	},
	module: {
		rules: [
			{
				test: /\.scss$/,
				exclude: /node_modules/,
				use: ETP.extract( {
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
				} )
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
		new ETP( config.output.resizeScssFileName )
	]
};

const adminJsConfig = {
	entry: config.source.adminjs,
	output: {
		filename: config.output.adminJsFileName,
		path: path.resolve( __dirname, config.output.jsDirectory )
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
	entry: config.source.resizejs,
	output: {
		filename: config.output.resizeJsFileName,
		path: path.resolve( __dirname, config.output.jsDirectory )
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

module.exports = [adminScssConfig, resizeScssConfig, adminJsConfig, resizeJsConfig];
