'use strict';

module.exports = function(grunt) {
  require('load-grunt-tasks')(grunt);
  require('time-grunt')(grunt);

  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),

    phplint: {
      options: {
        swapPath: '/tmp'
      },
      application: [
        'src/**/*.php',
        'tests/**/*.php'
      ]
    },
    phpcs: {
      options: {
        bin: 'vendor/bin/phpcs',
        standard: 'PSR2'
      },
      application: {
        dir: [
          'src',
          'tests'
        ]
      }
    },
    phpmd: {
      options: {
        bin: 'vendor/bin/phpmd',
        rulesets: 'unusedcode,naming,design,codesize',
        reportFormat: 'text'
      },
      application: {
        dir: 'src'
      }
    },
    phpcpd: {
      options: {
        bin: 'vendor/bin/phpcpd',
        quiet: false,
        ignoreExitCode: true
      },
      application: {
        dir: 'src'
      }
    },
    phpunit: {
      options: {
        bin: 'vendor/bin/phpunit',
          coverage: true
      },
      application: {
        coverageHtml: 'dist/coverage'
      }
    },
    climb: {
      options: {
        bin: 'vendor/bin/climb'
      },
      application: {
      }
    },
    security_checker: {
      options: {
        bin: 'vendor/bin/security-checker',
        format: 'text'
      },
      application: {
        file: 'composer.lock'
      }
    }
  });

  grunt.registerTask('qa', ['phplint', 'phpcs', 'phpmd', 'phpcpd']);
  grunt.registerTask('test', ['phpunit']);
  grunt.registerTask('security', ['climb', 'security_checker']);

  grunt.registerTask('default', ['qa', 'test']);
};
