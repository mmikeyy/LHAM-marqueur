<?php
$data = array();
$data['result'] = 0;
function fin($msg="", $ref=""){
	global $data, $msg_;
	if (is_array($msg) and count($msg)){
		$msg = $msg[0];
		if (count($msg) > 1){
			$ref = $msg[1];
		}
	}
	header('Content-Type:text/html; charset=UTF-8');
	if ($msg){
		if (isset($msg_) and array_key_exists($msg, $msg_)) $msg = $msg_[$msg];
		$data['msg'] = $msg;
		if ($ref)
			$data['ref'] = $ref;
	}

	die(json_encode($data));
}
function succes($msg="", $ref=""){
	global $data, $mysqli;
	if ($mysqli)
		$mysqli->commit() or fin('err_commit', "$msg / $ref");
	$data['result'] = 1;
	fin($msg, $ref);
}
function conv($str) {
    return(htmlspecialchars($str, ENT_NOQUOTES, "UTF-8"));
}

if (isset($_REQUEST['id'])){
	$id = $_REQUEST['id'];
} else {
	$id = 1;
}

if (isset($_POST['op'])){
	$op = $_POST['op'];
	if ($op == 'vider'){
		file_put_contents("./debug_$id.txt", '');
		die();
	} else if($op == 'get_files'){
		$d = dir('./');
		$data['liste'] = array();
		
		while (false !== ($entry = $d->read())) {
		   if (preg_match('#^debug_(.*)\.txt$#',$entry, $val)){
			   $data['liste'][] = $val[1];
		   }
		}
		succes();
	}  else if ($op == 'load'){
		$data['html'] = file_get_contents("./debug_$id.txt");
		if ($_REQUEST['court'] and strlen($data['html']) > 10000){
			$data['html'] = substr($data['html'], strlen($data['html'])-10000);
		}
		$data['html'] = str_replace("\n", '<br/>', $data['html']);
		succes();
	}
}






?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js" type="text/javascript"></script> 
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.4/jquery-ui.min.js"></script>
<script type="text/javascript" src="../ressources/jQuery_extend.js"></script>
<link rel="stylesheet" type="text/css" href="../ressources/std_format004.css"/>

<link rel="stylesheet" type="text/css" href="../ressources/jquery-ui-1.8.4.custom.css"/>
<link rel="stylesheet" type="text/css" href="../ressources/custom.css"/>

