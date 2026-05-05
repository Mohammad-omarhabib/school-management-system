<?php
	if(!defined('datalist_db_encoding')) define('datalist_db_encoding', 'UTF-8');
	if(function_exists('date_default_timezone_set')) @date_default_timezone_set('America/New_York');

	/* force caching */
	$last_modified = filemtime(__FILE__);
	$last_modified_gmt = gmdate('D, d M Y H:i:s', $last_modified) . ' GMT';
	$headers = (function_exists('getallheaders') ? getallheaders() : $_SERVER);
	if(isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == $last_modified)){
		@header("Last-Modified: {$last_modified_gmt}", true, 304);
		@header("Cache-Control: public, max-age=240", true);
		exit;
	}

	@header("Last-Modified: {$last_modified_gmt}", true, 200);
	@header("Cache-Control: public, max-age=240", true);
	@header('Content-Type: text/javascript; charset=' . datalist_db_encoding);
	$currDir = dirname(__FILE__);
	include("{$currDir}/defaultLang.php");
	include("{$currDir}/language.php");
?>
var AppGini = AppGini || {};
AppGini.ajaxCache = function(){
	var _tests = [];

	/*
		An array of functions that receive a parameterless url and a parameters object,
		makes a test,
		and if test passes, executes something and/or
		returns a non-false value if test passes,
		or false if test failed (useful to tell if tests should continue or not)
	*/
	var addCheck = function(check){ //
		if(typeof(check) == 'function'){
			_tests.push(check);
		}
	};

	var _jqAjaxData = function(opt){ //
		var opt = opt || {};   
		var url = opt.url || '';
		var data = opt.data || {};

		var params = url.match(/\?(.*)$/);
		var param = (params !== null ? params[1] : '');

		var sPageURL = decodeURIComponent(param),
			sURLVariables = sPageURL.split('&'),
			sParameter,
			i;

		for(i = 0; i < sURLVariables.length; i++){
			sParameter = sURLVariables[i].split('=');
			if(sParameter[0] == '') continue;
			data[sParameter[0]] = sParameter[1] || '';
		}

		return data;
	};

	var start = function(){ //
		if(!_tests.length) return; // no need to monitor ajax requests since no checks were defined
		var reqTests = _tests;
		$j.ajaxPrefilter(function(options, originalOptions, jqXHR){
			var success = originalOptions.success || $j.noop,
				data = _jqAjaxData(originalOptions),
				oUrl = originalOptions.url || '',
				url = oUrl.match(/\?/) ? oUrl.match(/(.*)\?/)[1] : oUrl;

			options.beforeSend = function(){ //
				var req, cached = false, resp;

				for(var i = 0; i < reqTests.length; i++){
					resp = reqTests[i](url, data);
					if(resp === false) continue;

					success(resp);
					return false;
				}

				return true;
			}
		});
	};

	return {
		addCheck: addCheck,
		start: start
	};
};

/* initials and fixes */
jQuery(function(){
	AppGini.count_ajaxes_blocking_saving = 0;

	/* add ":truncated" pseudo-class to detect elements with clipped text */
	$j.expr[':'].truncated = function(obj){
		var $this = $j(obj);
		var $c = $this
					.clone()
					.css({ display: 'inline', width: 'auto', visibility: 'hidden', 'padding-right': 0 })
					.css({ 'font-size': $this.css('font-size') })
					.appendTo('body');

		var e_width = $this.outerWidth();
		var c_width = $c.outerWidth();
		$c.remove();

		return ( c_width > e_width );
	};

	var fix_lookup_width = function(field){
		var s2 = $j('div.select2-container[id=s2id_' + field + '-container]');
		if(!s2.length) return;

		var s2new_width = 0, s2view_width = 0, s2parent_width = 0;

		var s2new = s2.parent().find('.add_new_parent:visible');
		var s2view = s2.parent().find('.view_parent:visible');
		if(s2new.length) s2new_width = s2new.outerWidth(true);
		if(s2view.length) s2view_width = s2view.outerWidth(true);
		s2parent_width = s2.parent().innerWidth();

		// console.log({ s2new_width: s2new_width, s2view_width: s2view_width, s2parent_width: s2parent_width });

		s2.css({ width: '100%', 'max-width': (s2parent_width - s2new_width - s2view_width - 1) + 'px' });
	}

	$j(window).resize(function(){
		var window_width = $j(window).width();
		var max_width = $j('body').width() * 0.5;

		$j('.select2-container:not(.option_list)').each(function(){
			var field = $j(this).attr('id').replace(/^s2id_/, '').replace(/-container$/, '');
			fix_lookup_width(field);
		});

		//fix_table_responsive_width();

		var full_img_factor = 0.9; /* xs */
		if(window_width >= 992) full_img_factor = 0.6; /* md, lg */
		else if(window_width >= 768) full_img_factor = 0.9; /* sm */

		$j('.detail_view .img-responsive').css({'max-width' : parseInt($j('.detail_view').width() * full_img_factor) + 'px'});

		/* remove labels from truncated buttons, leaving only glyphicons */
		$j('.btn.truncate:truncated').each(function(){
			// hide text
			var label = $j(this).html();
			var mlabel = label.replace(/.*(<i.*?><\/i>).*/, '$1');
			$j(this).html(mlabel);
		});
	});

	setTimeout(function(){ $j(window).resize(); }, 1000);
	setTimeout(function(){ $j(window).resize(); }, 3000);

	/* don't allow saving detail view when there's an ajax request to a url that matches the following */
	var ajax_blockers = new RegExp(/(ajax_combo\.php|_autofill\.php|ajax_check_unique\.php)/);
	$j(document).ajaxSend(function(e, r, s){
		if(s.url.match(ajax_blockers)){
			AppGini.count_ajaxes_blocking_saving++;
			$j('#update, #insert').prop('disabled', true);
		}
	});
	$j(document).ajaxComplete(function(e, r, s){
		if(s.url.match(ajax_blockers)){
			AppGini.count_ajaxes_blocking_saving = Math.max(AppGini.count_ajaxes_blocking_saving - 1, 0);
			if(AppGini.count_ajaxes_blocking_saving <= 0)
				$j('#update, #insert').prop('disabled', false);
		}
	});

	/* don't allow responsive images to initially exceed the smaller of their actual dimensions, or .6 container width */
	jQuery('.detail_view .img-responsive').each(function(){
		 var pic_real_width, pic_real_height;
		 var img = jQuery(this);
		 jQuery('<img/>') // Make in memory copy of image to avoid css issues
				.attr('src', img.attr('src'))
				.load(function() {
					pic_real_width = this.width;
					pic_real_height = this.height;

					if(pic_real_width > $j('.detail_view').width() * .6) pic_real_width = $j('.detail_view').width() * .6;
					img.css({ "max-width": pic_real_width });
				});
	});

	jQuery('.table-responsive .img-responsive').each(function(){
		 var pic_real_width, pic_real_height;
		 var img = jQuery(this);
		 jQuery('<img/>') // Make in memory copy of image to avoid css issues
				.attr('src', img.attr('src'))
				.load(function() {
					pic_real_width = this.width;
					pic_real_height = this.height;

					if(pic_real_width > $j('.table-responsive').width() * .6) pic_real_width = $j('.table-responsive').width() * .6;
					img.css({ "max-width": pic_real_width });
				});
	});

	/* toggle TV action buttons based on selected records */
	jQuery('.record_selector').click(function(){
		var id = jQuery(this).val();
		var checked = jQuery(this).prop('checked');
		update_action_buttons();
	});

	/* select/deselect all records in TV */
	jQuery('#select_all_records').click(function(){
		jQuery('.record_selector').prop('checked', jQuery(this).prop('checked'));
		update_action_buttons();
	});

	/* fix behavior of select2 in bootstrap modal. See: https://github.com/ivaynberg/select2/issues/1436 */
	jQuery.fn.modal.Constructor.prototype.enforceFocus = function(){ /**/ };

	/* remove empty navbar menus */
	$j('nav li.dropdown').each(function(){
		var num_items = $j(this).children('.dropdown-menu').children('li').length;
		if(!num_items) $j(this).remove();
	})

	update_action_buttons();

	/* remove empty images and links from TV, TVP */
	$j('.table a[href="<?php echo $Translation['ImageFolder']; ?>"], .table img[src="<?php echo $Translation['ImageFolder']; ?>"]').remove();

	/* remove empty email links from TV, TVP */
	$j('a[href="mailto:"]').remove();

	/* Disable action buttons when form is submitted to avoid user re-submission on slow connections */
	$j('form').eq(0).submit(function(){
		setTimeout(function(){
			$j('#insert, #update, #delete, #deselect').prop('disabled', true);
		}, 200); // delay purpose is to allow submitting the button values first then disable them.
	});
});

