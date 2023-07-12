<?php
if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
 }
//$autoload = dirname(__DIR__) . '/vendor/autoload.php';
//require_once $autoload;

//use Spipu\Html2Pdf\Html2Pdf;
require_once dirname(__DIR__) . '/dompdf/autoload.inc.php';
require_once dirname(__DIR__) . '/inc/SignProtocolByEmail.php';
require_once dirname(__DIR__) . '/inc/Buttons.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PluginProtocolsmanagerGenerate extends CommonDBTM {

		function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
			return self::createTabEntry('Protocols manager');
		}
		
		static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
			global $DB, $CFG_GLPI;
			
			$tab_access = self::checkRights();
			
			if ($tab_access == 'w') {
				$PluginProtocolsmanagerGenerate = new self();
				$PluginProtocolsmanagerGenerate->showContent($item);
			} else {
				echo "<div align='center'><br><img src='".$CFG_GLPI['root_doc']."/pics/warning.png'><br>".__('Access denied')."</div>";
			}
		}
		
		//check if logged user have rights to plugin
		static function checkRights() {
			global $DB;
			$active_profile = $_SESSION['glpiactiveprofile']['id'];
			$req = $DB->request('glpi_plugin_protocolsmanager_profiles',
							['profile_id' => $active_profile]);

			if ($row = $req->current()) {
				$tab_access = $row["tab_access"];
			} else {
				$tab_access = "";
			}
			return $tab_access;
		}
		
		//show plugin content
		function showContent($item) {
			global $DB, $CFG_GLPI;
			$id = $item->getField('id');
			$type_user   = $CFG_GLPI['linkuser_types'];
			# TODO: check for fields plugin
			$fieldsitemtableprefix = 'glpi_plugin_fields_';
			$rand = mt_rand();
			$counter = 0;
			
			#TODO - validate user field name
			if (isset($_SESSION['userfield'])) {
				$field_user = $_SESSION['userfield'];
			}
			else {
				$field_user = 'users_id';
			}
			
			$User_Fields = $DB->request(['glpi_plugin_fields_fields','glpi_plugin_fields_containers'],
				['FIELDS' => ['glpi_plugin_fields_fields' => ['name AS fieldname' ,'label'],
				 			 'glpi_plugin_fields_containers' => ['name AS containername']],
				['FKEY' => ['glpi_plugin_fields_fields' => 'plugin_fields_containers_id',
							'glpi_plugin_fields_containers'  => 'id']],
				['AND' => [ 'glpi_plugin_fields_fields.type' => "dropdown-User"]]
				]);
		/*  SELECT glpi_plugin_fields_fields.name AS fieldname,
			glpi_plugin_fields_fields.label,
			glpi_plugin_fields_containers.name AS containername
			FROM glpi_plugin_fields_fields,glpi_plugin_fields_containers
			where  glpi_plugin_fields_fields.plugin_fields_containers_id=glpi_plugin_fields_containers.id
			AND glpi_plugin_fields_fields.type="dropdown-User"
		*/
			// Select user field type in item for generate list
			echo "<form method='post' name='user_field$rand' id='user_field$rand' 
					action=\"" . $CFG_GLPI["root_doc"] . "/plugins/protocolsmanager/front/generate.form.php\">";
			echo "<table class='tab_cadre_fixe'>";
			echo "<tr><td style ='width:25%'></td>";
			echo "<td class='center' style ='width:25%'>";
			//TODO pretty change format the current active field in the selection list, if is active
			
			echo "<select name='userfield' style='font-size:14px; width:95%'>";
				foreach ($User_Fields as $fuid => $userfield) {
					echo '<option value="'.$userfield["fieldname"].'" '.($userfield["fieldname"] == $field_user ? 'selected style="font-weight:bold"' : '').'>'.__($userfield["label"],'fields').'</option>';
					if ($userfield["fieldname"] == $field_user) {
						$containerName = $userfield["containername"] ;
					}
				}
			echo "<option value='users_id' ".('users_id' == $field_user ? 'selected style="font-weight:bold"' : '').">".__('User')."</option>";
			echo "<option value='users_id_tech' ".('users_id_tech' == $field_user ? 'selected style="font-weight:bold"' : '').">".__('Technician')."</option>";
			echo "</select></td>";
			echo "<td style='width:10%'><input type='submit' name='choiceuserfield' class='submit' value='".__('Change Field','protocolsmanager')."'></td>";
			echo "<td style='width:30%'></td></tr>";
			//TODO - pretty look
			echo "<tr><td style ='width:25%'></td><td class='center'>" . __('Current User Field: ', 'protocolsmanager') . $field_user  . "</br> Current Fields container: " . $containerName . "</td>
				<td style='width:10%'></td>
				<td style='width:10%'></td></tr>";
			echo "</table>";
			Html::closeForm();

			// Generate protocol form 
			echo "<form method='post' name='protocolsmanager_form$rand'
					id='protocolsmanager_form$rand'
					action=\"" . $CFG_GLPI["root_doc"] . "/plugins/protocolsmanager/front/generate.form.php\">";
			echo "<table class='tab_cadre_fixe'>";
			//TODO note - pretty look
			echo "<tr><td></td><td colspan='2'><input type='text' name='notes' placeholder='".__('Note')."' style='width:89%; font-size:14px; padding: 2px'></td><td></td></tr>";
			echo "</table>";
			// Generate User items list
			echo "<div class='spaced'><table class='tab_cadre_fixehov' id='additional_table'>";
			$header = "<th width='10'><input type='checkbox' class='checkall' style='height:16px; width: 16px;'></th>";
			$header .= "<th>".__('Type')."</th>";
			$header .= "<th>".__('Manufacturer')."</th>";
			$header .= "<th>".__('Model')."</th>";
			$header .= "<th>".__('Name')."</th>";
			$header .= "<th>".__('Serial number')."</th>";
			$header .= "<th>".__('Inventory number')."</th>";
			$header .= "<th>".__('Comments')."</th></tr>";
			echo $header;
			
			foreach ($type_user as $itemtype) {
				if (!($item = getItemForItemtype($itemtype))) {
					continue;
				}
				if ($item->canView()) {
					$itemtable = getTableForItemType($itemtype);
					$fieldsitemtablewithuser = strtolower("$fieldsitemtableprefix" . "$itemtype" . "$containerName" .'s');
					if (("$field_user" == 'users_id') or ("$field_user" == 'users_id_tech')) {
						$iterator_params = [
							'FROM' => $itemtable,
							'WHERE' => [$field_user => $id]
						];
					}
					else {
						$sub_query = new QuerySubQuery([
							'SELECT' => 'items_id',
							'FROM' => $fieldsitemtablewithuser,
							'WHERE' => ['itemtype' => $itemtype, $field_user => $id]
							]);
							
							$iterator_params = [
							'FROM' => $itemtable,
							'WHERE' => ['id' => $sub_query]
							];
					}
					
					if ($item->maybeTemplate()) {
						$iterator_params['WHERE']['is_template'] = 0;
					}
					
					if ($item->maybeDeleted()) {
						$iterator_params['WHERE']['is_deleted'] = 0;
					}
					
					$item_iterator = $DB->request($iterator_params);
					$type_name = $item->getTypeName();
					$item_iterator->current();
					
					foreach ($item_iterator as $data) {
							$cansee = $item->can($data["id"], READ);
							$link  = $data["name"];
							if ($cansee) {
								$link_item = $item::getFormURLWithID($data['id']);
								if ($_SESSION["glpiis_ids_visible"] || empty($link)) {
									$link = sprintf(__('%1$s (%2$s)'), $link, $data["id"]);
								}
								$link = "<a href='".$link_item."'>".$link."</a>";
							}
							$linktype = "";
							if ($data[$field_user] == $id) {
								$linktype = self::getTypeName(1);
							}
							
							echo "<tr class='tab_bg_1'>";
							echo "<td width='10'>";
							echo "<input type='checkbox' name='number[]' value='$counter' class='child' style='height:16px; width: 16px;'>";
							echo "</td>";
							echo "<td class='center'>$type_name</td>";
							echo "<td class='center'>";
							
							if (isset($data["manufacturers_id"]) && !empty($data["manufacturers_id"])) {
								
								$man_id = $data["manufacturers_id"];
								
								$req = $DB->request(
									'glpi_manufacturers',
									['id' => $man_id ]);
									
								if ($row = $req->current()) {
									$man_name = $row["name"];
								}
								$man_name = trim($man_name);
								echo $man_name;
							}
							else {
								echo '&nbsp;';
								$man_name = '';
							}
							echo "</td>";
							echo "<td class='center'>";
							
							//TODO - add custom models from GenericObject plugin
							$mod_name = '';
							$prefix = strtolower($itemtype);
							if(isset($data[$prefix.'models_id']) && !empty($data[$prefix.'models_id'])) {
								$mod_id = $data[$prefix.'models_id'];
								
								$req2 = $DB->request(
									'glpi_'.$prefix.'models',
									['id' => $mod_id ]);
									
								if ($row2 = $req2->current()) {
									$mod_name = $row2["name"];
								}
								$mod_name = trim($mod_name);
								echo $mod_name;
							}
							else {
								echo '&nbsp;';
								$mod_name = '';
							}
							echo "</td>";
							echo "<td class='center'>$link</td>";
							echo "<td class='center'>";
							
							if (isset($data["serial"]) && !empty($data["serial"])) {
								$serial = $data["serial"];
								echo $serial;
							} else {
								echo '&nbsp;';
								$serial = '';
							}
							
							echo "</td>";
							echo "<td class='center'>";
							
							if (isset($data["otherserial"]) && !empty($data["otherserial"])) {
								$otherserial = $data["otherserial"];
								echo $otherserial;
							} else {
								echo '&nbsp;';
								$otherserial = '';
							}
							
							echo "</td>";
							
							if (isset($data["name"]) && !empty($data["name"])) {
								$item_name = $data["name"];
							}
							else {
								echo '&nbsp;';
								$item_name = '';
							}
							
							$Owner = new User();
							$Owner->getFromDB($id);
							$Author = new User();
							$Author->getFromDB(Session::getLoginUserID());
							$owner = $Owner->getFriendlyName();
							$author = $Author->getFriendlyName();
							echo "<input type='hidden' name='owner' value ='$owner'>";
							echo "<input type='hidden' name='author' value ='$author'>";
							echo "<input type='hidden' name='type_name[]' value='$type_name'>";
							echo "<input type='hidden' name='man_name[]' value='$man_name'>";
							echo "<input type='hidden' name='mod_name[]' value='$mod_name'>";
							echo "<input type='hidden' name='serial[]' value='$serial'>";
							echo "<input type='hidden' name='otherserial[]' value='$otherserial'>";
							echo "<input type='hidden' name='item_name[]' value='$item_name'>";
							echo "<input type='hidden' name='user_id' value='$id'>";
							echo "<td class='center'><input type='text' name='comments[]'></td>";
							echo "</tr>";
							
						$counter++;
					}
				}
			}
			echo "</table>";
			echo "</div>";
			// Choose protocol template and Create button
			echo "<table class='tab_cadre_fixe'>";
			echo "<tr><td style ='width:25%'></td>";
			echo "<td class='center' style ='width:25%'>";
			echo "<select name='list' style='font-size:14px; width:95%'>";
				foreach ($doc_types = $DB->request('glpi_plugin_protocolsmanager_config',
				['FIELDS' => ['glpi_plugin_protocolsmanager_config' => ['id', 'name']]]) as $uid => $list) {
					echo '<option value="';
					echo $list["id"];
					echo '">';
					echo $list["name"];
					echo '</option>';
				}
			echo "</select></td>";
			echo "<td style='width:10%'><input type='submit' name='generate' class='submit' value='".__('Create')."'></td>";
			echo "<td style='width:30%'></td></tr>";
			echo "</table>";
			Html::closeForm();
				
				//send email popup
				echo "<div class='dialog' title='".__('Send email')."'><p>" . __('Select recipients from template or enter manually to send email','protocolsmanager') . "</p><br><br>";
				echo "<form method='post' action='".$CFG_GLPI["root_doc"]."/plugins/protocolsmanager/front/generate.form.php'>";
				echo "<input type='hidden' id='dialogVal' name='doc_id' value=''>";
				echo "<input type='radio' name='send_type' id='manually' class='send_type' value='1'><b>" . __('Enter recipients manually','protocolsmanager') . "</b><br><br>";
				echo "<textarea style='width:90%; height:30px' name='em_list' class='man_recs' placeholder=" . __('Recipients (use ; to separate emails)','protocolsmanager') . "></textarea><br><br>";
				echo "<input type='text' style='width:90%' name='email_subject' class='man_recs' placeholder=" . __('Subject') . "><br><br>";
				echo "<textarea style='width:90%; height:80px' name='email_content' class='man_recs' placeholder=" . __('Content') . "></textarea><br><br>";
				echo "<input type='radio' name='send_type' id='auto' class='send_type' value='2'><b>" . __('Select recipients from template','protocolsmanager') . "</b><br><br>";
				echo "<select name='e_list' id='auto_recs' disabled='disabled' style='font-size:14px; width:95%'>";

				foreach ($DB->request('glpi_plugin_protocolsmanager_emailconfig') as $uid => $list) {
					echo '<option value="';
					echo $list["recipients"]."|".$list["email_subject"]."|".$list["email_content"]."|".$list["send_user"];
					echo '">';
					echo $list["tname"]." - ".$list["recipients"];
					echo '</option>';
				}

				echo "</select><br><br><input type='submit' name='send' class='submit' value=".__('Send').">";
				echo "<input type='hidden' name='author' value='$author'>";
				echo "<input type='hidden' name='owner' value='$owner'>";
				Html::closeForm();
				echo "</div>";

				//add custom row
				echo "<div class='spaced'><button class='addNewRow' id='addNewRow' style='background-color:#8ec547; color:#fff; cursor:pointer; font:bold 12px Arial, Helvetica; border:0; padding:5px;'>" . __('Add Custom Fields','protocolsmanager') . "</button></div>";

				echo "<div class='spaced'>";
				echo "<form method='post' name='docs_form' action='".$CFG_GLPI["root_doc"]."/plugins/protocolsmanager/front/generate.form.php'>";
				echo "<table class='tab_cadre_fixe'><td style='width:5%'><img src='../pics/arrow-left-top.png'></td><td style='width:5%'>";
				echo "<input type='submit' name='delete' class='submit' value=".__('Delete').">";
				echo "</td><td style='width:90%'></table>";
				echo "<table class='tab_cadre_fixehov' id='myTable'>";
				echo "<th width='10'><input type='checkbox' class='checkalldoc' style='height:16px; width: 16px;'></th>";
				$header2 = "<th>".__('Name')."</th>";
				$header2 .= "<th>".__('Type')."</th>";
				$header2 .= "<th>".__('Date')."</th>";
				$header2 .= "<th>".__('File')."</th>";
				$header2 .= "<th>".__('Creator')."</th>";
				$header2 .= "<th>".__('Comment')."</th>";
				if(self::checkSignProtocolsOn()) {
					$header2 .= "<th>" . __('Status') . "</th>";
				}
				$header2 .= "<th>".__('Send email')."</th></tr>";
				echo $header2;

				self::getAllForUser($id);
				echo "</table>";
				Html::closeForm();
				echo "</div>";

				return true;
		}
		
		//show user's generated protocols documents
		static function getAllForUser($id) {
			global $DB, $CFG_GLPI;

			$exports = [];
			$doc_counter = 0;

			$sql = [
						'SELECT' => [
							'glpi_plugin_protocolsmanager_protocols.*',
							'glpi_plugin_protocolsmanager_receipt.confirmed'
						],
						'FROM' => 'glpi_plugin_protocolsmanager_protocols',
						'LEFT JOIN' => [
						'glpi_plugin_protocolsmanager_receipt' => [
						['FKEY' => [
							'glpi_plugin_protocolsmanager_protocols' => 'document_id',
							'glpi_plugin_protocolsmanager_receipt' => 'protocol_id']]
							],
						],
						'WHERE' => [
							'user_id' => $id
						],
				];
			foreach ($DB->request($sql) as $export_data => $exports) {

					echo "<tr class='tab_bg_1'>";

					echo "<td class='center'>";
					echo "<input type='checkbox' name='docnumber[]' value='".$exports['document_id']."' class='docchild' style='height:16px; width: 16px;'>";
					echo "</td>";

					echo "<td class='center'>";
					$Doc = new Document();
					$Doc->getFromDB($exports['document_id']);
					echo $Doc->getLink();
					echo "</td>";

					echo "<td class='center'>";
					echo $exports['document_type'];
					echo "</td>";

					echo "<td class='center'>";
					echo $exports['gen_date'];
					echo "</td>";

					echo "<td class='center'>";
					echo $Doc->getDownloadLink();
					echo "</td>";

					echo "<td class='center'>";
					echo $exports['author'];
					echo "</td>";

					echo "<td class='center'>";
					echo $Doc->getField("comment");
					echo "</td>";
					if(self::checkSignProtocolsOn()){
						echo "<td class='center'>";
						echo $exports["confirmed"] == 0 ? __('No signed','protocolsmanager'): __('Signed','protocolsmanager') ;
						echo "</td>";
					}
					
					echo "<td class='center'>";
				//TODO - send default email with document to user
				/*
				echo "<div class='dialog' title='".__('Send email')."'>;
				echo "<form method='post' action='".$CFG_GLPI["root_doc"]."/plugins/protocolsmanager/front/generate.form.php'>";
				echo "<input type='submit' name='send' class='submit' value=".__('Send').">";
				echo "<input type='hidden' name='author' value='" . $exports['author'] . "'>";
				echo "<input type='hidden' name='owner' value='$owner'>";
				echo "<input type='hidden' name='doc_id' value='".$exports['document_id']."'>";
				echo "<input type='hidden' name='user_id' value='$id'>";
				echo ""
				Html::closeForm();
				echo "</div>";
				*/
				
				$hash =  $_GET['id'] * $exports['document_id'] * 386479 + 335235;
				echo "<span class='docid' style='display:none'>".$exports['document_id']."</span>";
				echo "<a class='openDialog' docid='".$exports['document_id']."' hash = ".$hash ." style='background-color:#8ec547; color:#fff; cursor:pointer; font:bold 12px Arial, Helvetica; border:0; padding:5px;' href='#'>".__('Send')."</a>";
				echo "</td>";
				echo "</tr>";
				$doc_counter++;
			}
			
			echo '<script type="text/javascript">
			var anchors = document.getElementsByClassName("openDialog");
			for(var i = 0; i < anchors.length; i++) {
				var anchor = anchors[i];
				anchor.onclick = function(e) {
					 let doc = e.target.getAttribute("docid");
					 let hash = e.target.getAttribute("hash");
					 let dane = {"id" : '. $_GET['id'] .', "doc_id" : doc, "hash": hash};
					 jQuery.ajax({
						type: "POST",
						url: "'. Plugin::getWebDir('protocolsmanager').'/ajax/SendOneMailAjax.php",
						data: dane,
						dataType: "text",
						success: function(data) {
							alert(data);
						}
					}); 
				}
			}
			</script>';
		}
		
		//make PDF and save to DB
		static function makeProtocol() {
			
			global $DB, $CFG_GLPI;
			
			$protocolsSignOn = false;
			$number = $_POST['number'];
			$type_name = $_POST['type_name'];
			$man_name = $_POST['man_name'];
			$mod_name = $_POST['mod_name'];
			$serial = $_POST['serial'];
			$otherserial = $_POST['otherserial'];
			$item_name = $_POST['item_name'];
			$owner = $_POST['owner'];
			$author = $_POST['author'];
			$doc_no = $_POST['list'];
			$id = $_POST['user_id'];
			$notes = $_POST['notes'];
			
			$prot_num = self::getDocNumber();
			
			$req = $DB->request(
				'glpi_plugin_protocolsmanager_config',
				['id' => $doc_no ]);
			
			if ($row = $req->current()) {
				$content = nl2br($row["content"]);
				$content = str_replace("{cur_date}", date("d.m.Y"), $content);
				$content = str_replace("{owner}", $owner, $content);
				$content = str_replace("{admin}", $author, $content);
				$upper_content = nl2br($row["upper_content"]);
				$upper_content = str_replace("{cur_date}", date("d.m.Y"), $upper_content);
				$upper_content = str_replace("{owner}", $owner, $upper_content);
				$upper_content = str_replace("{admin}", $author, $upper_content);
				$footer = nl2br($row["footer"]);
				$title = $row["name"];
				$full_img_name = $row["logo"];
				$font = $row["font"];
				$fontsize = $row["fontsize"];
				$city = $row["city"];
				$serial_mode = $row["serial_mode"];
				$orientation = $row["orientation"];
				$breakword = $row["breakword"];
				$email_mode = $row["email_mode"];
				$email_template = $row["email_template"];
			}
			
			$req = $DB->request(
				'glpi_plugin_protocolsmanager_emailconfig',
				['id' => $email_template ]);
			
			if ($row = $req->current()) {
				$send_user = $row["send_user"];
				$email_subject = $row["email_subject"];
				$email_content = $row["email_content"];
				$recipients = $row["recipients"];
			}
			
			$comments = $_POST['comments'];
			
			if (!isset($font) || empty($font)) {
				$font = 'dejavusans';
			}
			
			if (!isset($fontsize) || empty($fontsize)) {
				$fontsize = '9';
			}
			
			if (!isset($city) || empty($city)) {
				$city = '';
			}
			
			if (!isset($email_content) || empty($email_content)) {
				$email_content = '';
			}
			$email_content = str_replace("{owner}", $owner, $email_content);
			$email_content = str_replace("{admin}", $author, $email_content);
			$email_content = str_replace("{cur_date}", date("d.m.Y"), $email_content);
			
			if (!isset($email_subject) || empty($email_subject)) {
				$email_subject = '';
			}
			
			$email_subject = str_replace("{owner}", $owner, $email_subject);
			$email_subject = str_replace("{admin}", $author, $email_subject);
			$email_subject = str_replace("{cur_date}", date("d.m.Y"), $email_subject);
			
			if (!isset($recipients) || empty($recipients)) {
				$recipients = '';
			}
			
			//change margin if no image
			if (!isset($full_img_name) || empty($full_img_name)) {
				$backtop = "20mm";
				$islogo = 0;
			} else {
				$logo = GLPI_ROOT.'/files/_pictures/'.$full_img_name;
				$backtop = "40mm";
				$islogo = 1;
			}
			
			$imgtype = pathinfo($logo, PATHINFO_EXTENSION);
			$imgdata = file_get_contents($logo);
			$imgbase64 = 'data:image/' . $imgtype . ';base64,' . base64_encode($imgdata);
			
			ob_start();
			include dirname(__FILE__).'/template.php';
			$html = ob_get_clean();
			
			$html2pdf = new Dompdf([
					"defaultFont" => "$font",
					// "logOutputFile" => /tmp/DOMPDF_render.log.htm',
					"debugPng" => false, // extra messaging
					"debugKeepTemp" => false, // don't delete temp files
					'debugCss' => false, // output Style parsing information and frame details for every frame in the document
					'debugLayout' => false, // draw boxes around frames
					'debugLayoutLines' => false, // line boxes
					'debugLayoutBlocks' => false, // block frames
					'debugLayoutInline' => false, // inline frames
					'debugLayoutPaddingBox' => false // padding box
				]);
			$html2pdf->loadHtml($html);
			$html2pdf->setPaper('A4', $orientation);
			$html2pdf->render();
			
			$doc_name = $prot_num."-".date('mdY').'.pdf';
			$output = $html2pdf->output();
			file_put_contents(GLPI_UPLOAD_DIR .'/'.$doc_name, $output);
			
			$doc_id = self::createDoc($doc_name, $notes, $id);
			
			if ($email_mode == 1) {
				self::sendMail($doc_id, $send_user, $email_subject, $email_content, $recipients, $id);
			}

			$gen_date = date('Y-m-d H:i:s');

			$DB->insert('glpi_plugin_protocolsmanager_protocols', [
				'name' => $doc_name,
				'gen_date' => $gen_date,
				'author' => $author,
				'user_id' => $id,
				'document_id' => $doc_id,
				'document_type' => $title
				]
			);

			if(self::checkSignProtocolsOn()){
				$DB->insert('glpi_plugin_protocolsmanager_receipt', [
						'profile_id' => $id,
						'confirmed' => 0,
						'protocol_id' => $doc_id,
						'modified' => $gen_date
					]
				);
			}
		}
		
		static function getDocNumber() {
			global $DB;
			
			$req = $DB->request('SELECT MAX(id) as max FROM glpi_plugin_protocolsmanager_protocols');
			if ($row = $req->current()) {
				$nextnum = $row["max"];
				if (!$nextnum) {
					return 1;
				}
				else {
					$nextnum++;
					return $nextnum;
				}
			}
		}
		
		//create GLPI document
		static function createDoc($doc_name, $notes, $id) {
			global $DB, $CFG_GLPI;
			
			$req = $DB->request(
					'glpi_users',
					['id' => $id ]);

			if ($row = $req->current()) {
				$entity = $row["entities_id"];
			
			}
			if (!Session::haveAccessToEntity($entity)) {
				$entity = Session::getActiveEntity();
			}
			
			$input = [];
			$doc = new Document();
			$input["entities_id"] = $entity;
			$input["name"] = date('mdY_Hi');
			$input["upload_file"] = $doc_name;
			$input["documentcategories_id"] = 0;
			$input["mime"] = "application/pdf";
			$input["date_mod"] = date("Y-m-d H:i:s");
			$input["users_id"] = Session::getLoginUserID();
			$input["comment"] = $notes;
			$doc->check(-1, CREATE, $input);
			$document_id = $doc->add($input);
			return $document_id;
		}
		
		//delete selected documents
		static function deleteDocs() {
			global $DB, $CFG_GLPI;
			
			$docnumber = $_POST['docnumber'];
			
			foreach ($docnumber as $del_key) {
				$DB->delete(
					'glpi_plugin_protocolsmanager_protocols', [
						'document_id' => $del_key
					]
				);
				
				$doc = new Document();
				$doc->getFromDB($del_key);
				$doc->delete(['id' => $del_key], true);
			}
		}
		
		//send mail notification
		static function sendMail($doc_id, $send_user, $email_subject, $email_content, $recipients, $id) {
			global $CFG_GLPI, $DB;
			
			$nmail = new GLPIMailer();
			$sender=Config::getEmailSender(null,true);
			$nmail->SetFrom($sender["email"], $sender["name"], false);
			$recipients_array = explode(';',$recipients);
			$req = $DB->request('glpi_documents',['id' => $doc_id ]);
			
			if ($row = $req->current()) {
				$path = $row["filepath"];
				$filename = $row["filename"];
			}
			
			$fullpath = GLPI_ROOT."/files/".$path;
			
			// User email address from glpi database
			$req2 = $DB->request(
					'glpi_useremails',
					['users_id' => $id, 'is_default' => 1]);
				
			if ($row2 = $req2->current()) {
				$owner_email = $row2["email"];
			}
			
			if ($send_user == 1) {
				$nmail->AddAddress($owner_email);
			}
			
			foreach($recipients_array as $recipient) {
				$nmail->AddAddress($recipient); //TODO do konfiguracji
			}
			
			$nmail->Subject = $email_subject; //TODO do konfiguracji
			$nmail->addAttachment($fullpath, $filename);
			$nmail->Body = $email_content;
			
			if (!$nmail->Send()) {
				Session::addMessageAfterRedirect(__('Error in sending the email'), false, ERROR);
				GLPINetwork::addErrorMessageAfterRedirect();
				return false;
			} else {
				if ($send_user == 1) {
					Session::addMessageAfterRedirect(sprintf(__('An email was sent to %s'), implode(", ", $recipients_array)." ".$owner_email));
					return true;
				} else {
					Session::addMessageAfterRedirect(sprintf(__('An email was sent to %s'), implode(", ", $recipients_array)));
					return true;
				}
			}
		}
		
		static function sendOneMail($id) {
			global $CFG_GLPI, $DB;
			
			$nmail = new GLPIMailer();
			$sender=Config::getEmailSender(null,true);
			$nmail->SetFrom($sender["email"], $sender["name"], false);
			
			$doc_id = $_POST["doc_id"];
			
			//if email is filled manually
			if (isset($_POST["em_list"])) {
				$recipients = $_POST["em_list"];
			}
			
			if (isset($_POST["email_subject"])) {
				$email_subject = $_POST["email_subject"];
			} else {
				$email_subject = __('GLPI Protocols Manager mail','protocolsmanager');
			}
			
			if (isset($_POST['email_content'])) {
				$email_content = $_POST['email_content'];
			} else {
				$email_content = ' ';
			}
			
			//if email is from template
			if (isset($_POST['e_list'])) {
				$result = explode('|', $_POST['e_list']);
				$recipients = $result[0];
				$email_subject = $result[1];
				$email_content =  $result[2];
				$send_user =  $result[3];
			}
			
			$owner = $_POST["owner"];
			$author = $_POST["author"];
			$email_content = str_replace("{owner}", $owner, $email_content);
			$email_content = str_replace("{admin}", $author, $email_content);
			$email_content = str_replace("{cur_date}", date("d.m.Y"), $email_content);
			$email_subject = str_replace("{owner}", $owner, $email_subject);
			$email_subject = str_replace("{admin}", $author, $email_subject);
			$email_subject = str_replace("{cur_date}", date("d.m.Y"), $email_subject);
			$recipients_array = explode(';',$recipients);
			
			$req2 = $DB->request(
					'glpi_useremails',
					['users_id' => $id, 'is_default' => 1]);
				
			if ($row2 = $req2->current()) {
				$owner_email = $row2["email"];
			}
			
			if ($send_user == 1) {
				$nmail->AddAddress($owner_email);
			}
			
			foreach($recipients_array as $recipient) {
				$nmail->AddAddress($recipient); //do konfiguracji
			}
			
			$req = $DB->request(
					'glpi_documents',
					['id' => $doc_id ]);
				
			if ($row = $req->current()) {
				$path = $row["filepath"];
				$filename = $row["filename"];
			}
			
			$fullpath = GLPI_ROOT."/files/".$path;
			$nmail->IsHtml(true);
			$nmail->addAttachment($fullpath, $filename);
			if(self::checkSignProtocolsOn()){
				$temlateData = PluginProtocolsmanagerConfig::getDataTemplate();
				$subject = isset($temlateData['template_title']) ? $temlateData['template_title'] : 'message title';
				$body_message = isset($temlateData['template_body']) ? $temlateData['template_body'] : 'message body';
				$button = new Buttons();
				$email_subject = (!empty($email_subject)) ? $email_subject : $subject;
				$email_content .= $button->createSignProtocolButton($body_message, $CFG_GLPI);
			}
			$nmail->Body = htmlspecialchars_decode($email_content); // HTML in e-mail
			$nmail->IsHtml(true);
			$nmail->AltBody = strip_tags(htmlspecialchars_decode($email_content)); // for text mode - clean html tags
			
			if (!$nmail->Send()) {
				Session::addMessageAfterRedirect(__('Error in sending the email'), false, ERROR);
				return false;
			} else {
				if ($send_user == 1) {
					Session::addMessageAfterRedirect(sprintf(__('An email was sent to %s'), implode(", ", $recipients_array)." ".$owner_email));
					return true;
				} else {
					Session::addMessageAfterRedirect(sprintf(___('An email was sent to %s'), implode(", ", $recipients_array)));
					return true;
				}
			}
		}
		
		static function checkSignProtocolsOn() {
			global $DB;
			$query = (['FROM' => 'glpi_plugin_protocolsmanager_settings', 'WHERE' => ['id' => 1]]);
			return $DB->request($query)->current()['protocols_save_on'];
		}
}

