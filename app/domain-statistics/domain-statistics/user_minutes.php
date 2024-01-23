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

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

// Organisation class
if (!class_exists('organisations')) {
	class organisations {

		/**
		 * declare private variables
		 */
		private $app_name;
		private $app_uuid;
		private $permission_prefix;
		private $list_page;
		private $table;
		private $uuid_prefix;
		private $toggle_field;
		private $toggle_values;

		/**
		 * called when the object is created
		 */
		public function __construct() {

			//assign private variables
				$this->app_name = 'organisations';
				$this->app_uuid = 'a6a7c4c5-340a-43ce-bcbc-2ed9bab8659e';
				$this->permission_prefix = 'organisation_';
				$this->list_page = 'organisations.php';
				$this->table = 'organisations';
				$this->uuid_prefix = 'organisation_';
		}

		/**
		 * delete records
		 */
		public function delete($records) {
			if (permission_exists($this->permission_prefix.'delete')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//delete multiple records
					if (is_array($records) && @sizeof($records) != 0) {

						//build the delete array
							foreach ($records as $x => $record) {
								if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $record['uuid'];
									$array[$this->table][$x]['domain_uuid'] = $_SESSION['domain_uuid'];
								}
							}

						//delete the checked rows
							if (is_array($array) && @sizeof($array) != 0) {

								//execute delete
									$database = new database;
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->delete($array);
									unset($array);

								//clear the destinations session array
									if (isset($_SESSION['destinations']['array'])) {
										unset($_SESSION['destinations']['array']);
									}

								//set message
									message::add($text['message-delete']);
							}
							unset($records);
					}
			}
		}
	}
}

//check permissions
	if (permission_exists('bill_stat_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set additional variables
	$search = $_GET["search"] ?? '';
	$show = $_GET["show"] ?? '';

//set from session variables
	$list_row_edit_button = !empty($_SESSION['theme']['list_row_edit_button']['boolean']) ? $_SESSION['theme']['list_row_edit_button']['boolean'] : 'false';

//get the http post data
	if (!empty($_POST['bill_stats'])) {
		$action = $_POST['action'];
		$search = $_POST['search'];
		$bill_stats = $_POST['bill_stats'];
	}

//process the http post data by action
	if (!empty($action) && !empty($bill_stats)) {
		switch ($action) {
			case 'delete':
				if (permission_exists('bill_stat_delete')) {
					$obj = new bill_stats;
					$obj->delete($bill_stats);
				}
				break;
		}

		header('Location: user_minutes.php'.(!empty($search) ? '?search='.urlencode($search) : null));
		exit;
	}

//get order and order by
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//add the search string
	if (!empty($search)) {
		$search =  strtolower($_GET["search"]);
		$sql_search = " (lower(total_minutes) like :search )";
		$parameters['search'] = '%'.$search.'%';
	}

//get the count
	$sql = "select count(bill_stat_uuid) from v_bill_stats ";
	if (!empty($show) && $show == "all" && permission_exists('bill_stat_all')) {
		if (isset($sql_search)) {
			$sql .= "where ".$sql_search;
		}
	}
	$database = new database;
	$num_rows = $database->select($sql, $parameters ?? null, 'column');

//prepare to page the results
	$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
	$param = !empty($search) ? "&search=".$search : null;
	$param = (isset($_GET['show']) && $_GET['show'] == 'all' && permission_exists('organisation_all')) ? "&show=all" : null;
	$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = str_replace('count(bill_stat_uuid)', '*', $sql);
	// $sql .= order_by($order_by, $order, 'id', 'des');
	$sql .= limit_offset($rows_per_page, $offset);
	$database = new database;
	$bill_stats = $database->select($sql, $parameters ?? null, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = "Billing Minutes";
	require_once "resources/header.php";

	//get the users list
		$sql = "select user_uuid, username from v_users ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and user_enabled = 'true' ";
		$sql .= "order by username asc ";
		$parameters['domain_uuid'] = $domain_uuid;
		$database = new database;
		$users = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		$users_uuid_name = array();
		foreach ($users as $user) {
			$users_uuid_name[$user["user_uuid"]] = $user["username"];
		}

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>"."Billing Minutes"." (".$num_rows.")</b></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('bill_stat_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add'],'id'=>'btn_add','link'=>'user_minutes_edit.php']);
	}
	if (permission_exists('bill_stat_delete') && $organisations) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo 		"<form id='form_search' class='inline' method='get'>\n";
	if (permission_exists('organisation_all')) {
		if (isset($show) && $show == 'all') {
			echo "		<input type='hidden' name='show' value='all'>\n";
		}
		else {
			echo button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$_SESSION['theme']['button_icon_all'],'link'=>'?show=all']);
		}
	}
	echo 		"<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$_SESSION['theme']['button_icon_search'],'type'=>'submit','id'=>'btn_search']);

	if ($paging_controls_mini != '') {
		echo 	"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('bill_stat_delete') && $bill_stats) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo "Add/Reduce Billing Minutes of Users!!"."\n";
	echo "<br /><br />\n";

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	if (permission_exists('bill_stat_add') || permission_exists('bill_stat_edit') || permission_exists('bill_stat_delete')) {
		echo "	<th class='checkbox'>\n";
		echo "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);' ".(empty($organisations) ? "style='visibility: hidden;'" : null).">\n";
		echo "	</th>\n";
	}
	if (!empty($show) && $show == 'all' && permission_exists('bill_stat_all')) {
		echo th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	}
	echo th_order_by('username', 'User', $order_by, $order);
	echo th_order_by('total_minutes', 'Minutes', $order_by, $order);
	echo "	<th class='hide-sm-dn'></th>\n";
	if (permission_exists('bill_stat_edit') && !empty($list_row_edit_button) && $list_row_edit_button == 'true') {
		echo "	<td class='action-button'>&nbsp;</td>\n";
	}
	echo "</tr>\n";

	if (!empty($bill_stats)) {
		$x = 0;
		foreach ($bill_stats as $row) {
			if (permission_exists('bill_stat_edit')) {
				$list_row_url = "user_minutes_edit.php?id=".urlencode($row['bill_stat_uuid']);
			}
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			if (permission_exists('bill_stat_add') || permission_exists('bill_stat_edit') || permission_exists('bill_stat_delete')) {
				echo "	<td class='checkbox'>\n";
				echo "		<input type='checkbox' name='bill_stats[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				echo "		<input type='hidden' name='bill_stats[$x][uuid]' value='".escape($row['bill_stat_uuid'])."' />\n";
				echo "	</td>\n";
			}
			if (!empty($_GET['show']) && $_GET['show'] == 'all' && permission_exists('bill_stat_all')) {
				echo "	<td>".escape($_SESSION['domains'][$row['domain_uuid']]['domain_name'])."</td>\n";
			}
			echo "	<td>\n";
			if (permission_exists('bill_stat_edit')) {
				echo "	<a href='".$list_row_url."' title=\"".$text['button-edit']."\">".escape($users_uuid_name[$row['user_uuid']])."</a>\n";
			}
			else {
				echo "	".escape($users_uuid_name[$row['user_uuid']]);
			}
			echo "	</td>\n";

			echo "	<td>\n";
			echo "	".escape($row['total_minutes']);
			echo "	</td>\n";

			echo "</tr>\n";
			$x++;
		}
		unset($organisations);
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
