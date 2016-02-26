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

findDomain = (cfg, key, domain) ->
  cfg and key of cfg and cfg[key].indexOf(domain) > -1

module.exports.identify = (url, referer, parameters, config) ->
  domain = referer.replace(/^[a-z]+:\/\//, '').split('/')[0]
  if domain.indexOf('.tumblr.') > -1 or findDomain(config, 'domains', domain) or findDomain(parameters, 'domains', domain)
    {url: url, referer: referer, domain: domain}
  else
    false

module.exports.run = (casper, utilities, moreUtilities, parameters, config, metainfo) ->
  casper.do ->
    @start 'about:blank'
    @run ->
      artist = metainfo.domain.split '.'
      if artist[-2..-2][0] == 'tumblr' or artist.length == 2
        artist = artist[0]
      else
        artist = artist[-2..-2][0]
      url = metainfo.url
      originalFilename = url.split('/').pop()
      filename = originalFilename.replace /\.([a-z0-9A-Z]+)$/, '_by_' + artist + '.$1'
      unless 0 == filename.indexOf 'tumblr_'
        filename = 'tumblr_' +filename
      download =
        url: url
        referer: metainfo.referer
        filename: filename
        metadata:
          Artist: artist
          'Found at': metainfo.referer
          'Original filename': originalFilename
        comment: ''
      moreUtilities.exportDownloads @, [download]
      @exit 0