/* show/hide TV action buttons based on whether records are selected or not */
function update_action_buttons(){
	if(jQuery('.record_selector:checked').length){
		jQuery('.selected_records').removeClass('hidden');
		jQuery('#select_all_records')
			.prop('checked', (jQuery('.record_selector:checked').length == jQuery('.record_selector').length));
	}else{
		jQuery('.selected_records').addClass('hidden');
	}
}

/* fix table-responsive behavior on Chrome */
function fix_table_responsive_width(){
	var resp_width = jQuery('div.table-responsive').width();
	var table_width;

	if(resp_width){
		jQuery('div.table-responsive table').width('100%');
		table_width = jQuery('div.table-responsive table').width();
		resp_width = jQuery('div.table-responsive').width();
		if(resp_width == table_width){
			jQuery('div.table-responsive table').width(resp_width - 1);
		}
	}
}

function students_validateData(){
	$j('.has-error').removeClass('has-error');
	/* Field regno can't be empty */
	if($j('#regno').val() == ''){ modal_window({ message: '<div class="alert alert-danger"><?php echo addslashes($Translation['field not null']); ?></div>', title: "<?php echo addslashes($Translation['error:']); ?> Regno", close: function(){ $j('[name=regno]').focus().parents('.form-group').addClass('has-error'); } }); return false; };
	/* Field name can't be empty */
	if($j('#name').val() == ''){ modal_window({ message: '<div class="alert alert-danger"><?php echo addslashes($Translation['field not null']); ?></div>', title: "<?php echo addslashes($Translation['error:']); ?> Name", close: function(){ $j('[name=name]').focus().parents('.form-group').addClass('has-error'); } }); return false; };
	return true;
}
function units_validateData(){
	$j('.has-error').removeClass('has-error');
	return true;
}
function courses_validateData(){
	$j('.has-error').removeClass('has-error');
	return true;
}
function attendance_validateData(){
	$j('.has-error').removeClass('has-error');
	return true;
}
function Marks_validateData(){
	$j('.has-error').removeClass('has-error');
	return true;
}
function academic_year_validateData(){
	$j('.has-error').removeClass('has-error');
	return true;
}

function post(url, params, update, disable, loading, success_callback){
	$j.ajax({
		url: url,
		type: 'POST',
		data: params,
		beforeSend: function() {
			if($j('#' + disable).length) $j('#' + disable).prop('disabled', true);
			if($j('#' + loading).length && update != loading) $j('#' + loading).html('<div style="direction: ltr;"><img src="loading.gif"> <?php echo addslashes($Translation['Loading ...']); ?></div>');
		},
		success: function(resp) {
			if($j('#' + update).length) $j('#' + update).html(resp);
			if(success_callback != undefined) success_callback();
		},
		complete: function() {
			if($j('#' + disable).length) $j('#' + disable).prop('disabled', false);
			if($j('#' + loading).length && loading != update) $j('#' + loading).html('');
		}
	});
}

