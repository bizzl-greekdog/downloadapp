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
  if -1 < url.indexOf 'apod.nasa.gov/'
    url
  else if -1 < referer.indexOf 'apod.nasa.gov/'
    referer
  else
    false

module.exports.run = (casper, utilities, moreUtilities, parameters, url) ->
  checkQueue = [url]
  downloadQueue = []
  downloadItems = []

  casper.do ->
    @start()
    @label 'ANALYZE'
    @then ->
      url = checkQueue.shift()
      if not url
        @goto 'VIEW'
      else if -1 < url.indexOf 'apod/astropix'
        @open url
        @then ->
          num = @getElementAttribute(@x('html/body/center[3]/a[text() = \'<\']'), 'href')
          num = parseInt(num[2..-6], 10)+1
          downloadQueue.push "http://apod.nasa.gov/apod/ap#{num}.html"
          @goto 'ANALYZE'
      else
        downloadQueue.push url
        @goto 'ANALYZE'
    @label 'VIEW'
    @then ->
      url = downloadQueue.shift()
      if not url
        @goto 'END'
      else
        @open url
        @then ->
          referer = @getCurrentUrl()
          fileUrl = @getElementAttribute(@x('/html/body/center[1]/p[2]/a'), 'href')
          fileUrl = "http://apod.nasa.gov/apod/#{fileUrl}"
          origFn = fileUrl.replace(/\?.*$/, '').split('/').pop()
          title = @fetchText(@x '/html/body/center[2]/b[1]').trim()
          artist = @fetchText(@x '/html/body/center[2]').replace(/\n/g, ' ').replace(/\s+/g, ' ').split(':', 2)[1].trim()
          comment = moreUtilities
            .cleanText @fetchText @x '/html/body/p[1]'
            .replace /^Explanation: /, ''
            .trim()
            .replace ///\ *\n///g, ' '
            .replace ///\ \ ///g, "\n"
          downloadItems.push
            url: fileUrl,
            filename: 'apod_' + origFn,
            referer: referer,
            metadata:
              'Title': title,
              'Artist': artist,
              'Original filename': origFn,
              'Source': referer
            comment: comment
          @goto 'VIEW'
    @label 'END'
    @run ->
      utilities.dump downloadItems
      @exit 0
