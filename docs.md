# About Pulse - 2.0

Pulse streamlines and automates processes within the system based on predefined conditions or events. When these conditions are met, such as a student completing an assignment or an important announcement being made, Pulse takes immediate action by sending notifications to the relevant users. This functionality not only simplifies and expedites communication but also enhances user engagement and overall efficiency within the system. Pulse's ability to automate notifications can greatly improve the user experience and reduce the manual effort required for managing and disseminating important information.


# Architecture

The new Pulse architecture comprises the following key components:

1. **Automation Templates:**

   These are globally managed by automation managers with the appropriate capabilities.

2. **Automation Instances:**

   These instances are generated in accordance with automation templates and are maintained in synchronization with their respective templates. They provide the flexibility to override specific settings on a per-instance basis.

3. **Automation Conditions:**

   Conditions serve as triggers for automation and depend on events or task completions. They are constructed in a modular fashion for enhanced flexibility.

4. **Automation Actions:**

   These actions embody the results of the automation process, defining the actual events that occur. They are constructed in a modular fashion, with the initial priority centered primarily on notifications.


# Pulse - General settings

**Number of schedule count**

This configuration option enables precise control over the quantity of scheduled task notifications dispatched during each cron execution. By specifying a numerical value, you can effectively manage the frequency at which system administrators receive notifications regarding the completion or status of scheduled tasks.

