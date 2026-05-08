const fs = require('fs');
const os = require('os');
const path = require('path');
const { chromium } = require('playwright');

module.exports = function (config) {
  const tmpDir =
    process.env.KARMA_TMP_DIR ||
    fs.mkdtempSync(path.join(os.tmpdir(), 'product-import-karma-'));
  fs.mkdirSync(tmpDir, { recursive: true });
  process.env.TMPDIR = tmpDir;
  process.env.TMP = tmpDir;
  process.env.TEMP = tmpDir;
  process.env.CHROME_BIN = process.env.CHROME_BIN || chromium.executablePath();

  config.set({
    basePath: '',
    frameworks: ['jasmine', '@angular-devkit/build-angular'],
    plugins: [
      require('karma-jasmine'),
      require('karma-chrome-launcher'),
      require('karma-jasmine-html-reporter'),
      require('karma-coverage'),
      require('@angular-devkit/build-angular/plugins/karma'),
    ],
    client: {
      jasmine: {},
      clearContext: false,
    },
    jasmineHtmlReporter: {
      suppressAll: true,
    },
    coverageReporter: {
      dir: require('path').join(__dirname, './coverage/product-import-frontend'),
      subdir: '.',
      reporters: [{ type: 'html' }, { type: 'text-summary' }],
    },
    customLaunchers: {
      ChromeHeadlessNoSandbox: {
        base: 'ChromeHeadless',
        flags: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
      },
    },
    reporters: ['progress'],
    browsers: ['ChromeHeadlessNoSandbox'],
    browserNoActivityTimeout: 120000,
    browserDisconnectTimeout: 20000,
    browserDisconnectTolerance: 2,
    hostname: '127.0.0.1',
    restartOnFileChange: false,
    singleRun: true,
  });
};
