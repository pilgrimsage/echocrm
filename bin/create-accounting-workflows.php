<?php
/**
 * Creates four ready-made workflows for the accounting suite. All of them create a To-Do (they need
 * no mail server) and can be edited, switched off or deleted in Settings > Workflows:
 *   - Purchase Order above 50,000: a To-Do for the administrator to approve it (due in 2 days)
 *   - Payment saved as Pending (e.g. a cheque not yet cleared): a To-Do for its owner to confirm it (3 days)
 *   - Payment saved as Failed: a To-Do for its owner to follow up (1 day)
 *   - Bank transaction out above 100,000: a To-Do for the administrator to review it (1 day)
 * The To-Do text can use merge fields such as $purchaseorder_no. Each workflow runs once, when its
 * condition first becomes true. Safe to run more than once (a workflow with the same name is kept).
 *
 * Usage (from the project root):   php bin/create-accounting-workflows.php
 */
if (PHP_SAPI !== 'cli') {
	exit("Run this from the command line.\n");
}
chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/utils/utils.php';
vimport('includes.runtime.EntryPoint');
require_once 'modules/com_vtiger_workflow/VTWorkflowManager.inc';
require_once 'modules/com_vtiger_workflow/VTTaskManager.inc';

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();
$adminId = (int)$current_user->id;

function say($message) {
	echo $message . "\n";
}

function condition($field, $operation, $value) {
	return array('fieldname' => $field, 'operation' => $operation, 'value' => $value, 'valuetype' => 'rawtext', 'joincondition' => 'and', 'groupid' => '0');
}

$definitions = array(
	array(
		'module' => 'PurchaseOrder',
		'name' => 'Purchase order above 50,000: approval to-do',
		'conditions' => array(condition('hdnGrandTotal', 'greater than', '50000')),
		'todo' => 'Approve purchase order $purchaseorder_no',
		'description' => 'The purchase order $purchaseorder_no ($subject) is above 50,000 and needs approval before it is sent to the vendor.',
		'days' => 2, 'priority' => 'High', 'assignee' => $adminId,
	),
	array(
		'module' => 'Payments',
		'name' => 'Pending payment: confirm receipt to-do',
		'conditions' => array(condition('payment_status', 'is', 'Pending')),
		'todo' => 'Confirm pending payment $payment_no',
		'description' => 'Payment $payment_no of $amount is Pending (for example a cheque not yet cleared). Confirm it and set the status to Completed, or mark it Failed.',
		'days' => 3, 'priority' => 'Medium', 'assignee' => '',
	),
	array(
		'module' => 'Payments',
		'name' => 'Failed payment: follow-up to-do',
		'conditions' => array(condition('payment_status', 'is', 'Failed')),
		'todo' => 'Follow up failed payment $payment_no',
		'description' => 'Payment $payment_no of $amount failed. Contact the customer or vendor and record a new payment.',
		'days' => 1, 'priority' => 'High', 'assignee' => '',
	),
	array(
		'module' => 'BankTransactions',
		'name' => 'Large bank payment out: review to-do',
		'conditions' => array(condition('direction', 'is', 'Out'), condition('amount', 'greater than', '100000')),
		'todo' => 'Review large bank payment $transaction_no',
		'description' => 'Bank transaction $transaction_no of $amount went out of the account. Check it against the approval and the supporting document.',
		'days' => 1, 'priority' => 'High', 'assignee' => $adminId,
	),
);

$wm = new VTWorkflowManager($adb);
$tm = new VTTaskManager($adb);
foreach ($definitions as $definition) {
	if (!Vtiger_Module::getInstance($definition['module'])) {
		say("{$definition['module']} is not installed, skipped: {$definition['name']}");
		continue;
	}
	$existing = $adb->pquery('SELECT 1 FROM com_vtiger_workflows WHERE summary = ? AND module_name = ?', array($definition['name'], $definition['module']));
	if ($adb->num_rows($existing)) {
		say("Exists: {$definition['name']}");
		continue;
	}
	$workflow = $wm->newWorkflow($definition['module']);
	$workflow->description = $definition['name'];
	$workflow->test = json_encode($definition['conditions']);
	$workflow->executionCondition = VTWorkflowManager::$ONCE;
	$workflow->status = 1;
	$wm->save($workflow);

	$task = $tm->createTask('VTCreateTodoTask', $workflow->id);
	$task->summary = $definition['name'];
	$task->active = true;
	$task->todo = $definition['todo'];
	$task->description = $definition['description'];
	$task->status = 'Not Started';
	$task->priority = $definition['priority'];
	$task->days = (string)$definition['days'];
	$task->direction = 'after';
	$task->datefield = 'createdtime';
	$task->time = '';
	$task->sendNotification = '';
	$task->assigned_user_id = $definition['assignee'];
	$tm->saveTask($task);
	say("Created: {$definition['name']}");
}
say('Done. Review them in Settings > Workflows.');
