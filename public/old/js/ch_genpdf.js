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
					reportdom.find('table#nivoice-tab0 tr').each(function(){
						// Keep only the first 3 converted columns (tb1/tb2/tb3) per row.
						// Some views contain duplicate convert-tb blocks that would otherwise
						// create extra narrow cells and break footer/signature text wrapping.
						$(this).children('td:gt(2)').remove();
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
					reportdom.find('table#nivoice-tab0 tr').each(function(){
						var $row = $(this);
						var rowText = $row.text().toLowerCase();
						if(rowText.indexOf('prepared by') !== -1 || rowText.indexOf('authorized signatory') !== -1){
							// Remove blank/whitespace-only cells introduced by duplicate convert blocks.
							$row.children('td').each(function(){
								var text = $(this).text().replace(/\s|&nbsp;/g, '');
								if(text.length === 0){
									$(this).remove();
								}
							});
							// Keep the signature row as left and right blocks.
							$row.children('td:gt(1)').remove();
							$row.children('td:first').attr('style', 'text-align:left;width:65%;vertical-align:top;');
							$row.children('td:last').attr('style', 'text-align:right;width:35%;vertical-align:top;');
						}
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
					reportdom.find('.print-title').each(function(){
						var style = $(this).attr('style') || '';
						style = style.replace(/display\s*:\s*none;?/ig, '');
						$(this).attr('style', style + 'display:block;');
					}),
					(function(){
						reportdom.find('table#nivoice-tab0 tr').each(function(){
							var $row = $(this);
							var text = $row.text().toLowerCase();
							if(text.indexOf('voucher no') !== -1 && text.indexOf('voucher amount') !== -1 && text.indexOf('cheque no') !== -1){
								$row.children('td:first').attr('style', 'width:65%;text-align:left;vertical-align:top;');
								$row.children('td:last').attr('style', 'width:35%;text-align:right;vertical-align:top;');
							}
						});

						var $signatureRows = reportdom.find('table#nivoice-tab0 tr').filter(function(){
							var text = $(this).text().toLowerCase();
							return text.indexOf('prepared by') !== -1 || text.indexOf('authorized signatory') !== -1;
						});
						if(!$signatureRows.length){
							return;
						}

						var preparedHtml = '';
						$signatureRows.each(function(){
							if(preparedHtml === ''){
								var rowText = $(this).text().toLowerCase();
								if(rowText.indexOf('prepared by') !== -1){
									preparedHtml = $.trim($(this).find('label').first().html() || $(this).find('td:first').text() || '');
								}
							}
						});

						var $anchorTable = $signatureRows.last().closest('table#nivoice-tab0');
						$signatureRows.remove();

						if(preparedHtml === ''){
							preparedHtml = 'Prepared by:';
						}

						$anchorTable.append('<tr><td colspan="2" style="height:36px;border:none;"></td></tr>');
						$anchorTable.append(
							'<tr>' +
							'<td style="text-align:left;width:65%;vertical-align:top;">' +
							'<label style="font-size:13px;font-family:Times New Roman, serif;line-height:1.3;font-weight:400;margin:0;">' + preparedHtml + '</label>' +
							'</td>' +
							'<td style="text-align:right;width:35%;vertical-align:top;padding-top:2px;">' +
							'<label style="font-size:13px;font-family:Times New Roman, serif;line-height:1.3;font-weight:700;margin:0;">Authorized Signatory</label>' +
							'</td>' +
							'</tr>'
						);
					})(),
					reportdom.find('table#nivoice-tab0').each(function(){
						// Remove empty converted helper rows/tables that create large gaps.
						var compact = $(this).text().replace(/[\s\u00a0]|&nbsp;/g, '').toLowerCase();
						if(compact.length === 0){
							$(this).remove();
						}
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