function post2(url, params, notify, disable, loading, redirectOnSuccess){
	new Ajax.Request(
		url, {
			method: 'post',
			parameters: params,
			onCreate: function() {
				if($(disable) != undefined) $(disable).disabled=true;
				if($(loading) != undefined) $(loading).show();
			},
			onSuccess: function(resp) {
				/* show notification containing returned text */
				if($(notify) != undefined) $(notify).removeClassName('Error').appear().update(resp.responseText);

				/* in case no errors returned, */
				if(!resp.responseText.match(/<?php echo $Translation['error:']; ?>/)){
					/* redirect to provided url */
					if(redirectOnSuccess != undefined){
						window.location=redirectOnSuccess;

					/* or hide notification after a few seconds if no url is provided */
					}else{
						if($(notify) != undefined) window.setTimeout(function(){ $(notify).fade(); }, 15000);
					}

				/* in case of error, apply error class */
				}else{
					$(notify).addClassName('Error');
				}
			},
			onComplete: function() {
				if($(disable) != undefined) $(disable).disabled=false;
				if($(loading) != undefined) $(loading).hide();
			}
		}
	);
}
function passwordStrength(password, username){
	// score calculation (out of 10)
	var score = 0;
	re = new RegExp(username, 'i');
	if(username.length && password.match(re)) score -= 5;
	if(password.length < 6) score -= 3;
	else if(password.length > 8) score += 5;
	else score += 3;
	if(password.match(/(.*[0-9].*[0-9].*[0-9])/)) score += 3;
	if(password.match(/(.*[!,@,#,$,%,^,&,*,?,_,~].*[!,@,#,$,%,^,&,*,?,_,~])/)) score += 5;
	if(password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) score += 2;

	if(score >= 9)
		return 'strong';
	else if(score >= 5)
		return 'good';
	else
		return 'weak';
}
function validateEmail(email) { 
	var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
	return re.test(email);
}
function loadScript(jsUrl, cssUrl, callback){
	// adding the script tag to the head
	var head = document.getElementsByTagName('head')[0];
	var script = document.createElement('script');
	script.type = 'text/javascript';
	script.src = jsUrl;

	if(cssUrl != ''){
		var css = document.createElement('link');
		css.href = cssUrl;
		css.rel = "stylesheet";
		css.type = "text/css";
		head.appendChild(css);
	}

	// then bind the event to the callback function 
	// there are several events for cross browser compatibility
	if(script.onreadystatechange != undefined){ script.onreadystatechange = callback; }
	if(script.onload != undefined){ script.onload = callback; }

	// fire the loading
	head.appendChild(script);
}
/**
 * options object. The following members can be provided:
 *    url: iframe url to load
 *    message: instead of a url to open, you could pass a message. HTML tags allowed.
 *    id: id attribute of modal window. auto-generated if not provided
 *    title: optional modal window title
 *    size: 'default', 'full'
 *    close: optional function to execute on closing the modal
 *    footer: optional array of objects describing the buttons to display in the footer.
 *       Each button object can have the following members:
 *          label: string, label of button
 *          bs_class: string, button bootstrap class. Can be 'primary', 'default', 'success', 'warning' or 'danger'
 *          click: function to execute on clicking the button. If the button closes the modal, this
 *                 function is executed before the close handler
 *          causes_closing: boolean, default is true.
 */
function modal_window(options){
	return jQuery('body').agModal(options).agModal('show').attr('id');
}

function random_string(string_length){
	var text = "";
	var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

	for(var i = 0; i < string_length; i++)
		text += possible.charAt(Math.floor(Math.random() * possible.length));

	return text;
}

/**
 *  @return array of IDs (PK values) of selected records in TV (records that the user checked)
 */
function get_selected_records_ids(){
	return jQuery('.record_selector:checked').map(function(){ return jQuery(this).val() }).get();
}

function print_multiple_dv_tvdv(t, ids){
	document.myform.NoDV.value=1;
	document.myform.PrintDV.value=1;
	document.myform.SelectedID.value = '';
	document.myform.submit();
	return true;
}

function print_multiple_dv_sdv(t, ids){
	document.myform.NoDV.value=1;
	document.myform.PrintDV.value=1;
	document.myform.writeAttribute('novalidate', 'novalidate');
	document.myform.submit();
	return true;
}

function mass_delete(t, ids){
	if(ids == undefined) return;
	if(!ids.length) return;

	var confirm_message = '<div class="alert alert-danger">' +
			'<i class="glyphicon glyphicon-warning-sign"></i> ' + 
			'<?php echo addslashes($Translation['<n> records will be deleted. Are you sure you want to do this?']); ?>' +
		'</div>';
	var confirm_title = '<?php echo addslashes($Translation['Confirm deleting multiple records']); ?>';
	var label_yes = '<?php echo addslashes($Translation['Yes, delete them!']); ?>';
	var label_no = '<?php echo addslashes($Translation['No, keep them.']); ?>';
	var progress = '<?php echo addslashes($Translation['Deleting record <i> of <n>']); ?>';
	var continue_delete = true;

	// request confirmation of mass delete operation
	modal_window({
		message: confirm_message.replace(/\<n\>/, ids.length),
		title: confirm_title,
		footer: [ /* shows a 'yes' and a 'no' buttons .. handler for each follows ... */
			{
				label: '<i class="glyphicon glyphicon-trash"></i> ' + label_yes,
				bs_class: 'danger',
				// on confirming, start delete operations
				click: function(){

					// show delete progress, allowing user to abort operations by closing the window or clicking cancel
					var progress_window = modal_window({
						title: '<?php echo addslashes($Translation['Delete progress']); ?>',
						message: '' +
							'<div class="progress">' +
								'<div class="progress-bar progress-bar-warning" role="progressbar" style="width: 0;"></div>' +
							'</div>' + 
							'<button type="button" class="btn btn-default details_toggle" onclick="' +
								'jQuery(this).children(\'.glyphicon\').toggleClass(\'glyphicon-chevron-right glyphicon-chevron-down\'); ' +
								'jQuery(\'.well.details_list\').toggleClass(\'hidden\');'
								+ '">' +
								'<i class="glyphicon glyphicon-chevron-right"></i> ' +
								'<?php echo addslashes($Translation['Show/hide details']); ?>' +
							'</button>' +
							'<div class="well well-sm details_list hidden"><ol></ol></div>',
						close: function(){
							// stop deleting further records ...
							continue_delete = false;
						},
						footer: [
							{
								label: '<i class="glyphicon glyphicon-remove"></i> <?php echo addslashes($Translation['Cancel']); ?>',
								bs_class: 'warning'
							}
						]
					});

					// begin deleting records, one by one
					progress = progress.replace(/\<n\>/, ids.length);
					var delete_record = function(itrn){
						if(!continue_delete) return;
						jQuery.ajax(t + '_view.php', {
							type: 'POST',
							data: { delete_x: 1, SelectedID: ids[itrn] },
							success: function(resp){
								if(resp == 'OK'){
									jQuery(".well.details_list ol").append('<li class="text-success"><?php echo addslashes($Translation['The record has been deleted successfully']); ?></li>');
									jQuery('#record_selector_' + ids[itrn]).prop('checked', false).parent().parent().fadeOut(1500);
									jQuery('#select_all_records').prop('checked', false);
								}else{
									jQuery(".well.details_list ol").append('<li class="text-danger">' + resp + '</li>');
								}
							},
							error: function(){
								jQuery(".well.details_list ol").append('<li class="text-warning"><?php echo addslashes($Translation['Connection error']); ?></li>');
							},
							complete: function(){
								jQuery('#' + progress_window + ' .progress-bar').attr('style', 'width: ' + (Math.round((itrn + 1) / ids.length * 100)) + '%;').html(progress.replace(/\<i\>/, (itrn + 1)));
								if(itrn < (ids.length - 1)){
									delete_record(itrn + 1);
								}else{
									if(jQuery('.well.details_list li.text-danger, .well.details_list li.text-warning').length){
										jQuery('button.details_toggle').removeClass('btn-default').addClass('btn-warning').click();
										jQuery('.btn-warning[id^=' + progress_window + '_footer_button_]')
											.toggleClass('btn-warning btn-default')
											.html('<?php echo addslashes($Translation['ok']); ?>');
									}else{
										setTimeout(function(){ jQuery('#' + progress_window).agModal('hide'); }, 500);
									}
								}
							}
						});
					}

					delete_record(0);
				}
			},
			{
				label: '<i class="glyphicon glyphicon-ok"></i> ' + label_no,
				bs_class: 'success' 
			}
		]
	});
}

function mass_change_owner(t, ids){
	if(ids == undefined) return;
	if(!ids.length) return;

	var update_form = '<?php echo addslashes($Translation['Change owner of <n> selected records to']); ?> ' + 
		'<span id="new_owner_for_selected_records"></span><input type="hidden" name="new_owner_for_selected_records" value="">';
	var confirm_title = '<?php echo addslashes($Translation['Change owner']); ?>';
	var label_yes = '<?php echo addslashes($Translation['Continue']); ?>';
	var label_no = '<?php echo addslashes($Translation['Cancel']); ?>';
	var progress = '<?php echo addslashes($Translation['Updating record <i> of <n>']); ?>';
	var continue_updating = true;

	// request confirmation of mass update operation
	modal_window({
		message: update_form.replace(/\<n\>/, ids.length),
		title: confirm_title,
		footer: [ /* shows a 'continue' and a 'cancel' buttons .. handler for each follows ... */
			{
				label: '<i class="glyphicon glyphicon-ok"></i> ' + label_yes,
				bs_class: 'success',
				// on confirming, start update operations
				click: function(){
					var memberID = jQuery('input[name=new_owner_for_selected_records]').eq(0).val();
					if(!memberID.length) return;

					// show update progress, allowing user to abort operations by closing the window or clicking cancel
					var progress_window = modal_window({
						title: '<?php echo addslashes($Translation['Update progress']); ?>',
						message: '' +
							'<div class="progress">' +
								'<div class="progress-bar progress-bar-success" role="progressbar" style="width: 0;"></div>' +
							'</div>' + 
							'<button type="button" class="btn btn-default details_toggle" onclick="' +
								'jQuery(this).children(\'.glyphicon\').toggleClass(\'glyphicon-chevron-right glyphicon-chevron-down\'); ' +
								'jQuery(\'.well.details_list\').toggleClass(\'hidden\');'
								+ '">' +
								'<i class="glyphicon glyphicon-chevron-right"></i> ' +
								'<?php echo addslashes($Translation['Show/hide details']); ?>' +
							'</button>' +
							'<div class="well well-sm details_list hidden"><ol></ol></div>',
						close: function(){
							// stop updating further records ...
							continue_updating = false;
						},
						footer: [
							{
								label: '<i class="glyphicon glyphicon-remove"></i> <?php echo addslashes($Translation['Cancel']); ?>',
								bs_class: 'warning'
							}
						]
					});

					// begin updating records, one by one
					progress = progress.replace(/\<n\>/, ids.length);
					var update_record = function(itrn){
						if(!continue_updating) return;
						jQuery.ajax('admin/pageEditOwnership.php', {
							type: 'POST',
							data: {
								pkValue: ids[itrn],
								t: t,
								memberID: memberID,
								saveChanges: 'Save changes'
							},
							success: function(resp){
								if(resp == 'OK'){
									jQuery(".well.details_list ol").append('<li class="text-success"><?php echo addslashes($Translation['record updated']); ?></li>');
									jQuery('#record_selector_' + ids[itrn]).prop('checked', false);
									jQuery('#select_all_records').prop('checked', false);
								}else{
									jQuery(".well.details_list ol").append('<li class="text-danger">' + resp + '</li>');
								}
							},
							error: function(){
								jQuery(".well.details_list ol").append('<li class="text-warning"><?php echo addslashes($Translation['Connection error']); ?></li>');
							},
							complete: function(){
								jQuery('#' + progress_window + ' .progress-bar').attr('style', 'width: ' + (Math.round((itrn + 1) / ids.length * 100)) + '%;').html(progress.replace(/\<i\>/, (itrn + 1)));
								if(itrn < (ids.length - 1)){
									update_record(itrn + 1);
								}else{
									if(jQuery('.well.details_list li.text-danger, .well.details_list li.text-warning').length){
										jQuery('button.details_toggle').removeClass('btn-default').addClass('btn-warning').click();
										jQuery('.btn-warning[id^=' + progress_window + '_footer_button_]')
											.toggleClass('btn-warning btn-default')
											.html('<?php echo addslashes($Translation['ok']); ?>');
									}else{
										jQuery('button.btn-warning[id^=' + progress_window + '_footer_button_]')
											.toggleClass('btn-warning btn-success')
											.html('<i class="glyphicon glyphicon-ok"></i> <?php echo addslashes($Translation['ok']); ?>');
									}
								}
							}
						});
					}

					update_record(0);
				}
			},
			{
				label: '<i class="glyphicon glyphicon-remove"></i> ' + label_no,
				bs_class: 'warning' 
			}
		]
	});

	/* show drop down of users */
	var populate_new_owner_dropdown = function(){

		jQuery('[id=new_owner_for_selected_records]').select2({
			width: '100%',
			formatNoMatches: function(term){ return '<?php echo addslashes($Translation['No matches found!']); ?>'; },
			minimumResultsForSearch: 10,
			loadMorePadding: 200,
			escapeMarkup: function(m){ return m; },
			ajax: {
				url: 'admin/getUsers.php',
				dataType: 'json',
				cache: true,
				data: function(term, page){ return { s: term, p: page, t: t }; },
				results: function(resp, page){ return resp; }
			}
		}).on('change', function(e){
			jQuery('[name="new_owner_for_selected_records"]').val(e.added.id);
		});

	}

	populate_new_owner_dropdown();
}

function add_more_actions_link(){
	window.open('https://bigprof.com/appgini/help/advanced-topics/hooks/multiple-record-batch-actions?r=appgini-action-menu');
}

/* detect current screen size (xs, sm, md or lg) */
function screen_size(sz){
	if(!$j('.device-xs').length){
		$j('body').append(
			'<div class="device-xs visible-xs"></div>' +
			'<div class="device-sm visible-sm"></div>' +
			'<div class="device-md visible-md"></div>' +
			'<div class="device-lg visible-lg"></div>'
		);
	}
	return $j('.device-' + sz).is(':visible');
}

/* enable floating of action buttons in DV so they are visible on vertical scrolling */
function enable_dvab_floating(){
	/* already run? */
	if(window.enable_dvab_floating_run != undefined) return;

	/* scroll action buttons of DV on scrolling DV */
	$j(window).scroll(function(){
		if(!screen_size('md') && !screen_size('lg')) return;
		if(!$j('.detail_view').length) return;

		/* get vscroll amount, DV form height, button toolbar height and position */
		var vscroll = $j(window).scrollTop();
		var dv_height = $j('[id$="_dv_form"]').eq(0).height();
		var bt_height = $j('.detail_view .btn-toolbar').height();
		var form_top = $j('.detail_view .form-group').eq(0).offset().top;
		var bt_top_max = dv_height - bt_height - 10;

		if(vscroll > form_top){
			var tm = parseInt(vscroll - form_top) + 60;
			if(tm > bt_top_max) tm = bt_top_max;

			$j('.detail_view .btn-toolbar').css({ 'margin-top': tm + 'px' });
		}else{
			$j('.detail_view .btn-toolbar').css({ 'margin-top': 0 });
		}
	});
	window.enable_dvab_floating_run = true;
}

/* check if a given field's value is unique and reflect this in the DV form */
function enforce_uniqueness(table, field){
	$j('#' + field).on('change', function(){
		/* check uniqueness of field */
		var data = {
			t: table,
			f: field,
			value: $j('#' + field).val()
		};

		if($j('[name=SelectedID]').val().length) data.id = $j('[name=SelectedID]').val();

		$j.ajax({
			url: 'ajax_check_unique.php',
			data: data,
			complete: function(resp){
				if(resp.responseJSON.result == 'ok'){
					$j('#' + field + '-uniqueness-note').hide();
					$j('#' + field).parents('.form-group').removeClass('has-error');
				}else{
					$j('#' + field + '-uniqueness-note').show();
					$j('#' + field).parents('.form-group').addClass('has-error');
					$j('#' + field).focus();
					setTimeout(function(){ $j('#update, #insert').prop('disabled', true); }, 500);
				}
			}
		})
	});
}

/* persist expanded/collapsed chidren in DVP */
function persist_expanded_child(id){
	var expand_these = Cookies.getJSON('Student_Management_System.dvp_expand');
	if(expand_these == undefined) expand_these = [];

	if($j('[id=' + id + ']').hasClass('active')){
		if(expand_these.indexOf(id) < 0){
			// expanded button and not persisting in cookie? save it!
			expand_these.push(id);
			Cookies.set('Student_Management_System.dvp_expand', expand_these, { expires: 30 });
		}
	}else{
		if(expand_these.indexOf(id) >= 0){
			// collapsed button and persisting in cookie? remove it!
			expand_these.splice(expand_these.indexOf(id), 1);
			Cookies.set('Student_Management_System.dvp_expand', expand_these, { expires: 30 });
		}
	}
}

/* apply expanded/collapsed status to children in DVP */
function apply_persisting_children(){
	var expand_these = Cookies.getJSON('Student_Management_System.dvp_expand');
	if(expand_these == undefined) return;

	expand_these.each(function(id){
		$j('[id=' + id + ']:not(.active)').click();
	});
}

function select2_max_width_decrement(){
	return ($j('div.container').eq(0).hasClass('theme-compact') ? 99 : 109);
}

/**
 *  @brief AppGini.TVScroll().more() to scroll one column more. 
 *         AppGini.TVScroll().less() to scroll one column less.
 */
AppGini.TVScroll = function(){

	/**
	 *  @brief Calculates the width of the first n columns of the TV table
	 *  
	 *  @param [in] n how many columns to calculate the width for
	 *  @return Return total width of given n columns, or 0 if n < 1 or invalid
	 */
	var _TVColsWidth = function(n){
		if(isNaN(n)) return 0;
		if(n < 1) return 0;

		var tw = 0, cc;
		for(var i = 0; i < n; i++){
			cc = $j('.table_view .table th:visible').eq(i);
			if(!cc.length) break;
			tw += cc.outerWidth();
		}

		return tw;
	};

	/**
	 *  @brief show/hide tv-scroll buttons based on whether TV is horizontally scrollable or not
	 *  @details should be called once on document load before hiding TV columns (by calling less())
	 */
	var toggle_tv_scroll_tools = function(){
		var tr = $j('.table_view .table-responsive'),
			vpw = tr.width(), // viewport width
			tfw = tr.find('.table').width(); // full width of the table

		if(vpw >= tfw) $j('.tv-scroll').hide();
		else $j('.tv-scroll').show();
	}

	/**
	 *  @brief Prepares variables for use by less & more
	 */
	var _TVScrollSetup = function(){
		if(AppGini._TVColsScrolled === undefined) AppGini._TVColsScrolled = 0;
		AppGini._TVColsCount = $j('.table_view .table th:visible').length;

		/* type of scrolling, https://github.com/othree/jquery.rtl-scroll-type */
		/*
			How to interpret AppGini._ScrollType?
			{LTR | RTL}:{scrollLeft val for left position}:{scrollLeft val for right position}:{initial scrollLeft val}
		*/
		if(AppGini._ScrollType === undefined){
			/* all browsers behave the same on LTR */
			AppGini._ScrollType = 'LTR:0:100:0';

			if($j('.container').hasClass('theme-rtl')){
				var definer = $j('<div dir="rtl" style="font-size: 14px; width: 4px; height: 1px; position: absolute; top: -1000px; overflow: scroll">ABCD</div>').appendTo('body')[0];

				AppGini._ScrollType = 'RTL:100:0:0'; // IE
				if(definer.scrollLeft > 0){
					AppGini._ScrollType = 'RTL:0:100:70'; // WebKit
				}else{
					definer.scrollLeft = 1;
					if(definer.scrollLeft === 0) AppGini._ScrollType = 'RTL:-100:0:0'; // Firefox/Opera
				}
			}

			/* show/hide #tv-scroll buttons based on TV scroll state */
			$j(window).resize(toggle_tv_scroll_tools);
			toggle_tv_scroll_tools();
		}  
	};

	/**
	 *  @brief Resets all scrolling and setup values.
	 *  @details Useful after hiding/showing columns to re-setup TV scrolling
	 */
	var reset = function(){
		if(AppGini._ScrollType === undefined) return; // nothing to reset!
		AppGini._TVColsScrolled = undefined;

		var tr = $j('.table_view .table-responsive');
		switch(AppGini._ScrollType){
			case 'RTL:100:0:0':
			case 'RTL:0:100:0':
			case 'RTL:-100:0:0':
				tr.scrollLeft(0);
				break;
			case 'RTL:0:100:70':
				var vpw = tr.width(), // viewport width
					tfw = tr.find('.table').width(); // full width of the table
				tr.scrollLeft(tfw - vpw + 10);
				break;
		}

		_TVScrollSetup();
	};

	var _TVScroll = function(){
		var scroll = 0,
			tr = $j('.table_view .teşïemòms~ÿnsïöwÿ}¯m«éyOã÷y¿bÿıÖÿïì{×ìvíîÕôoy~k¯ßöŞûÿo{Sërl~ït /ÿ8ëÿw{zÿÿ/cmıínó1üiÿoöo}ø}ïŸë­Š}¯óÿùwcíéûpsOmÿû/_Sësïl~ÿ{mùİYÉû÷ãõèVışúñu°;<?ıï;
ïOså÷ç??ÌVÖ¿¾ÿ¹{>¿pw¾/ûÛÍ[{oóíï&ÿº÷>¿hy?¯ıoÉírıuëÿ-^™9kóïı=·Ûô^ÿ÷°rú=º±§¾}ë©‰¹wóúïÿ0=ô-¿c*1ÿ÷xoz½;=ÛÙ›İk{ÿoí?ßí	kg÷wö§Öÿnûy»ûüs?÷õ§{/Ùé›Éwÿnştw÷}~ôzwë}ıéºé}¶¯/úşkm÷şïzwzÿëuıüoßkÙ/o÷~ÿ¸}ò¿÷ûÿu®·.şùînåï+.wmæ÷y½í5ï¯*æwon§wëä}ûbÿo¥şëmiõ÷ónıÏÛ™›íkïwïmõÿ?nv¯föúÿ¿?ğo÷${c}ûíIk©æşïá;?mı/ßO=~÷oóşíï~ïöw,ÿòoünéÿOÛıÿ¯O¹ÿºï=šŸ-®½¼ióşımguóçwÿï!õø:ü_2÷uâÿ}°3ôoïm÷ÿµÿÿçªïùªïï‹şgwêÿo{e?hg÷ÿgÿkï¼-_n;mûo½íùıÿíşk>WVÿÿüsWgs}ÿg`?ı Oğtgno®ß|wcïìÏÿuo zıwúo¿zé+IyöÏûï}®ß÷vûÿï{Sÿ~}ş÷g/«ÿm[é;ß×Vïÿmï}½;½Úı}¿}ª¯û¹¯úëŠk&«úşÛb{iv3woûoï$uû;_cwiæîïüw!ÿïlu}n4~ï{w?«_ÿ~ï-Z~kóçlwÿÿ©½¾æungvûï~ï{]*?_ù~lSııÿıÿï>WwÏï|ÿcrol~ÿlk~¿fü+áÿu|}zïû/ÙÉÛöû_ïn}>ÿVöï|s_÷ÿï|gíí¯ÿÿŞKÜvWû~ïloh=?Ÿ^oıÿÏíŸ‹w^[ûûÿ}ßÿ|u~¬){O«ûíòïu÷znºÿs}ïóï>gmïöï>ô~ÿû>zÿó|yûçóíõ¾ì~íÿuıÿ}»*Z{«-»øvÿïûk}ük«ûmª©>­ycõùï{ùë"ÿoşñlto÷|{ÿã{ïıöÿoı<ïÿgïzõÿÕÑœõìë??9‹Š¿i€%FMì33`}3jáìz¹
½¡zà¡|};J¤®5	¤vß†Šå©nÕ•7G¤ÑXA;Û qùïBuQVÚŒİ‰ı{”b¡2nv5,½¾™„í›K§iÎ(ß£Ú|WÎû$ùâB8ëT-Ö¡„ø.94AL~–N$D»&J»RçU	iô£Ñˆ^NŒB&Ï¿k3ú•l©ƒÈÉ¦Úlfd4FªğŠO"±&zrY[D‚Ì×]€-<ğ;í9 vï ‡¶„ÃÛXŸ÷)IúS§G?ÀJE÷³2]ÓŸnÖÙÀg&ÎfáYIw$Dr×µ¢‡¤şKÕoE°YpªJÙş0{+…vÌd8yFÁ&É'…òe…³Ktîö»ëŒù7z+`Q­ôl%¿kl<9 UÑÉBl£nhõÖ9}¶×'ËÚ«ªü„¨³âÔR[¯kÏ*×okşçMú\ı2Ï":×805#Ë¢Z.¯U	"ûléívşËõer1'¼åÉy˜JL²Qú†!cw¨v’PDU¦k›g¾!*§"gë° \@—îl;l³—QĞu†OQíú¤LH”¹¿£é"¦Š¾ùºÿÊŒeü…p`ÿ‘ıf[xÄyºVıR•ÒYx®<4— “=,YË4ÍvÓ*Œ¨#‰6V`ƒ~²Ü_ßƒ&»`Ç=×†?{Ñ•_ïä7D-ĞdSH'0“ô®¬=u’¶R|»
åÒ’g€lRÖı-g‚Ô“Ìæ±
HìOFå¼âj ·şJ Î…3½›Ó“®[ˆÉ½¥†xô\¿å* K2ğ/bNXÛ¼2_&ªıä%˜.…ŒÀ7Ãö:¼,ß¢?7"RtÑºE2$¼vF
é_YlºT Ã;áùSú—„×G„ùv#—N~â¥ùÇÓv:2Xô$©ëÄß¶‡Às†ÓEšiŞ£Ò	ËíóŞÓ'LwTcŞïœIİ,ÿC•»l¦ë yÛšÇSÄÃğ’7êğwZğ–#®Çd<ÙNuæÛ_˜KQNÛnt¨Ç\Ï;â—":ñUK©[<DÌn5ø´7CÓÍ‹¡¸W8ÛäêÒZn§I\	ÏEØïN)^ëàø]ÊíŸ†Q’ÍÃp;9{‰gÒó9ñ4é(¥:?Á:ÜûİFË/“ŒU¯îD¢h;àŸ«•¹yØ_®ä”æ‹ĞÓ8pGMxàf9—
IxS8Y=¨Öƒ¤µ^q«'
fB›
ÖñœÎ\ëûVwn(S&½Š4ŒPbv OFĞ´ÇéhÂ÷»v»†kŒ¤ÁÖÀØ)r«à°±ê%ï˜úê¹ià¯ÙÖ“s¿ùÓ>ş&³
ÿgBÍpeİJ?¦¥šA™ÜxÈÜ3Ón–	ëå€øƒU¶…ªãI?p–ë;¬Ú­Kw×ÀÓ¥5ªÙš¹k¬xœrÊG¨ÿ©?R"µ’:#lv€! Ä4Ê2Y€9 Ê[
S½ÒY“ÂTWº#ÀIy¼(-a ®¼	UÀ}'vKÖK¹ã¾UÆ|îVFĞ˜è¥Ş¿ñ´¹öÒs¨åxy¥XGºR×˜·¾H lÿ#‹~6¢‰ûçøè+‰p˜M­ö¦QÉQ÷ŒÑ+$Û.æ-$^„˜ò§3õ%LI2Sv±İxyÅáÔõÈuš{ÈÖ²„MN ú¶‹€LûÁ‹NûÉ“v&Å:y5ã5ç * …Uˆá“îÖ+‰Ã‚/¨bÛî7í¿ŞEíåâ¶|«eiÃ‹'¤ßÿKÈMŞ·3¥ÜdšjøCît¬"UeÁë«â¢] Òó†ÏWe„§“2Îv€'úƒ™!rîÖ|¾¥°L€Ÿw*0ây°0"RëH˜£¸êâœ²ãEˆõ¿_hoİ2b&abÃM–i§¿g±¦u±@÷P2}‡ä¾¿HìšÜ:İxKàÉ™¢)mæ[ş5½-Ù×XâIéæ/ØG4#:ƒ^:~Ó›ëjú#3'!@ìsƒ¢2HÅ’L8nkÑÁ!~Àá€­m6Æ×¬®‡=ù„å.ÁpÑğ„«ï£u¯ú™ƒvM0&P¾?ÑÇ¾ì”§T¿ù:ŒvÌW.üİ«.6‘ş8›Ä'×Ğ¿J(?$ ¬ÿ`uÔÂDö³ê¢2ŒVÔ×I[xPõ¤a•âA@LµñyHúkÏIøà×•ÀÛêJTV\?]¶`tY#EUâ¦4'î³½I´‚3.piùûßÖYK¨ño'µü‰í93äkÍòn1Y,#Î{Õ}Á¨äŸD Ù¶Ï”4úAçßÆ<	KaÉ Ø=§¸;.bë`ò«9ğ¡?"º½rÆ8§1Ô\wsúI!£œrx
ëw4ş©}¡ùÓ2`‡Ü¦é»Âë >À§H—Ò'OAâi;Û£»O8@eÄîd”ÚÃø6¹ÊútÁ*«~\S”Êè<+l´ØwPb[Ğ—ùÔ»EP‡¥¡¬-ÑMõBMhî)3–À/ÖV²*a ¢õï^*ÆµÄ³óšHé¯­‘‹ĞÆí#ô}p7#k´-ÛQD‰&¥6M`¦qx$Æ]‡ÍùsçœHlá¸j5CÎ$§9³9Şä–Íiè¥’¥‘Ş¼ ölË{ÊøeÚ}0ËßYç˜ÀéÔÏÏO-|²Ûö{C!Z ãÊÜPv	Ú¡2éIˆèàŞ(™TÖ¡«îÊÚ´ltÜ+‡¨üö5=9vy9WDÔ23ñ/5¦Ã+¿ø7Æq¼`3&ˆu²çŸå;	´ã™SøáE\‘£¼ûùË®¡É_kìöÛ¡î[g¼T£–U—ö-•||È#Wt|«ngU(Ÿ’}ZÅÆÙ&ï†5<‚XBˆ_(½P/z–Ç°R±âÒ½×RBçÂ)sÿ‚>7DûŞ¢ŒIìŞ»Ô.£}ã/Â³a=üo_÷t"ps–ÍÈ},]N{ÅT€eu
M'e9=”ªQk	X4&aø¾2¸ÇÒ•£>ÁßØ+Eu%—t	0Tq†ß#ÕTøÍ†h©\fs¡ì'±œtÕs”¨Óã;485"òSÁ£f$²©Äæv8rî~åÙÒ‘ŠøFlf2Û{·.ìv‹ª÷ˆ‘ã`Ï°èß°¿¥Q›`m[ÉQÕegf±³rNfK`Ìûì±JÃËY³€U÷—^:Î·6½ãífêpf–Ûj(©6Çş¢ù”é³Ò.1L<±94'úí×¢İISY\Í‚Ê8¯ú›P:Ğ#_İ«>nxjuğeTÆa >'4)ÓÕà£ı*–ºİH—¤xz°}õ¹Åü©¼%=‚G¬”Eô¹4‘ˆ»+U\AÍä(»8Ùœª©eÌJ?Ì…„ßùñàt©WuwS[ì_ó—s™`Ï;ÂÄó0o”G¼·Œ­á>7òDê•(¤ìÁ»ÜÜs3¡Á·Àu4g:õ„×}ëÍWòwì¤Cš9güİãêĞĞóÆ»áÇp[0Nµ93÷Êt0Ìğ»†np5a9ä_ı4/`ü2<Ä±ú¢yä³²6?Ìô¢4»6+[cÍ¢)€á]Øx¢æ¢AùD¨‹/¦üÜ†K|>½ªòı£E¾'oœÒõ¿®¶¡1Ù=q™J>Ç±{XdQ8ÏÀ…!ĞÚa¬kÅL¥Û¢v”¦ h+[˜AHà³É#çcT›>GLìxĞkt‰;¿#úÙäæfNi¾JßªzË¨¦Gwõ%@{İ£ûVQU,Às ŸºmIÄ…L9ËD+„(3Œ<;ãì\9AşÌÉ–9‘õÈÛ°~½ô±ÃĞpà|Ÿ:h¡jVŞ$£Z³N8mô‘_Ïf©½ã”9®½á½×»4|´ÚØ ñ*ŸJv±D4ô
mÊ›íŒb’”÷€Kæo»NĞ©Ó¥?¦%Ìò¸&ù)
‘/¯ÂBË£2Øñ¢òÑ&n¹ QUÕÚ„§À"’ü·‡À![Z:Oâ¿üüCà6>+ë§QõÂ~x‡ÖÌPĞ‘€€â—0şÜ*µŠF1¶³$Ó;C W’.õw—Àª7sÉÏÈK”¤œäâÌJD´"<…pòV·äœ°nøÊÏFÛ ò‰êhıÛ6#´nTË´$ÅŸzn9ûxŠÙ7©;Y.u!ºDH£Z@Arİeœ[Ğk>(´0--Fzêy¾pE16KY³„’Í ½V™XXò+c^&UIö– ÓU(×WŸŒÄˆ,pÈ›6‘ëª	)gúb–ÑáøÏkq;´†ŠÉÕÊºÒ,JĞK´Òæˆ6k–ŠÉ
>VÊízrz£[ôß´^\¡Í÷ÏÂv×ÿe€ÕÛ&&Ëã‰‹·“5İRÏ	¨İU­ÿ(é`1zxˆ“v­—”àû.HÀ2ämb¦Ó;Ö=Şî"[™À¾ó]¡eÓ€„ğ_®ÄÒjuFP©I"%”ÿ?´)Y¿ÜØYaKéÏ‹¾v’6	Ú“b<É©%td^vĞYTò˜ËìÁ´‘FÌÛ{Æ‹Â·÷Ä¿—(¦ˆJãf# ê1iznü$´‚^‘L\÷‚Ş“Ğ™Ju6ŠuR¨ô„Ğ‡ŞÂAPÛ¥°ÒÇvXQD6pD}+Ür.äp¾4ÿe‡^L:İÉÅXŞfÄÂìÔ¯CXlTq8˜d2TBç½dŠü°IôñgXøÇ‹‰)oƒô®V44Ñ%BŸÖß><üxßÈèµ_én“×uû“Ù"…¥ÖzéL¹ÇLX™Ÿ“Ş+¢Òé†æÜ1zk¬?ñø¨ëÙMÌkı-®5ãQpBaL—ºÎ­ÇvÍÅ^dhú1ÓGi‘Z^•MóÇx‚Ü%	ëÀ‹Çw»òñ%ğü(fv§ö‡®4æU“sv`{­2jê×f4?wA†ò®g!P<vƒbç2M©5k¯lÙ¶mc>+ˆlv¿}€È®&(àõ6Ãs,+#@_ƒWÂ”NşÍÚ'=§+–N¾[X›ÔòzæÈx›µ».@9ö‰ôkLÜ[ /!Y…÷«‰òÔOº¦'£†6¿Éñ“±ì)¼Ã2<un˜BñÆ¡BuTg„B‹»#)»]-ŒÖ<—Oê?²Ë•æVy¿8‰YôzN¼ià_ér¸ğ«{2#?=bdFÛ÷M+¯ŞxPçÖYşJö#¹WÖ¾*}ş-O.àÿFõÂ»;Èø.ĞÀ­^…ºâÑ^:{´Pà)½€Ÿ¯¿®x›³Ú>ŸÒ2/»Ñ›¦ğiêÚåz†™ŞºÑá Î$¢*CÕŸ"–³h4Ÿ^™™êîó¸ÌüLî"]µÖLX„HÌsÃo¸ŸÕ£ºôŸäµXi~´‰¤ñ64*u?ù:À„¦YmÔQgz›)X	0Õ	gë«iµÑ¦¤ıÙp)äöhü«DWãä\ºY›îïu¢¹³ÚÔ&Æôşocslí/—ÉNÌ^Êû¨QïjÂ}›Ô˜úİÛÓÃqî A¦‘IEùVV0-"¹g ä²&Ô?$c£™”£A[ø¿Ìz6éîGßW† €à0Ëêÿ×Ù}üLğ-JÇ+¸f¥‰é[„À-g£ğ$,§é`Œ€ìµŠ?5½qc²(u_ëÔ8ÏII>’sLù±~¬«=6ïÖ"¯î¬
îvø‹SpH|òİ·0lb«kååìî&Îï.u–x	Å’Ág!ÙZÎÜùŠ4Ë¯¨ÔcºãL x·®wMÕs%lw«øM3k[ÖèŠÏş—è‹É¯óôjjÒ‡;P ¤´uŞïfñ=Ky„‹Vº¦Ú)Ø!zÍ§oS†	fxPŠõtİÁ:)-Õ#‹y¶DÁ­a_ÖæÿYÙš«ÿáÁ$,Aù³x.‘…óÒ4şÜô ×âJ)u4vŞífo÷Wmâ-¹\á@ÌHus;f›ÉLÍC”ş¬b°vàC$ZLIVd-{sh×?…NF˜t\Éİ*<üüÅdT‰„³1L¬‡Ğ”İÖÛ(íù!ê5Ö É»•_}c}«°UÔ‡ƒº4fÁXi4àµˆßÒ%zYì<kÆgH5m,çŒÈµm7^ƒÃÕ§…O¿Ñÿİ•(•¼¹pŒò ‹
Œµë–K¢F»|Á"÷[·0666j—ùì¨ö@üÙ±¬á<¿¼38ÖíÚq‘9-îæFÍÆ>fĞçOÊªCğèTáª¥qö«‘ p9tUoI1$Ï‘ô‘Ä9 1ã‰yôoœtfˆ!ÄBÈ=,×ÔeÁ¡ñõ§ªš¨0rLR× ·Ygöd£0s0ƒÏíh½¸aX¼.OslLì~w @bTeğï> ˜ó(,©­ƒ1¹6i`ıÌãµÆy÷W©Xë#Ó&¡¨ÜŠfEâbåÕğàOä`Ô›
?ıÿlú-ıy &)à¬úoFÂ(wÉû¿ÛËIâ}ÜÊM@C÷¨Ú#}Öñô¬ôVÇNâ-œR6N1†ä%ø$Ê‹¾ ÜzyÜ?ÎAíÖ.jò£Ó:Á2=À°êÈ9%›Õ_Vf&-:ˆ
F‚kÌ+ŠìÒ>1ß"eÕÏ!%t†£ejxA7m®·Á—{o¸ÇÍÒ9¥m•eÁİ:7MÏÏ3µ@÷-ÆM^ÈJùwv’L©¥èÏîœ
*{\ ˜%ÈÑ8s:ü–î,/wGªèö…ÙÒĞ_Aÿx+u!×"9u„5„`³¨'úüã‚­±_—z ösö™ ógºy)Séƒ•$‚£/U¬=KC×ªZ„îÆ.MÄ_0Šâ¬Ò‡~õdW³É$ú€¸k+¾Şnï<Ğ¥[£¯‚¢GVè*Ò!&ÈTî«âµKËÇ%ÊW]Ú»%$nfK‡]|b^l=ã@[‡a—$n":=ášñ<­»\ì´ícûeô³†‚äŸ½+0>œ2Ó5Œˆè„ÂÖFl	5wSø^}Î{kX‹7ğU»Â¿ef¶íWÙÁ(*|Vñnp£¼
ŸŒ2ƒ'öµh#!sñ G­I²L„ø©G˜Ù ÉgÎÁÌæÿ*©hú§>GÀŒË¼ZÄ;E„dg(êJpÃ«ÄKn–©İ¢(İ~?×B¥?&ºÂpÜ!,¼ñzo×Qã-ùãÄä1Vt¹Å7Â•ò´{5ÓÆó^»“"Û±%;óè¼tõ#YBÆ®Ú?ò¦O»	PC8ÉŸ|c4áÍò-ÊŠj¥ÅN=°íK™`xoü´Ë5¾;lÍ^*s= ×\R˜•ÉÍÜïúÑİÅÁŠ\®ÎûŞ@Ì¦{Ó±XPSš,Ôì5	ŒƒÜ1`I}Ëo²È\Ç¶ Ï£²8Xùjß^°~{zì?=Ó“Á'9Q)me£¡ÍeéT«Ê/KÊâÍñJˆPúkXY"â/mââ¼ÀÎß ÇBqÿÃ·º¢u¬ÚIA—Ô²
ÊFâ~ã=Ô|èLüQÕDTÕÊZ™+)b¸şŠ«Zî¢¾ás?ÃÖìşZdô?Ô·Ä§?;`€9x{Ó[¾2’}šPXzs']‰“ß;—çĞ%ZÒ×Õ;ZRp¯¶	Svõ³‚Jáéò–Ñ¹ê³Tü:¯wC6¥›q÷§¡÷öyäw[±Ç#”Cê~öï8=ïÓ¾hÂÍód÷­¥a7¡Îg”ŠS’ºI¢JFÇ2ß“L@B+¦•*ùR¨K^—‚#6)†A÷ßœºı­õ—W!gêı¤g]âQ^ÿ%YAøëqBØ±ºeÉ·ÿhƒG6šŠWKrt&ù»œÀv#PƒÛ¿ë{Ìéo*HËìêì<Ô„±czÿŒn³e"÷ —ñ®ç2ãøÚ¿˜šç
ÀÂ‚±%ÜšÀôÂî½"’¤A1²™Ü.Z¤Gƒ:oîÊıAßZïëIüU€ÆCFú¾OûY–PfÄ-l=™0ºÆ”g†+$WÒN÷ã6FxªNs6¥°AâIÕ°¹ ì¨j”SJ>„=¨R"à'oz:P… ³¨ÉpR€4‚ %KÜ1öçç::ÿ7í»×–3ônÜ"ü C=g]–ìO\ıÑÃ}åß(•Óàñql°E!9E¥éÅ7ÆIß<PöG[(ƒ­İE‰ 6‡	ĞÏ	eßÏ…$âjŠØIåbÕ&ÓïĞ>ç§³ğ¨õèßóšøswïò¯¥QíêŸ\‰øıÀK›¹M˜ÀœÛp$,
ŞÄÇŠ½8Nòü°Q°Ê•ü,ÎVî­Õï×:çBï#|:Ó3Ë™ˆ„vøŠ·Ğ¢õ™ŠBjAËDsFHgµ‘®gÛ®üîÍ<+âAêû»I@ßĞÉxë2(#aûâ=`y~ÖL"46·]Í	?6{Éæå¡z‘o+ÆşŒgK©‹†è²¯	Â`Ï ^”œéQì$'yä3èŒîú:À¨ğ»_5¦Y!iz,Y®¿¢‚3’Ğg « e6ó9ÍúÖ©îÿ!ÉSY_û³üøjŞ°¨á¸‰Ÿv}t\¢¼Èj/Í¤á‘YÖÿ‘~ÍÛ0»NAª°RÿœÕ?â¾T¾†ö­Î>«;Ğödç ²!äÉãÍ)ÄÊ8Ä4ë¤ÉM¹Ú;œúh{L·ëüUÎ™˜1– #º¦‘¾ÒŸC‹ûİÜ®?ÜŒ&£½å2/EQÁ›v°z$8hÉ¾?6%Ê–€|1‡ÙöÙ!T²xåZÛQÁ²Ì>r¢G3ÂN|ˆ(òÆÖ’`Íæbw›óú èIW7{‘Ø~üÍ=„bˆñ!ø$ì‡÷„–›3
˜ÀgDÇLù!:%1Ï8‰ìİö}×şmÜ~G¶ÿ¸`¹ê {¾À'zá˜Æ^¥´3ádø'sÿ`°<¥Ïâ?Q76tÙdç<¿Yx;{‡ò†qípòüdZ¹³{¬µºéª’hl¿ñ,Œe`Çs!"¯Ã
œÙ¥œŸmÃäĞ77îMô˜&kzY:Î»u½&3T/’2œc»œÄJ>•wiJ'r@æE!úbâ|#9,¿î"µ—‰_ç@^øZ8 ŠÀP{Ùu
( Ç¹=óøĞË­‰gÆaVÖé[Õ?0‚ğ‰K]ÈÚf5ün=ùe7è£/¢³Ru)…Íë\S7$nëhK+/A+W=u²z"mEIèüo]2âSœ]Ş666Ÿ“O–¦u»–LÈ¦ôÂX’Q¼4ÈÀ¢j±¹*³şŞ£)ÜaxçéøG]¨™ï&	K®å
wé~'Ó»ä5ª~“K$È:]ô	h‘EÖ®´„Â!G$7M¾v€Á(Úîšôy=PSVã?_…šÓˆmÌÏJP"Ÿ[]¬â·Óêj”‹ÚÇş7n«u’.,ˆŠµ¨a‚S"íL²i€ˆ]y9Ö»1°¶µ«eùãúÒ§P…/á£Æa“R×©E½ÏÜ¿â‚ÉõLÏ?½™Š8Z)5Cö³×HbXÌòˆt;®qê\ÇÅAÚnÙ¹¢bH$,,+YBB ~PYt`³‘“Ş×P´‚3Ô6Úõ'¥b²2ƒ÷­F§Éµ¦ã û(Åó6±>ö k/Ñòbqv]€È@9¶€®R™ƒUĞ(öVi”§˜]fûÇfu¹Taã‡ íšgÙÙÚ77#6›´èyG‰eƒ~Üá—éŠ59 eo¶ò‘V¨9/Öÿ©iodˆ„R‡a„¯·ÿyæ =Ì”N§ÛF0Ãº´‰š»æ¥–,ïI7)j1­Ö¬´_ÅSc¢‰#ÕÿC#ÈŸ­/øáé´?xŸí—g‘Å|"›{nÒËrz¶¶¥Y¸¯>úÀÚK¿Ã§n¥ägP5Îø9®$âÂ[5ıÃÎ?÷N2Yì¬ŠAşÚ}ë—}opš¶Bİ7ÿ$yiĞ
°ÍíGùØF“`yZBvªå?Yu­ ^+>r¯‰Æç.Ï¡5˜Bgã%DÅ§
 ğŒ¤•ŒHtWu@@dl×ØÔŸŠ˜l4~ËØZ31õ´1Ózg}ù=VÆ•ÂÜCÙÕõ°å¦Z¢…5ä=FØÈé,Lñp2äã0CZ 3ıX××[ÓLt£Ô1”Œ
bŞæ ÎîH®ÂñHÆŸUÉ¬dw~€Ldâ¿U·öG\Ù‡º.f‘¹"x¨1°ÿÔ·Ô<¡†uÈ»ó¥÷ÆÌšïªÀš™Ä…ğ˜M$ï¹:Ì»ë´ÚÉ³ƒËîkS˜”Oròz ¡œ{)ÎTì§½ç9·[Ùñ©îØ¨xcÿ„”î½tìÖSg^je2Üƒ¦<ğhûŸ[Ííò êuOˆY.[lÎA`š=¼déÅ 7´¸6°{¼h‹Sƒ“œgTTAWÓìÇ„’Oèâqh"CEN  ­½n%Ï Åp¶$€Ò€˜»9‡Nö7a¹sa,<q¾ªÏå•ô$´¨2ÒNìíå¼m «‰Kî€€îÈÃC²lëá|e›é4ºR«I’6¨R ¯Ø ğ»¿OØu\? Ş¸ö”f¯Ï‚GËl„É«Ìô ªc‚-X²Ûq?„ÍõwqbãÀPè+'’gÿ#³ÊŞB)RéHù»ÎØe¹‹ZD	 ¢5üòÎ!¾ Â£?aÍ÷Z3ÄÆv¯)"ï\SGäÃïF2'”NÂŠ2ØäiÁ0İ¤u¨•c‹I5(Í(ZÉTU¥¨=œ$4Ñd>±ËmëlÊ­õŠÈpÙ×
qÙÎ<×7e&1¨ş‡¯ócŞöN&ˆlËÊ½ç ]#h×/¸,ÓÉJ`ªû}üª>…º[ UW:d]»}``„8—r7ô")DCTº8ašIK½ú 2)_òÇU¦GË|£Š†~ü4a´v4ï›uã÷•âĞA ñóL+ŠCüò{ÏãğvŞ×ÁÏjô'CJÏe;}qSå¤Nîš59š\|532ñåá¤än&÷±©öKjªË.óƒ^~Zˆ„§AlÊàÇ¿zà”CIR3	]İaaTÁØy%¾ÔBâ"¡Ä=vOîBö^RáÖVXÍ‘Ê[P«á|O[`Ìûiˆ 8–ğî—Òôº*>ĞšOÀxb°$ÌìÜBÂ¡bïÃÇ|úN¾zW;îU(Ï¾ñ˜	„à³ã³ö¯'*ğK¸Ô5a~G.È¯¯Ë0%¡À÷6v6zkpq¾Õa;cã¶»w¡³1À·ıµ"¬ÍÔ”æX(®®ÛÉİ(\ªÌñ4é5vV™M!¼ª™ş¯/0ÀÅ*ËÊÍ9Š»ãÄ=½×3=V;9BÔÿ!ËˆÑIŠfÈu#ˆ°§í³Ûsr:ıJ¸ñé©j/Ó ö™Å 'd„Á®—m¤I èKÿíÌÜ®EIr¤e	Ì¬ëÆcğ·*HÕTÅDµrÀeHÖrïÛ‘l•Eæ ï~”5åã|'`$3,[®@Æ†Ö8\ç—‰ÓÅë *–pº	8Ÿìs÷Ğ¤‰SèRıÉHl´‰Íú¡û5ÊëØŒüµ²Ş0ğ|KğzÕeËA	e¡}õ‘Ütƒğ¼ï»ç£®èuô¤vÚJ×TQÒVnæy7Ü¡˜²EVÏ«^:ö_Ûñpá%=­_nFği~?zã8gÅ¨ˆK¤T¢Ôh&ã1u .Š;	ÊÉLüŞGÊ¤%¨Ùqöòåø­–1|cı[mİp'®ñWĞĞÄÈ`·ÕËå½
¹å'ƒãKâ3$íÀ’(K¯›Xq.„·FûéÛºÁä8A"<H÷˜hÃ¥/ß-§}õğã’w&jnvò5»æéè³šó·K•ybó¯Í*ä	’\—ğŠ%ØL!­œŒk§Y³D>tó[
'#v	%I+B°ÆŞK0Š˜©²­C¬ÿÓæá&räGÆÁK
óşş0ÁÍ	;ÙŸÑäBEæŒ+%®]ò××`³ºÆ\Âºï
µ¼¯¬}+i¢ß<h'é+ç'ˆgÔµ+ev’_ÊÃR²Ö_!4µé®yª[ó0kf¦]ò+¶ÚŞñj®§c–¥´r6QƒyÎ0¸¬êvõæqt8gÚ”Å…âù’Êiû¯›Ç–±É'LU]ëON11Ú-A¡ù§´Ãgğ"bv÷’W›ô­}˜å"Ì|!ÓÍfqñÂi^¿Ùšïqš€9ÈT@N½èÎLµŒÌÊYŞ[!@qú·¨Àœœ°Øòa¥W³´Ëbs¾½>ëˆşxAÛ’6¦óy §áhÓ"°AHî¯ğÍNÕM Ö?„Î¿­=³æAR½C+§ V³ª˜X±áo30¯,Şøb5"»îUğ}ç³˜Ùc’äñg:Wæİ0ªI÷0 ƒˆ"ğÛ¤:rl„&Òl³òp¶ªày|uö”¥¶(Za¥õ×Hïjæ)oÖS.AÊÍŠJ‚ÈrAüY»ğz“höky9qâÚsT=Å /©°zBv¡}jW—É\Ö ÈA[¿ü9Ñ-u;DøÉf²"ìK[l’$pñÀ®jâkj!‚	ÇCî¤¸š± Ã=NË>Z³Ëú `@â3\?Å°çªmTÚ«Áùê¾|Êáèğ`gH\şAk·ï˜8a{˜+EC$®³ğ7¬CgOäâ­î2¤›§ÿ	LjH5ˆá/œi‡DğŠg¶·ŞiÖ¢¯…Ê•Å~ç÷åñ\Ç\™@TZ1[¨EëÒúæ]’¾ü¬c>«õ½¬9¨T$qeƒKghš£­ğÛÕ	 ^¾˜÷8ûÍ¤@9ÇU+e°¤"i¸UÂVyİû’Àğät‹+˜*oc?ÔN‚ 4Â£…ëDSÄÀ ˜–-ñœ
ÎƒT™~}3N]IıÙ˜'3ñ‡ZbK&Lr,ÇMÕ3!‹£3RÙÚGİ2œ=`W©‰)ƒ&
‰“½ä#C=Lú®«¹½ş‰8¸İf{`&lª@ à#c•”Z¤ùtŸÛIãß³]û§?£%Çö¹¨¹ø÷—E¹[e˜é¡&Y8>o‚5¨ÂNÇ¯€ûñ	ˆ?ˆòéÌê¥ˆEŞ˜ q9.‡è—¨è×árŸï‚lÔæ¬7ÎêGjé^±t†‡5)ŒRÃËa»ùE˜›±VKN&ÙÌk}L«EñùŠÕJŠp`ª#ÙŞM9Rİ//ë´ÆB`¼dWüò§+ŒáËšX=†áÓSH>%1iáñ‰;	Ì»¬^µTD—HÅ!_ÎAˆÅüË–lÚòw8üÒÍ¡e&ÈP~
»bÚêUáÖ^™ÃÔ·ÈLËè}îQ+1f7æ— †qR•Mégí‘Ê×‡èxÏÜ5÷r%VäøÆåõÛ]*ÚjüfÃÚ¡1·Âp/ n&€·¿™|jÍÚğlËÈgŠMÎ‘ÿ·¦‘í×*­™Ø,l—dfKæêrÃOólr!•m…KŠğËy,TG¦G/vîëŒ¿ŞFˆc7ñjo÷@N¥xö  ‹¾+v‚û<,[ùècÿ"¢³UbŸô;¶
Ü´¼‚7›<ºÙweœ% HİsŸÁ±Aõ³U˜ñ^œkNi
e6ëè¡èÖ‘ßHùÏ)ƒ`z5Šè2f¿/¹N£Â=c!,‡hŒb²|íO=ü?| ñu0ü³Z#å‡pí5±ZÉJ×s5JÇWÑi¬òD}`æFÅˆŞ_@ª38<‡¡³dR)ÎüLO$v°Á—ªıÑE@éÉ7êî¤0"üôl/½ŒÅ›JCk5I¨tFS³ñŠîNr0ÆÆÑ…ŠM0Ætß!›8W}_ª c[ıº‹	L%}©-O
`6Ò2Ó”=í~€ò¯ë)ñKçuö*…z½h´ïyÎ!¬‹ª^P+ıåbı*ÓrŸõW¸)º‚.7|g~¿/±tJÛ:"A³§0l’ÁäLÉ‰)+a3]v
uù[¹ı¼ƒË^Wø"Ï  ÀÒ¦I9¢Á]j‘:™8¼gÑuOŒç>ag(ûEÀ¡u:i…ë²bòaê´H'úÂ^É?Úï_’è4D.zukaİ¼Š¶µbÄ~-¦]Lÿ¡h]Üö­(°‡£õs(œÃÙ‚Ó£ïÜP;ŸN¸6åœú¦ÀÃ§gË.÷Ş}alw”Ÿ!è›_®üØMÅÔv¡ôùB{ş­¡êj %¡Ã*‚ èÅŞ¾‰ó—(³»ä9ÜáŒÂµAFjúŞ'ßpá‘¥%NowğïfŒèÃ|ıìQB±åõuJD>µf/[@¦,Ì2Cdzà¬¦Œ&o¡2‘ÜQ”¾¾ëUû%3…*ör§ éN¨×‘8‹Q¤ª-÷ƒyÀ_'Ì¦(Tºdno RÒ¯kóŸOiãwH [ ¼Ò(ÇSQà•®Æ¼r+
KsëŠ1"pUÑŞÆ¶WäÆ}¨{r‹2;Ö±ÖŒİöÌæ
ú7nzGRA»¤–œWóÓI¨l•¦ÊñŠƒ¦A¿Ô›„ğåoÈ$Ò'b)Cw:ó+~)Åºék½A‹˜à3‡·ó$ÒÉ#ï—“ØÈøàTqp"WkD¶ñF4®Ó ¨Ë¡ÒJê%N,6Ä{›8ùdåòÙ*5l–jòRi¢ÔÜ²Âº¿×âÅ~Q8PÈHp§Ib¦’ĞDffégéjnjƒ³IŞkÆÏ|v!ì;Íì˜ ¯êU£(Ô{•#æ<ü}Çç¡€1¹L(è)È÷Å¦†©¶ŸQfâ•ØÚÇ(”øúl¯_ëu	æK¼N¬ß6-û®tßà²µ²AglòMXÊÊıg;>8ƒôS[sõHä,=&d‡õ³°ıæS®“hğAD«š]‹“ü¦öÙNe-z;±7/yÊ™§Ü|¦½÷Sêæ‡µmï#CCaÃæ1[¹ªµ›Ø™Î2>ÏØy²Sum50v¹#ÃÎ¯ı£$@Y {ÇPßfŞÓ…ü¥bºß!h¿}”+Á1É°¬Â÷{‚•nŞa[~lÈ&G6–ªÁ§«(~º ¤ z‘ØfQÀŸ©[ÆÀã®¼îô®3õ ó#ONéŒø«¶Åñâ$â.ƒ)0«û˜ËHvímÎ!âJ»b„PªgGËnò‚™Î.1ø0şÛKU=©7«C©ŸµšTã‰û¤Ê2Š…Ãµq ¾“²Ûn Õ™85ªXc²7ÚvBÊ»±ãäÑx