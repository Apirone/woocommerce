if (window.jQuery) { 
	function apirone_query(){

			var abfgetUrlParameter = function abfgetUrlParameter(sParam) {
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
	var key = abfgetUrlParameter("key");
	var order = abfgetUrlParameter("order");
    if (key != undefined && order != undefined) {
	abf_get_query='/?wc-api=check_payment&key='+key+'&order='+order;
	jQuery.ajax({
    url: abf_get_query,             // указываем URL и
    dataType : "text",                     // тип загружаемых данных
    success: function (data, textStatus) { // вешаем свой обработчик на функцию success
        //console.log(data);
        jQuery( ".apirone_result" ).html('<h4>'+data+'</h4>');
    } ,
    error: function(xhr, ajaxOptions, thrownError){
      jQuery( ".apirone_result" ).html( '<h4>Waiting for payment...</h4>' );
    }
	});
	}
    }
	setInterval(apirone_query, 5000);
}