<script type="text/javascript" src="../ressources/jquery.scrollTo-min.js"></script>

        <title>Debug</title>
	<style>
		button{
			margin-right:15px;
}
		</style>
	<script type="text/javascript">
	var LK = {}
	;(function(){
		// items = [{title, fn}, ...]; options = {[ev]} où ev est l'événement (clic) pour faire ouvrir le menu au curseur
		// pour chaque ouverture, créer un objet sans le stocker dans une variable
		LK.context_menu = function(obj, items, options){
			var this_ = this
			this.obj = obj
			this.items = items
			this.options = options || {}
			if ($('#mypopup_menu').length == 0){
				this.div = $('<div/>', {id:'mypopup_menu'}).appendTo('body').hide().bind('contextmenu',false)
				this.div.click(function(ev){
					var $obj = $(ev.target).closest('div')
					if ($obj.is('[data-no]')){
						
						var item = this_.items[parseInt($obj.attr('data-no'))]
						if (item.context){
							item.fn.call(item.context, this_.obj)
						} else{
							item.fn(this_.obj)
						}
							
					}
				})
				$('#mypopup_menu').LKdata('just_created',1)
				$('body').bind('mouseup.mypopup_menu', function(){
					setTimeout(function(){
						
						$('#mypopup_menu').remove()
						$('body').unbind('mouseup.mypopup_menu')
					})
				})
				
			} else{
				this.div = $('#mypopup_menu').LKdata('just_created',1)
			}
			this.update()
			this.display()
			
		}
	
		var cm = LK.context_menu.prototype
		cm.update = function(){
			var this_ = this
			this.div.html('')
			$.each(this.items, function(ind, val){
				if (!val) return
				var line
				this_.div.append(line = $('<div/>', {'data-no':ind}).text(val.title || '?????'))
				if (val['class']){
					line.addClass(val['class'])
					//alert(val['class'])
				}
				if (val.icon){
					if (typeof(val.icon) == 'string'){
						line.prepend($('<span>', {'class':'ui-icon ui-icon-' + val.icon, css:{marginRight:6}}))
					} else if (typeof(val.icon) == 'object'){
						if (val.icon.primary){
							line.css('paddingLeft', 20)
							.addClass(val.icon.primary)
						}
					}
				}
				
			})
			
		}
		cm.display = function(){
			var obj, remove_obj = false
			if (this.options.ev){
				var ev = this.options.ev
				obj = $('<div/>', {css:{position:'absolute', width:1, height:1, top:ev.pageY, left:ev.pageX}}).appendTo('body')
						
				remove_obj = true
			} else {
				obj = this.obj
			}
			this.div.position({my:'left', at:'right', of:obj, collision:'fit'})
				.show()
			if(remove_obj){
				obj.remove()
			}
		}
	
	})()
		
		$(function(){
			var id = '<?php echo $id ?>';
			
			LK.vider = function(){
					$.post('./debug_view.php', {
						op:'vider',
						id: id
					},
						function(data){
							$('#liste').html('')
						})

			}
			$('body').bind('mouseup',function(ev){
				if (ev.which != 3){
					return
				}
				new LK.context_menu($('body'), [
					{
						title: 'Vider',
						fn:function(){
							LK.vider()
						},
						icon:'trash'
					}
				], {ev:ev})
			})
			.bind('contextmenu',false)

			
			$('button').button()
			$('#vider').click(LK.vider)
			$('body').scrollTo('#fin')
			$('#chg_user').click(function(){
				var buttons = {}, court
				buttons['Accepter'] = function(){
					var selection = dial.find('option:selected').val()
					id = selection
					window.location = './debug_view.php' + '?id=' + id
					return
					$.post('./debug_view.php' + '?id=' + id, {op:'load', court:court.is(':checked')?1:0}, 
						function(data){
							$('#liste').html(data.html)
							dial.dialog('close')
						},'json'
					)
					

				}
				buttons['Fermer'] = function() {
					$(this).dialog('close')
				}
				var id = 'chg_user_dialog'
				if($('#' + id).length == 0){
					$('<div>',{id:id}).dialog({
						title:'dfgdgdfg',
						width:600,
						autoOpen:false,
						buttons:buttons,
						zIndex:100,
						close:function(){
							$(this).dialog('destroy').remove()
						}
					})

				} 
				var dial = $('#' + id), choix
				dial.text('Choisir le fichier de debuggage:')
					.append(choix = $('<select>'))
					.append($('<label>', {css:{marginLeft:15}}).text('Raccourci?').append(court = $('<input>',{type:'checkbox', checked:'checked',css:{marginLeft:10}})))
				$.post('./debug_view.php',
					{op:'get_files'},
					function(data){
						if (data.result){
							if (data.liste){
								$.each(data.liste, function(ind,val){
									choix.append($('<option>', {value:val}).text(val))
								})
							}
						} 
					},'json'
				)
				dial.dialog('open')
				
				
			})
			$('button#logs').click(function(){window.location = '../../show_logs'})
		})
		</script>
     </head>
   <body>
		<div id="ctrl">
			<button id="vider">Vider</button>
			<button id="trim">Raccourcir</button>
			<button id="chg_user">Changer usager</button>
			<button id="logs">Retour logs</button>

			
		</div>
		<hr>
		<div id="liste">
        <?php
        $a = file_get_contents("./debug_$id.txt");
	
		echo '<pre>';
		echo conv($a);
		echo '</pre>'
        ?>
		</div>
		<a id="fin"></a>
    </body>
</html>
