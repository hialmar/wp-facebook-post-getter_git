jQuery( document ).ready( function( $ )
{

  $.ajaxSetup({ cache: true });
  console.log('download FB api');
  $.getScript('https://connect.facebook.net/en_US/sdk.js', function(){
  	console.log('downloaded FB api');

	  window.fbAsyncInit = function() {
			FB.init({
			  appId: 'YOUR APP ID HERE,
			  version: 'v2.7' // or v2.1, v2.2, v2.3, ...
			});
			console.log('init FB api');
			console.log('get login FB api');
			FB.getLoginStatus(function(response) {
				console.log('got FB status');
			  if (response.status === 'connected') {
				console.log('connected');
				var accessToken = response.authResponse.accessToken;

				console.log(accessToken);

				$("#wpfpg_facebook_key").val(accessToken);
			  }  else {
				  console.log('not connected, trying to log in');
				FB.login(function(response) {
					console.log('connected');
					var accessToken = response.authResponse.accessToken;

					console.log(accessToken);

					$("#wpfpg_facebook_key").val(accessToken);
				}, {scope: 'user_photos,user_posts'});
			  }
			}, true );

  		}
  });


	$("#wpfpg_datepicker_since").datepicker({ dateFormat: 'dd MM yy' });

	$("#wpfpg_datepicker_until").datepicker({ dateFormat: 'dd MM yy' });
	

	$( ".new_line_number" ).linedtextarea({
		selectedLine: 1
	});

	$( "#wpfpg_new_post_category" ).select2();

	$( "#wpfpg_new_post_type" ).change( function( event )
	{
		if ( $( this ).val() !== 'post' )
		{
			$( "#wpfpg_new_post_category" ).prop( 'disabled', 'disabled' );
		}
		else
		{
			$( "#wpfpg_new_post_category" ).removeAttr( 'disabled' );
		}
	});
});