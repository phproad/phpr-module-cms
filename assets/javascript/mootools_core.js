/*
 *
 * MooTools-specific front-end library
 *
 */

/*
 * AJAX extensions
 */

Request.Phpr = new Class({
	Extends: Request.HTML,
	loadIndicatorName: false,
	lockName: false,
	singleUpdateElement: false,

	options: {
		handler: false,
		extraFields: {},
		loadIndicator: {
			show: false,
			hideOnSuccess: true
		},
		lock: true,
		lockName: false,
		evalResponse: true,
		onAfterError: $empty,
		onBeforePost: $empty,
		treeUpdate: false,
		confirm: false,
		preCheckFunction: false,
		postCheckFunction: false,
		prepareFunction: $empty,
		execScriptsOnFailure: true,
		evalScriptsAfterUpdate: false,
		alert: false,
		noLoadingIndicator: false // front-end feature
	},
	
	getRequestDefaults: function()
	{
		return {
			loadIndicator: {
				element: null
			},
			onFailure: this.popupError.bind(this),
			errorHighlight: {
				element: null,
				backgroundFromColor: '#f00',
				backgroundToColor: '#ffffcc'
			}
		};
	},

	initialize: function(options)
	{
		this.parent($merge(this.getRequestDefaults(), options));

		this.setHeader('PHPR-REMOTE-EVENT', 1);
		this.setHeader('PHPR-POSTBACK', 1);
		
		if (this.options.handler)
			this.setHeader('PHPR-EVENT-HANDLER', 'ev{'+this.options.handler+'}');

		this.addEvent('onSuccess', this.updateMultiple.bind(this));
		this.addEvent('onComplete', this.processComplete.bind(this));
	},
	
	post: function(data)
	{
		if (this.options.lock)
		{
			var lockName = this.options.lockName ? this.options.lockName : 'request' + this.options.handler + this.options.url;
			
			if (lockManager.get(lockName))
				return;
		}

		if (this.options.preCheckFunction)
		{
			if (!this.options.preCheckFunction.call())
				return;
		}

		if (this.options.alert)
		{
			alert(this.options.alert);
			return;
		}

		if (this.options.confirm)
		{
			if (!confirm(this.options.confirm))
				return;
		}
		
		if (this.options.postCheckFunction)
		{
			if (!this.options.postCheckFunction.call())
				return;
		}

		if (this.options.prepareFunction)
			this.options.prepareFunction.call();
			
		if (this.options.lock)
		{
			var lockName = this.options.lockName ? this.options.lockName : 'request' + this.options.handler + this.options.url;

			lockManager.set(lockName);
			this.lockName = lockName;
		}

		this.dataObj = data;
		
		if (!this.options.data)
		{
			var dataArr = [];

			switch ($type(this.options.extraFields)){
				case 'element': dataArr.push($(this.options.extraFields).toQueryString()); break;
				case 'object': case 'hash': dataArr.push(Hash.toQueryString(this.options.extraFields));
			}
			
			switch ($type(data)){
				case 'element': dataArr.push($(data).toQueryString()); break;
				case 'object': case 'hash': dataArr.push(Hash.toQueryString(data));
			}
			
			this.options.data = dataArr.join('&');
		}

		if (this.options.loadIndicator.show)
		{
			this.loadIndicatorName = 'request' + new Date().getTime();
			$(this.options.loadIndicator.element).showLoadingIndicator(this.loadIndicatorName, this.options.loadIndicator);
		}
		
		this.fireEvent('beforePost', {});
		
		if (MooTools.version >= "1.3")
			this.parent(this.options.data);
		else
			this.parent();
	},
	
	processComplete: function()
	{
		if (this.options.lock)
			lockManager.remove(this.lockName);
	},
	
	success: function(text)
	{
		var options = this.options, response = this.response;

		response.html = text.phprStripScripts(function(script){

			response.javascript = script;
		});

		if (options.update && options.update != 'multi' && !(/window.location=/.test(response.javascript)) && $(options.update))
		{
			if (this.options.treeUpdate)
			{
				var temp = this.processHTML(response.html);
				response.tree = temp.childNodes;
				if (options.filter) response.tree = response.elements.filter(options.filter);
				
				response.elements = temp.getElements('*');
		 		$(options.update).empty().adopt(response.tree);
			}
			else
	 			$(options.update).set({html: response.html});
		}

		this.fireEvent('beforeScriptEval', {});

		if (options.evalScripts && !options.evalScriptsAfterUpdate) 
			$exec(response.javascript);
		
		this.onSuccess(response.tree, response.elements, response.html, response.javascript);
	},
	
	updateMultiple: function(responseTree, responseElements, responseHtml, responseJavascript)
	{
		this.fireEvent('onResult', [this, responseHtml], 20);

		if (this.options.loadIndicator.hideOnSuccess)
			this.hideLoadIndicator();
			
		var updated_elements = [];

		if (!this.options.update || this.options.update == 'multi')
		{
			this.multiupdateData = new Hash();

			var pattern = />>[^<>]*<</g; 
			var Patches = responseHtml.match(pattern);
			if (!Patches) return;
			for ( var i=0; i < Patches.length; i++ )
			{
				var index = responseHtml.indexOf(Patches[i]) + Patches[i].length;
				var updateHtml = (i < Patches.length-1) ? responseHtml.slice( index, responseHtml.indexOf(Patches[i+1]) ) :
					responseHtml.slice(index);
				var updateId = Patches[i].slice(2, Patches[i].length-2);

				if ( $(updateId) )
				{
					$(updateId).set({html: updateHtml}); 
					updated_elements.push(updateId);
				}
			}
		}

		if (this.options.evalScripts && this.options.evalScriptsAfterUpdate) 
			$exec(this.response.javascript);
			
		$A(updated_elements).each(function(element_id){
			window.fireEvent('onAfterAjaxUpdate', element_id);
		});

		this.fireEvent('onAfterUpdate', [this, responseHtml], 20);
	},

	isSuccess: function(){
		return !this.xhr.responseText.test("@AJAX-ERROR@");
	},
	
	hideLoadIndicator: function()
	{
		if (this.options.loadIndicator.show)
			$(this.options.loadIndicator.element).hideLoadingIndicator(this.loadIndicatorName);
	},

	onFailure: function()
	{
		this.hideLoadIndicator();

		var javascript = null;
		text = this.xhr.responseText.phprStripScripts(function(script){javascript = script;});
		this.fireEvent('complete').fireEvent('failure', {message: text.replace('@AJAX-ERROR@', ''), responseText: text, responseXML: text} );
		
		if (this.options.execScriptsOnFailure)
			$exec(javascript);
			
		this.fireEvent('afterError', {});
	},
	
	popupError: function(xhr)
	{
		alert(xhr.responseText.replace('@AJAX-ERROR@', ''));
	},
	
	highlightError: function(xhr)
	{
		var element = null;

		if (this.options.errorHighlight.element != null)
			element = $(this.options.errorHighlight.element);
		else
		{
			if (this.dataObj && $type(this.dataObj) == 'element')
				element = $(this.dataObj).getElement('.formFlash');
		}

		if (!element)
			return;

		element.innerHTML = '';
		var pElement = new Element('p', {'class': 'error'});
		pElement.innerHTML = xhr.responseText.replace('@AJAX-ERROR@', '');
		pElement.inject(element, 'top');
		pElement.set('morph', {duration: 'long', transition: Fx.Transitions.Sine.easeOut});

		if (this.options.errorHighlight.backgroundFromColor)
		{
			pElement.morph({
				'background-color': [this.options.errorHighlight.backgroundFromColor, 
					this.options.errorHighlight.backgroundToColor]
			});
		}
		
		/*
		 * Re-align popup forms
		 */
		realignPopups();
	}
});

