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

var loadedPagesIndex = 0;

if( configObj.action == "confirmEmail" ){
	
	page.settings.userAgent = "Mozilla/5.0 (Windows NT 5.1; rv:12.0) Gecko/20100101 Firefox/12.0";
	page.open("https://twitter.com/", function(status){
		
		var isLoaded = page.injectJs( "jquery.min.js" );
		var isClientLoaded = page.injectJs( "jQueryTwitter.js" );
		var isConfigLoaded = page.injectJs( system.args[1] );
		var res = null;
		
		if( !isLoaded || !isClientLoaded || !isConfigLoaded ){
			console.log("FATAL: Could not load local JS scripts!");
		}
		
		var isLoggedIn = page.evaluate(function(){
			return jQuery(".logged-out").length == 1 ? false : true;
		});
		
		var isEmailVerified = page.evaluate( function(){
			return jQuery(".verify-email-banner").length == 0 ? true : false;
		});
		
		console.log("Logged in? " + isLoggedIn);
		
		console.log("Is verified? " + isEmailVerified);
		
		if( !isLoggedIn ){
			res = page.evaluate( function(){
				jQuery.phantomTwitter.fullSiteLogin( phantomConfig.email, phantomConfig.password );
				return jQuery("title").text();
			});
			console.log( "TITLE IS: " + res );
			loadedPagesIndex += 1;
		}else{
			
			// console.log( page.content );
			
			if( loadedPagesIndex < 2 ){
				console.log( configObj.link );
				page.open( configObj.link );
				loadedPagesIndex += 1;
			}else{
				console.log( "Exiting!" );
				phantom.exit();
			}
			
		}
		
	});
	
}else{
	
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
	
}