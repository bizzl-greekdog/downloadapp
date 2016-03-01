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
  domain = referer.replace(/^[a-z]+:\/\//, '').split('/')[0]
  if domain == 'steamcommunity.com'
    referer
  else
    domain = url.replace(/^[a-z]+:\/\//, '').split('/')[0]
    if domain == 'steamcommunity.com'
      url
    else
      false

module.exports.run = (casper, utilities, moreUtilities, parameters, config, url) ->
  casper.do ->
    @start url
    @then ->
      comment = moreUtilities.cleanText @safeGetHTML '.nonScreenshotDescription'
      artist = moreUtilities.cleanText @safeGetHTML '.creatorsBlock .linkAuthor a'
      if not artist
        artist = moreUtilities.cleanText @safeGetHTML '.creatorsBlock .friendBlockContent'
      artist = artist.split("\n")[0]
      title = moreUtilities.cleanText @safeGetHTML '.workshopItemTitle'
      fileUrl = @tryAnyOfThese [{selector: '.general_btn.downloadImage', key: 'href'}, {selector: '.mediaTop > a', key: 'href'}], false
      fileName = "steamcommunity_#{title}_by_#{artist}.png"
      fileName = fileName.replace /[!"'?#$]/g, ''
      fileName = fileName.replace /[ ]/g, '_'
      download =
        url: fileUrl,
        filename: fileName,
        referer: @getCurrentUrl(),
        comment: comment,
        metadata:
          Artist: artist,
          Title: title,
          Source: @getCurrentUrl
      moreUtilities.exportDownloads @, [download]
    @run ->
      @exit 0
