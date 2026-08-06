/**
 * Edit user credits.
 *
 * @module     pulseaction_credits/edit_user_credits
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'core_form/modalform', 'core/local/inplace_editable/events'],
    function ($, Notification, ModalForm, InplaceEditableEvents) {

        /**
         * Initialize edit user credits functionality.
         * @param {string} title Modal title
         */
        var init = function (title) {

            document.addEventListener('click', function (e) {

                if (!e.target.closest('[data-action="edit-user-credits"]')) {
                    return true;
                }

                e.preventDefault();

                var button = e.target.closest('[data-action="edit-user-credits"]');
                var userid = button.dataset.userid;
                var courseid = button.dataset.courseid;

                if (!courseid) {
                    Notification.addNotification({
                        message: 'Course ID not found',
                        type: 'error'
                    });
                    return;
                }

                showUserOverrideModal(userid, courseid, title);
            });

            // Update the notifications after inplace editable element is updated.
            document.addEventListener(InplaceEditableEvents.eventTypes.elementUpdated, e => {
                const inplaceEditable = e.target;
                if (inplaceEditable.getAttribute('data-component') == 'pulseaction_credits') {
                    Notification.fetchNotifications();
                }
            });
        };

        /**
         * Show the edit credits modal with dynamic form.
         *
         * @param {number} userid User ID
         * @param {number} courseid Course ID
         * @param {string} title Modal title
         */
        var showUserOverrideModal = function (userid, courseid, title) {

            const modalForm = new ModalForm({
                formClass: 'pulseaction_credits\\form\\edit_user_credits',
                args: { userid: userid, courseid: courseid },
                modalConfig: {
                    title: title,
                    large: true
                },
                returnFocus: null
            });

            // Listen to form events.
            modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, function (e) {
                const response = e.detail;

                if (response.result) {
                    Notification.addNotification({
                        message: response.message,
                        type: 'success'
                    });
                    // Reload the page to show updated data.
                    window.location.reload();
                } else {
                    Notification.addNotification({
                        message: response.message,
                        type: 'error'
                    });
                }
            });
            modalForm.show();
        };

        return {
            init: init
        };

    });
