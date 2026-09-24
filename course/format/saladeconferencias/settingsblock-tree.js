/**
 * Settings block tree: expand branches labelled with <span> only (e.g. "Usuários").
 *
 * @package   format_saladeconferencias
 * @copyright 2025 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    function findGroup(item, root) {
        var owns = item.getAttribute('aria-owns');
        if (owns) {
            try {
                var byowns = root.querySelector('#' + CSS.escape(owns));
                if (byowns) {
                    return byowns;
                }
            } catch (e) {
                var fallback = root.querySelector('#' + owns);
                if (fallback) {
                    return fallback;
                }
            }
        }
        return item.querySelector(':scope > ul[role="group"]');
    }

    function bindTree(root) {
        if (root.getAttribute('data-pdi-settings-tree-fix') === '1') {
            return;
        }
        root.setAttribute('data-pdi-settings-tree-fix', '1');

        root.addEventListener('click', function(e) {
            var item = e.target.closest('[role="treeitem"].contains_branch');
            if (!item || !root.contains(item)) {
                return;
            }

            var branchRow = item.querySelector(':scope > p.tree_item.branch');
            if (!branchRow || !branchRow.contains(e.target)) {
                return;
            }

            var link = e.target.closest('a[href]');
            if (link && branchRow.contains(link)) {
                var href = (link.getAttribute('href') || '').trim();
                if (href && href !== '#') {
                    return;
                }
            }

            var group = findGroup(item, root);
            if (!group) {
                return;
            }

            var expand = item.getAttribute('aria-expanded') !== 'true';
            item.setAttribute('aria-expanded', expand ? 'true' : 'false');
            if (expand) {
                group.removeAttribute('aria-hidden');
            } else {
                group.setAttribute('aria-hidden', 'true');
            }

            e.preventDefault();
            e.stopImmediatePropagation();
        }, true);
    }

    function init() {
        document.querySelectorAll('.block_settings .block_tree').forEach(bindTree);
    }

    document.addEventListener('core_block/contentUpdated', function(e) {
        var target = e.target;
        if (!target || !target.closest) {
            return;
        }
        var block = target.closest('.block_settings');
        if (!block) {
            return;
        }
        var tree = block.querySelector('.block_tree');
        if (tree) {
            bindTree(tree);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
