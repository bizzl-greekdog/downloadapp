###
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
###

module.exports.identify = (url, referer, parameters, config) ->
  if url == 'furaffinity:watchlist' or -1 < url.indexOf 'www.furaffinity.net/'
    url
  else if -1 < referer.indexOf 'www.furaffinity.net/'
    referer
  else
    false

module.exports.run = (casper, utilities, moreUtilities, parameters, config, url) ->
  checkQueue = [url]
  downloadQueue = []

  casper.do ->
    @start 'http://www.furaffinity.net/msg/others'
    @thenBypassIf (-> !@exists('a[href="/login/"]')), 3
    @then ->
      @log 'Not logged in', 'info'
      @click 'a[href="/login/"]'
    @waitForSelector 'form'
    @then ->
      @log 'Logging in', 'info'
      @fill 'form', {
        'name': parameters.user
        'pass': parameters.password
      }, true
    @label 'ANALYZE'
    @then ->
      url = checkQueue.shift()
      if not url
        if downloadQueue.length > 1000
          moreUtilities.alert @, "Prescan done, #{downloadQueue.length} pages will be enqueued"
          @goto 'ENQUEUE'
          return
        else if downloadQueue.length > 10
          moreUtilities.alert @, "Prescan done, #{downloadQueue.length} pages will be scanned"
        else if downloadQueue.length > 1
          moreUtilities.notify @, "Prescan done, #{downloadQueue.length} pages will be scanned"
        @goto 'VIEW'
      else if url == 'furaffinity:watchlist'
        @open 'http://www.furaffinity.net/msg/submissions/'
        @then ->
          @goto 'WATCHLIST'
      else
        path = url.split '/'
        if path[3] == 'view'
          downloadQueue.push url
        else if path[3] == 'full'
          path[3] = 'view'
          url = path.join '/'
          downloadQueue.push url
        else if path[3] in ['user', 'gallery', 'scraps']
          path = path[0..4]
          path[3] = 'gallery'
          path.push 1
          url = path.join '/'
          @open url
          @goto 'GALLERY'
          return
        @goto 'ANALYZE'
    @label 'WATCHLIST'
    @then ->
      images = @getElementsAttribute('#messages-form .t-image a', 'href').filter (e, i, a) -> String(e).substr(0, 6) == '/view/'
      processed = 0
      images.forEach (image) ->
        image = 'http://furaffinity.net' + image
        if -1 == downloadQueue.indexOf image
          downloadQueue.push image
          processed++
      if processed
        @click 'a.more'
        @then ->
          @goto 'WATCHLIST'
      else
        @goto 'ANALYZE'
    @label 'GALLERY'
    @then ->
      views = @getElementsAttribute '.submission-list a[href*="/view/"]', 'href'
      @log @page.url + ' has ' + views.length, 'info'
      @log 'Total: ' + downloadQueue.length, 'info'
      if views.length
        for view in views
          downloadQueue.push 'http://www.furaffinity.net' + view
        path = (@page.url.split '/')[0..5]
        path[5] = 1 + parseInt path[5]
        url = path.join '/'
        @open url
        @goto 'GALLERY'
      else
        path = (@page.url.split '/')[0..4]
        path[3] = 'scraps'
        path.push 1
        url = path.join '/'
        @open url
        @goto 'SCRAPS'
    @label 'SCRAPS'
    @then ->
      views = @getElementsAttribute '.submission-list a[href*="/view/"]', 'href'
      @log @page.url + ' has ' + views.length, 'info'
      @log 'Total: ' + downloadQueue.length, 'info'
      if views.length
        for view in views
          downloadQueue.push 'http://www.furaffinity.net' + view
        path = (@page.url.split '/')[0..5]
        path[5] = 1 + parseInt path[5]
        url = path.join '/'
        @open url
        @goto 'SCRAPS'
      else
        @goto 'ANALYZE'
    @label 'VIEW'
    @then ->
      url = downloadQueue.shift()
      if not url
        @goto 'END'
      else
        @open url
        @then ->
          url = @page.url
          fileUrl = 'http:' + @getElementAttribute('a[href*=facdn]', 'href')
          title = @getHTML '#page-submission td.cat b'
          artist = @fetchText '#page-submission td.cat a[href*=user]'
          fileName = fileUrl.split('/').pop()
          comment = moreUtilities.cleanText @getHTML('#page-submission td.alt1[width="70%"]')

          downloadItem =
            url: fileUrl,
            filename: fileName,
            referer: url,
            comment: comment,
            metadata:
              'Artist': artist,
              'Title': title,
              'Source': url,
              'Original Filename': fileUrl.split('/').pop()
          moreUtilities.exportDownloads @, [downloadItem]

          @goto 'VIEW'
    @label 'ENQUEUE'
    @then ->
      moreUtilities.enqueueUrls @, downloadQueue
      @goto 'END'
    @label 'END'
    @run ->
      @exit 0
