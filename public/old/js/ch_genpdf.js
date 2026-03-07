(function ($) {
	$.fn.genpdftable = function (spec) {
		//set default option
		var options = $.extend({
				src: '',
				orentation: 'P',// L for landscape
				size: 'A4',// can have A5 A6 etc...
				caller: '',
		},spec);
		if(options.caller == ''){
			var rt = "<a class='pull-right DTTT_button btn btn-white btn-info btn-bold'  id='ch_gen_pdfreport' data-tooltip='tooltip' data-placement='top' title='Export to pdf' style='cursor:pointer'"+
					"<span><i class='fa fa-file-pdf-o red'></i> </span>"+
					"</a>";
			$(".tableTools-container").append(rt);
			caller = $("#ch_gen_pdfreport");
		}else{
			caller=$(options.caller);
		}
		var prd = $(this);
		var baseUrl = options.src.substring(0, 19);//options.src.lastIndexOf('/')
		caller.click(function(){
			$('#pdfmodal').remove();
			var rc =	"<div id='pdfmodal' class='modal fade' tabindex='-1' role='dialog' aria-labelledby='myModalLabel' aria-hidden='true'>"+
								"<div class='modal-dialog' style='width:90%'>"+
								"<div class='modal-content text-center'>"+
								"<span data-dismiss='modal' aria-label='Close' style=' cursor:pointer; position:absolute; top:-12px; right:-10px;'><span class='white fa fa-times-circle fa-2x'></span></span>"+
								"<img id='loadingMessage' src='"+baseUrl+"/images/loading.gif'>"+
								"<iframe src='"+options.src+"' width='100%'  frameborder='0' style='overflow:hidden; min-height:1200px; margin-bottom:-5px;' scrolling='no' id='iframe' name='iframe'> </iframe>"+
								"</div>"+
								"</div>"+
								"</div>"+
								"<form id='report_form' method='post' action='"+options.src+"' target='iframe'>"+
								"<input type='hidden' id='dom' name='dom' value='Could not get content...'/>"+
								"<input type='hidden' id='title' name='title' value='report' />"+
								"<input type='hidden' id='orentation' name='orentation' value='"+options.orentation+"' />"+
								"<input type='hidden' id='size' name='size' value='"+options.size+"' />"+
								"</form>";
			var reportdom =$('<div>').append(prd.clone(true));
			
			title =($('div.table-header').length == 1)? $('div.table-header').text():$('div#breadcrumbs ul.breadcrumb li:last').text();
			title = '<div class="col-lg-12" style="margin-top:-20px;"><h3 style="text-align:center;">'+title+'</h3></div>';
			$.when($('body').append(rc)).done(function(){
				$.when(
					reportdom.find('table').each(function(){
						$(this).find('th.no-printpdf').each(function(){
							var index = parseInt(this.cellIndex)+1;
							reportdom.find('th:nth-child('+index+'), td:nth-child('+index+')').remove();
						});
						$(this).removeClass().removeAttr('style');
						$(this).addClass('table table-striped table-bordered table-hover table-condensed');
					}),
					reportdom.find('thead tr, th, td').each(function(){
						$(this).removeClass().removeAttr('style');
					}),
					reportdom.find('th div').each(function(){
						$(this).contents().unwrap();
					}),
					$('form#report_form #dom').val(title+reportdom.html()),
					$('form#report_form #title').val($(document).find("title").text())
				).done(function(){	
					$.when($('form#report_form').submit(), $('form#report_form').submit(function(event){
						event.preventDefault();
					})).done(function(){			
						$('#pdfmodal').modal({show:true});
					});	
				});
				$('#iframe').load(function () {
					$('#loadingMessage').css('display', 'none');
				});
			});
		})
	}
	$.fn.genpdfpage = function (spec) {
		//set default option 
		var options = $.extend({
				content:'.page-content',
				src: '',
				orentation: 'P',// L for landscape
				size: 'A4',// can have A5 A6 etc...
		},spec);
		var baseUrl = options.src.substring(0, options.src.lastIndexOf('/'));
		$(this).click(function(){
			$('#pdfmodal').remove();
			var rc =	"<div id='pdfmodal' class='modal fade' tabindex='-1' role='dialog' aria-labelledby='myModalLabel' aria-hidden='true'>"+
								"<div class='modal-dialog' style='width:90%'>"+
								"<div class='modal-content text-center'>"+
								"<span data-dismiss='modal' aria-label='Close' style=' cursor:pointer; position:absolute; top:-12px; right:-10px;'><span class='white fa fa-times-circle fa-2x'></span></span>"+
								"<img id='loadingMessage' src='"+baseUrl+"/images/loading.gif'>"+
								"<iframe src='"+options.src+"' width='100%'  frameborder='0' style='overflow:hidden; min-height:1200px; margin-bottom:-5px;' scrolling='no' id='iframe' name='iframe'> </iframe>"+
								"</div>"+
								"</div>"+
								"</div>"+
								"<form id='report_form' method='post' action='"+options.src+"' target='iframe'>"+
								"<input type='hidden' id='dom' name='dom' value='Could not get content...'/>"+
								"<input type='hidden' id='title' name='title' value='report' />"+
								"<input type='hidden' id='orentation' name='orentation' value='"+options.orentation+"' />"+
								"<input type='hidden' id='size' name='size' value='"+options.size+"' />"+
								"</form>";
			var reportdom =$(options.content).clone(true);
			$.when($('body').append(rc)).done(function(){
				$.when(				
					reportdom.find('a').each(function(){
						$(this).remove();
					}),
					reportdom.find('div').each(function(){
						var $this = $(this);
						if(!$this.hasClass('space')){
							if($this.html().replace(/\s|&nbsp;/g, '').length == 0)
								$this.remove();
						}
						$this.has('div').contents().unwrap();
					}),
					reportdom.find('span').each(function(){
						$(this).removeClass().removeAttr('style');
					}),
					reportdom.find('table').each(function(){
						$(this).find('th.no-printpdf').each(function(){
							var index = parseInt(this.cellIndex)+1;
							reportdom.find('th:nth-child('+index+'), td:nth-child('+index+')').remove();
						});
						$(this).removeClass().removeAttr('style');
						$(this).addClass('table table-striped table-bordered table-hover table-condensed');
					}),
					reportdom.contents().filter(function() { return (this.nodeType == 3 && !/\S/.test(this.nodeValue)); }).remove(),
					$('form#report_form #dom').val($.trim(reportdom.html())),
					$('form#report_form #title').val($(document).find("title").text())
				).done(function(){	
					$.when($('form#report_form').submit(), $('form#report_form').submit(function(event){
						event.preventDefault();
					})).done(function(){			
						$('#pdfmodal').modal({show:true});
					});	
				});
				$('#iframe').load(function () {
					$('#loadingMessage').css('display', 'none');
				});
			});
		});
	}
	$.fn.genpdfinvoice = function (spec) {
		//set default option 
		var options = $.extend({
				content:'.page-content',
				src: '',
				orentation: 'P',// L for landscape
				size: 'A4',// can have A5 A6 etc...
		},spec);
		var baseUrl = options.src.substring(0, options.src.lastIndexOf('/'));
		$(this).click(function(){
			$('#pdfmodal').remove();
			var rc =	"<div id='pdfmodal' class='modal fade' tabindex='-1' role='dialog' aria-labelledby='myModalLabel' aria-hidden='true'>"+
								"<div class='modal-dialog' style='width:90%'>"+
								"<div class='modal-content text-center'>"+
								"<span data-dismiss='modal' aria-label='Close' style=' cursor:pointer; position:absolute; top:-12px; right:-10px;'><span class='white fa fa-times-circle fa-2x'></span></span>"+
								"<img id='loadingMessage' src='"+baseUrl+"/images/loading.gif'>"+
								"<iframe src='"+options.src+"' width='100%'  frameborder='0' style='overflow:hidden; min-height:1200px; margin-bottom:-5px;' scrolling='no' id='iframe' name='iframe'> </iframe>"+
								"</div>"+
								"</div>"+
								"</div>"+
								"<form id='report_form' method='post' action='"+options.src+"' target='iframe'>"+
								"<input type='hidden' id='dom' name='dom' value='Could not get content...'/>"+
								"<input type='hidden' id='title' name='title' value='report' />"+
								"<input type='hidden' id='orentation' name='orentation' value='"+options.orentation+"' />"+
								"<input type='hidden' id='size' name='size' value='"+options.size+"' />"+
								"</form>";
			var reportdom =$(options.content).clone(true);
			$.when($('body').append(rc)).done(function(){
				$.when(				
					reportdom.find('a').each(function(){
						$(this).remove();
					}),
					reportdom.find('table').addClass('table-condensed'),
					reportdom.find('div#convert-tb0').wrap('<table width="100%" id="nivoice-tab0"><tr></tr></table>'),
					reportdom.find('div#convert-tb1').wrap('<td width="70%"></td>'),
					reportdom.find('div#convert-tb2').wrap('<td></td>'),
					reportdom.find('div').each(function(){
						var $this = $(this);
						if(!$this.hasClass('space')){
							if($this.html().replace(/\s|&nbsp;/g, '').length == 0)
								$this.remove();
						}
						$this.has('div').contents().unwrap();
					}),
					reportdom.find('span').each(function(){
						$(this).removeClass().removeAttr('style');
					}),
					reportdom.contents().filter(function() { return (this.nodeType == 3 && !/\S/.test(this.nodeValue)); }).remove(),
					$('form#report_form #dom').val($.trim(reportdom.html())),
					$('form#report_form #title').val($(document).find("title").text())
				).done(function(){	
					$.when($('form#report_form').submit(), $('form#report_form').submit(function(event){
						event.preventDefault();
					})).done(function(){			
						$('#pdfmodal').modal({show:true});
					});	
				});
				$('#iframe').load(function () {
					$('#loadingMessage').css('display', 'none');
				});
			});
		});
	}
	$.fn.genpdfprofile = function (spec) {
		//set default option 
		var options = $.extend({
				content:'.page-content',
				src: '',
				orentation: 'P',// L for landscape
				size: 'A4',// can have A5 A6 etc...
		},spec);
		var baseUrl = options.src.substring(0, options.src.lastIndexOf('/'));
		$(this).click(function(){
			$('#pdfmodal').remove();
			var rc =	"<div id='pdfmodal' class='modal fade' tabindex='-1' role='dialog' aria-labelledby='myModalLabel' aria-hidden='true'>"+
								"<div class='modal-dialog' style='width:90%'>"+
								"<div class='modal-content text-center'>"+
								"<span data-dismiss='modal' aria-label='Close' style=' cursor:pointer; position:absolute; top:-12px; right:-10px;'><span class='white fa fa-times-circle fa-2x'></span></span>"+
								"<img id='loadingMessage' src='"+baseUrl+"/images/loading.gif'>"+
								"<iframe src='"+options.src+"' width='100%'  frameborder='0' style='overflow:hidden; min-height:1200px; margin-bottom:-5px;' scrolling='no' id='iframe' name='iframe'> </iframe>"+
								"</div>"+
								"</div>"+
								"</div>"+
								"<form id='report_form' method='post' action='"+options.src+"' target='iframe'>"+
								"<input type='hidden' id='dom' name='dom' value='Could not get content...'/>"+
								"<input type='hidden' id='title' name='title' value='report' />"+
								"<input type='hidden' id='orentation' name='orentation' value='"+options.orentation+"' />"+
								"<input type='hidden' id='size' name='size' value='"+options.size+"' />"+
								"</form>";
			var reportdom =$(options.content).clone(true);
			$.when($('body').append(rc)).done(function(){
				$.when(				
					reportdom.find('a').each(function(){
						$(this).remove();
					}),
					reportdom.find('table').addClass('table-condensed'),
					reportdom.find('div#convert-table').wrap('<table width="100%" id="profile-tab0"><tr></tr></table>'),
					reportdom.find('span#convert-tab1').wrap('<td width="30%"><div width="100%"> </div></td>'),
					reportdom.find('span#convert-tab2').wrap('<td></td>'),
					reportdom.find('div').each(function(){
						var $this = $(this);
						if(!$this.hasClass('space')){
							if($this.html().replace(/\s|&nbsp;/g, '').length == 0)
								$this.remove();
						}
						$this.has('div').contents().unwrap();
					}),
					reportdom.find('span').each(function(){
						$(this).removeClass().removeAttr('style');
					}),
					reportdom.contents().filter(function() { return (this.nodeType == 3 && !/\S/.test(this.nodeValue)); }).remove(),
					$('form#report_form #dom').val($.trim(reportdom.html())),
					$('form#report_form #title').val($(document).find("title").text())
				).done(function(){	
					$.when($('form#report_form').submit(), $('form#report_form').submit(function(event){
						event.preventDefault();
					})).done(function(){			
						$('#pdfmodal').modal({show:true});
					});	
				});
				$('#iframe').load(function () {
					$('#loadingMessage').css('display', 'none');
				});
			});
		});
	}
	
	$.fn.genpdfpayslip = function (spec) {
		//set default option 
		var options = $.extend({
				content:'.page-content',
				src: '',
				orentation: 'P',// L for landscape
				size: 'A4',// can have A5 A6 etc...
		},spec);
		var baseUrl = options.src.substring(0, options.src.lastIndexOf('/'));
		$(this).click(function(){
			$('#pdfmodal').remove();
			var rc =	"<div id='pdfmodal' class='modal fade' tabindex='-1' role='dialog' aria-labelledby='myModalLabel' aria-hidden='true'>"+
								"<div class='modal-dialog' style='width:90%'>"+
								"<div class='modal-content text-center'>"+
								"<span data-dismiss='modal' aria-label='Close' style=' cursor:pointer; position:absolute; top:-12px; right:-10px;'><span class='white fa fa-times-circle fa-2x'></span></span>"+
								"<img id='loadingMessage' src='"+baseUrl+"/images/loading.gif'>"+
								"<iframe src='"+options.src+"' width='100%'  frameborder='0' style='overflow:hidden; min-height:1200px; margin-bottom:-5px;' scrolling='no' id='iframe' name='iframe'> </iframe>"+
								"</div>"+
								"</div>"+
								"</div>"+
								"<form id='report_form' method='post' action='"+options.src+"' target='iframe'>"+
								"<input type='hidden' id='dom' name='dom' value='Could not get content...'/>"+
								"<input type='hidden' id='title' name='title' value='report' />"+
								"<input type='hidden' id='orentation' name='orentation' value='"+options.orentation+"' />"+
								"<input type='hidden' id='size' name='size' value='"+options.size+"' />"+
								"</form>";
			var reportdom =$(options.content).clone(true);
			$.when($('body').append(rc)).done(function(){
				$.when(				
					reportdom.find('a').each(function(){
						$(this).remove();
					}),
					reportdom.find('table').addClass('table-condensed'),
					reportdom.find('div#convert-table1').wrap('<table width="100%" ><tr></tr></table>'),
					reportdom.find('div#convert-tab11').wrap('<td width="50%"></td>'),
					reportdom.find('div#convert-tab12').wrap('<td></td>'),
					reportdom.find('div#convert-table2').wrap('<table width="100%" ><tr></tr></table>'),
					reportdom.find('div#convert-tab21').wrap('<td width="50%" style="vertical-align:top;"></td>'),
					reportdom.find('div#convert-tab22').wrap('<td style="vertical-align:top;"></td>'),
					reportdom.find('div').each(function(){
						var $this = $(this);
						if(!$this.hasClass('space')){
							if($this.html().replace(/\s|&nbsp;/g, '').length == 0)
								$this.remove();
						}
						$this.has('div').contents().unwrap();
					}),
					reportdom.find('span').each(function(){
						$(this).removeClass().removeAttr('style');
					}),
					reportdom.contents().filter(function() { return (this.nodeType == 3 && !/\S/.test(this.nodeValue)); }).remove(),
					$('form#report_form #dom').val($.trim(reportdom.html())),
					$('form#report_form #title').val($(document).find("title").text())
				).done(function(){	
					$.when($('form#report_form').submit(), $('form#report_form').submit(function(event){
						event.preventDefault();
					})).done(function(){			
						$('#pdfmodal').modal({show:true});
					});	
				});
				$('#iframe').load(function () {
					$('#loadingMessage').css('display', 'none');
				});
			});
		});
	}
	
	$.fn.genpdfleave = function (spec) {
		//set default option 
		var options = $.extend({
				content:'.page-content',
				src: '',
				orentation: 'P',// L for landscape
				size: 'A4',// can have A5 A6 etc...
		},spec);
		var baseUrl = options.src.substring(0, options.src.lastIndexOf('/'));
		$(this).click(function(){
			$('#pdfmodal').remove();
			var rc =	"<div id='pdfmodal' class='modal fade' tabindex='-1' role='dialog' aria-labelledby='myModalLabel' aria-hidden='true'>"+
								"<div class='modal-dialog' style='width:90%'>"+
								"<div class='modal-content text-center'>"+
								"<span data-dismiss='modal' aria-label='Close' style=' cursor:pointer; position:absolute; top:-12px; right:-10px;'><span class='white fa fa-times-circle fa-2x'></span></span>"+
								"<img id='loadingMessage' src='"+baseUrl+"/images/loading.gif'>"+
								"<iframe src='"+options.src+"' width='100%'  frameborder='0' style='overflow:hidden; min-height:1200px; margin-bottom:-5px;' scrolling='no' id='iframe' name='iframe'> </iframe>"+
								"</div>"+
								"</div>"+
								"</div>"+
								"<form id='report_form' method='post' action='"+options.src+"' target='iframe'>"+
								"<input type='hidden' id='dom' name='dom' value='Could not get content...'/>"+
								"<input type='hidden' id='title' name='title' value='report' />"+
								"<input type='hidden' id='orentation' name='orentation' value='"+options.orentation+"' />"+
								"<input type='hidden' id='size' name='size' value='"+options.size+"' />"+
								"</form>";
			var reportdom =$(options.content).clone(true);
			$.when($('body').append(rc)).done(function(){
				$.when(				
					reportdom.find('a').each(function(){
						$(this).remove();
					}),
					reportdom.find('h3.widget-title').append('&nbsp; &nbsp;(year:'+$('#year').val()+')'),
					reportdom.find('form').remove(),
					reportdom.find('table').addClass('table-condensed'),
					reportdom.find('div#convert-table').wrap('<table width="100%" ><tr></tr></table>'),
					reportdom.find('div#convert-tab1').wrap('<td></td>').removeClass('grid4'),
					reportdom.find('div#convert-tab2').wrap('<td></td>').removeClass('grid4'),
					reportdom.find('div#convert-tab3').wrap('<td></td>').removeClass('grid4'),
					reportdom.find('div#convert-tab4').wrap('<td></td>').removeClass('grid4'),
					reportdom.find('div').each(function(){
						var $this = $(this);
						if(!$this.hasClass('space')){
							if($this.html().replace(/\s|&nbsp;/g, '').length == 0)
								$this.remove();
						}
						$this.has('div').contents().unwrap();
					}),
					reportdom.find('table').each(function(){
						$(this).find('th.no-printpdf').each(function(){
							var index = parseInt(this.cellIndex)+1;
							reportdom.find('th:nth-child('+index+'), td:nth-child('+index+')').remove();
						});
					}),
					reportdom.find('span').each(function(){
						//$(this).removeClass().removeAttr('style');
					}),
					reportdom.contents().filter(function() { return (this.nodeType == 3 && !/\S/.test(this.nodeValue)); }).remove(),
					$('form#report_form #dom').val($.trim(reportdom.html())),
					$('form#report_form #title').val($(document).find("title").text())
				).done(function(){	
					$.when($('form#report_form').submit(), $('form#report_form').submit(function(event){
						event.preventDefault();
					})).done(function(){			
						$('#pdfmodal').modal({show:true});
					});	
				});
				$('#iframe').load(function () {
					$('#loadingMessage').css('display', 'none');
				});
			});
		});
	}
	$.fn.genpdfinvoice2 = function (spec) {
		//set default option 
		var options = $.extend({
				content:'.page-content',
				src: '',
				orentation: 'P',// L for landscape
				size: 'A4',// can have A5 A6 etc...
		},spec);
		var baseUrl = options.src.substring(0, options.src.lastIndexOf('/'));
		$(this).click(function(){
			$('#pdfmodal').remove();
			var rc =	"<div id='pdfmodal' class='modal fade' tabindex='-1' role='dialog' aria-labelledby='myModalLabel' aria-hidden='true'>"+
								"<div class='modal-dialog' style='width:90%'>"+
								"<div class='modal-content text-center'>"+
								"<span data-dismiss='modal' aria-label='Close' style=' cursor:pointer; position:absolute; top:-12px; right:-10px;'><span class='white fa fa-times-circle fa-2x'></span></span>"+
								"<img id='loadingMessage' src='"+baseUrl+"/images/loading.gif'>"+
								"<iframe src='"+options.src+"' width='100%'  frameborder='0' style='overflow:hidden; min-height:1200px; margin-bottom:-5px;' scrolling='no' id='iframe' name='iframe'> </iframe>"+
								"</div>"+
								"</div>"+
								"</div>"+
								"<form id='report_form' method='post' action='"+options.src+"' target='iframe'>"+
								"<input type='hidden' id='dom' name='dom' value='Could not get content...'/>"+
								"<input type='hidden' id='title' name='title' value='report' />"+
								"<input type='hidden' id='orentation' name='orentation' value='"+options.orentation+"' />"+
								"<input type='hidden' id='size' name='size' value='"+options.size+"' />"+
								"</form>";
			var reportdom =$(options.content).clone(true);
			$.when($('body').append(rc)).done(function(){
				$.when(				
					reportdom.find('a').each(function(){
						$(this).remove();
					}),
					reportdom.find('table').addClass('table-condensed'),
					reportdom.find('div#convert-tb0').wrap('<table width="100%" id="nivoice-tab0"><tr></tr></table>'),
					reportdom.find('div#convert-tb1').wrap('<td></td>'),
					reportdom.find('div#convert-tb2').wrap('<td></td>'),
					reportdom.find('div#convert-tb3').wrap('<td></td>'),
					reportdom.find('div').each(function(){
						var $this = $(this);
						if(!$this.hasClass('space')){
							if($this.html().replace(/\s|&nbsp;/g, '').length == 0)
								$this.remove();
						}
						$this.has('div').contents().unwrap();
					}),
					reportdom.find('span').each(function(){
						$(this).removeClass().removeAttr('style');
					}),
					reportdom.contents().filter(function() { return (this.nodeType == 3 && !/\S/.test(this.nodeValue)); }).remove(),
					$('form#report_form #dom').val($.trim(reportdom.html())),
					$('form#report_form #title').val($(document).find("title").text())
				).done(function(){	
					$.when($('form#report_form').submit(), $('form#report_form').submit(function(event){
						event.preventDefault();
					})).done(function(){			
						$('#pdfmodal').modal({show:true});
					});	
				});
				$('#iframe').load(function () {
					$('#loadingMessage').css('display', 'none');
				});
			});
		});
	}
	$.fn.genpdfinvoice3 = function (spec) {
		//set default option 
		var options = $.extend({
				content:'.page-content',
				src: '',
				orentation: 'P',// L for landscape
				size: 'A4',// can have A5 A6 etc...
		},spec);
		var baseUrl = options.src.substring(0, options.src.lastIndexOf('/'));
		$(this).click(function(){
			$('#pdfmodal').remove();
			var rc =	"<div id='pdfmodal' class='modal fade' tabindex='-1' role='dialog' aria-labelledby='myModalLabel' aria-hidden='true'>"+
								"<div class='modal-dialog' style='width:90%'>"+
								"<div class='modal-content text-center'>"+
								"<span data-dismiss='modal' aria-label='Close' style=' cursor:pointer; position:absolute; top:-12px; right:-10px;'><span class='white fa fa-times-circle fa-2x'></span></span>"+
								"<img id='loadingMessage' src='"+baseUrl+"/images/loading.gif'>"+
								"<iframe src='"+options.src+"' width='100%'  frameborder='0' style='overflow:hidden; min-height:1200px; margin-bottom:-5px;' scrolling='no' id='iframe' name='iframe'> </iframe>"+
								"</div>"+
								"</div>"+
								"</div>"+
								"<form id='report_form' method='post' action='"+options.src+"' target='iframe'>"+
								"<input type='hidden' id='dom' name='dom' value='Could not get content...'/>"+
								"<input type='hidden' id='title' name='title' value='report' />"+
								"<input type='hidden' id='orentation' name='orentation' value='"+options.orentation+"' />"+
								"<input type='hidden' id='size' name='size' value='"+options.size+"' />"+
								"</form>";
			var reportdom =$(options.content).clone(true);
			$.when($('body').append(rc)).done(function(){
				$.when(				
					reportdom.find('a').each(function(){
						$(this).remove();
					}),
					reportdom.find('table').addClass('table-condensed'),
					reportdom.find('div#convert-tb0').wrap('<table width="100%" id="nivoice-tab0"><tr></tr></table>'),
					reportdom.find('div#convert-tb1').wrap('<td style="text-align:left"></td>'),
					reportdom.find('div#convert-tb2').wrap('<td style="text-align:center"></td>'),
					reportdom.find('div#convert-tb3').wrap('<td style="text-align:right"></td>'),
					reportdom.find('div').each(function(){
						var $this = $(this);
						if(!$this.hasClass('space')){
							if($this.html().replace(/\s|&nbsp;/g, '').length == 0)
								$this.remove();
						}
						$this.has('div').contents().unwrap();
					}),
					reportdom.find('span').each(function(){
						$(this).removeClass().removeAttr('style');
					}),
					reportdom.contents().filter(function() { return (this.nodeType == 3 && !/\S/.test(this.nodeValue)); }).remove(),
					$('form#report_form #dom').val($.trim(reportdom.html())),
					$('form#report_form #title').val($(document).find("title").text())
				).done(function(){	
					$.when($('form#report_form').submit(), $('form#report_form').submit(function(event){
						event.preventDefault();
					})).done(function(){			
						$('#pdfmodal').modal({show:true});
					});	
				});
				$('#iframe').load(function () {
					$('#loadingMessage').css('display', 'none');
				});
			});
		});
	}
}(jQuery));
