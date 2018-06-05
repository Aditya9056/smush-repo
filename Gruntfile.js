module.exports = function( grunt ) {
	require('load-grunt-tasks')(grunt);

	var commonFiles = [
		'_src/**',
		'assets/**',
		'extras/**',
		'lib/**',
		'uninstall.php',
		'wp-smush.php'
	];

	var includeFilesPro = commonFiles.slice(0).concat([
		'changelog.txt',
		'!extras/free-dashboard/**'
	]);

	var includeFilesFree = commonFiles.slice(0).concat([
		'readme.txt',
		'!extras/dash-notice/**'
	]);

	var changelog = grunt.file.read('.changelog');

	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		clean: {
			main: ['build/']
		},

		checktextdomain: {
			options:{
				text_domain: 'wp-smushit',
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				]
			},
			files: {
				src:  [
					'lib/**/*.php',
					'uninstall.php',
					'wp-smush.php'
				],
				expand: true
			}
		},

		makepot: {
			options: {
				domainPath: 'languages',
				exclude: [
					'extras/.*'
				],
				mainFile: 'wp-smush.php',
				potFilename: 'wp-smushit.pot',
				potHeaders: {
					'report-msgid-bugs-to': 'https://wpmudev.org',
					'language-team': 'LANGUAGE <EMAIL@ADDRESS>'
				},
				type: 'wp-plugin',
				updateTimestamp: false // Update POT-Creation-Date header if no other changes are detected
			},
			pro: {
				options: {
					cwd: 'build/wp-smush-pro'
				}
			},
			free: {
				options: {
					cwd: 'build/wp-smush'
				}
			}
		},

		copy: {
			pro: {
				src:  includeFilesPro,
				dest: 'build/wp-smush-pro/',
				options: {
					process: function (content, srcpath) {
						return content.replace( /\%\%CHANGELOG\%\%/g, changelog )
							.replace( /\/\*\nThis plugin was originally developed by Alex Dunae \(http:\/\/dialect.ca\/\).\n/g, '/*' );
					}
				}
			},
			free: {
				src:  includeFilesFree,
				dest: 'build/wp-smush/',
				options: {
					process: function (content, srcpath) {
						return content.replace( /WDP ID\:       912164\n\*\//g, '*\/' )
							.replace( /Plugin Name\:  Smush Pro/g, 'Plugin Name:  Smush' )
							.replace( /Plugin URI\:   http:\/\/premium.wpmudev.org\/projects\/wp-smush-pro\//g, 'Plugin URI:   http://wordpress.org/extend/plugins/wp-smushit/' )
							.replace( /SEO using the \</g, 'SEO using the free <' )
							.replace( /Author - Aaron Edwards, Sam Najian, Umesh Kumar\n/g, '' )
							.replace( /\%\%CHANGELOG\%\%/g, changelog );
					}
				}
			}
		},

		compress: {
			pro: {
				options: {
					archive: './build/wp-smush-pro-<%= pkg.version %>.zip'
				},
				expand: true,
				cwd: 'build/wp-smush-pro/',
				src: ['**/*'],
				dest: 'wp-smush-pro/'
			},
			free: {
				options: {
					archive: './build/wp-smush-<%= pkg.version %>.zip'
				},
				expand: true,
				cwd: 'build/wp-smush/',
				src: ['**/*'],
				dest: 'wp-smush/'
			}
		},
	});

	grunt.registerTask('prepare', [
		'checktextdomain'
	]);

	grunt.registerTask('build', [
		'copy:pro',
		'makepot:pro',
		'compress:pro'
	]);

	grunt.registerTask('build:wporg', [
		'copy:free',
		'makepot:free',
		'compress:free'
	]);
};