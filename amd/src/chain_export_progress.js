// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Chain export progress polling (AMD).
 *
 * @module     block_configurable_reports/chain_export_progress
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    var SELECTOR_ROOT = '[data-region="chain-export-progress"]';

    /**
     * Format seconds as human-readable ETA.
     *
     * @param {Number} seconds
     * @return {String}
     */
    var formatEta = function(seconds) {
        if (!seconds || seconds < 1) {
            return '';
        }
        if (seconds < 60) {
            return seconds + ' s';
        }
        var minutes = Math.round(seconds / 60);
        return minutes + ' min';
    };

    /**
     * Update progress UI from status payload.
     *
     * @param {Object} root
     * @param {Object} status
     */
    var applyStatus = function(root, status) {
        var percent = status.progresspercent;
        var done = status.progressdone;
        var total = status.progresstotal;
        var terminal = ['completed', 'partial', 'failed', 'cancelled'].indexOf(status.status) >= 0;
        var finishedok = status.status === 'completed' || status.status === 'partial';
        if (finishedok && total > 0) {
            percent = 100;
            done = total;
        }

        root.find('[data-progressbar]').attr('aria-valuenow', percent)
            .css('width', percent + '%')
            .text(percent + '%');
        root.find('[data-progresslabel]').text(done + ' / ' + total);

        if (status.status === 'queued' && status.queueposition > 0) {
            Str.get_string('chainexportqueuewait', 'block_configurable_reports', {
                position: status.queueposition,
                eta: formatEta(status.etaseconds) || '?'
            }).then(function(msg) {
                root.find('[data-statustext]').text(msg);
            }).catch(Notification.exception);
        } else if (terminal) {
            Str.get_string('chainexportstatus_' + status.status, 'block_configurable_reports').then(function(msg) {
                root.find('[data-statustext]').text(msg);
            }).catch(function() {
                root.find('[data-statustext]').text(status.status);
            });
        } else {
            Str.get_string('chainexportstatus_' + status.status, 'block_configurable_reports').then(function(msg) {
                var statusText = msg;
                if (status.etaseconds > 0) {
                    statusText += ' ~' + formatEta(status.etaseconds);
                }
                root.find('[data-statustext]').text(statusText);
            }).catch(function() {
                root.find('[data-statustext]').text(status.status);
            });
        }

        if (status.errormessage) {
            root.find('[data-error]').removeClass('d-none').text(status.errormessage);
        } else {
            root.find('[data-error]').addClass('d-none');
        }

        var downloadLink = root.find('[data-downloadlink]');
        if (status.downloadable) {
            downloadLink.removeClass('d-none');
            var ready = root.find('[data-downloadready]');
            if (ready.length && status.exportedcount > 0) {
                Str.get_string('chainexportdownloadready', 'block_configurable_reports', status.exportedcount)
                    .then(function(msg) {
                        ready.removeClass('d-none').text(msg);
                    });
            }
            root.find('[data-nodownload]').addClass('d-none');
        } else {
            downloadLink.addClass('d-none');
            root.find('[data-downloadready]').addClass('d-none');
            if (finishedok && status.dismissable) {
                root.find('[data-nodownload]').removeClass('d-none');
            } else {
                root.find('[data-nodownload]').addClass('d-none');
            }
        }

        var dismissBtn = root.find('[data-dismissbutton]');
        if (status.dismissable) {
            dismissBtn.removeClass('d-none');
        } else {
            dismissBtn.addClass('d-none');
        }

        if (terminal) {
            root.data('polling', 0);
            root.find('[data-cancelbutton]').addClass('d-none');
        }
    };

    /**
     * Poll job status.
     *
     * @param {Object} root
     */
    var poll = function(root) {
        if (!root.data('polling')) {
            return;
        }
        var jobid = root.data('jobid');
        Ajax.call([{
            methodname: 'block_configurable_reports_get_chain_export_status',
            args: {jobid: jobid},
            done: function(status) {
                applyStatus(root, status);
                if (root.data('polling')) {
                    window.setTimeout(function() {
                        poll(root);
                    }, 2500);
                }
            },
            fail: Notification.exception
        }]);
    };

    var bindDismiss = function(root) {
        root.find('[data-dismissbutton]').on('click', function(e) {
            e.preventDefault();
            Str.get_strings([
                {key: 'chainexportdismissconfirm', component: 'block_configurable_reports'},
                {key: 'yes'},
                {key: 'no'}
            ]).then(function(strings) {
                if (!window.confirm(strings[0])) {
                    return;
                }
                Ajax.call([{
                    methodname: 'block_configurable_reports_dismiss_chain_export',
                    args: {jobid: root.data('jobid')},
                    done: function(status) {
                        if (status.dismissed && root.data('newexporturl')) {
                            window.location.href = root.data('newexporturl');
                            return;
                        }
                        applyStatus(root, status);
                    },
                    fail: Notification.exception
                }]);
            });
        });
    };

    /**
     * Bind cancel button.
     *
     * @param {Object} root
     */
    var bindCancel = function(root) {
        root.find('[data-cancelbutton]').on('click', function(e) {
            e.preventDefault();
            Str.get_strings([
                {key: 'chainexportcancelconfirm', component: 'block_configurable_reports'},
                {key: 'chainexportcancelhint', component: 'block_configurable_reports'},
                {key: 'yes'},
                {key: 'no'}
            ]).then(function(strings) {
                if (!window.confirm(strings[0] + '\n\n' + strings[1])) {
                    return;
                }
                Ajax.call([{
                    methodname: 'block_configurable_reports_cancel_chain_export',
                    args: {jobid: root.data('jobid')},
                    done: function(status) {
                        applyStatus(root, status);
                    },
                    fail: Notification.exception
                }]);
            });
        });
    };

    /**
     * Init module.
     */
    var init = function() {
        $(SELECTOR_ROOT).each(function() {
            var root = $(this);
            if (root.data('initialized')) {
                return;
            }
            root.data('initialized', 1);
            root.data('polling', 1);
            bindCancel(root);
            bindDismiss(root);
            poll(root);
        });
    };

    return {
        init: init
    };
});