function init_fronted_ajax()
{
	Request.Phpr.implement({
		active_request_num: 0,
		loading_indicator_element: null,
		
		getRequestDefaults: function()
		{
			return {
				onBeforePost: this.frontend_before_ajax_post.bind(this),
				onComplete: this.frontend_after_ajax_post.bind(this),
				onFailure: this.popupError.bind(this),
				execScriptsOnFailure: true
			};
		},
		
		popupError: function(xhr)
		{
			alert(xhr.responseText.replace('@AJAX-ERROR@', '').replace(/(<([^>]+)>)/ig,""));
		},
		
		frontend_before_ajax_post: function()
		{
			if (this.options.noLoadingIndicator)
				return;
			
			this.active_request_num++;
			this.frontend_create_loading_indicator();
		},

		frontend_after_ajax_post: function()
		{
			if (this.options.noLoadingIndicator)
				return;

			this.active_request_num--;
			
			if (this.active_request_num == 0)
				this.frontend_remove_loading_indicator();
		},
		
		frontend_create_loading_indicator: function()
		{
			if (this.loading_indicator_element)
				return;

			this.loading_indicator_element = new Element('p', {'class': 'ajax_loading_indicator'}).inject(document.body, 'top');
			this.loading_indicator_element.innerHTML = '<span>Loading...</span>';
		},
		
		frontend_remove_loading_indicator: function()
		{
			if (this.loading_indicator_element)
				this.loading_indicator_element.destroy();
				
			this.loading_indicator_element = null;
		}
	});
	
	window.addEvent('domready', function(){
		window.fireEvent('frontendready');
	});
}

