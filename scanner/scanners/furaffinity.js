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
  module.exports.identify = function(url, referer) {
    if (url === 'furaffinity:watchlist' || -1 < url.indexOf('www.furaffinity.net/')) {
      return url;
    } else if (-1 < referer.indexOf('www.furaffinity.net/')) {
      return referer;
    } else {
      return false;
    }
  };

  module.exports.run = function(casper, utilities, moreUtilities, parameters, url) {
    var checkQueue, downloadQueue;
    checkQueue = [url];
    downloadQueue = [];
    return casper["do"](function() {
      this.start('http://www.furaffinity.net/msg/others');
      this.thenBypassIf((function() {
        return !this.exists('a[href="/login/"]');
      }), 3);
      this.then(function() {
        this.log('Not logged in', 'info');
        return this.click('a[href="/login/"]');
      });
      this.waitForSelector('form');
      this.then(function() {
        this.log('Logging in', 'info');
        return this.fill('form', {
          'name': parameters.user,
          'pass': parameters.password
        }, true);
      });
      this.label('ANALYZE');
      this.then(function() {
        var path, ref;
        url = checkQueue.shift();
        if (!url) {
          if (downloadQueue.length > 1) {
            moreUtilities.notify(this, "Prescan done, " + downloadQueue.length + " pages will be scanned");
          }
          return this.goto('VIEW');
        } else if (url === 'furaffinity:watchlist') {
          this.open('http://www.furaffinity.net/msg/submissions/');
          return this.then(function() {
            return this.goto('WATCHLIST');
          });
        } else {
          path = url.split('/');
          if (path[3] === 'view') {
            downloadQueue.push(url);
          } else if (path[3] === 'full') {
            path[3] = 'view';
            url = path.join('/');
            downloadQueue.push(url);
          } else if ((ref = path[3]) === 'user' || ref === 'gallery' || ref === 'scraps') {
            path = path.slice(0, 5);
            path[3] = 'gallery';
            path.push(1);
            url = path.join('/');
            this.open(url);
            this.goto('GALLERY');
            return;
          }
          return this.goto('ANALYZE');
        }
      });
      this.label('WATCHLIST');
      this.then(function() {
        var images, processed;
        images = this.getElementsAttribute('#messages-form .t-image a', 'href').filter(function(e, i, a) {
          return String(e).substr(0, 6) === '/view/';
        });
        processed = 0;
        images.forEach(function(image) {
          image = 'http://furaffinity.net' + image;
          if (-1 === downloadQueue.indexOf(image)) {
            downloadQueue.push(image);
            return processed++;
          }
        });
        if (processed) {
          this.click('a.more');
          return this.then(function() {
            return this.goto('WATCHLIST');
          });
        } else {
          return this.goto('ANALYZE');
        }
      });
      this.label('GALLERY');
      this.then(function() {
        var j, len, path, view, views;
        views = this.getElementsAttribute('.submission-list a[href*="/view/"]', 'href');
        this.log(this.page.url + ' has ' + views.length, 'info');
        this.log('Total: ' + downloadQueue.length, 'info');
        if (views.length) {
          for (j = 0, len = views.length; j < len; j++) {
            view = views[j];
            downloadQueue.push('http://www.furaffinity.net' + view);
          }
          path = (this.page.url.split('/')).slice(0, 6);
          path[5] = 1 + parseInt(path[5]);
          url = path.join('/');
          this.open(url);
          return this.goto('GALLERY');
        } else {
          path = (this.page.url.split('/')).slice(0, 5);
          path[3] = 'scraps';
          path.push(1);
          url = path.join('/');
          this.open(url);
          return this.goto('SCRAPS');
        }
      });
      this.label('SCRAPS');
      this.then(function() {
        var j, len, path, view, views;
        views = this.getElementsAttribute('.submission-list a[href*="/view/"]', 'href');
        this.log(this.page.url + ' has ' + views.length, 'info');
        this.log('Total: ' + downloadQueue.length, 'info');
        if (views.length) {
          for (j = 0, len = views.length; j < len; j++) {
            view = views[j];
            downloadQueue.push('http://www.furaffinity.net' + view);
          }
          path = (this.page.url.split('/')).slice(0, 6);
          path[5] = 1 + parseInt(path[5]);
          url = path.join('/');
          this.open(url);
          return this.goto('SCRAPS');
        } else {
          return this.goto('ANALYZE');
        }
      });
      this.label('VIEW');
      this.then(function() {
        url = downloadQueue.shift();
        this.echo(url);
        if (!url) {
          return this.goto('END');
        } else {
          this.open(url);
          return this.then(function() {
            var artist, comment, downloadItem, fileName, fileUrl, title;
            url = this.page.url;
            fileUrl = 'http:' + this.getElementAttribute('a[href*=facdn]', 'href');
            title = this.getHTML('#page-submission td.cat b');
            artist = this.fetchText('#page-submission td.cat a[href*=user]');
            fileName = fileUrl.split('/').pop();
            comment = moreUtilities.cleanText(this.getHTML('#page-submission td.alt1[width="70%"]'));
            downloadItem = {
              url: fileUrl,
              filename: fileName,
              referer: url,
              comment: comment,
              metadata: {
                'Artist': artist,
                'Title': title,
                'Source': url,
                'Original Filename': fileUrl.split('/').pop()
              }
            };
            moreUtilities.exportDownloads(this, [downloadItem]);
            return this.goto('VIEW');
          });
        }
      });
      this.label('END');
      return this.run(function() {
        return this.exit(0);
      });
    });
  };

}).call(this);
