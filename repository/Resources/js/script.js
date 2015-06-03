/*
Lp digital - v1.2 June2015
Backbee Demo
required: jquery
*/


$(document).ready(function() {

  //init SHORTCUTS
  $("#access-shortcuts-wrapper>ul").initShortcuts();

    //Placeholder Fix (no Modernizr)
  $('.lt-ie10 [placeholder]').focus(function() {
    var input = $(this);
    if (input.val() == input.attr('placeholder')) {
    input.val('');
    input.removeClass('placeholder');
    }
  }).blur(function() {
    var input = $(this);
    if (input.val() == '' || input.val() == input.attr('placeholder')) {
    input.addClass('placeholder');
    input.val(input.attr('placeholder'));
    }
  }).blur();
  $('.lt-ie10 [placeholder]').parents('form').submit(function() {
    $(this).find('.lt-ie10 [placeholder]').each(function() {
    var input = $(this);
    if (input.val() == input.attr('placeholder')) {
      input.val('');
    }
    });
  });

  //Printable version
  $('.btn-printer').on('click', function(e) {
      e.preventDefault(); window.print();
  });

  //Video RWD
  $(".content-video iframe").resizeEmbed();

  //Smooth scroll & and positionning anchor under sticky header
  $('a[href*=#]:not([href=#]):not([data-toggle])').click(function() {
    var stickyHeight = 100;
    if (location.pathname.replace(/^\//,'') == this.pathname.replace(/^\//,'') && location.hostname == this.hostname) {
      var target = $(this.hash);
      target = target.length ? target : $('[name=' + this.hash.slice(1) +']');
      if (target.length) {
        $('html,body').animate({
          scrollTop: target.offset().top-stickyHeight //50px header affix
        }, 1000);
      }
    }
  });

//END ready
});

// Window Load
$(window).load(function(){
  //owl - company
  var owlCompany = $('.owl-fw').owlCarousel({
    pagination: true,
    itemsCustom: [[0,1]],
    navigation: false,
    autoHeight: true,
    navigation: true,
    navigationText: ["<i class=\"fa fa-angle-left\"></i>","<i class=\"fa fa-angle-right\"></i>"]
  });

  //
  $('.owl-demo').each(function(){
    var owl = $(this);
    var hasPagination = ($(this).hasClass("owl-pagination")) ? true : false;
    owl.owlCarousel({
      pagination: hasPagination,
      navigation: true,
      autoPlay: 6000, //default 5000
      autoHeight: true,
      itemsCustom: [[0, 2], [768, 4]],
      navigationText: ["<i class=\"fa fa-angle-left\"></i>","<i class=\"fa fa-angle-right\"></i>"],
      afterInit : function(){
        owl.removeClass('slider-loader');
      }
    });
  });
  $('.owl-slider').each(function(){
    var owl = $(this);
    var hasPagination = ($(this).hasClass("owl-pagination")) ? true : false;
    owl.owlCarousel({
      pagination: hasPagination,
      navigation: true,
      autoPlay: 6000, //default 5000
      autoHeight: true,
      navigationText: ["<i class=\"fa fa-angle-left\"></i>","<i class=\"fa fa-angle-right\"></i>"],
      afterInit : function(){
        owl.removeClass('slider-loader');
      }
    });
  });
  //

});
// End window load

$(".follow-link").on("click",function(e){
  var _self = $(this);
  var follow = _self.find(_self.data("follow"));
  if(!follow.is(e.target)){
    window.location.href = follow.attr("href");
  }
});

$(".follow-link .btn-info").on("click",function(e){
  e.preventDefault;
  console.log("click",e);
});


//initShortcuts
$.fn.initShortcuts = function(options) {

  var obj = $(this);
  obj.find('a').focus(function(e) {obj.css('height', 'auto'); });
  obj.find('a').blur(function(e) {obj.css('height', '0px'); });

  return this;
};


//FN Video RWD
$.fn.resizeEmbed = function(options) {
    var defaults = {
    };
    var options = $.extend(defaults, options);
    var obj = $(this);

    obj.each(function() {
     var newWidth = $(this).parent().width();
     $(this)
      // jQuery .data does not work on object/embed elements
      .attr('data-aspectRatio', this.height / this.width)
      .removeAttr('height')
      .removeAttr('width')
      .width(newWidth)
      .height(newWidth * $(this).attr('data-aspectRatio'));
    });

    $(window).on("resize",function() {
       obj.each(function() {
        var newWidth = $(this).parent().width();
        $(this)
          .width(newWidth)
          .height(newWidth * $(this).attr('data-aspectRatio'));
       });
    });

    return this;
};