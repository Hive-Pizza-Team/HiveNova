/* Page filter toggles (buildings/research/shipyard/techtree) */
$(function(){
            $("#btn1").on('click',function() {
                $(".infoso").hide();
                $(".infos").show();
$("#btn2, #btn3").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });
 $(function(){
            $("#btn2").on('click',function() {
                $(".infos").toggle();
                $(".infoso").show();
$("#btn1, #btn3").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });
 $(function(){
            $("#btn3").on('click',function() {
                $(".infos").show();
                $(".infoso").show();
$("#btn2, #btn1").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });

         $(function(){
            $("#gl1").on('click',function() {
                $(".planetb").hide();
                $(".planetb1").show();
$("#gl2, #gl3").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });
 $(function(){
            $("#gl2").on('click',function() {
                $(".planetb1").toggle();
                $(".planetb").show();
$("#gl1, #gl3").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });
 $(function(){
            $("#gl3").on('click',function() {
                $(".planetb").show();
                $(".planetb1").show();
$("#gl2, #gl1").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });

 $(function(){
            $("#lab1").on('click',function() {
                $(".infos").hide();
                $("#t108, #t113, #t114, #t123, #t124").show();
$("#lab2, #lab3, #lab4, #lab5").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });
 $(function(){
            $("#lab2").on('click',function() {
                $(".infos").hide();
                $("#t109, #t106, #t110, #t111, #t120, #t121, #t122, #t199").show();
$("#lab1, #lab3, #lab4, #lab5").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });
 $(function(){
            $("#lab3").on('click',function() {
                $(".infos").hide();
                $("#t114, #t115, #t117, #t118").show();
$("#lab2, #lab1, #lab4, #lab5").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });
 $(function(){
            $("#lab4").on('click',function() {
                $(".infos").hide();
                $("#t131, #t132, #t133").show();
$("#lab2, #lab1, #lab3, #lab5").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });
 $(function(){
            $("#lab5").on('click',function() {
                $(".infos").show();
                
$("#lab2, #lab1, #lab4, #lab3").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });

        $(function(){
            $("#ship1").on('click',function() {
                $(".infos").hide();
                $("#s202, #s203, #s208, #s209, #s212").show();
$("#ship2, #ship3").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });
                $(function(){
            $("#ship2").on('click',function() {
                $(".infos").hide();
                $("#s204, #s205, #s206, #s207, #s210, #s211, #s213, #s214").show();
$("#ship1, #ship3").removeClass("selected");
 $(function(){
            $("#ship3").on('click',function() {
                $(".infos").show();
                
$("#ship1, #ship2").removeClass("selected");
	
$(this).addClass("selected");
            }); 
        });


	
$(this).addClass("selected");
            }); 
        });

$(function(){
            $("#0h").on('click',function() {
                for(i=1;i<=99;i++)
                $("#h"+i).hide();
$("#0s").show();
$("#0h").hide();
                
            }); 
        });

      $(function(){
            $("#0s").on('click',function() {
                for(i=1;i<=99;i++)
                $("#h"+i).show();
$("#0h").show();
$("#0s").hide();
                
            }); 
        });

  $(function(){
            $("#100h").on('click',function() {
for(i=101;i<=199;i++)
                $("#h"+i).hide();
$("#100s").show();
$("#100h").hide();
                
            }); 
        });

  $(function(){
            $("#100s").on('click',function() {
for(i=101;i<=199;i++)
                $("#h"+i).show();
$("#100h").show();
$("#100s").hide();
                
            }); 
        });

  $(function(){

            $("#200h").on('click',function() {
for(i=201;i<=299;i++)
                $("#h"+i).hide();
$("#200s").show();
$("#200h").hide();
                
            }); 
        });
 $(function(){

            $("#200s").on('click',function() {
for(i=201;i<=299;i++)
                $("#h"+i).show();
$("#200s").hide();
$("#200h").show();
                
            }); 
        });
  $(function(){

            $("#400h").on('click',function() {
for(i=401;i<=499;i++)
                $("#h"+i).hide();
$("#400s").show();
$("#400h").hide();
                
            }); 
        });
 $(function(){

            $("#400s").on('click',function() {
for(i=401;i<=499;i++)
                $("#h"+i).show();
$("#400s").hide();
$("#400h").show();
                
            }); 
        });
  $(function(){

            $("#500h").on('click',function() {
for(i=501;i<=599;i++)
                $("#h"+i).hide();
$("#500s").show();
$("#500h").hide();
                
            }); 
        });
 $(function(){

            $("#500s").on('click',function() {
for(i=501;i<=599;i++)
                $("#h"+i).show();
$("#500s").hide();
$("#500h").show();
                
            }); 
        });
  $(function(){

            $("#600h").on('click',function() {
for(i=601;i<=699;i++)
                $("#h"+i).hide();
$("#600s").show();
$("#600h").hide();
                
            }); 
        });
 $(function(){

            $("#600s").on('click',function() {
for(i=601;i<=699;i++)
                $("#h"+i).show();
$("#600s").hide();
$("#600h").show();
                
            }); 
        });
