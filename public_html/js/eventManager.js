/* EVENT
*/
function Event(name, desc) {
	if (typeof(name) != "string" || name == "") {
		throw(new Error("Invalid Argument","Expected a non-empty string for name.  Got '"+name+"'."));
	}
	if ((typeof(desc) != "undefined" && typeof(desc) != "string" && desc != null) || typeof(desc) == "undefined") {
		throw(new Error("Invalid Argument "+typeof(desc),"Expected a non-empty string for description.  Got '"+desc+"' instead."));
	}

	var name = name; // private
	this.getName = function() {
		return name;
	}
	
	var desc = desc; // private
	this.getDesc = function() {
		return desc;
	}
}
// should subscriptions be 'filled' ratehr than events 'occuring'?
Event.prototype.occurred = false;
Event.prototype.equals = function(otherEvent) {
	if (this.getName() == otherEvent.getName() && (this.getDesc() == null || otherEvent.getDesc() == null || this.getDesc() == otherEvent.getDesc())) {
		return true;
	}
	return false;
}
Event.prototype.stringifyPrep = function() {
	//create a temporary event so that changes don't actually effect the existing object
	// make the two events identical
	var tempEvent = new Event(this.getName(), this.getDesc());
	tempEvent.occurred = this.occurred;

	// make private vars public
	tempEvent.name = tempEvent.getName();
	tempEvent.desc = tempEvent.getDesc();
	
	// return a JSON formatted string that contains the private vars
	return tempEvent;
}

/* SUBSCRIPTION
*/
function Subscription(events, callback, persist) {
	if (events == null || (events.constructor != Array && events.constructor != Event)) {
		throw(new Error("Invalid Argument", "Expected a single event or an array of events for events.  Got '"+events+"' instead."));
	} else if (events.constructor != Array && events.constructor == Event) {
		events = [events];
	}
	
	if (callback == null || typeof(callback) != "function") {
		throw(new Error("Invalid Argument", "Expected a function for callback.  Got '"+callback+"' instead."));
	}
	
	if ((typeof(persist) != "undefined" && typeof(persist) != "boolean" && persist != null) || persist == "undefined") {
		throw(new Error("Invalid Argument", "Expected a boolean for callback.  Got '"+persist+"' instead."));
	}

	var events = events; // private, read only
	this.getEvents = function() {
		return events;
	}
	
	var callback = callback; // private, only this subscription can fire the callback method
	this.fireCallback = function(data) {
		callback(this.getEvents(), data);
	}
	
	var persist = persist;
	this.persists = function() {
		return persist;
	}
	
	this.reset = function() {
		var events = this.getEvents();
		for (var i = 0; i < events.length; i++) {
			events[i].occurred = false;
		}
	}
}
Subscription.prototype.checkFulfilled = function() {
	var events = this.getEvents();
	for (var i = 0; i < events.length; i++) {
		if (!events[i].occurred) {
			return false;
		}
	}
	return true; // fireCallback() here instead of in EventManager.alertSubscription?
}
Subscription.prototype.stringifyPrep = function() {
	//create a temporary event so that changes don't actually effect the existing object
	// make the two events identical
	// ignore callback... not shown in JSON.stringify anyways
	var tempSubscription = new Subscription(this.getEvents(), function() {}, null, true);

	// make private vars public
	tempEvents = new Array();
	for (var i = 0; i < tempSubscription.getEvents().length; i++) {
		tempEvents.push(tempSubscription.getEvents()[i].stringifyPrep());
	}
	tempSubscription.events = tempEvents;
	
	// return a JSON formatted string that contains the private vars
	return tempSubscription;
}

/* EVENT MANAGER
*/
function EventManager() {
	var subscriptions = new Array(); // private
	this.getSubscriptions = function() {
		return subscriptions;
	}
	this.subscribe = function(subscription) {
		subscriptions.push(subscription);
	}
	
	var broadcasts = new Array(); // private
	this.getBroadcasts = function() {
		return broadcasts;
	}
	this.broadcast = function(event, data) {
		// should be able to handle array of events
		broadcasts.push({event:event, data:data});
		this.fillSubscriptions({event:event, data:data});
	}
}
EventManager.prototype.fillSubscriptions = function(broadcast) {
	for(var i = 0; i < this.getSubscriptions().length; i++) {
    	var subscription = this.getSubscriptions()[i];
    	if (!subscription.checkFulfilled()) {
    		for (var j = 0; j < subscription.getEvents().length; j++) {
    			var subscriptionEvent = subscription.getEvents()[j];
    			if (broadcast.event.equals(subscriptionEvent)) {
    				subscriptionEvent.occurred = true;
    			}
    			if (subscription.checkFulfilled()) {
    				subscription.fireCallback(broadcast.data); // do this in subscription.checkFulfilled()?
    				if (subscription.persists()) {
    					subscription.reset();
    				}
    			}
    		}
    	}
    }
}
EventManager.prototype.stringifyPrep = function() {
	//create a temporary event so that changes don't actually effect the existing object
	// make the two events identical
	var tempEM = new EventManager();

	// make private vars public
	var tempSubscriptions = new Array();
	for (var i = 0; i < this.getSubscriptions().length; i++) {
		tempSubscriptions.push(this.getSubscriptions()[i].stringifyPrep());
	}
	tempEM.subscriptions = tempSubscriptions;

	var tempBroadcasts = new Array();
	for (var i = 0; i < this.getBroadcasts().length; i++) {
		tempBroadcasts.push(this.getBroadcasts()[i].stringifyPrep());
	}
	tempEM.broadcasts = tempBroadcasts;
	
	// return a JSON formatted string that contains the private vars
	return tempEM;
}