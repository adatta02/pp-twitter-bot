jQuery.phantomTwitter = { };
jQuery.phantomTwitter.login = function(email, password){
	 jQuery("input[name='username']").val( email );
	 jQuery("input[name='password']").val( password );
	 jQuery("input[name='password']").parents("form").submit();
};

function phantomLogin( email, password ){
	 jQuery("input[name='username']").val( email );
	 jQuery("input[name='password']").val( password );
	 jQuery("input[name='password']").parents("form").submit();
}