![Pulse-general-setting](https://github.com/bdecentgmbh/moodle-mod_pulse/assets/57126778/fe81d840-4fb0-4c7f-a605-c09d8e7a3853)

**Available events (Events Condition)**

This setting determines which Moodle events are available for selection in the Events condition within automation templates and instances. By default, the most commonly used events are included, but site administrators can customize this list to include additional events or remove unused ones.

The default events include:
- Course events (course viewed, course completed, activity/resource created)
- Assignment events (extension granted, submission created, submission graded)
- Forum events (discussion created, post created)
- Quiz events (attempt started, attempt submitted)
- Enrollment events (user enrolled, user unenrolled)
- Completion & grading events (activity completion updated, user graded)

Site administrators can access this setting through: **Site administration → Plugins → Activity modules → Pulse → Events condition settings**

![Event-conditions](https://github.com/user-attachments/assets/1212e89c-8c73-491b-ac4e-daf88c987863)

**Custom mail recipients (Notification Action)**

This setting allows site administrators to define custom email addresses that can receive Pulse notifications, even if they are not Moodle users. This is useful for sending notifications to department email addresses, external stakeholders, or support teams.

***How to configure:***

Enter one recipient per line in the format: `Name, Email Address`

Examples:
- `HR Department, hr@mycompany.com`
- `Finance Team, finance@mycompany.com`
- `Bob Smith, bob@teacherorg.com.au`

***Using custom recipients:***

After configuring custom recipients in this global setting, they will appear in the Recipients, CC, and BCC dropdowns when configuring automation template and instance notifications. They are displayed in the format: `Name (email@address.com)`

**Default last name for service accounts**

This setting provides a fallback last name for custom email recipients when no last name is specified in their name. This is important because:
- Moodle requires both first and last names for user accounts
- Without a last name, Moodle may automatically delete incomplete user accounts
- Placeholders like `{User_Fullname}` may cause errors if last name is missing

**Default value:** `Service Account`

Example: If you add a custom recipient as `"Finance, finance@company.com"` with default last name set to "Service Account", the system creates:
- First name: Finance
- Last name: Service Account
- Email: finance@company.com

Site administrators can access these settings through: **Site administration → Plugins → Activity modules → Pulse → Pulse notifications**

![custom-email-address](https://github.com/user-attachments/assets/6f3ae7d3-d2ea-4459-90d9-e32b9aae9df2)

# Relation between templates and instances

The relationship between templates and instances ensures that settings defined in the template are synchronized with instances based on that template, except when a specific setting in an instance has been overridden. This means that any changes made to a setting in the template will automatically apply to all instances derived from the template.

For each setting within an instance, there is an override toggle available to protect locally made changes. Settings that have been locally modified will not be affected by changes to the same setting in the template.

Within the template, there is information indicating the number of instances where a setting has been locally overridden. Clicking on this number will open a modal with a link to the corresponding automation instance.

# Automation templates

Users with the appropriate permissions create automation templates globally, independent of specific courses. The template itself doesn't perform any actions; it serves as the foundation for creating the instances.

# Manage Automation templates lists

The 'Manage Automation Templates' provides comprehensive control over your automation templates. It empowers you to create new templates, efficiently organize existing ones through sorting and course category-based filtering. This presents a list of templates, each accompanied by additional options such as visibility, status, and editing for individual templates.

![Pulse-automation-template-lists](https://github.com/bdecentgmbh/moodle-mod_pulse/assets/57126778/ab734e1e-759f-4d11-928b-8c285eb44f67)


***Create New Template***

The 'Create new template' button that allow you to create custom templates for Automation templates.

***Sort***

It provides with the ability to arrange and display a list of automation templates in a alphabetic order by the 'Reference' parameter.

***Filter***

It provides the capability to filter and display a list of templates based on course categories.

***Template icon***

The 'Bell' icon represents the actions enabled for notifications in the automation template. The available actions include 'Notification,' 'Assignment,' 'Membership,' and 'Skills.'

***Template Title***

The title of the automation template should provide a generic explanation of its purpose.

***Edit Title***

To modify the template title, simply click on the pencil icon located next to the template title.

***Notification Pills***

The pills provide additional important information about the automation template. In this case, it explains that it's a notification.

***Reference***

This serves as the reference for the template, providing a unique identifier. It will be part of the unique identifier for the automation instance.

***Edit Settings***

To make changes to the template, click on the 'Cog' icon.

***Visibility***

To adjust the visibility of a template, simply click on the 'Eye' icon. A template set to 'Not Visible' will be concealed within courses. Existing automation instances will remain accessible, but new instances cannot be created

***Status***

Use this toggle to enable or disable a template. When a template is disabled, it also disables all automation instances unless they are locally enabled using an override. Enabling or disabling the template triggers the display of a modal window, allowing you to update the status of either the template alone or both the template and its instances.

***Number of Automation template instances Badge***

The count of automation instances currently utilizing the template is displayed, with the number in brackets indicating the quantity of disabled instances.



## General settings

1. **Title**

   Provide a title for this automation template. This title is intended for administrative purposes and helps in identifying the template.

2. **Reference**

   Assign a reference to this automation template. This identifier is also for administrative purposes and assists in uniquely identifying the template.

3. **Visibility**

   This option allows you to show or hide the automation template on the Automation Templates list.

   ***Note:*** If hidden, users won't be able to create new instances based on this template, but existing instances will still be available.

4. **Internal Notes**

   Including any internal notes or information relevant to this automation template. These details will be visible within the template for reference.

5. **Status**

   This option is for enabling or disabling the template.

   ***Enabled:*** Allows instances of this template to be created. Enabling the template may also prompt the user to decide whether to enable all existing instances based on the template or only the template itself and not its instances.

   ***Disabled:*** Turns off the automation template and its instances. Users can still enable instances individually if needed. Disabling the template may prompt the user to decide whether to disable all existing instances based on the template or only the template itself and not its instances.

6. **Tags**

   Adding tags can help categorize and organize templates for administrative purposes.

7. **Available for tenants**

   Specify for which Moodle Workplace tenants this template should be available. Select one or more tenants to make the template accessible to specific groups.

8. **Available in Course Categories**

   Select the course categories to be available for the specific template. By selecting one or more categories, you can define where users have the option to create instances based on this template. In the event that no categories are selected, the template will be accessible across all categories.

![Pulse-automation-template - Edit settings](https://github.com/bdecentgmbh/moodle-mod_pulse/assets/57126778/d4220218-02f2-4069-ace5-bcf05953250c)


## Conditions

1. **Trigger**

   Choose the trigger events that will activate and be visible on the automation instances. You can select one or more of the following trigger options:

   ***Activity Completion:*** This automation will be triggered when an activity within the course is marked as completed. You will need to specify the activity within the automation instance.

   ***Course Completion:*** This automation will be triggered when the entire course is marked as completed, where this instance is used.

   ***Enrolments:*** This automation will be triggered when a user is enrolled in the course where this instance is located.

   ***Session:*** This automation will be triggered when a session is booked within the course. This trigger is only available within the course and should be selected within the automation instance.

   ***Cohort Membership:*** This automation will be triggered if the user is a member of one of the selected cohorts.

   ***Course Group:*** This automation will be triggered based on the user's membership in course groups. You will need to configure the group criteria within the automation instance.

   ***Course Dates:*** This automation will be triggered when the course start date or course end date is reached. This trigger is based on course dates, not individual enrolment dates, making it ideal for sending notifications to all enrolled users at a specific point in the course timeline.

   ***User Inactivity:*** This automation will be triggered when a user has been inactive for a specified period. Inactivity can be measured based on course access or activity completion, making it ideal for re-engaging students who have stopped participating.

   ***Events:*** This automation will be triggered when specific Moodle events occur, such as assignment extensions being granted, submissions being created, or discussions being started. This condition monitors events in real-time and can target either the user affected by the event or the user who triggered it.

2. **Trigger operator**

   Choose the operator that determines how the selected triggers are evaluated:

   ***Any:*** At least one of the selected triggers must occur to activate the automation.

   ***All:*** All of the selected triggers must occur simultaneously to activate the automation.

![Pulse-automation-template - Condition](https://github.com/bdecentgmbh/moodle-mod_pulse/assets/57126778/843795af-0972-490b-b078-cf69fc837eb1)


## Notifications

1. **Sender**

   Determines how the selected triggers are evaluated.

   Choose the sender of the notification from the following options:

   **Course Teacher:** The notification will be dispatched from the course teacher, specifically the first one assigned if there are multiple course teachers. In the event that the user is not part of any group, the notification will default to the site support contact.

   *`Note that this is determined by capability, not by an actual role.`*

   **Group Teacher:** The notification will be dispatched by the non-editing teacher who belongs to the same group as the user, specifically the first non-editing teacher assigned if there are multiple in the group. If no non-editing teacher is present in the group, the notification will default to the course teacher.

   *`Note that this is determined by capability, not by an actual role.`*

   **Tenant Role (Workplace Feature):** The notification will be initiated by the user designated to the specified role within the tenant, with preference given to the first one assigned if there are multiple users in that role. In the absence of any user with the selected role, the notification will revert to the site support contact.

   *`Note that this is determined by capability, not by an actual role.`*

   **Custom:** If this option is enabled, an additional configuration for 'Sender Email' will be accessible. In this field, you have the option to specify a precise email address for use as the sender.

   ***Sender email:*** You can enter a specific email address to be used as the sender.

2. **Schedule**

   This scheduling allows you to control when the notification is delivered to its intended recipients.

   Choose the interval for sending notifications:

   **Once:** Send the notification only one time.

   **Daily:** Send the notification every day at the time selected below.

   **Weekly:** Send the notification every week on the day of the week and time of day selected below.

   **Monthly:** Send the notification every month on the day of the month and time of day selected below.

3. **Delay**

   A notification that is postponed for a specific period before it is sent to the recipient.

   Choose the delay option for sending notifications.

   **None:** Send notifications immediately upon the condition being met.

   **Before:** The notification to be dispatched a specified number of days or hours before the condition is met. It's important to note that this feature is exclusively applicable to timed events, such as appointment sessions.

   **After:** The notification to be dispatched a specific number of days or hours after the condition has been met. This functionality is available for all types of conditions..

4. **Delay duraion**

   The duration time for the delay in sending the notification. This duration should be specified in either days or hours, depending on the chosen delay option.

5. **Limit Number of Notifications**

   This limit is typically imposed to prevent users from receiving an excessive number of notifications, which could be overwhelming or spammy. Enter a number to limit the total number of notifications sent. Enter "0" for no limit. This is only relevant if the schedule is not set to "Once."

6. **Recipients**

   The selected roles with the capability to receive notifications will be used for determining the recipients of notifications.

7. **CC**

   The selected roles with the capability to receive notifications will be used as a CC (Carbon Copy) to the main recipient.

8. **BCC**

   The selected roles with the capability to receive notifications will be used as a BCC (Blind Carbon Copy) to the main recipient.

9. **Subject**

   Refers to the title that you would provide for an notification to briefly describe the content or purpose of the notification.

10. **Header Content**

      The context of email notifications encompasses the information and elements that are presented at the outset of an email message, preceding the main body of the email. This field is equipped to accommodate filters and placeholders

11. **Static Content**

      Static content is positioned in the second segment of the notification content. This static content also offers support for filters and placeholders.

12. **Footer Content**

      The context of notifications refers to the information and elements placed at the bottom of a notification message.

13. **Preview**

      This option displays the notification, enabling you to choose an example user for evaluating the notification's content within a modal window accessed by clicking the button.

![Pulse-automation-template - Notification](https://github.com/bdecentgmbh/moodle-mod_pulse/assets/57126778/b46142ed-a4f5-445a-b2ef-98691cb3bcfd)


# Automation instances

Users with the requisite permissions can generate automation instances by selecting an existing template. Within each automation instance, the initial values for settings are inherited from the template. However, should a user desire to deviate from the template's values, they have the option to locally override them by activating the 'override' toggle and implementing local adjustments to the settings.

# Manage Automation instances lists

Automation instances can be generated within pre-existing automation templates, offering the ability to effectively oversee instance lists with sorting options. This enables comprehensive control over each instance, with additional features including editing, duplication, viewing report, visibility, and instance deletion.

![Pulse-automation-instances](https://github.com/bdecentgmbh/moodle-mod_pulse/assets/57126778/3bc322af-a13f-489c-8699-187bce4d1097)

***Select Template***

You can select an automation template from the following list in the drop-down box to create an automation instance.

***Add Automation Instances***

The 'Add automation instances' button that allow you to create custom 'instance' for automation template.

***Manage templates***

The 'Manage Templates' button redirects you to the Manage Automation Templates listing page to manage it.

***Sort***

It provides with the ability to arrange and display a list of automation instances in a Alphabets order by the 'Reference'.

***Instance icon***

The 'Bell' icon represents the actions enabled for notifications in the automation template instance. The available actions include 'Notification,' 'Assignment,' 'Membership,' and 'Skills.'

***Instances Title***

The title of the automation template instance should provide a generic explanation of its purpose.

***Edit Title***

To modify the template title, simply click on the pencil icon located next to the instance title.

***Notification Pills***

The pills provide additional important information about the automation instance. In this case, it explains that it's a notification.

***Reference***

This serves as the reference for the instance, providing a unique identifier. It will be part of the unique identifier for the automation instance.

***Edit settings***

To make changes to the instance, click on the 'Cog' icon.

***Duplicate Instance***

To duplicate specific automation instances, click on this 'Copy' icon.

***Instances Report***

To access the report page of the automation instance schedule, click on the 'Calendar' icon. This report will provide details including 'Course full name,' 'Message type,' 'Subject,' 'Full name,' 'Time created,' 'Scheduled time,' 'Status,' and the option to 'Download table data.

***Visibility***

To adjust the visibility of a template, simply click on the 'Eye' icon. A template set to 'Not Visible' will be concealed within courses. Existing automation instances will remain accessible, but new instances cannot be created

***Delete Instances***

To remove specific automation instances from the automation template, simply click on the 'Bin' icon.


## General settings

1. **Title**

   Provide a title for this automation template instance. This title is for administrative purposes and helps in identifying the template.

   *`Toggle button - If you enable the toggle button, the provided value will be applied for the 'title' in the instance; otherwise, the automation templates value of the 'title'  will be applied.`*

2. **Reference**

   Assign a reference to this automation instance. This identifier is also for administrative purposes and helps uniquely identify the instance. The 'reference' setting of this instance will have the prefix of its automation template's 'Reference'.

   *`Toggle button -  If you enable the toggle button, the provided value will be applied for the 'reference' in the instance; otherwise, the automation templates value of the 'reference'  will be applied.`*

3. **Internal Notes**

   Include any internal notes or information related to this automation template that will be visible on this template.

   *`Toggle button -  If you enable the toggle button, the provided value will be applied for the 'Internal Notes' in the instance; otherwise, the automation templates value of the 'Internal Notes'  will be applied.`*

4. **Status**

    This option is for enabling or disabling the template.

     ***Enabled:*** Allows instances of this template to be created and overrides the option, even if the automation template is enabled or disabled.

     ***Disabled:*** Disables the automation instances, regardless of whether the automation template is enabled or disabled.

     *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'status' in the instance; otherwise, the automation templates option of the 'status' will be applied.`*

5. **Tags**

   Adding tags can help categorize and organize templates for administrative purposes.

   *`Toggle button - If you enable the toggle button, the provided value will be applied for the 'tags' in the instance; otherwise, the automation templates value of the 'tags' will be applied.`*

6. **Available for tenants**

   Specify for which Moodle Workplace tenants this template should be available. Select one or more tenants to make the template accessible to specific groups.

   *`Toggle button - If you enable the toggle button, the provided value will be applied for the 'Available for tenants' in the instance; otherwise, the automation templates value of the 'Available for tenants'  will be applied.`*

![Pulse-automation-instances - Edit settings](https://github.com/bdecentgmbh/moodle-mod_pulse/assets/57126778/45e26035-f730-4e23-a275-3043b15d7879)


## Conditions

1. **Trigger operator**

   Choose the operator that determines how the selected triggers are evaluated:

   ***Any:*** At least one of the selected triggers must occur to activate the automation.

   ***All:*** All of the selected triggers must occur simultaneously to activate the automation.

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Trigger operator' in the instance; otherwise, the automation templates of the 'Trigger Operator' option will be applied.`*

2. **Activity completion**

![activity-completion-options](https://github.com/user-attachments/assets/a0c78d5a-5135-4df3-8965-7acec82723ef)

   This automation will be triggered when activities within the course reach specific completion states.

   **Disabled:** Activity completion condition is disabled.

   **All:** Activity completion condition applies to all enrolled users. Enabling this option will make the activity completion configuration options visible.

   **Upcoming:** Activity completion condition only applies to future enrollments. Enabling this option will make the activity completion configuration options visible.

   ***Method:*** This setting determines how activity completion is evaluated:

   **Activity count** — The automation triggers when a specified number of activities have been completed. This is useful when you want to trigger an action after students complete any X activities, regardless of which specific activities they are. Enabling this option will make the 'Number of activities' field visible.

   **Selected activities** — The automation triggers based on the completion of specific selected activities. This allows you to target particular activities in your course. Enabling this option will make the 'Select activities' and 'Activities matching' options visible.

   ***Number of activities:*** This field specifies how many activities must be completed to trigger the automation. This option is only available when Method is set to 'Activity count'.

   *Example: If set to "3", the automation will trigger when the student completes any 3 activities from the selected activities list.*

   ***Select activities:*** This multi-select field allows you to choose specific activities from your course. Only activities with completion tracking enabled are available for selection. This field is available in the instance (not in the template) as it requires course-specific activities.

   *Note: Even when using the "Activity count" method, you must select activities. The system counts completions from this pool of selected activities.*

   ***Activities matching:*** This setting determines how selected activities are evaluated. This option is only available when Method is set to 'Selected activities'.

   **ANY** — The automation triggers when any one of the selected activities is completed. This is useful for alternatives or when you want flexibility in which activity students complete.

   **ALL** — The automation triggers only when all of the selected activities are completed. This ensures students have completed every selected activity before the automation runs.

   *Example: If you select 3 assignments and set this to "ALL", students must complete all 3 assignments before receiving the notification. If set to "ANY", completing just 1 of the 3 assignments will trigger the automation.*

   ***Completion status:*** This setting determines which completion state is required for activities to count toward triggering the automation:

   **Completed** — All completion conditions of the activity must be met. For example, if an activity requires "View" AND "Submit assignment", both conditions must be completed. If the activity has a passing grade requirement, it must be achieved.

   **Partially completed** — At least one, but not all, completion conditions of the activity have been met. For example, if an assignment has completion conditions for "View" AND "Make a submission", the student has viewed it but not submitted yet. This is useful for identifying students who started but didn't finish an activity.

   *Note: This status only applies to activities with multiple completion conditions. Activities with a single completion condition cannot be partially completed.*

   **Failed** — The activity is marked as completed, but the student did not achieve the passing grade. This status only applies to activities with passing grade requirements (e.g., assignments, quizzes with grade-to-pass settings).

   **Passed** — The activity is completed with a passing grade. This status only applies to activities with passing grade requirements.

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Activity completion' in the instance; otherwise, the automation templates of the 'Activity completion' option will be applied.`*

3. **Enrolments**

   This automation will be triggered when a user is enrolled in the course where this instance is located.

   ***Disabled:*** Enrolment condition is disabled.

   ***All:*** Enrolment condition applies to all enrolments.

   ***Upcoming:*** Enrolment condition only applies to future enrolments.

4. **Session Booking**

   This automation will activate when a session module is scheduled within the course. This trigger is exclusive to the course and should be chosen when configuring the automation instance. The options for session triggers include:

   ***Disabled:*** Session trigger is disabled.

   ***All:*** Session trigger applies to all enrolled users. Enabling this option will make the 'Session module' option visible.

   ***Upcoming:*** Session trigger only applies to future enrollments. Enabling this option will make the 'Session module' option visible.

   *`Session module: This setting allows you to choose the session module that will be associated with a session booking condition.`*

5. **Cohort Membership**

   This automation will be triggered when a user belongs to one of the selected cohorts. The options for cohort membership include:

   ***Disabled:*** Cohort membership condition is disabled.

   ***All:*** Cohort membership condition applies to all enrolled users. Enabling this option will make the 'Cohort' option visible.

   ***Upcoming:*** Cohort membership condition only applies to future enrollments. Enabling this option will make the 'Cohort' option visible.

   *`Cohorts: This setting allows you to choose the cohorts. This selection determines which specific cohorts will trigger the automation when the users are assign on the cohorts.`*

6. **Course completion**

   This automation will be triggered when the course is marked as completed, where this instance is used. The options for course completion include:

   **Disabled:** Course completion condition is disabled.

   **All:** Course completion condition applies to all enrolled users.

   **Upcoming:** Course completion condition only applies to future enrollments.

7. **Course group**

![course-groups-options](https://github.com/user-attachments/assets/337a9846-c5fa-4a73-9f61-37360992b7c2)

   This automation will be triggered based on the user's course group membership. The options for course group include:

   **Disabled:** Course group condition is disabled.

   **All:** Course group condition applies to all enrolled users. Enabling this option will make the 'Course groups' option visible.

   **Upcoming:** Course group condition only applies to future enrollments. Enabling this option will make the 'Course groups' option visible.

   ***Course groups:*** This setting determines which users will trigger the automation based on their group membership:

   **No group** — Automation will only trigger for users who are not members of any group in the course.

   **Any group** — Automation will only trigger for users who are members of any group in the course.

   **Select groups** — Automation will only trigger for users who are members of at least one of the selected groups. Enabling this option will make the 'Select groups' field visible.

   *Note: If this is selected in the template and no group is available or selected in the instance, then it will not have any effect (no group membership is required to trigger the action).*

   **Selected groupings** — Automation will only trigger for users who are members of at least one group within the selected groupings. Enabling this option will make the 'Select groupings' field visible.

   *Note: If this is selected in the template and no grouping is available or selected in the instance, then it will not have any effect (no group membership is required to trigger the action).*

   ***Select groups:*** This field allows you to choose specific course groups. Only users who belong to at least one of these selected groups will trigger the automation. This option is only available when 'Course groups' is set to 'Select groups'.

   *Note: This field will only be available in the instance, not in the template, as it requires course-specific groups.*

   ***Select groupings:*** This field allows you to choose specific course groupings. Users who belong to any group within these selected groupings will trigger the automation. This option is only available when 'Course groups' is set to 'Selected groupings'.

   *Note: This field will only be available in the instance, not in the template, as it requires course-specific groupings.*

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Course group' in the instance; otherwise, the automation templates option of the 'Course group' will be applied.`*

8. **Course dates**

![course-dates-condition-options](https://github.com/user-attachments/assets/8a707a2d-1d92-4baa-b8d3-ecc23f373ebb)

   This automation will be triggered when the course start date or course end date is reached. Unlike enrolment-based conditions, this trigger is based on the course's scheduled dates, making it ideal for sending notifications to all users who were enrolled before a specific course milestone. The options for course dates include:

   **Disabled:** Course dates condition is disabled.

   **All:** Course dates condition applies to all users enrolled before the selected course date is reached. Enabling this option will make the 'Course date type' option visible.

   **Upcoming:** Course dates condition only applies to users enrolled before the selected course date. This is the recommended setting for course dates to ensure notifications are sent based on course timeline events. Enabling this option will make the 'Course date type' option visible.

   ***Course date type:*** This setting determines which course date will trigger the automation:

   **Course start date** — The automation will be triggered when the course start date is reached. All users enrolled before (or on) the course start date will receive the notification. Users who enrol after the course has started will not be included.

   *Example: If a course starts on December 1st, students enrolled on November 15th and November 28th will receive notifications (with any configured delay applied from the start date). A student enrolled on December 20th will not receive the notification.*

   **Course end date** — The automation will be triggered when the course end date is reached. All users enrolled before (or on) the course end date with active enrolments will receive the notification.

   ***Working with delays:***

   The Course dates condition works seamlessly with notification delays (configured in the Notifications section):

   **None** — Notification is sent exactly when the course date is reached.

   **Before** — Notification is sent a specified number of days/hours before the course date. For example, "3 days before course start" will send the notification 3 days prior to the course start date.

   **After** — Notification is sent a specified number of days/hours after the course date. For example, "3 days after course start" will send the notification 3 days after the course start date.

   ***Important notes:***

   - This condition is based on **course dates**, not individual enrolment dates. All eligible users receive notifications at the same time relative to the course date.
   - Only users who enrolled **before the target date** (course date + delay) will receive notifications.
   - The condition uses a scheduled task that runs periodically to check if course dates have been reached.
   - For course end date, only users with active enrolments are considered.

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Course dates' in the instance; otherwise, the automation templates option of the 'Course dates' will be applied.`*

9. **User inactivity**

![user-inactivity-options](https://github.com/user-attachments/assets/a19e62db-906a-482c-aa74-2df0e6b5b01c)

   This automation will be triggered when a user has been inactive for a specified period. This condition helps identify and re-engage students who have stopped participating in the course. The options for user inactivity include:

   **Disabled:** User inactivity condition is disabled.

   **All:** User inactivity condition applies to all enrolled users. Enabling this option will make the 'User inactivity' type and related settings visible.

   **Upcoming:** User inactivity condition only applies to future enrollments. Enabling this option will make the 'User inactivity' type and related settings visible.

   ***User inactivity type:*** This setting determines how inactivity is measured:

   **Based on access** — Triggers if the student has not accessed/opened the course during the inactivity period specified below. The system checks:
   - Last access time from course logs
   - User enrollment time (to avoid triggering for newly enrolled students)
   - Course access logs (if logging is enabled)

   **Based on activity completion** — Triggers if the student has not completed any activity during the inactivity period specified below. The system checks:
   - Activity completion records
   - Only counts activities that are marked as completed (not in progress)
   - Filters by the "Included activities" setting

   ***Included activities:*** This setting determines which activities count toward activity completion for inactivity detection. This option is only visible when "Based on activity completion" is selected.

   **All activities** — All course activities with completion tracking are considered. If the student completes any activity, they are considered active.

   **Completion relevant activities** — Only activities that are part of the course completion criteria are considered. This focuses on key activities required for course completion, ignoring optional or supplementary activities.

   ***Inactivity period:*** Duration input field that determines how long the student must be inactive before the condition triggers an action. You can specify the duration in:
   - Minutes
   - Hours
   - Days
   - Weeks

   *Example: If set to "7 days", the automation will trigger for users who have not been active in the last 7 days.*

   ***Require previous activity:*** Select setting (default: false) that controls whether the user must have been previously active before being considered inactive.

   **No (default)** — The automation will trigger for any user who is inactive, even if they were never active in the course.

   **Yes** — The automation will only trigger if the user had been active previously within the activity period defined below. This prevents triggering for users who never engaged with the course in the first place.

   *Use case: Enable this to target only students who were previously engaged but have since become inactive, rather than students who never started.*

   ***Activity period:*** Duration input field that defines the lookback window for checking if the user was previously active. This field is only visible when "Require previous activity" is enabled. You can specify the duration in:
   - Minutes
   - Hours
   - Days
   - Weeks

   *Example: If "Inactivity period" is 7 days and "Activity period" is 30 days, the automation will only trigger for users who:*
   1. *Had activity in the last 30 days (were previously engaged), AND*
   2. *Have been inactive for the last 7 days (stopped participating recently)*


   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'User inactivity' in the instance; otherwise, the automation templates option of the 'User inactivity' will be applied.`*

10. **Events**

![event-condition-options](https://github.com/user-attachments/assets/e234c914-29cf-48bc-8004-a115804e8585)

   This automation will be triggered when specific Moodle events occur in your course. This condition monitors events in real-time, such as assignment extensions being granted, submissions being created, quiz attempts being submitted, or forum discussions being started. The options for events include:

   **Disabled:** Events condition is disabled.

   **All:** Events condition applies to all enrolled users. Enabling this option will make the event configuration options visible.

   **Upcoming:** Events condition only applies to future enrollments. Enabling this option will make the event configuration options visible.

   ***Event:*** Select the specific event that will trigger this automation. The available events are configured by the site administrator.

   ***User:*** This setting determines which user will receive the notification when the event occurs:

   **Affected user** — The user who is directly affected by the event. For example:
   - When an assignment extension is granted, the affected user is the student who received the extension
   - When a submission is graded, the affected user is the student whose work was graded
   - When a user is enrolled, the affected user is the newly enrolled student

   **Related user** — The user who triggered or caused the event to occur. For example:
   - When an assignment extension is granted, the related user is the teacher who granted the extension
   - When a submission is graded, the related user is the grader/teacher
   - When a forum post is created, the related user is the person who created the post

   **All users** — All enrolled users in the course will receive the notification when the event occurs.

   *Example use case: When a teacher grants an assignment extension, you can automatically notify the student (affected user) about their new submission deadline without requiring manual communication.*

   ***Event context:*** This setting determines where the event must occur to trigger the automation:

   **None** — Only core Moodle events or course-level events will trigger the automation. This is the default setting and works for system-wide events.

   **Everywhere** — Events that occur anywhere in the course context or any course activity will trigger the automation. This includes events from all activities (assignments, quizzes, forums, etc.) within the course.

   **Selected activity** — Only events that occur in a specific selected activity will trigger the automation. Enabling this option will make the 'Select activity' field visible, allowing you to choose which course activity to monitor.

   *Note: The "Event context" setting helps you control the scope of event monitoring. For example, if you want to monitor submission events only from a specific assignment, use "Selected activity".*

   ***Select activity:*** This field allows you to choose a specific course activity to monitor. Only events that occur in the selected activity will trigger the automation. This option is only available when 'Event context' is set to 'Selected activity'.

   *Note: This field will only be available in the instance, not in the template, as it requires course-specific activities.*


![Pulse-automation-instances - Condition](https://github.com/user-attachments/assets/29ca1435-8298-457b-8ea0-576bd8cac0e4)


## Notifications

1. **Sender**

   Determines how the selected triggers are evaluated.

   Choose the sender of the notification from the following options:

   **Course Teacher:** The notification will be dispatched from the course teacher, specifically the first one assigned if there are multiple course teachers. In the event that the user is not part of any group, the notification will default to the site support contact.

   *`Note that this is determined by capability, not by an actual role.`*

   **Group Teacher:** The notification will be dispatched by the non-editing teacher who belongs to the same group as the user, specifically the first non-editing teacher assigned if there are multiple in the group. If no non-editing teacher is present in the group, the notification will default to the course teacher.

   *`Note that this is determined by capability, not by an actual role.`*

   **Tenant Role (Workplace Feature):** The notification will be initiated by the user designated to the specified role within the tenant, with preference given to the first one assigned if there are multiple users in that role. In the absence of any user with the selected role, the notification will revert to the site support contact.

   *`Note that this is determined by capability, not by an actual role.`*

   **Custom:** If this option is enabled, an additional configuration for 'Sender Email' will be accessible. In this field, you have the option to specify a precise email address for use as the sender.

   ***Sender email:*** You can enter a specific email address to be used as the sender.

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'sender' option in the instance; otherwise, the automation templates of the 'sender' option will be applied.`*

2. **Schedule**

   This scheduling allows you to control when the notification is delivered to its intended recipients.

   Choose the interval for sending notifications:

   **Once:** Send the notification only one time.

   **Daily:** Send the notification every day at the time selected below.

   **Weekly:** Send the notification every week on the day of the week and time of day selected below.

   **Monthly:** Send the notification every month on the day of the month and time of day selected below.

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Schedule' in the instance; otherwise, the automation templates option of the 'Schedule' will be applied.`*


3. **Delay**

   A notification that is postponed for a specific period before it is sent to the recipient.

   Choose the delay option for sending notifications.

   **None:** Send notifications immediately upon the condition being met.

   **Before:** The notification to be dispatched a specified number of days or hours before the condition is met. It's important to note that this feature is exclusively applicable to timed events, such as appointment sessions.

   **After:** The notification to be dispatched a specific number of days or hours after the condition has been met. This functionality is available for all types of conditions..

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Delay' in the instance; otherwise, the automation templates option of the 'Delay' will be applied.`*

4. **Delay duraion**

   The duration time for the delay in sending the notification. This duration should be specified in either days or hours, depending on the chosen delay option.

5. **Suppress module**

   The "Suppress Module" functions by proactively preventing the dispatch of notifications once one or more selected activities have been successfully completed.

6. **Suppress Operator**

   The selection of the "suppression operator" is pivotal in determining the precise influence of these activities on the overall notification process.

7. **Limit Number of Notifications**

   This limit is typically imposed to prevent users from receiving an excessive number of notifications, which could be overwhelming or spammy. Enter a number to limit the total number of notifications sent. Enter "0" for no limit. This is only relevant if the schedule is not set to "Once."

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Limit Number of Notifications' in the instance; otherwise, the automation templates option of the 'Limit Number of Notifications' will be applied.`*

8. **Recipients**

   Select who will receive the notification. You can choose from:
   - **User roles** with the capability to receive notifications (e.g., students, teachers, managers)
   - **Custom email addresses** configured by the site administrator in the global settings

   Custom email addresses appear in the format `Name (email@address.com)` and allow you to send notifications to external recipients such as department emails, support teams, or external stakeholders who don't have Moodle accounts.

   *Note: Custom email recipients must be configured by the site administrator in Site administration → Plugins → Activity modules → Pulse → Pulse notifications before they appear in this dropdown.*

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Recipients' in the instance; otherwise, the automation templates option of the 'Recipients' will be applied.`*

9. **CC**

   Select additional recipients who will receive a Carbon Copy (CC) of the notification. You can choose from:
   - **User roles** with the capability to receive notifications
   - **Custom email addresses** configured by the site administrator

   CC recipients receive a copy of the notification, and all recipients can see who else received the notification (both primary recipients and CC recipients).

   *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'CC' in the instance; otherwise, the automation templates option of the 'CC' will be applied.`*

10. **BCC**

      Select additional recipients who will receive a Blind Carbon Copy (BCC) of the notification. You can choose from:
      - **User roles** with the capability to receive notifications
      - **Custom email addresses** configured by the site administrator

      BCC recipients receive a copy of the notification, but unlike CC recipients, other recipients cannot see who received the BCC copy. This is useful for discreet monitoring or record-keeping purposes.

      *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'BCC' in the instance; otherwise, the automation templates option of the 'BCC' will be applied.`*

11. **Subject**

      Refers to the title that you would provide for an notification to briefly describe the content or purpose of the notification.

      *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Subject' in the instance; otherwise, the automation templates option of the 'Subject' will be applied.`*

12. **Header Content**

      The context of email notifications encompasses the information and elements that are presented at the outset of an email message, preceding the main body of the email. This field is equipped to accommodate filters and placeholders

      *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Header Content' in the instance; otherwise, the automation templates option of the 'Header Content' will be applied.`*

13. **Static Content**

      Static content is positioned in the second segment of the notification content. This static content also offers support for filters and placeholders.

      *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Static Content' in the instance; otherwise, the automation templates option of the 'Static Content' will be applied.`*

14. **Dynamic Content**

      This option allows teachers to incorporate content or descriptions from various activities into the notification. Dynamic content is placed after the static content and before the footer. It supports filters and placeholders.

      **Note:** The Dynamic Content option is visible only on the automation instance page. The data from the selected dynamic content activity will be used as a placeholder for "Mod_".

15. **Content Type**

      Refers to the format of the content being used that helps to describe the type of data or information contained within a resource. Please note that this feature supports specific mod types, such as Page and Book.

      Choose the type of content to be added below the Dynamic content:

      **Placeholder:** The dynamic activity will only be used to fill the "Mod_" placeholders. The actual content of the notification won't be affected.

      **Description**: If this option is selected, the description of the chosen activity will be included in the body of the notification.

      **Content**: 
               * The content of the selected activity will be included in the notification body. it only available for the Page and Books modules.
               * For Books modules, its chapters are used as content. Using the "Chapter" config, teachers can specify which chapter to include with the notification.

16. **Content Length**

      Refers to the size or extent of a piece of content.

      Choose the content length to include in the notification

      **Teaser**: If chosen, only the first paragraph will be used, followed by a 'Read More' link.

      **Full, Linked**: If 'Full, Linked' is selected, the entire content shall be used with a link to the content provided after it.

      **Full, Not Linked**: If 'Full, Not Linked' is selected, the entire content shall be used without a link to the content afterward.

17. ***Chapters***

      Refer to the divisions or sections within a book that help organize and structure the content.

      Select which chapter of the chosen activity will be included in the notification body. To view the chapter content, select the specific chapter using the 'Chapters' option and the content using the 'Content' option for the 'Book' activity.

18. **Footer Content**

      The context of notifications refers to the information and elements placed at the bottom of a notification message.

      *`Toggle button - If you enable the toggle button, the provided option will be applied for the 'Footer Content' in the instance; otherwise, the automation templates option of the 'Footer Content' will be applied.`*

19. **Preview**

      This option displays the notification, enabling you to choose an example user for evaluating the notification's content within a modal window accessed by clicking the button.

![Pulse-automation-instances - Notification](https://github.com/bdecentgmbh/moodle-mod_pulse/assets/57126778/e16ca2ac-c191-49cb-8c90-3089e1f852e3)

# Pulse Email Placeholders
Email placeholders are dynamic variables that can be used to personalize and customize email notifications sent from our pulse. These placeholders automatically populate with specific user or system information when an email is generated, ensuring that each recipient receives relevant and personalized content.

![placeholder](https://github.com/bdecentgmbh/moodle-mod_pulse/assets/98076459/f72635aa-cb7b-4610-981d-9e9e5330b1db)

Here are some common email placeholders and their meanings:

1. **User Profile Information**:

    User profile information refers to the details and data associated with an individual user within our system. This information is typically collected
    during user registration or through profile settings and can include various personal and demographic data.

    ***User_Firstname***: This placeholder represents the user's first name.

    ***User_Lastname***: This placeholder represents the user's last name.

    ***User_Fullname***: This placeholder represents the user's full name (combination of
    first name and last name).

    ***User_Email***: This placeholder represents the user's email address.

    ***User_Username***: This placeholder represents the user's username.

    ***User_Description***: This placeholder represents any description or biography provided
    by the user.

    ***User_Department***: This placeholder represents the department or organizational unit
    to which the user belongs.

    ***User_Phone***: This placeholder represents the user's phone number.

    ***User_Address***: This placeholder represents the user's street address.

    ***User_City***: This placeholder represents the user's city or locality.

    ***User_Country***: This placeholder represents the user's country of residence.

    ***User_Institution***: This placeholder represents the user's institution or organization.

    ***User_Profilefield_fieldname***: This placeholder likely represents a custom profile field
    with a specific label or name that you have defined in your system.

2. **Course Information**:

    Course information placeholders allow you to dynamically include specific details about courses in various contexts such as pulse email notifications. These placeholders are replaced with actual course data when used, providing personalized and contextually relevant information to users.

    ***Course_Fullname***: This placeholder represents the full name or title of the course.

    ***Course_Shortname***: This placeholder represents the short name or abbreviated identifier of the course.

    ***Course_Summary***: This placeholder represents the summary or description of the course.

    ***Course_Courseurl***: This placeholder represents the URL or link to access the course.

    ***Course_Startdate***: This placeholder represents the start date of the course.

    ***Course_Enddate***: This placeholder represents the end date of the course.

    ***Course_Id***: This placeholder represents the unique ID or identifier of the course.

    ***Course_Category***: This placeholder represents the category or classification of the course.

    ***Course_Idnumber***: This placeholder represents the ID number assigned to the course.

    ***Course_Format***: This placeholder represents the format or layout of the course.

    ***Course_Visible***: This placeholder represents whether the course is visible or hidden.

    ***Course_Groupmode***: This placeholder represents the group mode setting of the course.

    ***Course_Groupmodeforce***: This placeholder represents whether the group mode is enforced in the course.

    ***Course_Defaultgroupingid***: This placeholder represents the default grouping ID for the course.

    ***Course_Lang***: This placeholder represents the language setting of the course.

    ***Course_Calendartype***: This placeholder represents the calendar type associated with the course.

    ***Course_Theme***: This placeholder represents the theme applied to the course.

    ***Course_Timecreated***: This placeholder represents the timestamp when the course was created.

    ***Course_Timemodified***: This placeholder represents the timestamp when the course was last modified.

    ***Course_Enablecompletion***: This placeholder represents whether course completion tracking is enabled.

    ***Course_customfield_fieldname***: The placeholder typically used to retrieve and display values from custom fields that have been defined and assigned to courses within the system.

3. **Sender Information**:

    The "sender" is typically the person or entity responsible for generating or sending the communication, such as an pulse email notification.

    ***Sender_Firstname***: Refers to the first name of the sender.

    ***Sender_Lastname***: Refers to the last name of the sender.

    ***Sender_Email***: Refers to the email address of the sender.

4. **Enrolments & Completion Information**:

    "Enrolments & Completion Information" refers to the details associated with a student's enrolment in a course and their progress towards course completion. These placeholders allow for dynamic insertion of specific enrolment and completion data into pulse notifications.

    ***Enrolment_Status***: Represents the current status of the student's enrolment in the course. This could include values such as "Active", "suspended" etc.

    ***Enrolment_Progress***: Indicates the student's progress towards completing the course. This might be represented as a percentage.

    ***Enrolment_Startdate***: Displays the date when the student was enrolled in the course.

    ***Enrolment_Enddate***: Indicates the date when the student's enrolment in the course is scheduled to end.

    ***Enrolment_Courseduedate***: Represents the due date or deadline associated with the course enrolment, indicating when the course must be completed.

5. **Calendar Information Placeholders**:

    The placeholders related to "Calendar Information" that can be used to provide calendar-related details to users:

    ***Calendar_UpcomingActivitiesList***: This placeholder represents a list of upcoming activities scheduled in the moodle calendar.

    ***Calendar_EventDatesList***:  This placeholder represents a list of specific event dates associated with course activities.

6. **Site Information**:

    The "Site Information" placeholders provide details about the Moodle site itself, allowing you to dynamically include site-specific information in pulse notifications.

    ***Site_Fullname***: Represents the full name or title of the Moodle site.

    ***Site_Shortname***: Represents the short name or abbreviation of the Moodle site.

    ***Site_Summary***: Provides a brief summary or description of the Moodle site.

    ***Site_Siteurl***: Represents the URL (web address) of the Moodle site.

7. **Course Activities Information**:

    The "Course Activities Information" placeholders provide details about specific activities within a Moodle course, allowing you to incorporate activity-specific information into pulse notifications.

    ***Mod_Type***: Represents the type or category of the Moodle activity (e.g., Assignment, Quiz, Forum, etc.).

    ***Mod_Name***: Represents the name or title of the Moodle activity.

    ***Mod_Intro***: Provides an introduction or description of the Moodle activity.

    ***Mod_Url***: Represents the URL (web address) of the Moodle activity.

    ***Mod_Duedate***: Represents the due date or deadline associated with the Moodle activity.

8. **Training Information**:

    The placeholders related to "Training Information" can be used to provide specific details about training courses, modules, and events within Moodle.

    ***Training_Upcomingmods***: Represents upcoming modules or activities within a course.

    ***Training_Courseprogress***: Represents the progress or completion status of a training course.

    ***Training_Eventdates***: Represents important event dates related to training activities.

    ***Training_Coursedue***: Represents the due date or deadline for a training course.

    ***Training_Activityduedate***: Represents the due date or deadline for a specific training activity or module.

9. **Assignments**:

    The "Assignment_extensions" placeholder will be replaced with a list of assignments that meet the specified conditions:

    *`Only assignments from the same course as the instance will be included.`*

    *`Only assignments that have not been completed by the student will be listed.`*

    *`Only assignments where the submission deadline was extended will be displayed.`*

    The format of the assignment extensions list will be as follows:

        Assignment Title 1: New Deadline 1 (previously: Default Deadline 1)

10. **Logs**:

    The "Event" placeholder in the context of Moodle refers to dynamic pieces of information associated with specific events that occur within the Moodle system. These placeholders are used to capture and display details related to various events, such as user actions, course activities, system operations, and more. When an event occurs, Moodle captures relevant data about the event, which can then be accessed and utilized through placeholders pulse notifications.

    ***Event_Name***: Represents the name or title of the event that occurred. This could be descriptive text indicating the type of event, such as "Assignment Extension Granted."

    ***Event_Namelinked***: Provides a linked representation of the event name. This placeholder could be used to create a hyperlink to more detailed information about the event, allowing users to navigate to relevant content.

    ***Event_Description***: Describes the details or nature of the event. It typically provides additional information about what happened during the event, such as "An extension has been granted for assignment submission."
    ***Event_Time***: Indicates the timestamp when the event occurred. This placeholder displays the date and time of the event, formatted according to the system settings or locale.

    ***Event_Context***: Represents the context or location where the event took place. For example, this could be the course name or activity name associated with the event.

    ***Event_Contextlinked***: Provides a linked representation of the event context. Similar to Event_Namelinked, this placeholder can be used to create hyperlinks for navigating to specific contexts within Moodle.

    ***vent_Affecteduserfullname***: Displays the full name of the user who is directly affected by the event. This could be the user receiving the extension, for instance.

    ***Event_Affecteduserfullnamelinked***: Provides a linked representation of the affected user's full name. It can be used to generate clickable links to the user's profile or relevant information.

    ***Event_Relateduserfullname***: Represents the full name of another user who is related to or involved in the event. In the case of an extension being granted, this might be the teacher or administrator who granted the extension.

    ***Event_Relateduserfullnamelinked***: Offers a linked representation of the related user's full name. This placeholder can be used to create clickable links to the related user's profile or details.

11. **Face to Face**:

    The placeholders provided are specific to the Moodle "Face-to-face" module, which represents session information for in-person or physical sessions managed within Moodle. These placeholders can be used pulse notification customization features to display details related to face-to-face sessions.

    ***Mod_session_Starttime***: This placeholder displays the time at which the session is scheduled to begin.

    ***Mod_session_Startdate***: This placeholder displays the date on which the session is scheduled to start.

    ***Mod_session_Enddate***: This placeholder displays the date on which the session is scheduled to end.

    ***Mod_session_Endtime***: This placeholder displays the time at which the session is scheduled to conclude.

    ***Mod_session_Link***: Provides a link to additional details or resources related to the face-to-face session.

    ***Mod_session_Details***: This placeholder can provide users with further context or information regarding the session, such as agenda items, topics covered, or special instructions.

    ***Mod_session_Discountcode***: This placeholder could represent a discount code or promotional code associated with the face-to-face session.

    ***Mod_session_Capacity***: Represents the capacity or maximum number of participants allowed for the face-to-face session.

    ***Mod_session_Normalcost***: Indicates the normal cost or standard fee associated with attending the face-to-face session.

    ***Mod_session_Discountcost***: Represents the discounted cost or fee for attending the face-to-face session, typically when a discount code (provided via Mod_session_Discountcode) is applied.

    ***Mod_session_Type***: Represents the type or category of the face-to-face session.

12. **Meta Data Information:**

    The placeholder refers to the metadata information stored and managed by the "local metadata" plugin in Moodle.
    The {Mod_metadata_customfield} placeholder can dynamically retrieve and display specific metadata values based on the context of the pulse notification.

13. **Reaction**:

    The placeholder {reaction} represents the reaction associated with a Pulse notification in Moodle. This placeholder is used to display or refer to the specific reaction triggered by a Pulse notification within the Moodle environment.

