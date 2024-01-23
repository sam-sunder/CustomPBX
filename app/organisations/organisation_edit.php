<?php

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('organisation_add') && !permission_exists('organisation_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	if (!empty($_REQUEST["id"]) && is_uuid($_REQUEST["id"])) {
		$action = "update";
		$organisation_uuid = $_REQUEST["id"];
		$id = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//set the defaults
	$organisation_uuid = '';
	$organisation_name = '';

//get http post variables and set them to php variables
	if (!empty($_POST)) {
		$organisation_uuid = $_POST["organisation_uuid"] ?? null;
		$organisation_name = $_POST["organisation_name"];
	}

//process the user data and save it to the database
	if (!empty($_POST) && empty($_POST["persistformvar"])) {

		//delete the organisation
			if (permission_exists('organisation_delete')) {
				if ($_POST['action'] == 'delete' && is_uuid($organisation_uuid)) {
					//prepare
						$array[0]['checked'] = 'true';
						$array[0]['uuid'] = $organisation_uuid;
					//delete
						$obj = new organisations;
						$obj->delete($array);
					//redirect
						header('Location: organisations.php');
						exit;
				}
			}

		//get the uuid from the POST
			if ($action == "update") {
				$organisation_uuid = $_POST["organisation_uuid"];
			}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: organisations.php');
				exit;
			}

		//check for all required data
			$msg = '';
			if (empty($organisation_name)) { $msg .= $text['message-required']." "."Organisation Name"."<br>\n"; }
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

		//add the organisation_uuid
			if (empty($organisation_uuid)) {
				$organisation_uuid = uuid();
			}

		//prepare the array
			$array['organisations'][0]['organisation_uuid'] = $organisation_uuid;
			$array['organisations'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
			$array['organisations'][0]['organisation_name'] = $organisation_name;

		//save to the data
			$database = new database;
			$database->app_name = 'organisations';
			$database->app_uuid = 'a6a7c4c5-340a-43ce-bcbc-2ed9bab8659e';
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
				header('Location: organisations.php');
				return;
			}
	}

//pre-populate the form
	if (!empty($_GET) && is_array($_GET) && (empty($_POST["persistformvar"]) || $_POST["persistformvar"] != "true")) {
		$organisation_uuid = $_GET["id"];
		$sql = "select * from v_organisations ";
		$sql .= "where organisation_uuid = :organisation_uuid ";
		$parameters['organisation_uuid'] = $organisation_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters ?? null, 'row');
		if (!empty($row)) {
			$organisation_name = $row["organisation_name"];
		}
		unset($sql, $parameters, $row);
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	$document['title'] = "Add/Edit Organisations";
	require_once "resources/header.php";

//show the content
	echo "<form name='frm' id='frm' method='post'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Add/Edit Organisations</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'organisations.php']);
	if ($action == 'update' && permission_exists('organisation_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','style'=>'margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if ($action == 'update' && permission_exists('organisation_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
	}

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "Name \n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' style='position: relative;' align='left'>\n";
	echo "	<input class='formfld' type='text' name='organisation_name' maxlength='255' value='".escape($organisation_name)."'>\n";
	echo "<br />\n";
	echo "Name of the organisation \n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br /><br />";

	if ($action == "update") {
		echo "<input type='hidden' name='organisation_uuid' value='".escape($organisation_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
