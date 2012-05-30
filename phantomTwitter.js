var system = require('system');
var fs = require('fs');
var page = require('webpage').create();

if (system.args.length === 1) {
    console.log('FATAL: You must specify a config file.');
    phantom.exit();
}

if( !fs.isFile(system.args[1]) ){
	console.log("FATAL: Config file does not exist.");
	phantom.exit();
}

var configFile = fs.read( system.args[1] ).replace("var phantomConfig = ", "");
configFile = configFile.replace(";", "");
var configObj = JSON.parse( configFile );

page.settings.userAgent = "Mozilla/5.0 (BlackBerry; U; BlackBerry 9700; pt) AppleWebKit/534.8+ (KHTML, like Gecko) Version/6.0.0.546 Mobile Safari/534.8+";

page.open('https://mobile.twitter.com/session/new', function(status) {
	
	var isLoaded = page.injectJs( "jquery.min.js" );
	var isConfigLoaded = page.injectJs( "jQueryTwitter.js" );
	var isClientLoaded = page.injectJs( system.args[1] );
	var res = null;
	
	if( !isLoaded || !isConfigLoaded || !isClientLoaded ){
		console.log("FATAL: Could not load local JS scripts!");
	}
	
	var isLoggedIn = page.evaluate(function(){
		return jQuery("input[value='Sign out']").length > 0 ? true : false;
	});
	
	console.log("Logged in? " + isLoggedIn);
	
	if( !isLoggedIn ){
		res = page.evaluate( function(){
			jQuery.phantomTwitter.login( phantomConfig.email, phantomConfig.password );
			return jQuery("title").text();
		});
		console.log( res );
		return true;
	}
		
	console.log( "Trying " + configObj.action );
	
	if( configObj.action == "confirmEmail" ){
		
		page.open( configObj.link, function(status){
			console.log("loaded " + configObj.link);
			console.log( page.content );
			// phantom.exit();
		});
		
		return true;
	}
	
	res = page.evaluate( function(){
		jQuery.phantomTwitter.takeLoggedInAction( phantomConfig.action, phantomConfig );
		return jQuery("title").text();
	});
	
	if( res == 0 ){
		console.log("Completed bot run. Exiting!");
		phantom.exit();
	}
	
});