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

system = require 'system'
fs = require 'fs'

HERE = fs.absolute(system.args[3]).split '/'
HERE.pop()
HERE = HERE.join '/'

casper = require(HERE + '/loopy-casper').create {
  pageSettings:
    loadImages: false
    loadPlugins: false
  verbose: true
  logLevel: 'debug'
}

utilities = require 'utils'
moreUtilities = require HERE + '/more-utils'
YAML = require HERE + '/node_modules/yamljs/index'

parameters = fs.read HERE + '/../app/config/parameters.yml'
parameters = YAML.parse(parameters).parameters.scanners

scanners = fs.read HERE + '/../app/config/scanners.yml'
scanners = YAML.parse(scanners).scanners

url = casper.cli.args.shift()
if not url
  casper.log 'No url given', 'error'
  casper.exit -1
referer = casper.cli.args.shift()
if not referer
  referer = url

for scanner, config of scanners
  scannerFile = [HERE, 'scanners', scanner].join('/')
  scannerModule = require scannerFile
  if not scannerModule.identify
    continue
  identified = scannerModule.identify url, referer, parameters[scanner], config
  if identified
    scannerModule.run casper, utilities, moreUtilities, parameters[scanner], config, identified
    break

if not identified
  casper.do ->
    download =
      referer: referer
      comment: ''
      metadata:
        'Found at': referer
    @start url,
      method: 'head'
      headers:
        Referer: referer
    @then ->
      download.url = @getCurrentUrl()
      download.filename = download.url.split('/').pop()
      download.metadata.Source = download.url
    @run ->
      #utilities.dump [download]
      moreUtilities.exportDownloads @, [download]
      @exit 0
