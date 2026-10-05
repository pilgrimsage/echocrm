<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

/**
 * Credit Note: a document issued against an Invoice to reduce what the customer owes (sales
 * return, post-sale discount, correction). It is an inventory module (line items, taxes, tax
 * region, addresses) modelled on Invoice, but it does not touch product stock and it is not
 * counted as sales in Invoice reports.
 *
 * Created by bin/create-credit-note-module.php.
 */
class CreditNote extends CRMEntity {
	var $log;
	var $db;

	var $table_name = "vtiger_creditnote";
	var $table_index = 'creditnoteid';
	var $tab_name = Array('vtiger_crmentity', 'vtiger_creditnote', 'vtiger_creditnotebillads', 'vtiger_creditnoteshipads', 'vtiger_creditnotecf', 'vtiger_inventoryproductrel');
	var $tab_name_index = Array('vtiger_crmentity' => 'crmid', 'vtiger_creditnote' => 'creditnoteid', 'vtiger_creditnotebillads' => 'creditnotebilladdressid', 'vtiger_creditnoteshipads' => 'creditnoteshipaddressid', 'vtiger_creditnotecf' => 'creditnoteid', 'vtiger_inventoryproductrel' => 'id');

	/**
	 * Mandatory table for supporting custom fields.
	 */
	var $customFieldTable = Array('vtiger_creditnotecf', 'creditnoteid');

	var $column_fields = Array();

	var $sortby_fields = Array('subject', 'creditnote_no', 'creditnotestatus', 'smownerid', 'accountname', 'lastname');

	// This is used to retrieve related vtiger_fields from form posts.
	var $additional_column_fields = Array('assigned_user_name', 'smownerid', 'opportunity_id', 'case_id', 'contact_id', 'task_id', 'note_id', 'meeting_id', 'call_id', 'email_id', 'parent_name', 'member_id');

	// This is the list of vtiger_fields that are in the lists.
	var $list_fields = Array(
		'Credit Note No' => Array('creditnote' => 'creditnote_no'),
		'Subject' => Array('creditnote' => 'subject'),
		'Invoice' => Array('creditnote' => 'invoiceid'),
		'Status' => Array('creditnote' => 'creditnotestatus'),
		'Total' => Array('creditnote' => 'total'),
		'Assigned To' => Array('crmentity' => 'smownerid')
	);

	var $list_fields_name = Array(
		'Credit Note No' => 'creditnote_no',
		'Subject' => 'subject',
		'Invoice' => 'invoice_id',
		'Status' => 'creditnotestatus',
		'Total' => 'hdnGrandTotal',
		'Assigned To' => 'assigned_user_id'
	);
	var $list_link_field = 'subject';

	var $search_fields = Array(
		'Credit Note No' => Array('creditnote' => 'creditnote_no'),
		'Subject' => Array('creditnote' => 'subject'),
		'Account Name' => Array('creditnote' => 'accountid'),
		'Created Date' => Array('crmentity' => 'createdtime'),
		'Assigned To' => Array('crmentity' => 'smownerid'),
	);

	var $search_fields_name = Array(
		'Credit Note No' => 'creditnote_no',
		'Subject' => 'subject',
		'Account Name' => 'account_id',
		'Created Time' => 'createdtime',
		'Assigned To' => 'assigned_user_id'
	);

	// This is the list of vtiger_fields that are required.
	var $required_fields = array("accountname" => 1);

	//Added these variables which are used as default order by and sortorder in ListView
	var $default_order_by = 'crmid';
	var $default_sort_order = 'ASC';

	var $mandatory_fields = Array('subject', 'createdtime', 'modifiedtime', 'assigned_user_id', 'quantity', 'listprice', 'productid');

	// For Alphabetical search
	var $def_basicsearch_col = 'subject';

	var $entity_table = "vtiger_crmentity";

	// For workflows update field tasks is deleted all the lineitems.
	var $isLineItemUpdate = true;

	/**	Constructor which will set the column_fields in this object
	 */
	function __construct() {
		$this->log = Logger::getLogger('CreditNote');
		$this->db = PearDatabase::getInstance();
		$this->column_fields = getColumnFields('CreditNote');
	}

	function CreditNote() {
		self::__construct();
	}

