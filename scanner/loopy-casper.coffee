`require = patchRequire(require)`

casperModule = require 'casper'
Casper = casperModule.Casper
utils = require 'utils'
f = utils.format

LoopyCasper = -> LoopyCasper.super_.apply @, arguments
utils.inherits LoopyCasper, Casper

###
 * Revised checkStep() function for realizing label() and goto()
 * Every revised points are commented.
 *
 * @param  Casper    self        A self reference
 * @param  function  onComplete  An options callback to apply on completion
###
LoopyCasper.prototype.checkStep = (self, onComplete) ->
  if not self.pendingWait and not self.loadInProgress
    # Added:  New Property. self.current is current execution step pointer
    self.current = self.step
    step = self.steps[self.step++]
    if utils.isFunction step
      self.runStep step
      # Added: @navigation step is executed already or not.
      step.executed = true
    else
      self.result.time = new Date().getTime() - self.startTime
      self.log f('Done %s steps in %dms', self.steps.length, self.result.time), 'info'
      clearInterval self.checker
      self.emit 'run.complete'
      if utils.isFunction onComplete
        try
          onComplete.call(self, self);
        catch err
          self.log f('Could not complete final step: %s', err), 'error'
      else
        # default behavior is to exit
        self.exit()

###
 * Revised then() function for realizing label() and goto()
 * Every revised points are commented.
 *
 * @param  function  step  A function to be called as a step
 * @return Casper
###
LoopyCasper.prototype.then = (step) ->
  if not @started
    throw new CasperError 'Casper not started; please use Casper#start'
  if not utils.isFunction step
    throw new CasperError 'You can only define a step as a function'
  # check if casper is running
  if @checker == null
    # append step to the end of the queue
    step.level = 0
    @steps.push step
    # Added: New Property. @navigation step is executed already or not.
    step.executed = false
    # Moved: from bottom
    @emit 'step.added', step
  else
    # Added: Add step to @steps only in the case of not being executed yet.
    if not @steps[@current].executed
      # insert substep a level deeper
      try
        # Changed:  (@step-1) is not always current navigation step
        # step.level = @steps[@step - 1].level + 1;   <=== Original
        step.level = @steps[@current].level + 1
      catch e
        step.level = 0
      insertIndex = @step
      while @steps[insertIndex] and step.level == @steps[insertIndex].level
        insertIndex++
      @steps.splice insertIndex, 0, step
      # Added:  New Property. @navigation step is executed already or not.
      step.executed = false
      # Moved:  from bottom
      @emit 'step.added', step
  # Added:  End of if() that is added.
  #    @emit('step.added', step);   # Move above. Because then() is not always adding step. only first execution time.
  this

###
 * Adds a new navigation step by 'then()'  with naming label
 *
 * @param    String    labelname    Label name for naming execution step
###
LoopyCasper.prototype.label = (labelname) ->
  # make empty step
  step = new Function f '"empty function for label: %s "', labelname
  # Adds new property 'label' to the step for label naming
  step.label = labelname
  # Adds new step by then()
  @then step

###
 * Goto labeled navigation step
 *
 * @param String    labelname    Label name for jumping navigation step
###
LoopyCasper.prototype.goto = (labelname) ->
  # Search for label in steps array
  for i in [0..@steps.length - 1]
    # found?
    if @steps[i].label == labelname
      # new step pointer is set
      @step = i

# End of Extending Casper functions for realizing label() and goto()
#================================================================================
#================================================================================

#================================================================================
#================================================================================
# Extending Casper functions for dumpSteps()

###
 * Dump Navigation Steps for debugging
 * When you call @function, you cat get current all information about CasperJS Navigation Steps
 * @is compatible with label() and goto() functions already.
 *
 * @param   Boolen   showSource    showing the source code in the navigation step?
 *
 * All step No. display is (steps array index + 1),  in order to accord with logging [info] messages.
 *
###
LoopyCasper.prototype.dumpSteps = (showSource) ->
  @echo '=========================== Dump Navigation Steps ==============================', 'RED_BAR'
  if @current
    @echo f('Current step No. = %d', @current + 1), 'INFO'
  @echo f('Next    step No. = %d', (@step + 1)), 'INFO'
  @echo f('steps.length = %d', @steps.length), 'INFO'
  @echo '================================================================================', 'WARNING'

  for i in [0..@steps.length - 1]
    step = @steps[i]
    msg = f 'Step: %d/%d     level: %d', i + 1, @steps.length, step.level
    if step.executed
      msg += '     executed: ' + step.executed
    color = 'PARAMETER'
    if step.label
      color = 'INFO';
      msg += f '     label: %s', step.label
    if i == @current
      @echo msg + '     <====== Current Navigation Step.', 'COMMENT'
    else
      @echo msg, color
    if showSource
      @echo '--------------------------------------------------------------------------------'
      @echo @steps[i]
      @echo '================================================================================', 'WARNING'

LoopyCasper.prototype['do'] = (callback) ->
  callback.call this

LoopyCasper.prototype.printf = ->
  @echo f.apply utils, arguments

LoopyCasper.prototype.getJSON = ->
  JSON.parse @getPageContent()

LoopyCasper.prototype.tryAnyOfThese = (things, default_) ->
  for thing in things
    selector = thing['selector']
    attribute = thing['key']
    m = @getElementAttribute selector, attribute
    if m
      return m
  return default_

LoopyCasper.prototype.x = (xPath) ->
  casperModule.selectXPath xPath

module.exports.LoopyCasper = LoopyCasper
module.exports.create = (options) ->
  new LoopyCasper options
