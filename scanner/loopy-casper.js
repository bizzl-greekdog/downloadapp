// Generated by CoffeeScript 1.10.0
(function() {
  require = patchRequire(require);
  var Casper, LoopyCasper, casperModule, f, utils;

  casperModule = require('casper');

  Casper = casperModule.Casper;

  utils = require('utils');

  f = utils.format;

  LoopyCasper = function() {
    return LoopyCasper.super_.apply(this, arguments);
  };

  utils.inherits(LoopyCasper, Casper);


  /*
   * Revised checkStep() function for realizing label() and goto()
   * Every revised points are commented.
   *
   * @param  Casper    self        A self reference
   * @param  function  onComplete  An options callback to apply on completion
   */

  LoopyCasper.prototype.checkStep = function(self, onComplete) {
    var err, error, step;
    if (!self.pendingWait && !self.loadInProgress) {
      self.current = self.step;
      step = self.steps[self.step++];
      if (utils.isFunction(step)) {
        self.runStep(step);
        return step.executed = true;
      } else {
        self.result.time = new Date().getTime() - self.startTime;
        self.log(f('Done %s steps in %dms', self.steps.length, self.result.time), 'info');
        clearInterval(self.checker);
        self.emit('run.complete');
        if (utils.isFunction(onComplete)) {
          try {
            return onComplete.call(self, self);
          } catch (error) {
            err = error;
            return self.log(f('Could not complete final step: %s', err), 'error');
          }
        } else {
          return self.exit();
        }
      }
    }
  };


  /*
   * Revised then() function for realizing label() and goto()
   * Every revised points are commented.
   *
   * @param  function  step  A function to be called as a step
   * @return Casper
   */

  LoopyCasper.prototype.then = function(step) {
    var e, error, insertIndex;
    if (!this.started) {
      throw new CasperError('Casper not started; please use Casper#start');
    }
    if (!utils.isFunction(step)) {
      throw new CasperError('You can only define a step as a function');
    }
    if (this.checker === null) {
      step.level = 0;
      this.steps.push(step);
      step.executed = false;
      this.emit('step.added', step);
    } else {
      if (!this.steps[this.current].executed) {
        try {
          step.level = this.steps[this.current].level + 1;
        } catch (error) {
          e = error;
          step.level = 0;
        }
        insertIndex = this.step;
        while (this.steps[insertIndex] && step.level === this.steps[insertIndex].level) {
          insertIndex++;
        }
        this.steps.splice(insertIndex, 0, step);
        step.executed = false;
        this.emit('step.added', step);
      }
    }
    return this;
  };


  /*
   * Adds a new navigation step by 'then()'  with naming label
   *
   * @param    String    labelname    Label name for naming execution step
   */

  LoopyCasper.prototype.label = function(labelname) {
    var step;
    step = new Function(f('"empty function for label: %s "', labelname));
    step.label = labelname;
    return this.then(step);
  };


  /*
   * Goto labeled navigation step
   *
   * @param String    labelname    Label name for jumping navigation step
   */

  LoopyCasper.prototype.goto = function(labelname) {
    var i, j, ref, results;
    results = [];
    for (i = j = 0, ref = this.steps.length - 1; 0 <= ref ? j <= ref : j >= ref; i = 0 <= ref ? ++j : --j) {
      if (this.steps[i].label === labelname) {
        results.push(this.step = i);
      } else {
        results.push(void 0);
      }
    }
    return results;
  };


  /*
   * Dump Navigation Steps for debugging
   * When you call @function, you cat get current all information about CasperJS Navigation Steps
   * @is compatible with label() and goto() functions already.
   *
   * @param   Boolen   showSource    showing the source code in the navigation step?
   *
   * All step No. display is (steps array index + 1),  in order to accord with logging [info] messages.
   *
   */

  LoopyCasper.prototype.dumpSteps = function(showSource) {
    var color, i, j, msg, ref, results, step;
    this.echo('=========================== Dump Navigation Steps ==============================', 'RED_BAR');
    if (this.current) {
      this.echo(f('Current step No. = %d', this.current + 1), 'INFO');
    }
    this.echo(f('Next    step No. = %d', this.step + 1), 'INFO');
    this.echo(f('steps.length = %d', this.steps.length), 'INFO');
    this.echo('================================================================================', 'WARNING');
    results = [];
    for (i = j = 0, ref = this.steps.length - 1; 0 <= ref ? j <= ref : j >= ref; i = 0 <= ref ? ++j : --j) {
      step = this.steps[i];
      msg = f('Step: %d/%d     level: %d', i + 1, this.steps.length, step.level);
      if (step.executed) {
        msg += '     executed: ' + step.executed;
      }
      color = 'PARAMETER';
      if (step.label) {
        color = 'INFO';
        msg += f('     label: %s', step.label);
      }
      if (i === this.current) {
        this.echo(msg + '     <====== Current Navigation Step.', 'COMMENT');
      } else {
        this.echo(msg, color);
      }
      if (showSource) {
        this.echo('--------------------------------------------------------------------------------');
        this.echo(this.steps[i]);
        results.push(this.echo('================================================================================', 'WARNING'));
      } else {
        results.push(void 0);
      }
    }
    return results;
  };

  LoopyCasper.prototype['do'] = function(callback) {
    return callback.call(this);
  };

  LoopyCasper.prototype.printf = function() {
    return this.echo(f.apply(utils, arguments));
  };

  LoopyCasper.prototype.getJSON = function() {
    return JSON.parse(this.page.plainText);
  };

  LoopyCasper.prototype.tryAnyOfThese = function(things, default_) {
    var attribute, j, len, m, selector, thing;
    for (j = 0, len = things.length; j < len; j++) {
      thing = things[j];
      selector = thing['selector'];
      attribute = thing['key'];
      if (this.exists(selector)) {
        m = this.getElementAttribute(selector, attribute);
        if (m) {
          return m;
        }
      }
    }
    return default_;
  };

  LoopyCasper.prototype.safeGetHTML = function(selector, default_) {
    if (default_ == null) {
      default_ = '';
    }
    if (this.exists(selector)) {
      return this.getHTML(selector);
    } else {
      return default_;
    }
  };

  LoopyCasper.prototype.x = function(xPath) {
    return casperModule.selectXPath(xPath);
  };

  module.exports.LoopyCasper = LoopyCasper;

  module.exports.create = function(options) {
    return new LoopyCasper(options);
  };

}).call(this);
