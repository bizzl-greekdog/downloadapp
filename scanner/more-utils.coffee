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
    casper.echo 'DOWNLOAD ' + JSON.stringify(element)

module.exports.notify = (casper, message) ->
  casper.echo 'NOTIFY ' + message
