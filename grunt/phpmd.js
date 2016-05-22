'use strict';

module.exports.tasks = {
  phpmd: {
    options: {
      bin: 'vendor/bin/phpmd',
      rulesets: 'phpmd.xml',
      reportFormat: 'text'
    },
    application: {
      dir: 'src'
    }
  }
};