	/**
	 * Module specific save: stores the line items, taxes and totals. Unlike an Invoice nothing is
	 * deducted from (or returned to) product stock.
	 */
	function save_module($module) {
		/* $_REQUEST['REQUEST_FROM_WS'] is set from webservices script.
		 * Depending on $_REQUEST['totalProductCount'] value inserting line items into DB.
		 * This should be done by webservices, not be normal save of Inventory record.
		 * So unsetting the value $_REQUEST['totalProductCount'] through check point
		 */
		if (isset($_REQUEST['REQUEST_FROM_WS']) && $_REQUEST['REQUEST_FROM_WS']) {
			unset($_REQUEST['totalProductCount']);
		}
		//in ajax save we should not call this function, because this will delete all the existing product values
		if (isset($_REQUEST)) {
			$_REQUEST['ajxaction'] = isset($_REQUEST['ajxaction']) ? $_REQUEST['ajxaction'] : '';
			if ($_REQUEST['action'] != 'CreditNoteAjax' && $_REQUEST['ajxaction'] != 'DETAILVIEW'
					&& $_REQUEST['action'] != 'MassEditSave' && $_REQUEST['action'] != 'ProcessDuplicates'
					&& $_REQUEST['action'] != 'SaveAjax' && $this->isLineItemUpdate != false && $_REQUEST['action'] != 'FROM_WS') {
				//Based on the total Number of rows we will save the product relationship with this entity
				saveInventoryProductDetails($this, 'CreditNote');
			}
		}
		// Update the currency id and the conversion rate for the credit note
		$update_query = "update vtiger_creditnote set currency_id=?, conversion_rate=? where creditnoteid=?";
		$update_params = array($this->column_fields['currency_id'], $this->column_fields['conversion_rate'], $this->id);
		$this->db->pquery($update_query, $update_params);
	}

	/**	function used to get the name of the current object
	 *	@return string $this->name - name of the current object
	 */
	function get_summary_text() {
		return $this->name;
	}

	// Function to get column name - Overriding function of base class
	function get_column_value($columname, $fldvalue, $fieldname, $uitype, $datatype = '') {
		if ($columname == 'invoiceid') {
			if ($fldvalue == '') return null;
		}
		return parent::get_column_value($columname, $fldvalue, $fieldname, $uitype, $datatype);
	}

