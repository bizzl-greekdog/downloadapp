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
  if url == config.watchlist.key or -1 < url.indexOf 'weasyl.com/'
    url
  else if -1 < referer.indexOf 'weasyl.com/'
    referer
  else
    false

module.exports.run = (casper, utilities, moreUtilities, parameters, config, url) ->
  checkQueue = [url]
  downloadQueue = []

  patternUrl = null
  scraps = false

  casper.do ->
    @start config.watchlist.url
    @then ->
# "Ad Blocker"
      @page.onResourceRequested = (requestData, request) ->
        if -1 == requestData['url'].indexOf 'weasyl.com/'
          request.abort()
    @thenBypassIf (-> '/signin' != @getElementAttribute '#hg-login', 'href'), 2
    @then ->
      @open 'https://www.weasyl.com/signin'
      @then ->
        @fill '#login-box form', {
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
      else if url == config.watchlist.key
        @open config.watchlist.url
        @goto 'WATCHLIST'
      else
        path = url.split '/'
        if path[3] in ['submission', 'character']
          downloadQueue.push url
        else if path[3] in ['submissions', 'collections', 'favorites', 'characters']
          @open url
          @goto 'GALLERY'
          return
        else if path[3] in ['profile', 'user']
          checkQueue.push 'https://www.weasyl.com/submissions/' + path[4]
          checkQueue.push 'https://www.weasyl.com/characters/' + path[4]
        else if '~' == path[3].charAt 0
          checkQueue.push 'https://www.weasyl.com/submissions/' + path[3].substring 1
          checkQueue.push 'https://www.weasyl.com/characters/' + path[3].substring 1
        else
          moreUtilities.alert @, "#{url} is a weird url for weasyl"
        @goto 'ANALYZE'
    @label 'WATCHLIST'
    @then ->
      views = @getElementsAttribute '.thumbnail-grid .item .thumb a.thumb-bounds', 'href'
      @log "#{@getCurrentUrl()} has #{views.length}", 'info'
      @log "Total: #{downloadQueue.length}", 'info'
      if views.length
        for view in views
          view = "https://www.weasyl.com#{view}"
          if view not in downloadQueue
            downloadQueue.push view
          else
            moreUtilities.notify @, "#{view} appeared more than once in prescan"
      if @exists 'a.button.notifs-next'
        @click 'a.button.notifs-next'
        @goto 'WATCHLIST'
      else
        @goto 'ANALYZE'
    @label 'GALLERY'
    @then ->
      views = @getElementsAttribute '.thumbnail-grid .item .thumb a.thumb-bounds', 'href'
      @log "#{@getCurrentUrl()} has #{views.length}", 'info'
      @log "Total: #{downloadQueue.length}", 'info'
      if views.length
        for view in views
          view = "https://www.weasyl.com#{view}"
          if view not in downloadQueue
            downloadQueue.push view
          else
            moreUtilities.notify @, "#{view} appeared more than once in prescan"
      if @exists '.sectioned-main a.button:not([href*="backid="])'
        @click '.sectioned-main a.button:not([href*="backid="])'
        @goto 'GALLERY'
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
          url = @getCurrentUrl()
          fileUrl = @getElementAttribute '#detail-actions a[href*=submission], #detail-actions a[href*=submit]', 'href'
          if fileUrl == null
            moreUtilities.alert @, "#{url} might be a video"
          else
            comment = moreUtilities.cleanText @getHTML '#detail-description .formatted-content:not(.markdown-preview)'
            title = @fetchText('#detail-bar-title').trim()
            artist = @fetchText('#db-user .username').trim()
            fileExt = fileUrl.split('.').pop().replace /\?.*$/, ''
            fileNr = url.split('/')[4]
            fileTitle = title.toLowerCase().replace(/['"\n]/g, '').replace /[ ]/g, '_'
            fileName = "weasyl_#{fileNr}_#{fileTitle}_by_#{artist}.#{fileExt}"
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
