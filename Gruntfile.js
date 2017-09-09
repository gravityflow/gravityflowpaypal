
module.exports = function(grunt) {
    'use strict';
    var slug = 'gravityflowpaypal';

    var config = grunt.file.readJSON('config.json');
    var gfVersion = '';

    require('matchdep').filterDev('grunt-*').forEach( grunt.loadNpmTasks );

    grunt.getVersion = function(){
        var p = slug.replace('gravityflow', '') + '.php';
        if(gfVersion == '' && grunt.file.exists(p)){
            var source = grunt.file.read(p);
            var re = /Version:\s(.*)/;
            var found = source.match(re);
            gfVersion = found[1];
        }

        return gfVersion;
    };

    grunt.getDropboxConfig = function(){
        var key = config.dropbox.upload_path;
        var obj = {};

        key += 'Extensions/' + slug.replace('gravityflow', '');
        obj[key] = [ slug + '_<%= grunt.getVersion() %>.zip'];

        return obj;
    };

    grunt.initConfig({

        /**
         * Generate a POT file.
         */
        makepot: {
            all: {
                options: {
                    cwd: '.',
					exclude: ['tmp/.*', 'tests/.*', 'vendor/.*', 'node_modules/.*'],
					mainFile: slug.replace('gravityflow', '') + '.php',
                    domainPath: 'languages',
                    potComments: 'Copyright 2015-{year} Steven Henty.',
                    potHeaders: {
                        'language-team': 'Steven Henty <support@gravityflow.io>',
                        'last-translator': 'Steven Henty <support@gravityflow.io>',
                        'report-msgid-bugs-to': 'https://www.gravityflow.io',
                        'Project-Id-Version': slug,
                        'language': 'en_US',
                        'plural-forms': 'nplurals=2; plural=(n != 1);',
                        'x-poedit-basepath': '../',
                        'x-poedit-bookmarks': '',
                        'x-poedit-country': 'United States',
                        'x-poedit-keywordslist': true,
                        'x-poedit-searchpath-0': '.',
                        'x-poedit-sourcecharset': 'utf-8',
                        'x-textdomain-support': 'yes',
                        'x-generator' : 'Gravity Flow Build Script'
                    },
                    type: 'wp-plugin',
                    updateTimestamp: true
                }
            }
        },

        /**
         * Unit tests
         */
        phpunit: {
            classes: {
                dir:''
            },
            options: {
                bin: 'vendor/bin/phpunit',
                bootstrap: 'tests/phpunit/includes/bootstrap.php',
                configuration:'tests/phpunit.xml',
                colors: true
            }
        },

        /**
         * Minify JavaScript source files.
         */
        uglify: {
            gravityflow: {
                expand: true,
                ext: '.min.js',
                src: [
                    'js/*.js',

                    // Exclusions
                    '!js/*.min.js',
                ]
            },
            extension: {
                expand: true,
                ext: '.min.js',
                src: [
                    'js/*.js',
                    // Exclusions
                    '!js/*.min.js',
                ]
            }
        },
        /**
         * Minify CSS source files.
         */
        cssmin: {
            gravityflow: {
                expand: true,
                ext: '.min.css',
                src: [
                    'css/*.css',
                    // Exclusions
                    '!css/*.min.css',
                ]
            },
            extension: {
                expand: true,
                ext: '.min.css',
                src: [
                    'css/*.css',
                    // Exclusions
                    '!css/*.min.css',
                ]
            },
        },

        /**
         * Compression tasks
         */
        compress: {
            all_slugs: {
                options: {
                    archive: slug + '_<%= grunt.getVersion() %>.zip'
                },
                files: [
                    { src: 'includes/**', dest: slug + '/' },
                    { src: 'js/**', dest: slug + '/'  },
                    { src: 'css/**', dest: slug + '/'  },
                    { src: 'images/**', dest: slug + '/'  },
                    { src: 'languages/**', dest: slug + '/'  },
                    { src: 'readme.txt', dest: slug + '/'  },
                    { src: slug.replace('gravityflow', '') + '.php', dest: slug + '/'  },
                    { src: 'class-**.php', dest: slug + '/'  },
                    { src: 'index.php', dest: slug + '/' }
                ]
            },
        },

        /**
         * Cleaning - removing temp files
         */
        clean: {
            options: {
                force: true
            },
            all: [ slug + '_<%= grunt.getVersion() %>.zip' ]
        },

        /**
         * Shell commands
         */
        shell: {
            options: {
                stdout: true,
                stderr: true
            },
            transifex:{
                command: [
                    'tx pull -a -f --minimum-perc=1'
                ].join('&&')
            }
        },

        /**
         * Dropbox integration
         */
        dropbox: {
            options: {
                cwd: 'docs',
                access_token: config.dropbox.access_token
            },
            upload: {
                files: grunt.getDropboxConfig()
            }
        },
        potomo: {
            dist: {
                options: {
                    poDel: false
                },
                files: [{
                    expand: true,
                    cwd: 'languages',
                    src: ['*.po'],
                    dest: 'languages',
                    ext: '.mo',
                    nonull: true
                }]
            }
        },
        watch: {
            scripts: {
                files: ['js/*.js', 'css/*.css'],
                tasks: ['cssmin:' + slug],
                options: {
                    spawn: false,
                },
            },
        },

    });

    //grunt.registerTask('minimize', [ 'clean', 'uglify:extension', 'cssmin:extension' ]);
    //grunt.registerTask('checksum', [ 'minimize', 'makepot' ]);
	grunt.registerTask('translations', [ 'makepot', 'shell:transifex', 'potomo' ]);
    grunt.registerTask('default', [ 'clean', 'compress:all_slugs', 'dropbox', 'clean' ]);

};
