(function($){
    $(document).ready(function(){
        var $wh_secret = $(".webhook_secret");
        $wh_secret.next().click(function(e){
            e.preventDefault();
            if($wh_secret.attr("type") == "text"){
                $wh_secret.attr("type", "password");
            }else{
                $wh_secret.attr("type", "text");
            }
        });
    });
})(jQuery);