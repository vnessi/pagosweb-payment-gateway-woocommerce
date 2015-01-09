 
jQuery(document).ready(function(){
    jQuery("#genux_cardtype").change(function() {
        var parent = jQuery(this).val(); //get option value from parent 
        
        switch(parent){ //using switch compare selected option and populate child
              case '4':
                jQuery("#cuotas_tr").hide();
                jQuery("#cedula_tr").show();
                //jQuery("#cedula_tr").append('<td><label for="pagosweb_cedula">Cedula: <span class="required">*</span></label></td>');
                //jQuery("#cedula_tr").append('<td><input type="text" name="pagosweb_cedula" id="pagosweb_cedula" placeholder="1234567-0"></td>');  
                break; 
            default: //default child option is blank
                jQuery("#cedula_tr").hide();
                jQuery("#cuotas_tr").show();
                
                break;
           }
    });

    jQuery("#pagosweb_cedula").keyup(function(){
        jQuery("#pagosweb_cedula").validate_ci();
    });
});
