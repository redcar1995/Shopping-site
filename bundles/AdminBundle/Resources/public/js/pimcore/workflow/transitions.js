/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

pimcore.registerNS("pimcore.workflow.transitions.x");

pimcore.workflow.transitions.perform = function (ctype, cid, elementEditor, workflow, transition) {


    Ext.Ajax.request({
        url :  transition.isGlobalAction ? '/admin/workflow/submit-global-action' : '/admin/workflow/submit-workflow-transition',
        method: 'post',
        params: {
            ctype: ctype,
            cid: cid,
            workflowName: workflow,
            transition: transition.name
        },
        success: function(response) {
            var data = Ext.decode(response.responseText);

            if(data.success) {

                pimcore.helpers.showNotification(t("workflow_transition_applied_successfully"), transition.label, "success");

                elementEditor.reload();

            } else {
                Ext.MessageBox.alert(data.message, data.reason);
            }


        },
        failure: function(res) {
            alert('oh no!');
        }
    });
};
