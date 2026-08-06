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
 * Chapter datasource for the notification action autocomplete selector.
 *
 * This module is compatible with core/form-autocomplete.
 *
 * @module     pulseaction_notification/chaptersource
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/fragment',
    'core/str', 'core/modal_events', 'core/modal'],
    function ($, Ajax, Notification, Fragment, Str, ModalEvents, Modal) {

        const previewModalBody = function (contextID, userid = null) {

            var params;
            if (window.tinyMCE !== undefined && window.tinyMCE.get('id_pulsenotification_headercontent_editor')) {
                // EditorPlugin = window.tinyMCE;
                params = {
                    contentheader: window.tinyMCE.get('id_pulsenotification_headercontent_editor').getContent(),
                    contentstatic: window.tinyMCE.get('id_pulsenotification_staticcontent_editor').getContent(),
                    contentfooter: window.tinyMCE.get('id_pulsenotification_footercontent_editor').getContent(),
                    userid: userid
                };
            } else {
                // EditorPlugin = document;
                params = {
                    contentheader: document.querySelector('#id_pulsenotification_headercontent_editoreditable').innerHTML,
                    contentstatic: document.querySelector('#id_pulsenotification_staticcontent_editoreditable').innerHTML,
                    contentfooter: document.querySelector('#id_pulsenotification_footercontent_editoreditable').innerHTML,
                    userid: userid
                };
            }

            var dynamicparams = {};

            if (document.querySelector('[name=pulsenotification_dynamiccontent]') !== null) {
                dynamicparams = {
                    contentdynamic: document.querySelector('[name=pulsenotification_dynamiccontent]').value,
                    contenttype: document.querySelector('[name=pulsenotification_contenttype]').value,
                    chapterid: document.querySelector('[name=pulsenotification_chapterid]').value,
                    contentlength: document.querySelector('[name=pulsenotification_contentlength]').value,
                };
            }
            // Get the form data.
            var formData;
            var form = document.forms['pulse-automation-template'];
            var formdata = new FormData(form);
            formdata = new URLSearchParams(formdata).toString();
            formData = {
                formdata: formdata
            };

            var finalParams = { ...params, ...dynamicparams, ...formData };

            return Fragment.loadFragment('pulseaction_notification', 'preview_content', contextID, finalParams);
        };

        const previewModal = function (contextID) {

            var promise;
            if (typeof Modal.registerModalType !== 'undefined') {
                // Moodle 4.3+
                promise = Modal.create({
                    title: Str.get_string('preview', 'pulseaction_notification'),
                    body: previewModalBody(contextID),
                    large: true,
                });
            } else {
                // Old Moodle (<4.3) fallback
                promise = new Promise(function(resolve, reject) {
                    require(['core/modal_factory'], function(ModalFactory) {
                        ModalFactory.create({
                            title: Str.get_string('preview', 'pulseaction_notification'),
                            body: previewModalBody(contextID),
                            large: true,
                        }).then(resolve).catch(reject);
                    }, reject);
                });
            }

            promise.then((modal) => {
                modal.show();

                modal.getRoot().on(ModalEvents.bodyRendered, function () {
                    modal.getRoot().get(0).querySelector('[name=userselector]').addEventListener('change', (e) => {
                        e.preventDefault();
                        var target = e.target;
                        modal.setBody(previewModalBody(contextID, target.value));
                    });
                });

                return;
            }).catch();
        };

        const notificationModal = function (contextID, instance, userid, relateduserid) {

            var params = {
                instanceid: instance,
                userid: userid,
                relateduserid: relateduserid
            };
            var promise;
            if (typeof Modal.registerModalType !== 'undefined') {
                // Moodle 4.3+
                promise = Modal.create({
                    title: Str.get_string('preview', 'pulseaction_notification'),
                    body: Fragment.loadFragment('pulseaction_notification', 'preview_instance_content', contextID, params),
                    large: true,
                });
            } else {
                // Old Moodle (<4.3) fallback
                promise = new Promise(function(resolve, reject) {
                    require(['core/modal_factory'], function(ModalFactory) {
                        ModalFactory.create({
                            title: Str.get_string('preview', 'pulseaction_notification'),
                            body: Fragment.loadFragment('pulseaction_notification', 'preview_instance_content', contextID, params),
                            large: true,
                        }).then(resolve).catch(reject);
                    }, reject);
                });
            }

            promise.then((modal) => {
                modal.show();
                return;
            }).catch();
        };

        var reportmodalinitialised = false;

        return {

            processResults: function (selector, modules) {
                return modules;
            },

            transport: function (selector, query, success, failure) {

                var mod = document.querySelector("#id_pulsenotification_dynamiccontent");

                var promise = Ajax.call([{
                    methodname: 'pulseaction_notification_get_chapters',
                    args: { mod: mod.value }
                }]);

                promise[0].then(function (result) {
                    success(result);
                    return;
                }).fail(failure);
            },

            updateChapter: function (ctxID) {

                const SELECTORS = {
                    chaperType: "#id_pulsenotification_contenttype",
                    mod: "#id_pulsenotification_dynamiccontent"
                };

                // Read the list of page/book cm ids from the data attribute written by PHP
                // instead of passing it through js_call_amd (which has a 1024-char limit).
                var modEl = document.querySelector(SELECTORS.mod);
                var contentMods = modEl ? JSON.parse(modEl.dataset.contentmods || 'null') : null;

                // Disable the content type option for modules other than book and page.
                if (contentMods !== null) {
                    var type = document.querySelector(SELECTORS.chaperType);
                    document.querySelector(SELECTORS.mod).addEventListener("change", (e) => {
                        var target = e.currentTarget;
                        var selected = target.value;
                        if (contentMods.includes(selected.toString())) {
                            Array.prototype.find.call(type.options, function (cmid) {
                                if (cmid.value == '2') {
                                    cmid.disabled = false;
                                }
                            });
                        } else {
                            Array.prototype.find.call(type.options, function (cmid) {
                                if (cmid.value == '2') {
                                    cmid.disabled = true;
                                }
                            });
                        }
                    });
                }

                document.querySelector(SELECTORS.chaperType).addEventListener("change", () => resetChapter());
                document.querySelector(SELECTORS.mod).addEventListener("change", () => resetChapter());
                var chapter = document.querySelector("#id_pulsenotification_chapterid");

                /**
                 *
                 */
                function resetChapter() {
                    chapter.innerHTML = '';
                    chapter.value = '';
                    var event = new Event('change');
                    chapter.dispatchEvent(event);
                }
            },

            previewNotification: function (contextid) {

                var btn = document.querySelector('[name="pulsenotification_preview"]');

                if (btn === null) {
                    return;
                }

                btn.addEventListener('click', function () {
                    previewModal(contextid);
                });
            },

            reportModal: function (contextID) {

                if (reportmodalinitialised) {
                    return;
                }
                reportmodalinitialised = true;

                document.addEventListener('click', function (e) {

                    if (e.target.closest('[data-target="view-content"]') !== null) {

                        var target = e.target.closest('a');

                        var instance = target.dataset.instanceid;
                        var userid = target.dataset.userid;
                        var relateduserid = target.dataset.relateduserid || null;

                        notificationModal(contextID, instance, userid, relateduserid); // Notification modal.
                    }
                });
            },

            toogleEmailVarsVisibility: function (actionStatusID) {
                var selector = ".mod-pulse-emailvars-toggle";
                var emailvars = document.querySelectorAll(selector);
                if (emailvars === undefined || emailvars === null) {
                    return;
                }

                const actionStatus = document.querySelector(actionStatusID);
                const toogleVisibility = function (editor) {
                    editor.hidden = !parseInt(actionStatus.value);
                };

                emailvars.forEach(toogleVisibility);
                actionStatus
                    ?.addEventListener('change', () => emailvars.forEach(toogleVisibility));
            },
        };

    });