function popupAjaxError(xhr)
{
	alert(xhr.responseText.replace('@AJAX-ERROR@', '').replace(/(<([^>]+)>)/ig,""));
}

/*
 * Element extensions
 */

Element.implement({
	getForm: function()
	{
		return this.findParent('form');
	},

	findParent: function(tagName)
	{
		var CurrentParent = this;
		while (CurrentParent != null && CurrentParent != document)
		{
			if ($(CurrentParent).get('tag') == tagName)
				return $(CurrentParent);

			CurrentParent = CurrentParent.parentNode;
		}

		return null;
	},

	selectParent: function(selector)
	{
		var CurrentParent = this;
		while (CurrentParent != null && CurrentParent != document)
		{
			if ($(CurrentParent).match(selector))
				return $(CurrentParent);

			CurrentParent = CurrentParent.parentNode;
		}

		return null;
	},
	
	sendPhpr: function(handlerName, options)
	{
		var action = $(this).get('action');
		
		var defaultOptions = {url: action, handler: handlerName, loadIndicator: {element: this}};
		new Request.Phpr($merge(defaultOptions, options)).post(this);
		return false;
	},
	
	sendRequest: function(handlerName, options)
	{
		if (!$type(options))
		 	options = {extraFields: {}};

		if (!$type(options.extraFields))
			options.extraFields = {};
			
		var updateElements = $type(options.update) ? options.update : null;

		options.update = null;
		options.extraFields = $merge(options.extraFields, {
			cms_handler_name: handlerName, 
			cms_update_elements: updateElements});

		return this.sendPhpr('on_handle_request', options);
	},
	
	focusField: function(field)
	{
		var fieldObj = $type(field) == 'string' ? $(field) : field;

		if (fieldObj && !fieldObj.disabled)
		{
			fieldObj.focus();
		}
	},
	
	hide: function()
	{
		this.addClass('hidden');
	},

	show: function()
	{
		this.removeClass('hidden');
	}
});

/*
 * String extensions
 */

String.implement({
	phprStripScripts: function(option){
		var scripts = '';

		var text = this.replace(/<script[^>]*>([^\b]*?)<\/script>/gi, function(){
			scripts += arguments[1] + '\n';
			return '';
		});

		if (option === true) $exec(scripts);
		else if ($type(option) == 'function') option(scripts, text);
		return text;
	},
	
	htmlEscape: function(){
		var value = this.replace("<", "&lt;");
		value = value.replace(">", "&gt;");
		return value.replace('"', "&quot;");
	}
});

/*
 * Async. lock  manager
 */

LockManager = new Class({
	locks: false,

    initialize: function(name){
        this.locks = new Hash();
    },

	set: function(name)
	{
		this.locks.set(name, 1);
	},

	get: function(name)
	{
		return this.locks.has(name);
	},

	remove: function(name)
	{
		this.locks.erase(name);
	}
});

lockManager = new LockManager();

/*
 * Initialization
 */

window.addEvent('domready', init_fronted_ajax);