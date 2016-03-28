module.exports.cleanText = (text) ->
  text
  .replace /[\t\f\r]/g, ''
  .replace /<br[^>]*>/g, '\n'
  .replace /<a[^>]*iconusername[^>]*>[^<]*<img[^>]*title="([^"]+)"[^>]*>[^<]*<\/a>/g, '$1'
  .replace /<(\/?[bisu])(?: [^>]*)?>/g, '[$1]'
  .replace /<[^>]+>/g, ''
  .replace /^[\t\n\f\r]*|[\t\n\f\r]*$/g, ''
  .replace /\n\n\n+/g, '\n\n'
  .replace /;([^ ])/g, '; $1'
  .replace /^\n+/, ''
  .replace /\[(\/?[bisu])]/g, '<$1>'
  .replace /&nbsp;/g, ' '
  .trim()

module.exports.cleanFilename = (filename) ->
  filename
  .replace /[<]/g, '('
  .replace /[>]/g, ')'
  .replace /[ ]?:[ ]?/g, ' - '
  .replace /"/g, ''
  .replace /[ ]?\/[ ]?/g, ' - '
  .replace /[ ]?\\[ ]?/g, ' - '
  .replace /[ ]?\|[ ]?/g, ' - '
  .replace /[?]/g, ''
  .replace /[*]/g, ''
  .replace /[\x00-\x1f]/g, ''

class module.exports.PatternUrl
  constructor: (@pattern, startValue, @stepWidth) ->
    @i = startValue

  nextPage: ->
    i = @i
    @i += @stepWidth
    @pattern.replace /%i/, i

  toString: ->
    @pattern + '; ' + @i + '; ' + @stepWidth

module.exports.scanLine = ->
  system = require 'system'
  system.stdin.readLine()

module.exports.exportDownloads = (casper, a) ->
  a.forEach (element) ->
    if element.filename
      element.filename = module.exports.cleanFilename element.filename
    casper.echo 'DOWNLOAD ' + JSON.stringify(element)

module.exports.enqueueUrls = (casper, urls) ->
  urls.forEach (url) ->
    casper.echo 'ENQUEUE ' + url

module.exports.notify = (casper, message) ->
  casper.echo 'NOTIFY ' + message

module.exports.alert = (casper, message) ->
  casper.echo 'ALERT ' + message
