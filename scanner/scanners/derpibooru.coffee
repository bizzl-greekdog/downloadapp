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
  domain = url.replace(/^[a-z]+:\/\//, '').split('/')[0]
  if domain == 'derpibooru.org' or domain == 'derpiboo.ru'
    url
  else
    domain = referer.replace(/^[a-z]+:\/\//, '').split('/')[0]
    if domain == 'derpibooru.org' or domain == 'derpiboo.ru'
      referer
    else
      false

module.exports.run = (casper, utilities, moreUtilities, parameters, config, url) ->
  casper.do ->
    @start url
    @then ->
      fileUrl = @getElementAttribute 'a[href*="/download/"][href*="__"]', 'href'
      originalFilename = fileUrl.replace(/\?.*$/, '').split('/').pop()
      source = @getElementAttribute '.source_url > a', 'href'
      if source
        moreUtilities.enqueueUrls @, [source]
      else
        source = ''
      artist = @evaluate ->
        artists = []
        Array.prototype.slice.call(document.querySelectorAll '.tag-ns-artist').forEach (tag) ->
          artists.push tag.getAttribute 'data-tag-name-in-namespace'
        artists.join ', '
      if @exists '.image-description'
        comment = @getHTML '.image-description'
        comment = moreUtilities.cleanText comment.replace /^<h3.*?<\/h3>/, ''
      else
        comment = ''
      download =
        url: fileUrl,
        filename: 'derpibooru_' + originalFilename,
        referer: @getCurrentUrl(),
        metadata:
          Artist: artist,
          'Original filename': originalFilename,
          'Found at': @getCurrentUrl(),
          Source: source
        comment: comment
      moreUtilities.exportDownloads @, [download]
    @run ->
      @exit 0
