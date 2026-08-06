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
 * Live in-form pre-check of matching users for an automation's trigger conditions.
 *
 * Watches the conditions block for changes, posts the current form payload to the precheck
 * endpoint and updates a count badge. Clicking "Show users" fetches a sample and renders a
 * modal table.
 *
 * @module     mod_pulse/precheck
 * @copyright  2026, bdecent gmbh bdecent.de
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define("mod_pulse/precheck", ['jquery', 'core/modal_factory', 'core/str', 'core/notification'],
    function($, ModalFactory, Str, Notification) {

        var SELECTORS = {
            WIDGET: '#pulse-precheck',
            BADGE: '#pulse-precheck-count',
            STATUS: '#pulse-precheck-status',
            SHOW: '#pulse-precheck-show',
            CONDITIONS: '[name^="condition["], [name="triggeroperator"]',
        };
        var DEBOUNCE_MS = 800;
        var debouncetimer = null;
        var inflight = null;
        var activeModal = null;
        var paginationState = null;

        /**
         * Initialise the widget.
         *
         * @param {Object} opts {courseid, instanceid}
         */
        var init = function(opts) {
            var $widget = $(SELECTORS.WIDGET);
            if (!$widget.length) {
                return;
            }
            var $form = $widget.closest('form');
            if (!$form.length) {
                return;
            }

            $form.on('change input', SELECTORS.CONDITIONS, function() {
                scheduleRefresh($form, opts);
            });
            $widget.on('click', SELECTORS.SHOW, function(e) {
                e.preventDefault();
                showUsers($form, opts);
            });

            // Initial fetch.
            refresh($form, opts);
        };

        var scheduleRefresh = function($form, opts) {
            if (debouncetimer) {
                clearTimeout(debouncetimer);
            }
            $(SELECTORS.BADGE).text('…');
            debouncetimer = setTimeout(function() {
                refresh($form, opts);
            }, DEBOUNCE_MS);
        };

        // Parse a bracketed field name like "condition[notenrolled][window][number]" into
        // ["notenrolled", "window", "number"]. Returns null if the name doesn't start with
        // "condition[" or doesn't parse. Empty bracket "[]" pairs become empty string segments
        // which the caller treats as array appends.
        var parseConditionName = function(name) {
            if (name.indexOf('condition[') !== 0) {
                return null;
            }
            var tail = name.substring('condition'.length);
            var parts = [];
            var re = /\[([^\]]*)\]/g;
            var m;
            while ((m = re.exec(tail)) !== null) {
                parts.push(m[1]);
            }
            return parts.length ? parts : null;
        };

        var serialise = function($form) {
            var conditions = {};
            var triggerop = parseInt($form.find('[name="triggeroperator"]').val() || 2, 10);
            var hasenabled = false;

            $form.find('[name^="condition["]').each(function() {
                var path = parseConditionName(this.name);
                if (!path) {
                    return;
                }

                var $el = $(this);
                var val;
                if ($el.is(':checkbox')) {
                    val = $el.prop('checked') ? $el.val() : null;
                } else if ($el.is('select[multiple]')) {
                    val = $el.val() || [];
                } else {
                    val = $el.val();
                }
                if (val === null || val === undefined || val === '') {
                    return;
                }

                // Walk into nested objects, creating containers as we go. An empty segment ""
                // means an array append (e.g. condition[x][field][] multiselects).
                var node = conditions;
                for (var i = 0; i < path.length - 1; i++) {
                    var key = path[i];
                    if (key === '') {
                        // Shouldn't appear mid-path; skip defensively.
                        return;
                    }
                    if (typeof node[key] !== 'object' || node[key] === null) {
                        node[key] = {};
                    }
                    node = node[key];
                }
                var last = path[path.length - 1];
                if (last === '') {
                    // Array append.
                    var arrKey = path[path.length - 2];
                    if (!Array.isArray(node)) {
                        // Can't append to non-array; promote to array if empty object.
                        // (Reaches here only for weird cases; the loop above already nested.)
                    }
                    if (!Array.isArray(node)) {
                        // The array is at the parent under `arrKey`. Re-resolve.
                        node = conditions;
                        for (var j = 0; j < path.length - 2; j++) {
                            node = node[path[j]];
                        }
                        if (!Array.isArray(node[arrKey])) {
                            node[arrKey] = [];
                        }
                        if (Array.isArray(val)) {
                            node[arrKey] = node[arrKey].concat(val);
                        } else {
                            node[arrKey].push(val);
                        }
                    }
                } else {
                    node[last] = val;
                }

                // hasEnabled = any condition[X][status] is > 0.
                if (path.length === 2 && path[1] === 'status' && parseInt(val, 10) > 0) {
                    hasenabled = true;
                }
            });

            return {
                conditions: conditions,
                triggeroperator: triggerop,
                hasenabled: hasenabled,
            };
        };

        var refresh = function($form, opts) {
            var payload = serialise($form);
            if (!payload.hasenabled) {
                renderCount(0, false, true);
                return $.Deferred().resolve().promise();
            }
            return fetch($form, opts, payload, false).done(function(resp) {
                if (resp && typeof resp.total !== 'undefined') {
                    renderCount(resp.total, resp.truncated, false);
                }
            }).fail(function() {
                $(SELECTORS.BADGE).text('?');
            });
        };

        var showUsers = function($form, opts) {
            var payload = serialise($form);
            if (!payload.hasenabled) {
                return;
            }
            // Always start fresh — the hidden.bs.modal cleanup may not have run
            // if the modal element was destroyed before the event fired.
            activeModal = null;
            paginationState = {$form: $form, opts: opts, payload: payload};
            loadPage(0);
        };

        var loadPage = function(page) {
            var state = paginationState;
            if (!state) {
                return;
            }
            if (activeModal) {
                activeModal.setBody(
                    '<div class="text-center py-4">' +
                    '<div class="spinner-border text-primary" role="status">' +
                    '<span class="sr-only">Loading…</span></div></div>'
                );
            }
            Str.get_strings([
                {key: 'precheck_modaltitle',    component: 'mod_pulse'},
                {key: 'precheck_modalcolid',    component: 'mod_pulse'},
                {key: 'precheck_modalcolname',  component: 'mod_pulse'},
                {key: 'precheck_modalcolemail', component: 'mod_pulse'},
                {key: 'precheck_modalnone',     component: 'mod_pulse'},
                {key: 'precheck_modalmore',     component: 'mod_pulse'},
                {key: 'precheck_modalfallback', component: 'mod_pulse'},
                {key: 'precheck_pageinfo',      component: 'mod_pulse'},
            ]).done(function(s) {
                fetch(state.$form, state.opts, state.payload, true, page).done(function(resp) {
                    renderPage(resp, page, s);
                }).fail(Notification.exception);
            });
        };

        var renderPage = function(resp, page, s) {
            var body = buildBody(resp, page, s);
            if (activeModal) {
                activeModal.setBody(body);
                return;
            }
            ModalFactory.create({
                type: ModalFactory.types.CANCEL,
                title: s[0],
                body: body,
                large: true,
            }).done(function(modal) {
                activeModal = modal;
                modal.getRoot().on('hidden.bs.modal', function() {
                    activeModal = null;
                });
                modal.getRoot().on('click', '.pulse-precheck-page', function(e) {
                    e.preventDefault();
                    loadPage(parseInt($(this).data('page'), 10));
                });
                modal.show();
            });
        };

        var buildBody = function(resp, page, s) {
            var body;
            if (!resp.sample || !resp.sample.length) {
                body = '<p>' + s[4] + '</p>';
            } else {
                var rows = resp.sample.map(function(u) {
                    return '<tr><td>' + u.id + '</td>' +
                           '<td>' + escapeHtml(u.fullname) + '</td>' +
                           '<td>' + escapeHtml(u.email) + '</td></tr>';
                }).join('');
                body = '<table class="table table-sm table-striped">' +
                    '<thead><tr><th>' + s[1] + '</th><th>' + s[2] + '</th><th>' + s[3] + '</th></tr></thead>' +
                    '<tbody>' + rows + '</tbody></table>';

                if (resp.totalpages > 1) {
                    var pageInfo = s[7]
                        .replace('{$a->page}', page + 1)
                        .replace('{$a->totalpages}', resp.totalpages)
                        .replace('{$a->total}', resp.total);
                    body += '<p class="text-muted small text-center mb-1">' + pageInfo + '</p>';
                    body += buildPagination(page, resp.totalpages);
                }
            }
            if (resp.fallback && resp.fallback.length) {
                body += '<p class="text-muted small mt-2"><em>' +
                        s[6].replace('{$a}', resp.fallback.join(', ')) +
                        '</em></p>';
            }
            return body;
        };

        var buildPagination = function(currentPage, totalPages) {
            var html = '<nav aria-label="Users pagination">' +
                       '<ul class="pagination pagination-sm justify-content-center mb-0">';

            var prevDisabled = currentPage <= 0;
            html += '<li class="page-item' + (prevDisabled ? ' disabled' : '') + '">';
            html += prevDisabled
                ? '<span class="page-link">&laquo;</span>'
                : '<a class="page-link pulse-precheck-page" href="#" data-page="' + (currentPage - 1) + '">&laquo;</a>';
            html += '</li>';

            var startPage = Math.max(0, currentPage - 2);
            var endPage   = Math.min(totalPages - 1, currentPage + 2);

            if (startPage > 0) {
                html += '<li class="page-item">' +
                        '<a class="page-link pulse-precheck-page" href="#" data-page="0">1</a></li>';
                if (startPage > 1) {
                    html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                }
            }
            for (var i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += '<li class="page-item active"><span class="page-link">' + (i + 1) + '</span></li>';
                } else {
                    html += '<li class="page-item">' +
                            '<a class="page-link pulse-precheck-page" href="#" data-page="' + i + '">' + (i + 1) + '</a></li>';
                }
            }
            if (endPage < totalPages - 1) {
                if (endPage < totalPages - 2) {
                    html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                }
                html += '<li class="page-item">' +
                        '<a class="page-link pulse-precheck-page" href="#" data-page="' + (totalPages - 1) + '">' +
                        totalPages + '</a></li>';
            }

            var nextDisabled = currentPage >= totalPages - 1;
            html += '<li class="page-item' + (nextDisabled ? ' disabled' : '') + '">';
            html += nextDisabled
                ? '<span class="page-link">&raquo;</span>'
                : '<a class="page-link pulse-precheck-page" href="#" data-page="' + (currentPage + 1) + '">&raquo;</a>';
            html += '</li>';

            html += '</ul></nav>';
            return html;
        };

        var fetch = function($form, opts, payload, sample, page) {
            if (inflight) {
                inflight.abort();
            }
            var requestdata = {
                sesskey: M.cfg.sesskey,
                instanceid: opts.instanceid || 0,
                courseid: opts.courseid || 0,
                triggeroperator: payload.triggeroperator,
                conditions: JSON.stringify(payload.conditions),
                sample: sample ? 1 : 0,
                page: page || 0,
            };
            // Diagnostic — last request/response are accessible from the browser console as
            // `window.modPulsePrecheck`. Useful when the badge disagrees with expectations.
            inflight = $.ajax({
                url: M.cfg.wwwroot + '/mod/pulse/automation/instances/precheck_ajax.php',
                method: 'POST',
                dataType: 'json',
                data: requestdata,
            });
            inflight.always(function(respOrXhr) {
                window.modPulsePrecheck = {
                    sentAt: new Date(),
                    payload: payload,
                    requestdata: requestdata,
                    response: typeof respOrXhr === 'object' && respOrXhr && respOrXhr.responseJSON
                        ? respOrXhr.responseJSON
                        : respOrXhr,
                };
            });
            return inflight;
        };

        var renderCount = function(total, truncated, idle) {
            var $badge = $(SELECTORS.BADGE);
            $badge.removeClass('badge-secondary badge-info badge-success badge-warning');
            if (idle) {
                $badge.addClass('badge-secondary').text('—');
                $(SELECTORS.SHOW).addClass('disabled').attr('aria-disabled', 'true');
                return;
            }
            $badge.addClass(total === 0 ? 'badge-warning' : 'badge-success')
                  .text(total + (truncated ? '+' : ''));
            $(SELECTORS.SHOW).toggleClass('disabled', total === 0)
                             .attr('aria-disabled', total === 0 ? 'true' : 'false');
        };

        var escapeHtml = function(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
                return {
                    '&': '&amp;', '<': '&lt;', '>': '&gt;',
                    '"': '&quot;', "'": '&#39;',
                }[c];
            });
        };

        return {init: init};
    });
