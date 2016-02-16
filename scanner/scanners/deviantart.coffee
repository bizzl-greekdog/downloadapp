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

module.exports.identify = (url, referer) ->
  if url == 'deviantart:watchlist' or -1 < url.indexOf 'deviantart.com/'
    url
  else if -1 < referer.indexOf 'deviantart.com/'
    referer
  else
    false

module.exports.run = (casper, utilities, moreUtilities, parameters, url) ->
  #magicUrls = ['deviantart:watchlist']
  #checkQueue = (arg for arg in args when arg.substr(0, 4) == 'http' or arg in magicUrls)
  checkQueue = [url]
  downloadQueue = []

  patternUrl = null
  scraps = false

  casper.do ->
    @start 'http://www.deviantart.com/notifications/'
    @then ->
      # "Ad Blocker"
      @page.onResourceRequested = (requestData, request) ->
        if -1 == requestData['url'].indexOf 'deviantart.com/'
          request.abort()
    @thenBypassIf (-> 'users/login' in @page.url), 1
    @then ->
      @fill 'form#login', {
        'username': parameters.user
        'password': parameters.password
      }, true
    @label 'ANALYZE'
    @then ->
      url = checkQueue.shift()
      patternUrl = null
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
      else if url == 'deviantart:watchlist'
        patternUrl = new moreUtilities.PatternUrl 'http://my.deviantart.com/global/difi/?c[]="MessageCenter","get_views",[284144,"oq:devwatch:%i:24:b:tg=deviations"]&t=json', 0, 24
        @open patternUrl.nextPage()
        @then ->
          @goto 'WATCHLIST'
      else
        path = url.split '/'
        if path[3] == 'art'
          downloadQueue.push url
          @goto 'ANALYZE'
        else
          username = path[2].split('.')[0]
          scraps = false
          patternUrl = new moreUtilities.PatternUrl 'http://' + username + '.deviantart.com/global/difi/?c[]=Resources;htmlFromQuery;gallery%3A' + username + '%20sort%3Atime,%i,24,thumb150,artist%3A0&t=json', 0, 24
          @open patternUrl.nextPage()
          @then ->
            @goto 'GALLERY'
    @label 'GALLERY'
    @then ->
      username = @getCurrentUrl().split('/')[2].split('.')[0]
      json = @getJSON()
      if not json or json.DiFi.response.calls[0].response.content.resources.length == 0
        if scraps
          @goto 'ANALYZE'
        else
          patternUrl = new moreUtilities.PatternUrl 'http://' + username + '.deviantart.com/global/difi/?c[]=Resources;htmlFromQuery;gallery%3A' + username + '%20sort%3Atime%20in%3Ascraps,%i,24,thumb150,artist%3A0&t=json', 0, 24
          scraps = true
          @open patternUrl.nextPage()
          @then ->
            @goto 'GALLERY'
      else
        json.DiFi.response.calls[0].response.content.resources.forEach (item, key, resources) ->
          if !item[2]
            return
          downloadQueue.push item[2].match(/http:\/\/[^"]*?\.deviantart\.com\/art\/[^"]+/)[0]
        @open patternUrl.nextPage()
        @then ->
            @goto 'GALLERY'
    @label 'WATCHLIST'
    @then ->
      json = @getJSON()
      if not json or json.DiFi.response.calls[0].response.content[0].result.hits.length == 0
        @goto 'ANALYZE'
      else
        json.DiFi.response.calls[0].response.content[0].result.hits.forEach (hit, key, hits) ->
          downloadQueue.push hit.url
        @open patternUrl.nextPage()
        @then ->
          @goto 'WATCHLIST'
    @label 'VIEW'
    @then ->
      url = downloadQueue.shift()
      if not url
        @goto 'END'
      else
        @open url
        @then ->
          url = @getCurrentUrl()
          fileUrl = @getElementAttribute('.dev-page-download', 'href')
          if fileUrl == null
            fileUrl = @evaluate ->
              result = null
              document.querySelectorAll('.dev-page-button:not(.pdw_button_download)').forEach (button) ->
                if null == result
                  return
                if -1 < button.textContent.search 'Download'
                  result = button.href
              return result
          if fileUrl == null
            fileUrl = @tryAnyOfThese [
                selector: 'img.dev-content-full'
                key: 'src'
              ,
                selector: 'img.dev-content-normal'
                key: 'src'
              ], ''

          title = @evaluate -> document.querySelector('.dev-title-container > h1 > a').textContent.trim()
          artist = @evaluate -> document.querySelector('a.username, h3.more-from-artist-title > a, div.dev-title-container > h1 > a.username').textContent.trim()
          try
            comment = moreUtilities.cleanText @getHTML('.text-ctrl')
          catch e
            comment = ''

          @downloadItem =
            url: null
            filename: null
            referer: url
            comment: comment
            metadata:
              Artist: artist
              Title: title
              Source: url
              'Original Filename': null
          @open fileUrl,
            method: 'head'
            headers:
              Referer: url
          @then ->
            fileUrl = @getCurrentUrl()
            originalFilename = fileUrl.split('/').pop()

            fileName = 'deviantart_' + originalFilename
            artistRegex = new RegExp '_by_' + @downloadItem.metadata.Artist.replace(/[-_ ]/g, '[-_]') + '\.' + fileName.split('.').pop() + '$', 'i'
            if not fileName.match artistRegex
              fileName = fileName.replace new RegExp('\.' + fileName.split('.').pop() + '$', 'i'), '_by_' + @downloadItem.metadata.Artist + '.' + fileName.split('.').pop()

            @downloadItem.url = fileUrl
            @downloadItem.filename = fileName
            @downloadItem.metadata['Original Filename'] = originalFilename

            moreUtilities.exportDownloads @, [@downloadItem]

            @goto 'VIEW'
    @label 'ENQUEUE'
    @then ->
      moreUtilities.enqueueUrls @, downloadQueue
      @goto 'END'
    @label 'END'
    @run ->
      @exit 0
