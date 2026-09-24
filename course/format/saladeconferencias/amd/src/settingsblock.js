// Drop-in for core block_settings/settingsblock (format_saladeconferencias; no core edits).
// Skips the site-admin <a> rewrite when there is no inner link so Tree init can run.

/**
 * @module     format_saladeconferencias/settingsblock
 * @copyright  2025 Viddia (http://viddia.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {notifyBlockContentUpdated} from 'core_block/events';
import Tree from 'core/tree';

export const init = (instanceId, siteAdminNodeId) => {
    const adminTree = new Tree('.block_settings .block_tree');
    const blockNode = document.querySelector(`[data-instance-id="${instanceId}"]`);

    if (siteAdminNodeId) {
        const treeRootEl = adminTree.treeRoot && adminTree.treeRoot.get(0);
        const siteAdminLink = treeRootEl
            ? treeRootEl.querySelector(`#${siteAdminNodeId} a`)
            : null;
        if (siteAdminLink) {
            const newContainer = document.createElement('span');
            newContainer.setAttribute('tabindex', '0');
            siteAdminLink.childNodes.forEach(node => newContainer.appendChild(node));
            siteAdminLink.replaceWith(newContainer);
        }
    }

    adminTree.finishExpandingGroup = function(item) {
        Tree.prototype.finishExpandingGroup.call(adminTree, item);
        if (blockNode) {
            notifyBlockContentUpdated(blockNode);
        }
    };

    adminTree.collapseGroup = function(item) {
        Tree.prototype.collapseGroup.call(adminTree, item);
        if (blockNode) {
            notifyBlockContentUpdated(blockNode);
        }
    };
};
