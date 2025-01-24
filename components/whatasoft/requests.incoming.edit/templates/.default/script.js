$(document).ready(function(){
  var block = $('.request_detail_edit');
  
  block.on('click', '.show_block', function(){
    var type = $(this).data('block');
    $('.form_submit_type', block).val(type);
    $('form .hidden_blocks', block).hide();
    $('.'+type).show();
    return false;
  });
  
  block.on('click', '.set_date .set_day', function(){
    var date = $(this).data('date');
    var parent = $(this).closest('.set_date');
    $('input', parent).val(date);
    return false;
  });
  
  block.on('click', '.item-header', function(){
    var main = $(this).closest('.accordeon-block');
    var item = $(this).closest('.accordeon-item');
    
    main.find('.accordeon-item').not(item).removeClass('open').find('.item-content').slideUp();
    item.toggleClass('open').find('.item-content').slideToggle();
  });
  
  block.on('click', '.toggle-blocks', function(){
    var type = $(this).data('block');
    var toggle_block = $('.'+type);
    $('.toggle_blocks_cont .hidden_blocks').not(toggle_block).slideUp('fast');
    toggle_block.slideToggle();
  });
  
  block.on('click', '.call-transfer-switch', function(){
    let $element = $(this);
    let form_title = $element.text();
    let form_action = $element.data('action');
    let s_block = '.' + $element.data('block');
    let $block = $(s_block);
    let $title = $('#call-transfer-title');
    let $action = $('#call-transfer-action');
    $title.text(form_title);
    $action.val(form_action);
    $block.show();
  });
  
  block.on('click', '.hide-block', function(){
    let $element = $(this);
    let s_block = '.' + $element.data('block');
    let $block = $(s_block);
    $block.hide();
  });
  
  block.on('click', '#show-sms-templates-btn', function(){
    $('#email-templates-wrapper').hide();
    $('#sms-templates-wrapper').toggle();
  });
  
  block.on('click', '#show-email-templates-btn', function(){
    $('#sms-templates-wrapper').hide();
    $('#email-templates-wrapper').toggle();
  });
  
  setInterval(function(){
    var rc = $('.request-column.right', block)[0];
    if(rc){
      block[rc.offsetWidth - rc.clientWidth > 0 ? 'addClass': 'removeClass']('right-scroll');
    }
  }, 100);
});

function addElement(deal, el){
	var d_id = $('.was_bs.info').find('input[name="DEAL_ID"]').val();
	var cur_el_xml_id = $(el).closest('.form-group').find('.XML_ID').val();
	var cur_data = $(el).closest('.form-group').find('input[type="text"]').val();
	var cur_send_data = {};
	cur_send_data.deal_id = d_id;
	if (cur_el_xml_id == 'CRM_WEBFORM_LEAD_EMAIL'){
		cur_send_data.email = cur_data;
	}
	if (cur_el_xml_id == 'CRM_WEBFORM_LEAD_PHONE'){
		cur_send_data.phone = cur_data;
	}
	$.post('/local/pages/call_center/updatephoneemail.php', cur_send_data, function(res, data){
		$(el).closest('.form-group').append('<br />' + res.result);
		if (res.success == 'y'){
			if (cur_el_xml_id == 'CRM_WEBFORM_LEAD_EMAIL'){
				$('#email-value').append('<option value="' + cur_data + '">' + cur_data + '</option>');
			}
			if (cur_el_xml_id == 'CRM_WEBFORM_LEAD_PHONE'){
				$('#phone-value').append('<option value="' + cur_data + '">' + cur_data + '</option>');
			}
		}
	}, 'json')

}