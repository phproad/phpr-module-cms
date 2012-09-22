/**
 * Scripts Ahoy! Software
 *
 * Copyright (c) 2012 Scripts Ahoy! (scriptsahoy.com)
 * All terms, conditions and copyrights as defined
 * in the Scripts Ahoy! License Agreement
 * http://www.scriptsahoy.com/license
 *
 */

/**
 * Ahoy namespace
 */

var ahoy = { 
	self:      null, // Generic self object
	behaviors: { }   // Behaviors (see below)
};

/**
 * Ajax interface
 * 
 * Usage: ahoy.post('#form').action('core:on_null').success(function(){ alert('Success!'); }).send();
 */

ahoy.post = function(obj) {
	return new ahoy.post_object(obj)
};

ahoy.post_object = function(obj) {

	this._data = {
		update: {},
		extraFields: {},
		selectorMode: true
	};
	this._action = 'core:on_null';
	this._form = null;

	// Manually sets a field/value
	this.set = function(field, value) {
		this._data[field] = value;
		return this;
	};

	// Used at the end of chaining to fire the ajax call
	this.send = function() {
		var self = this;
		ahoy.self = self._form;
		return self._form.sendRequest(self._action, self._data);
	};

	// Defines an ajax action (optional)
	this.action = function(value) {
		this._action = value;
		return this;
	};

	this.confirm = function(value) {
		this._data.confirm = value;
		return this;
	};

	this.success = function(func) {
		this._data.onSuccess = func;
		return this;
	};

	this.error = function(func) {
		this._data.onFailure = func;
		return this;		
	};

	this.prepare = function(func, pre_check) {
		if (pre_check)
			this._data.preCheckFunction = func;
		else
			this._data.onBeforePost = func;
		return this;
	};

	this.complete = function(func, after_update) {
		if (after_update)
			this._data.onAfterUpdate = func;
		else
			this._data.onComplete = func;
		return this;
	};

	this.update = function(element, partial) {
		if (partial)
			this._data.update[element] = partial;
		else
			this._data.update = element;
		return this;
	};

	this.data = function(field, value) {
		if (value !== undefined)
			this._data.extraFields[field] = value;
		else
			this._data.extraFields = field;
		return this;
	};

	this.get_form = function(form) {
		form = (!form) ? jQuery('<form></form>') : form;
		form = (form instanceof jQuery) ? form : jQuery(form);
		form = (form.is('form')) ? form : form.closest('form');
		form = (form.attr('id')) ? jQuery('form#'+form.attr('id')) : form.attr('id', 'form_element');		
		return form;	
	};

	this._form = this.get_form(obj);
};


/**
 * Form validation
 * 
 * Usage:
 * ahoy.validate.add_rule('field_name', 'page_home_code').required("This is required!").set();
 * ahoy.validate.bind($('#form'), 'page_home_code', function() { alert('Success!'); });
 */

ahoy.validate = {

	// Default options used for jQ validate
	default_options: {
		onkeyup: false,
		ignore:":not(:visible):not(:disabled)",
		submitHandler: function(form) {
			form.submit();
		}
	},

	// Binds a set of rules (code) to a form (form)
	bind: function(form, code, on_success, extra_options) {

		if (!ahoy[code])
			return;

		var self = ahoy.validate;
		var options = {
			messages: ahoy[code].validate_messages,
			rules: ahoy[code].validate_rules,
			submitHandler: on_success
		};

		if (extra_options)
			options = $.extend(true, options, extra_options);
		
		ahoy.validate.init(form, options);
	},

	// Initialises jQ validate on an element with defaults
	init: function(element, set_options, ignore_defaults) {
		var self = ahoy.validate;
		
		var options = (ignore_defaults) 
			? set_options 
			: $.extend(true, self.default_options, set_options);

		element.validate(options);
	},

	// Creates a new rule instance
	add_rule: function(object, code) {
		return new ahoy.validate_object(object, code);		
	},

	// Adds or removes rules to an exisiting validation form
	set_rules: function(form, code, is_remove) {

		if (ahoy[code] === undefined)
			return;

		rules = ahoy[code].validate_rules;
		messages = ahoy[code].validate_messages;

		if (rules === undefined)
			return;
		
		$.each(rules, function(name,field){
			
			var set_rules = (messages[name] !== undefined) 
				? $.extend(true, field, { messages: messages[name] }) 
				: field; 
			
			var field = $(form).find('[name="'+name+'"]');

			if (field.length > 0)
			{
				if (is_remove)
					field.rules('remove');
				else
					field.rules('add', set_rules);
			}
		});
	}

};