	/*
	 * Function to get the secondary query part of a report
	 * @param - $module primary module name
	 * @param - $secmodule secondary module name
	 * returns the query string formed on fetching the related data for report for secondary module
	 */
	function generateReportsSecQuery($module, $secmodule, $queryPlanner) {

		// Define the dependency matrix ahead
		$matrix = $queryPlanner->newDependencyMatrix();
		$matrix->setDependency('vtiger_crmentityCreditNote', array('vtiger_usersCreditNote', 'vtiger_groupsCreditNote', 'vtiger_lastModifiedByCreditNote'));
		$matrix->setDependency('vtiger_inventoryproductrelCreditNote', array('vtiger_productsCreditNote', 'vtiger_serviceCreditNote'));

		if (!$queryPlanner->requireTable('vtiger_creditnote', $matrix)) {
			return '';
		}

		$matrix->setDependency('vtiger_creditnote', array('vtiger_crmentityCreditNote', "vtiger_currency_info$secmodule",
			'vtiger_creditnotecf', 'vtiger_invoiceCreditNote', 'vtiger_creditnotebillads',
			'vtiger_creditnoteshipads', 'vtiger_inventoryproductrelCreditNote', 'vtiger_contactdetailsCreditNote', 'vtiger_accountCreditNote'));

		$query = $this->getRelationQuery($module, $secmodule, "vtiger_creditnote", "creditnoteid", $queryPlanner);

		if ($queryPlanner->requireTable('vtiger_crmentityCreditNote', $matrix)) {
			$query .= " left join vtiger_crmentity as vtiger_crmentityCreditNote on vtiger_crmentityCreditNote.crmid=vtiger_creditnote.creditnoteid and vtiger_crmentityCreditNote.deleted=0";
		}
		if ($queryPlanner->requireTable('vtiger_creditnotecf')) {
			$query .= " left join vtiger_creditnotecf on vtiger_creditnote.creditnoteid = vtiger_creditnotecf.creditnoteid";
		}
		if ($queryPlanner->requireTable("vtiger_currency_info$secmodule")) {
			$query .= " left join vtiger_currency_info as vtiger_currency_info$secmodule on vtiger_currency_info$secmodule.id = vtiger_creditnote.currency_id";
		}
		if ($queryPlanner->requireTable('vtiger_invoiceCreditNote')) {
			$query .= " left join vtiger_invoice as vtiger_invoiceCreditNote on vtiger_invoiceCreditNote.invoiceid=vtiger_creditnote.invoiceid";
		}
		if ($queryPlanner->requireTable('vtiger_creditnotebillads')) {
			$query .= " left join vtiger_creditnotebillads on vtiger_creditnote.creditnoteid=vtiger_creditnotebillads.creditnotebilladdressid";
		}
		if ($queryPlanner->requireTable('vtiger_creditnoteshipads')) {
			$query .= " left join vtiger_creditnoteshipads on vtiger_creditnote.creditnoteid=vtiger_creditnoteshipads.creditnoteshipaddressid";
		}
		if ($queryPlanner->requireTable('vtiger_productsCreditNote')) {
			$query .= " left join vtiger_products as vtiger_productsCreditNote on vtiger_productsCreditNote.productid = vtiger_inventoryproductreltmpCreditNote.productid";
		}
		if ($queryPlanner->requireTable('vtiger_serviceCreditNote')) {
			$query .= " left join vtiger_service as vtiger_serviceCreditNote on vtiger_serviceCreditNote.serviceid = vtiger_inventoryproductreltmpCreditNote.productid";
		}
		if ($queryPlanner->requireTable('vtiger_groupsCreditNote')) {
			$query .= " left join vtiger_groups as vtiger_groupsCreditNote on vtiger_groupsCreditNote.groupid = vtiger_crmentityCreditNote.smownerid";
		}
		if ($queryPlanner->requireTable('vtiger_usersCreditNote')) {
			$query .= " left join vtiger_users as vtiger_usersCreditNote on vtiger_usersCreditNote.id = vtiger_crmentityCreditNote.smownerid";
		}
		if ($queryPlanner->requireTable('vtiger_contactdetailsCreditNote')) {
			$query .= " left join vtiger_contactdetails as vtiger_contactdetailsCreditNote on vtiger_creditnote.contactid = vtiger_contactdetailsCreditNote.contactid";
		}
		if ($queryPlanner->requireTable('vtiger_accountCreditNote')) {
			$query .= " left join vtiger_account as vtiger_accountCreditNote on vtiger_accountCreditNote.accountid = vtiger_creditnote.accountid";
		}
		if ($queryPlanner->requireTable('vtiger_lastModifiedByCreditNote')) {
			$query .= " left join vtiger_users as vtiger_lastModifiedByCreditNote on vtiger_lastModifiedByCreditNote.id = vtiger_crmentityCreditNote.modifiedby ";
		}
		if ($queryPlanner->requireTable("vtiger_createdbyCreditNote")) {
			$query .= " left join vtiger_users as vtiger_createdbyCreditNote on vtiger_createdbyCreditNote.id = vtiger_crmentityCreditNote.smcreatorid ";
		}

		//if secondary modules custom reference field is selected
		$query .= parent::getReportsUiType10Query($secmodule, $queryPlanner);

		return $query;
	}

	/*
	 * Function to get the relation tables for related modules
	 * @param - $secmodule secondary module name
	 * returns the array with table names and fieldnames storing relations between module and this module
	 */
	function setRelationTables($secmodule) {
		$rel_tables = array(
			"Documents" => array("vtiger_senotesrel" => array("crmid", "notesid"), "vtiger_creditnote" => "creditnoteid"),
			"Accounts" => array("vtiger_creditnote" => array("creditnoteid", "accountid")),
			"Contacts" => array("vtiger_creditnote" => array("creditnoteid", "contactid")),
			"Invoice" => array("vtiger_creditnote" => array("creditnoteid", "invoiceid")),
		);
		return $rel_tables[$secmodule];
	}

