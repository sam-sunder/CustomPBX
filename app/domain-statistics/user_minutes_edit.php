<?php

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('bill_stat_add') && !permission_exists('bill_stat_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (!empty($_REQUEST["id"]) && is_uuid($_REQUEST["id"])) {
		$action = "update";
		$bill_stat_uuid = $_REQUEST["id"];
		$id = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//set the defaults
	$bill_stat_uuid = '';
	$total_minutes = 0;

//get http post variables and set them to php variables
	if (!empty($_POST)) {
		$bill_stat_uuid = $_POST["bill_stat_uuid"] ?? null;
		$user_uuid = $_POST["user_uuid"] ?? null;
		$total_minutes = $_POST["total_minutes"];
	}

//process the user data and save it to the database
	if (!empty($_POST) && empty($_POST["persistformvar"])) {

		//delete the bill_stat
			if (permission_exists('bill_stat_delete')) {
				if ($_POST['action'] == 'delete' && is_uuid($bill_stat_uuid)) {
					//prepare
						$array[0]['checked'] = 'true';
						$array[0]['uuid'] = $bill_stat_uuid;
					//delete
						$obj = new bill_stat;
						$obj->delete($array);
					//redirect
						header('Location: user_minutes.php');
						exit;
				}
			}

		//get the uuid from the POST
			if ($action == "update") {
				$bill_stat_uuid = $_POST["bill_stat_uuid"];
			}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: user_minutes.php');
				exit;
			}

		//check for all required data
			$msg = '';
			if (empty($user_uuid)) { $msg .= $text['message-required']." "."User"."<br>\n"; }
			if (!empty($msg) && empty($_POST["persistformvar"])) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//add the bill_stat_uuid
			if (empty($bill_stat_uuid)) {
				$bill_stat_uuid = uuid();
			}

		//prepare the array
			$array['bill_stats'][0]['bill_stat_uuid'] = $bill_stat_uuid;
			$array['bill_stats'][0]['user_uuid'] = $user_uuid;
			$array['bill_stats'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
			$array['bill_stats'][0]['total_minutes'] = $total_minutes;

		//save to the data
			$database = new database;
			$database->save($array);
			$message = $database->message;

		//redirect the user
			if (isset($action)) {
				if ($action == "add") {
					$_SESSION["message"] = $text['message-add'];
				}
				if ($action == "update") {
					$_SESSION["message"] = $text['message-update'];
				}
				header('Location: user_minutes.php');
				return;
			}
	}

	if ($action == "add") {
		$sql = "select * from v_bill_stats ";
		$database = new database;
		$bill_stats = $database->select($sql, $parameters ?? null, 'all');
		$bill_stat_user_uuids = " ( ";
		foreach ($bill_stats as $row) {
			$bill_stat_user_uuids .= "'".$row["user_uuid"]."', ";
		}
		$bill_stat_user_uuids = rtrim($bill_stat_user_uuids, ", ");
		$bill_stat_user_uuids .= " ) ";
		unset($sql, $parameters);
	}

	//get the users list
		$sql = "select * from v_users ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and user_enabled = 'true' ";
		if ($action == "add") {
			$sql .= "and user_uuid not in ".$bill_stat_user_uuids." ";
		}
		$sql .= "order by username asc ";
		$parameters['domain_uuid'] = $domain_uuid;
		$database = new database;
		$users = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

//pre-populate the form
	if (!empty($_GET) && is_array($_GET) && (empty($_POST["persistformvar"]) || $_POST["persistformvar"] != "true")) {
		$bill_stat_uuid = $_GET["id"];
		$sql = "select * from v_bill_stats ";
		$sql .= "where bill_stat_uuid = :bill_stat_uuid ";
		$parameters['bill_stat_uuid'] = $bill_stat_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters ?? null, 'row');
		if (!empty($row)) {
			$total_minutes = $row["total_minutes"];
			$user_uuid = $row["user_uuid"];
		}
		unset($sql, $parameters, $row);
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	$document['title'] = "Add/Edit Billing Minutes";
	require_once "resources/header.php";

//show the content
	echo "<form name='frm' id='frm' method='post'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Add/Edit Billing Minutes</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'user_minutes.php']);
	if ($action == 'update' && permission_exists('bill_stat_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','style'=>'margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if ($action == 'update' && permission_exists('bill_stat_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
	}

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr id='tr_user'>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-user']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "			<select name=\"user_uuid\" class='formfld' style='width: auto;'>\n";
	echo "			<option value=\"\"></option>\n";
	foreach($users as $field) {
		if ($field['user_uuid'] == $user_uuid) { $selected = "selected='selected'"; } else { $selected = ''; }
		echo "			<option value='".escape($field['user_uuid'])."' $selected>".escape($field['username'])."</option>\n";
	}
	echo "			</select>";
	unset($users);
	echo "			<br>\n";
	echo "			".$text['description-user']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "Total Minutes \n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' style='position: relative;' align='left'>\n";
	echo "<input class='formfld' type='number' name='total_minutes' maxlength='255' value=".escape($total_minutes ? $total_minutes : 0)." required='required'>\n";
	echo "<br />\n";
	echo "Total Billing Minutes \n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br /><br />";

	if ($action == "update") {
		echo "<input type='hidden' name='bill_stat_uuid' value='".escape($bill_stat_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
