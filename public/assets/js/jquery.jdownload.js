/*
 * jDownload - A jQuery plugin to assist file downloads
 * Examples and documentation at: http://jdownloadplugin.com
 * Version: 1.3 (18/11/2010)
 * Copyright (c) 2010 Adam Chambers, Tim Myers
 * Licensed under the GNU General Public License v3: http://www.gnu.org/licenses/gpl.html
 * Requires: jQuery v1.4+ & jQueryUI 1.8+
*/

(function($) {

	$.fn.jDownload = function(settings){
		
		var config = {  
			root         : "/",
			filePath     : null,
			event        : "click", // default click event??
			dialogTitle  : "下载商品信息",
			dialogDesc   : 'Download the file now?',
			dialogWidth  : 400,
			dialogHeight : 'auto',
			dialogModal  : true,
			showfileInfo : true,
			start        : null,
			stop         : null,
			download     : null,
			cancel       : null
		}
				   	
	  	settings = $.extend(config, settings);
	  	
	  	var dialogID = "jDownloadDialog_"+$('.jDownloadDialog').length;
	  	var iframeID = "jDownloadFrame_"+$('.jDownloadFrame').length;
	  	
	  	// create html iframe and dialog
	  	var iframeHTML = '<iframe class="jDownloadFrame" src="" id="'+iframeID+'"></iframe>';	
	  	var dialogHTML = '<div class="jDownloadDialog" title="'+settings.dialogTitle+'" id="'+dialogID+'"></div>';
	  	
	  	// append both to document
	  	$('body').append(iframeHTML+dialogHTML);
	  	
	  	
	  	var iframe = $('#'+iframeID);
	  	var dialog = $('#'+dialogID);
	  	
	  	// set iframe styles
	  	iframe.css({
	  		"height"    : "0px",
	  		"width"     : "0px",
	  		"visibility"   : "hidden"
	  	});
	  	
	  	// set dialog options
	  	dialog.dialog({
	  		autoOpen : false,
	  		buttons	 : {
	  			"取消": function() { 
	  				if($.isFunction(settings.cancel)) {
	  					settings.cancel();
	  				}
	  				$(this).dialog('close');
	  			}, 
	  			
	  			"马上下载": function() {
	  				if($.isFunction(settings.download)) {
	  					settings.download();
	  				}
	  				start_download();
	  			}
	  		},
	  		width    : settings.dialogWidth,
	  		height   : settings.dialogHeight,
	  		modal    : settings.dialogModal,
	  		close    : ($.isFunction(settings.stop)) ? settings.stop : null
		});


		$(this).bind(settings.event, function(){
		
			if($.isFunction(settings.start)) {	
				settings.start();
			}
			
			var _this = $(this);
			
			
			dialog.html("");
		
			// if filePath is not specified then use the href attribute
			var filePath = (settings.filePath == null) ? $(this).attr('href') : settings.filePath;
			
			dialog.html('<p>下载文件努力生成中，请稍候...</p><img src="'+settings.root+'assets/img/loader.gif" alt="Loading" />');
			
			$.ajax({
				type : 'GET',
				url  : settings.dlurl + $('#'+settings.formid).serialize(),
//				data : 'action=download&path='+filePath,
				error : function(XMLHttpRequest, textStatus, errorThrown) {
					dialog.html("<p class=\"jDownloadError\">下载数据生成失败，请重新选择数据，再下载！</p>");
				},
				success : function(res) {
					var data = JSON.parse(res);
					if(data.error == 'denied'){
						
						// append new file info
						dialog.html('<p class=\"jDownloadError\">文件类型不被允许.</p>');
					
					}else if(data.code >= 1){
					
						// append new file info
						dialog.html('<p class=\"jDownloadError\">下载数据生成失败，请重新选择数据，再下载！</p>');
					
					}else{
						
						// parse JSON
						settings.filePath = data.filepath;
						var html  = "<div class=\"jDownloadInfo\">";
						html += "<p>商品下载数据生成： "+data.filename+"，共"+ data.filesize+"KB!</p>";
					    html += "<p>点击”马上下载“按钮，开始下载！ </p>";
					    html += "</div>";
					
						// remove any old file info & error messages
						$('.jDownloadInfo, .jDownloadError').remove();
					
						var desc = (_this.attr('title').length > 0) ? _this.attr('title') : 'Download the file now?';
					
						// append new file info
						dialog.html(html);
						
					}
				}
		
			});
			
			// open dialog 
			dialog.data('jDownloadData', {filePath : filePath}).dialog('open');
					
			return false;
				
		});
		
		/* Iniate download when value Ok is iniated via the dialog */
		function start_download(i){
			
			
			// change iframe src to fieDownload.php with filePath as query string?? 
			iframe.attr('src', settings.filePath);
			
			// Close dialog
			dialog.dialog('close');
			
			return false;
		}
		
	}
	
})(jQuery);