?>

<script>

$(function(){
	$(".man_recs").prop('disabled', true);
	$('.send_type').click(function(){
		if($(this).prop('id') == "manually"){
			$(".man_recs").prop('disabled', false);
			$("#auto_recs").prop('disabled', true);
		}else{
			$(".man_recs").prop('disabled', true);
			$("#auto_recs").prop('disabled', false);
		}
	});
});

$(function(){
	$(".dialog").dialog({ autoOpen: false, modal: true, height: 500, width: 500 });
	$("#myTable").on('click','.openDialog',function(){
		// get the current row
		var currentRow=$(this).closest("tr");
		var docid=currentRow.find(".docid").html(); // get current row 1st table cell TD value
		$('#dialogVal').val(docid);
		$(".dialog").dialog('open');
		});
});

$(function(){
	$('.checkall').on('click', function() {
	$('.child').prop('checked', this.checked)
	});
});

$(function(){
	$('.checkalldoc').on('click', function() {
		$('.docchild').prop('checked', this.checked)
	});
});

$(function() {
		var counter = $('.child').length;
		var ctr = 0;
		
		$("#addNewRow").on("click", function () {
			var newRow = $("<tr class='tab_bg_1'>");
		var cols = "";
		cols += '<td><input type="button" class="ibtnDel" value="&#10006" style="background-color:red; font-size:9px;"></td>';
		cols += '<td class="center"><input type="text" style="width:80%" name="type_name[]"></td>';
		cols += '<td class="center"><input type="text" style="width:90%" name="man_name[]"></td>';
		cols += '<td class="center"><input type="text" style="width:90%" name="mod_name[]"></td>';
		cols += '<td class="center"><input type="text" style="width:90%" name="item_name[]"></td>';
		cols += '<td class="center"><input type="text" style="width:90%" name="serial[]"></td>';
		cols += '<td class="center"><input type="text" style="width:90%" name="otherserial[]"></td>';
		cols += '<td class="center"><input type="text" style="width:90%" name="comments[]"><input type="hidden" name="number[]" value="' + counter + '"></td>';
		newRow.append(cols);
		$("#additional_table").append(newRow);
		counter++;
		ctr++;
	});

	$("#additional_table").on("click", ".ibtnDel", function (event) {
		$(this).closest("tr").remove();
		ctr -= 1
	});
});

</script>