// Validate object: binds an element (field) to a code 
// within the Ahoy namespace (ahoy.code) for use/reuse
ahoy.validate_object = function(field, code) {

	this._rules = {};
	this._messages = {};
	this._field = field;
	this._object = code;

	if (ahoy[code] === undefined) {
		ahoy[code] = {
			validate_rules: {},
			validate_messages: {}
		};
	}

	// Manually sets a rule
	this.set_rule = function(field, value) {
		this._rules[field] = value;
		return this;
	};	

	// Manually sets a message
	this.set_message = function(field, value) {
		this._messages[field] = value;
		return this;
	};

	// Chained rules
	this.required = function(message) {
		this._rules.required = true;
		this._messages.required = message;
		return this;
	};

	// Requires at least X (min_filled) inputs (element class) populated 
	this.required_multi = function(min_filled, element_class, message) {
		this._rules.required_multi = [min_filled, element_class];
		this._messages.required_multi = message;
		return this;
	};

	this.number = function(message) {
		this._rules.number = true;
		this._messages.number = message;
		return this;
	};

	this.phone = function(message) {
		this._rules.phone = true;
		this._messages.phone = message;
		return this;
	};

	this.email = function(message) {
		this._rules.email = true;
		this._messages.email = message;
		return this;
	};

	this.date = function(message) {
		this._rules.ahoyDate = true;
		this._messages.ahoyDate = message;
		return this;
	};

	this.url = function(message) {
		this._rules.fullUrl = true;
		this._messages.fullUrl = message;
		return this;
	};

	this.range = function(range, message) {
		this._rules.range = range;
		this._messages.range = message;
		return this;
	};

	this.min = function(min, message) {
		this._rules.min = min;
		this._messages.min = message;
		return this;
	};

	this.max = function(max, message) {
		this._rules.max = max;
		this._messages.max = message;
		return this;
	};

	this.min_words = function(words, message) {
		this._rules.minWord = words;
		this._messages.minWord = message;
		return this;
	};

	this.matches = function(field, message) {
		this._rules.equalTo = field;
		this._messages.equalTo = message;
		return this;
	};

	// Submits a remote action using ahoy.post
	this.action = function(action, message) {
		this._rules.ahoyRemote = { action:action };
		this._messages.ahoyRemote = message;
		return this;
	};

	// Used at the end of chaining to lock in settings
	this.set = function() {
		ahoy[this._object].validate_rules[this._field] = this._rules;
		ahoy[this._object].validate_messages[this._field] = this._messages;
	};

};

/**
 * Helpers
 */

function root_url(url) {
	if (typeof root_dir === 'undefined' || !root_dir)
		return url;
		
	if (url.substr(0,1) == '/')
		url = url.substr(1);
	
	return root_dir + url;
}

function asset_url(url) {
	if (typeof asset_dir === 'undefined' || !asset_dir)
		return url;
		
	if (url.substr(0,1) == '/')
		url = url.substr(1);
	
	return root_url(asset_dir + url);
}

// Toggle text between current and data-toggle-text contents
$.fn.extend({
	toggleText: function() {
		var self = $(this);
		var text = self.text();
		var ttext = self.data('toggle-text');
		var tclass = self.data('toggle-class');
		self.text(ttext).data('toggle-text', text).toggleClass(tclass);
	}
});

// Debug helper displays JS object contents
ahoy.var_dump = function(obj, alert) {
    var out = '';
    for (var i in obj) {
        out += i + ": " + obj[i] + "\n";
    }

    if (alert)
    	alert(out);
    else 
	    jQuery('<pre />').html(out).appendTo(jQuery('body'));
    
};

// Calculate size of object/assoc array
ahoy.object_length = function(obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) size++;
    }
    return size;
};

(function($) {
/**
 * Runs functions given in arguments in series, each functions passing their results to the next one.
 * Return jQuery Deferred object.
 *
 * @example
 * $.waterfall(
 *    function() { return $.ajax({url : first_url}) },
 *    function() { return $.ajax({url : second_url}) },
 *    function() { return $.ajax({url : another_url}) }
 *).fail(function() {
 *    console.log(arguments)
 *).done(function() {
 *    console.log(arguments)
 *})
 *
 * @example2
 * event_chain = [];
 * event_chain.push(function() { var deferred = $.Deferred(); deferred.resolve(); return deferred; });
 * $.waterfall.apply(this, event_chain).fail(function(){}).done(function(){});
 * 
 * @author Dmitry (dio) Levashov, dio@std42.ru
 * @return jQuery.Deferred
 */
$.waterfall = function() {
	var steps   = [],
		dfrd    = $.Deferred(),
		pointer = 0;

	$.each(arguments, function(i, a) {
		steps.push(function() {
			var args = [].slice.apply(arguments), d;

			if (typeof(a) == 'function') {
				if (!((d = a.apply(null, args)) && d.promise)) {
					d = $.Deferred()[d === false ? 'reject' : 'resolve'](d);
				}
			} else if (a && a.promise) {
				d = a;
			} else {
				d = $.Deferred()[a === false ? 'reject' : 'resolve'](a);
			}

			d.fail(function() {
				dfrd.reject.apply(dfrd, [].slice.apply(arguments));
			})
			.done(function(data) {
				pointer++;
				args.push(data);

				pointer == steps.length
					? dfrd.resolve.apply(dfrd, args)
					: steps[pointer].apply(null, args);
			});
		});
	});

	steps.length ? steps[0]() : dfrd.resolve();

	return dfrd;
}

})(jQuery);