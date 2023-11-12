(function($){
    $(document).ready(function(){
        var $secret_input = $('input[type="password"]');
        $secret_input.next().click(function(e){
            e.preventDefault();
            var $the_input = $(this).prev();
            if($the_input.attr('type') == 'text'){
                $the_input.attr('type', 'password');
            }else{
                $the_input.attr('type', 'text');
            }
        });
    });
})(jQuery);