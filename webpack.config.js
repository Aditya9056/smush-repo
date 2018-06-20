const _          = require('lodash');
const path       = require('path');
const webpack    = require('webpack');
const ATP        = require('autoprefixer');
const CSSExtract = require("mini-css-extract-plugin");

// The path where the Shared UI fonts & images should be sent.
const config = {
	output: {
		imagesDirectory: '../images',
		fontsDirectory: '../fonts'
	}
};

const sharedConfig = {
	mode: 'production',

	stats: {
		colors: true,
		entrypoints: true
	},

	watchOptions: {
		ignored: /node_modules/,
		poll: 1000
	}
};

const scssConfig = _.assign(_.cloneDeep(sharedConfig), {
	entry: {
		'admin': './_src/scss/app.scss',
		'common': './_src/scss/common.scss'
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
				use: [ CSSExtract.loader,
					{
						loader: 'css-loader'
					},
					{
						loader: 'postcss-loader',
						options: {
							plugins: [
								ATP({
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
		new CSSExtract({
            filename: '../css/[name].min.css'
        })
	]
});

const jsConfig = _.assign(_.cloneDeep(sharedConfig), {
	entry: {
		'shared-ui': '@wpmudev/shared-ui',
		'admin': './_src/js/app.js',
		'media': './_src/js/media.js',
		'resize-detection': './_src/js/public-resize-detection.js'
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
			ClipboardJS: '@wpmudev/shared-ui/js/clipboard.js', // Vendor script in Shared UI.
			A11yDialog: '@wpmudev/shared-ui/js/a11y-dialog.js' // Vendor script in Shared UI.
		} )
	]
});

module.exports = [scssConfig, jsConfig];
