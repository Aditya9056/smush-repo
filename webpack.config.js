const _          = require('lodash'),
	  path       = require('path'),
	  webpack    = require('webpack'),
	  ATP        = require('autoprefixer'),
	  CSSExtract = require("mini-css-extract-plugin");

const { CleanWebpackPlugin } = require('clean-webpack-plugin');

// The path where the Shared UI fonts & images should be sent.
const config = {
	output: {
		imagesDirectory: '../images',
		fontsDirectory: '../fonts',
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
		'smush-admin': './_src/scss/app.scss',
		'smush-common': './_src/scss/common.scss',
		'smush-rd': './_src/scss/resize-detection.scss'
	},

	output: {
		filename: '[name].min.css',
		path: path.resolve( __dirname, 'app/assets/css' )
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
        }),
		new CleanWebpackPlugin()
	]
});

const jsConfig = _.assign(_.cloneDeep(sharedConfig), {
	entry: {
		'smush-sui': './_src/js/shared-ui.js',
		'smush-admin': './_src/js/app.js',
		'smush-media': './_src/js/smush/media.js',
		'smush-rd': './_src/js/frontend/public-resize-detection.js',
		'smush-blocks': './_src/js/smush/blocks.js',
		'smush-lazy-load': 'lazysizes/lazysizes.js'
	},

	output: {
		filename: '[name].min.js',
		path: path.resolve( __dirname, 'app/assets/js' )
	},

	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [ '@babel/env', '@babel/react' ]
					}
				}
			}
		]
	},

	devtool: 'source-map',

	plugins: [
		// Automatically load modules instead of having to import or require them everywhere.
		new webpack.ProvidePlugin( {
			A11yDialog: '@wpmudev/shared-ui/js/a11y-dialog.js' // Vendor script in Shared UI.
		} ),
		new CleanWebpackPlugin()
	]
});

module.exports = [scssConfig, jsConfig];
