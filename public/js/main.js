function handle_messages(response){
    if (response.mode !== 'messages'){
        return;
    }
    swal('', response.messages.join('\n'), response.type);
}
function handle_script(response){
    if (response.mode !== 'script'){
        return;
    }
    $('body').append(response.html);
}
function throw_error(){    
    swal('', 'Hubo un error. Intente nuevamente', 'error');
}
function clear_inputs($form){    
    $form.find('[type="text"],[type="password"]').val('');
    $form.find('[type="checkbox"]').prop('checked', false);
    $form.find('select option').prop('selected', false);
}
function do_form_ajax($form){
    var $submit = $form.find('[type="submit"]');
    
    $form.addClass('submitting');

    if ($submit.length > 0) {
        $submit.data('html', $submit.html());
        $submit.html('<i class="fa fa-spinner fa-spin"></i>');
    }
    
    $.ajax({
        url: $form.attr('action'),
        type: $form.attr('method'),
        data: $form.serializeArray(),
        success: function (data) {
            switch (data.mode) {
                case 'home':
                    clear_inputs($form);
                    window.location = '/';
                    break;
                case 'reload':
                    clear_inputs($form);
                    window.location.reload();
                    break;
                case 'redirect':                    
                    clear_inputs($form);                    
                    window.location = data.page;
                    break;
                case 'messages':
                    handle_messages(data);
                    break;
                case 'script':
                    handle_script(data);
                    break;
            }
        },
        error: function (data) {
            throw_error();
        },
        complete: function (data) {
            if ($submit.length > 0) {
                $submit.html($submit.data('html'));
                $submit.removeAttr('data-html');
            }
            $form.removeClass('submitting');
            console.log(data);
        }
    });
}
function handle_process(event){
    // to do
}
function upload_image($image_file, success, complete){
    var file = $image_file[0].files[0];
    var name = $image_file.attr('name');
    var form = new FormData();
    form.append(name, file);
    $.ajax({
        url: '/ajax_upload_image',
        type: "POST",
        xhr: function() {  // custom xhr
            var myXhr = $.ajaxSettings.xhr();
            if (myXhr.upload) { // check if upload property exists
                myXhr.upload.addEventListener('progress', handle_process, false); // for handling the progress of the upload
            }
            return myXhr;
        },
        data: form,
        /*enctype: 'multipart/form-data',*/
        cache: false,
        contentType: false,  //must
        processData: false,  //must
        success: function (data)
        {
            switch (data.mode) {
                case 'image_uploaded':
                    success(data);
                    break;
                case 'messages':
                    handle_messages(data);
                    break;
            }
        },
        error: function(data){
            throw_error();
        },
        complete: function(){
            complete();
        }
    });
}
$(document).ready(function(){
   
        $('.thumb-container .thumb-file').change(function(){
        var $file = $(this);
        var $thumb = $file.parents('.thumb-container');
        var $image_id = $thumb.find('.image-id');
        $thumb.addClass('loading');
        upload_image($file, function(data){    
            $thumb.find('img').attr('src', data.path);
            $image_id.val(data.image_id).trigger('change');
            if ($thumb.find('form').length > 0){
                $thumb.find('form').submit();
            }
        }, function(){                  
            $thumb.removeClass('loading'); 
        });
    });
    $('form.clear-inputs').submit(function(e){
        var $form = $(this);
        $form.find('input, select, textarea').each(function(){
            var $input = $(this);
            if ($input.val() === ''){
                $input.prop('disabled', true);
            }
        });
    });
    $('form.ajax-form').submit(function(e){
        e.preventDefault();
        
        var $form = $(this);
        
        if ($form.hasClass('submitting')){
            return;
        }
        
        if ($form.hasClass('password')){
            swal({      
                title: "Atención",
                text: "Ingrese su contraseña",   
                type: "input",  
                inputType: "password", 
                showCancelButton: true,    
                confirmButtonText: "Si",   
                cancelButtonText: "No", 
                closeOnConfirm: true
            }, function(typedPassword){
                if (typedPassword){
                    $form.append('<input type="hidden" name="password" value="'+typedPassword+'"/>');
                    do_form_ajax($form);
                }
            });
        }
        else if ($form.hasClass('confirm')){
            swal({      
                title: '',
                text: "¿Estás seguro?",   
                type: "info",   
                showCancelButton: true,    
                confirmButtonText: "Si",   
                cancelButtonText: "No", 
                closeOnConfirm: true 
            }, function(isConfirm){
                if (isConfirm){
                    do_form_ajax($form);
                }
            });
        }        
        else{
            do_form_ajax($form);            
        }
    });

    $('#log-in, #profile-h').click(function(){
        $('.signup-box').addClass('hidden');
        $('.login-box').removeClass('hidden');
        $('#torneos-menu-shown').addClass('hidden');
        $('#menu-main-shown').addClass('hidden');

    });

    $('.login-box img, .signup-box img').click(function(){
        $('.signup-box').addClass('hidden');
        $('.login-box').addClass('hidden');
    });

    $('#sign-up').click(function(){
        $('.login-box').addClass('hidden');
        $('.signup-box').removeClass('hidden');
        $('#torneos-menu-shown').addClass('hidden');
        $('#menu-main-shown').addClass('hidden');
    });

    $('.signup-box img').click(function(){
        $('.sign-up-box').addClass('hidden');
    });

   $('#menu-main').hover(function(e){
       e.preventDefault();
       $('#menu-main-shown').removeClass('hidden');
       $(this).addClass('active-menu');
        $('#torneos-menu-shown').addClass('hidden');
        $('.signup-box').addClass('hidden');
        $('.login-box').addClass('hidden');
        

    });


   $('#menu-torneos').hover(function(e){
       e.preventDefault();
       $('#torneos-menu-shown').removeClass('hidden');
       $(this).addClass('active-menu');
        $('#menu-main-shown').addClass('hidden');
        $('.signup-box').addClass('hidden');
        $('.login-box').addClass('hidden');
    });


    $('main').hover(function(){
        if (!$('#torneos-menu-shown').hasClass('hidden')){
            $('#torneos-menu-shown').addClass('hidden');
        }
        if (!$('#menu-main-shown').hasClass('hidden')){
            $('#menu-main-shown').addClass('hidden');
        }    
        if (!$('.login-box').hasClass('hidden')){
            $('.login-box').addClass('hidden');
        }   
        if (!$('.signup-box').hasClass('hidden')){
            $('.signup-box').addClass('hidden');
        }           
    });

  

    $('.box .box-collapse-btn').click(function(e){
        e.preventDefault();
        var $box = $(this).parents('.box');
        if ($box.hasClass('collapsed')){
            $box.removeClass('collapsed')
        }
        else{
            $box.addClass('collapsed')
        }
    });
    $('.report').click(function(e){
       e.preventDefault();
       swal('No está implementado aún');
    });
    $('input[type="datepicker"]').datepicker({
        format: 'dd/mm/yyyy',
        language: 'es'
    });
    $('.custom-radio').click(function(e){
        e.preventDefault();
        var $this = $(this);
        
        if ($this.hasClass('disabled')){
            return;
        }
        
        var $group = $this.data('group');        
        $('.custom-radio[data-group="'+$group+'"]').removeClass('on').find('input').prop('checked', false);
        $this.addClass('on').find('input').prop('checked', true);
    });
});
