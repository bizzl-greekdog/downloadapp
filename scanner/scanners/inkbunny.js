// Generated by CoffeeScript 1.10.0

/*
 * Copyright (c) 2016 Benjamin Kleiner
 *
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

(function() {
  module.exports.identify = function(url, referer, parameters, config) {
    if (-1 < url.indexOf('inkbunny.net/')) {
      return url;
    } else if (-1 < referer.indexOf('inkbunny.net/')) {
      return referer;
    } else {
      return false;
    }
  };

  module.exports.run = function(casper, utilities, moreUtilities, parameters, config, url) {
    var checkQueue, downloadQueue, patternUrl, scraps;
    checkQueue = [url];
    downloadQueue = [];
    patternUrl = null;
    scraps = false;
    return casper["do"](function() {
      this.start('https://inkbunny.net/profile.php');
      this.then(function() {
        return this.page.onResourceRequested = function(requestData, request) {
          if (-1 === requestData['url'].indexOf('inkbunny.net/')) {
            return request.abort();
          }
        };
      });
      this.waitForSelector('#nav_bottom', (function() {}), (function() {}));
      this.thenBypassIf((function() {
        return this.getCurrentUrl().indexOf('error.php') === -1;
      }), 2);
      this.then(function() {
        this.open('https://inkbunny.net/login.php');
        return this.then(function() {
          return this.fill('form[action="login_process.php"]', {
            'username': parameters.user,
            'password': parameters.password
          }, true);
        });
      });
      this.label('ANALYZE');
      this.then(function() {
        url = checkQueue.shift();
        if (!url) {
          if (downloadQueue.length > 1000) {
            moreUtilities.alert(this, "Prescan done, " + downloadQueue.length + " pages will be enqueued");
            this.goto('ENQUEUE');
            return;
          } else if (downloadQueue.length > 10) {
            moreUtilities.alert(this, "Prescan done, " + downloadQueue.length + " pages will be scanned");
          } else if (downloadQueue.length > 1) {
            moreUtilities.notify(this, "Prescan done, " + downloadQueue.length + " pages will be scanned");
          }
          return this.goto('VIEW');
        } else {
          downloadQueue.push(url);
          return this.goto('ANALYZE');
        }
      });
      this.label('WATCHLIST');
      this.then(function() {
        return this.goto('ANALYZE');
      });
      this.label('GALLERY');
      this.then(function() {
        return this.goto('ANALYZE');
      });
      this.label('VIEW');
      this.then(function() {
        url = downloadQueue.shift();
        if (!url) {
          return this.goto('END');
        } else {
          this.open(url);
          return this.then(function() {
            var artist, comment, downloadItem, fileName, fileUrl, title;
            url = this.getCurrentUrl();
            if (this.exists('#size_container a[href*=metapix]')) {
              fileUrl = this.getElementAttribute('#size_container a[href*=metapix]', 'href');
            } else {
              fileUrl = this.getElementAttribute('img#magicbox', 'src');
            }
            if (this.exists('#charsheet_content')) {
              comment = moreUtilities.cleanText(this.getHTML('#charsheet_content'));
            } else {
              comment = moreUtilities.cleanText(this.getHTML('.elephant_bottom:not(.elephant_top) .content > div:nth-child(1)'));
            }
            title = this.fetchText('.pooltable>tbody>tr>td>div>table>tbody>tr>td>h1').trim();
            artist = this.fetchText('.content>table>tbody>tr>td>div>a:not([href*=php])').trim();
            fileName = 'inkbunny_' + fileUrl.split('/').pop();
            downloadItem = {
              url: fileUrl,
              filename: fileName,
              referer: url,
              comment: comment,
              metadata: {
                Artist: artist,
                Title: title,
                Source: url,
                'Original Filename': fileUrl.split('/').pop()
              }
            };
            moreUtilities.exportDownloads(this, [downloadItem]);
            return this.goto('VIEW');
          });
        }
      });
      this.label('ENQUEUE');
      this.then(function() {
        moreUtilities.enqueueUrls(this, downloadQueue);
        return this.goto('END');
      });
      this.label('END');
      return this.run(function() {
        return this.exit(0);
      });
    });
  };

}).call(this);
