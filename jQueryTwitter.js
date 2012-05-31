jQuery.phantomTwitter = { };
jQuery.phantomTwitter.login = function(email, password){
	 jQuery("input[name='username']").val( email );
	 jQuery("input[name='password']").val( password );
	 jQuery("input[name='password']").parents("form").submit();
};

jQuery.phantomTwitter.fullSiteLogin = function(email, password){
	jQuery("input[name='session[username_or_email]']").val( email );
	jQuery("input[name='session[password]']").val( password );
	jQuery(".signin:last").submit();
};

jQuery.phantomTwitter.takeLoggedInAction = function(action, params){
	
	if( action == "tweet" ){
		
	}
	
	if( action == "confirmEmail" ){
		window.location = params.url;
		jQuery("title").text("0");
	}
	
};