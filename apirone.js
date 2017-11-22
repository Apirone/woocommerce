if (window.jQuery) { 
	function apirone_query(){

			var getUrlParameter = function getUrlParameter(sParam) {
    var sPageURL = decodeURIComponent(window.location.search.substring(1)),
        sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : sParameterName[1];
        }
    }
};
	var key = getUrlParameter("key");
	var order = getUrlParameter("order");

	get_query='/?wc-api=check_payment&key='+key+'&order='+order;
	jQuery.ajax({
    url: get_query,             // указываем URL и
    dataType : "text",                     // тип загружаемых данных
    success: function (data, textStatus) { // вешаем свой обработчик на функцию success
        //console.log(data);
        jQuery( ".apirone_result" ).html('<strong>'+data+'</strong>');
        if(jQuery(".response-message-text").text() == '*ok*'){
        }
    } ,
    error: function(xhr, ajaxOptions, thrownError){
      jQuery( ".apirone_result" ).html( '<strong>Waiting for payment...</strong>' );
    }
	});
	}
	setInterval(apirone_query, 5000);
}