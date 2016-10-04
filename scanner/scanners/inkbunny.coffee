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
  if -1 < url.indexOf 'inkbunny.net/'
    url
  else if -1 < referer.indexOf 'inkbunny.net/'
    referer
  else
    false

module.exports.run = (casper, utilities, moreUtilities, parameters, config, url) ->
  checkQueue = [url]
  downloadQueue = []

  patternUrl = null
  scraps = false

  casper.do ->
    @start 'https://inkbunny.net/profile.php'
    @then ->
# "Ad Blocker"
      @page.onResourceRequested = (requestData, request) ->
        if -1 == requestData['url'].indexOf 'inkbunny.net/'
          request.abort()
    @waitForSelector '#nav_bottom', (->), (->)
    @thenBypassIf (-> @getCurrentUrl().indexOf('error.php') == -1), 2
    @then ->
      @open 'https://inkbunny.net/login.php'
      @then ->
        @fill 'form[action="login_process.php"]', {
          'username': parameters.user
          'password': parameters.password
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
      else
        downloadQueue.push url
        @goto 'ANALYZE'
    @label 'WATCHLIST'
    @then ->
      @goto 'ANALYZE'
    @label 'GALLERY'
    @then ->
      @goto 'ANALYZE'
    @label 'VIEW'
    @then ->
      url = downloadQueue.shift()
      if not url
        @goto 'END'
      else
        @open url
        @then ->
          url = @getCurrentUrl()
          if @exists '#size_container a[href*=metapix]'
            fileUrl = @getElementAttribute '#size_container a[href*=metapix]', 'href'
          else
            fileUrl = @getElementAttribute 'img#magicbox', 'src'
          if @exists '#charsheet_content'
            comment = moreUtilities.cleanText @getHTML '#charsheet_content'
          else
            comment = moreUtilities.cleanText @getHTML '.elephant_bottom:not(.elephant_top) .content > div:nth-child(1)'
          title = @fetchText('.pooltable>tbody>tr>td>div>table>tbody>tr>td>h1').trim()
          artist = @fetchText('.content>table>tbody>tr>td>div>a:not([href*=php])').trim()
          fileName = 'inkbunny_' + fileUrl.split('/').pop()
          downloadItem =
            url: fileUrl,
            filename: fileName
            referer: url
            comment: comment
            metadata:
              Artist: artist
              Title: title
              Source: url
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