	// Function to unlink an entity with given Id from another entity
	function unlinkRelationship($id, $return_module, $return_id) {
		if (empty($return_module) || empty($return_id)) return;

		if ($return_module == 'Accounts' || $return_module == 'Contacts' || $return_module == 'Invoice') {
			$this->trash('CreditNote', $id);
		} elseif ($return_module == 'Documents') {
			$sql = 'DELETE FROM vtiger_senotesrel WHERE crmid=? AND notesid=?';
			$this->db->pquery($sql, array($id, $return_id));
		} else {
			parent::unlinkRelationship($id, $return_module, $return_id);
		}
	}

	function insertIntoEntityTable($table_name, $module, $fileid = '') {
		//Ignore relation table insertions while saving of the record
		if ($table_name == 'vtiger_inventoryproductrel') {
			return;
		}
		parent::insertIntoEntityTable($table_name, $module, $fileid);
	}

	/*Function to create records in current module.
	**This function called while importing records to this module*/
	function createRecords($obj) {
		$createRecords = createRecords($obj);
		return $createRecords;
	}

	/*Function returns the record information which means whether the record is imported or not
	**This function called while importing records to this module*/
	function importRecord($obj, $inventoryFieldData, $lineItemDetails) {
		$entityInfo = importRecord($obj, $inventoryFieldData, $lineItemDetails);
		return $entityInfo;
	}

	/*Function to return the status count of imported records in current module.
	**This function called while importing records to this module*/
	function getImportStatusCount($obj) {
		$statusCount = getImportStatusCount($obj);
		return $statusCount;
	}

	function undoLastImport($obj, $user) {
		$undoLastImport = undoLastImport($obj, $user);
	}

	/** Function to export the credit note records in CSV Format
	 * @param reference variable - where condition is passed when the query is executed
	 * Returns Export Credit Note Query.
	 */
	function create_export_query($where) {
		global $log;
		global $current_user;
		$log->debug("Entering create_export_query(" . $where . ") method ...");

		include("include/utils/ExportUtils.php");

		//To get the Permitted fields query and the permitted fields list
		$sql = getPermittedFieldsQuery("CreditNote", "detail_view");
		$fields_list = getFieldsListFromQuery($sql);
		$fields_list .= getInventoryFieldsForExport($this->table_name);

		$query = "SELECT $fields_list FROM " . $this->entity_table . "
				INNER JOIN vtiger_creditnote ON vtiger_creditnote.creditnoteid = vtiger_crmentity.crmid
				LEFT JOIN vtiger_creditnotecf ON vtiger_creditnotecf.creditnoteid = vtiger_creditnote.creditnoteid
				LEFT JOIN vtiger_invoice ON vtiger_invoice.invoiceid = vtiger_creditnote.invoiceid
				LEFT JOIN vtiger_creditnotebillads ON vtiger_creditnotebillads.creditnotebilladdressid = vtiger_creditnote.creditnoteid
				LEFT JOIN vtiger_creditnoteshipads ON vtiger_creditnoteshipads.creditnoteshipaddressid = vtiger_creditnote.creditnoteid
				LEFT JOIN vtiger_inventoryproductrel ON vtiger_inventoryproductrel.id = vtiger_creditnote.creditnoteid
				LEFT JOIN vtiger_products ON vtiger_products.productid = vtiger_inventoryproductrel.productid
				LEFT JOIN vtiger_service ON vtiger_service.serviceid = vtiger_inventoryproductrel.productid
				LEFT JOIN vtiger_contactdetails ON vtiger_contactdetails.contactid = vtiger_creditnote.contactid
				LEFT JOIN vtiger_account ON vtiger_account.accountid = vtiger_creditnote.accountid
				LEFT JOIN vtiger_currency_info ON vtiger_currency_info.id = vtiger_creditnote.currency_id
				LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid
				LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid";

		$query .= $this->getNonAdminAccessControlQuery('CreditNote', $current_user);
		$where_auto = " vtiger_crmentity.deleted=0";

		if ($where != "") {
			$query .= " where ($where) AND " . $where_auto;
		} else {
			$query .= " where " . $where_auto;
		}

		$log->debug("Exiting create_export_query method ...");
		return $query;
	}

	/**
	 * Function to get importable mandatory fields
	 * By default some fields like Quantity, List Price is not mandaroty for Invertory modules but
	 * import fails if those fields are not mapped during import.
	 */
	function getMandatoryImportableFields() {
		return getInventoryImportableMandatoryFeilds($this->moduleName);
	}
}
