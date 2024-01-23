<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2018 - 2019
	the Initial Developer. All Rights Reserved.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

// check permissions
	if (!permission_exists('bill_stat_add') && !permission_exists('bill_stat_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

	$total_minutes = 0;

	$sql = "select * from v_bill_stats ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "and user_uuid = :user_uuid ";
	$parameters['domain_uuid'] = $_SESSION["domain_uuid"];
	$parameters['user_uuid'] = $_SESSION["user_uuid"];
	$database = new database;
	$row = $database->select($sql, $parameters ?? null, 'row');
	if (!empty($row)) {
		$total_minutes = $row["total_minutes"];
		$bill_stat_uuid = $row["bill_stat_uuid"];
		$action = "update";
	}
	else { $action = "add"; }
	unset($sql, $parameters, $row);

	if (!empty($_POST)) {
		$bill_stat_uuid = $_POST["bill_stat_uuid"] ?? null;
		$total_minutes = $_POST["total_minutes"];
	}

	if (!empty($_POST) && empty($_POST["persistformvar"])) {

	//get the uuid from the POST
			if ($action == "update") {
				$bill_stat_uuid = $_POST["bill_stat_uuid"];
			}

	//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: /');
				exit;
			}
			$msg = "";

			if (empty($total_minutes)) { $msg .= "Total minutes can't be empty. <br>\n"; }
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

	//check for all required data
			if (empty($total_minutes)) { $total_minutes = 0; }

	//add the bridge_uuid
			if (empty($bill_stat_uuid)) {
				$bill_stat_uuid = uuid();
			}

	//prepare the array
			$array['bill_stats'][0]['bill_stat_uuid'] = $bill_stat_uuid;
			$array['bill_stats'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
			$array['bill_stats'][0]['user_uuid'] = $_SESSION["user_uuid"];
			$array['bill_stats'][0]['total_minutes'] = $total_minutes;

	//save to the data
			$database = new database;
			$database->save($array);
			$message = $database->message;

	//redirect the user
			if (isset($action)) {
				$_SESSION["message"] = "Total minutes updated successfully";
				header('Location: /');
				return;
			}
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);
//pre-populate the form
//show the header
	$document['title'] = $text['title-bridge'];
	require_once "resources/header.php";

//show the content
	echo "<form name='frm' id='frm' method='post'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Add Billing Minutes</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'/']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "Total Minutes\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' style='position: relative;' align='left'>\n";
	echo "<input class='formfld' type='number' name='total_minutes' maxlength='255' value=".escape($total_minutes ? $total_minutes : 0)." required='required'>\n";
	echo "<br />\n";
	echo "Set Total Billing minutes that you paid for.\n";
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
