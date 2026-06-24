<?php
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
 * Configurable Reports a Moodle block for creating customizable reports
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = "Configurable Reports";
$string['blockname'] = "Configurable Reports";
$string['report_courses'] = "Courses report";
$string['report_users'] = "Users report";
$string['report_sql'] = "SQL Report";
$string['managereports'] = "Manage reports";

$string['report'] = "Report";
$string['reports'] = "Reports";

$string['columns'] = "Columns";
$string['conditions'] = "Conditions";
$string['permissions'] = "Permissions";
$string['plot'] = "Plot - Graphs";
$string['filters'] = "Filters	";
$string['calcs'] = "Calculations";
$string['ordering'] = "Ordering";
$string['customsql'] = "Custom SQL";
$string['addreport'] = "Add report";
$string['type'] = "Type of report";
$string['columncalculations'] = "Column Calculations";
$string['newreport'] = "New report";
$string['column'] = "Column";
$string['confirmdeletereport'] = "Are you sure you want to delete this report?";
$string['noreportsavailable'] = "No reports available";
$string['downloadreport'] = "Download report";
$string['reportlimit'] = "Report row limit";
$string['reportlimitinfo'] = "Limit the number of rows that are displayed in the report table
    (Default is 5000 rows. Better to have some limit, so users will not over load the DB engine)";

$string['configurable_reports:addinstance'] = 'Add a new configurable reports block';
$string['configurable_reports:myaddinstance'] = 'Add a new configurable reports block to MY HOME page';
$string['configurable_reports:manageownreports'] = "Manage own reports";
$string['configurable_reports:managereports'] = "Manage reports";
$string['configurable_reports:managesqlreports'] = "Manage SQL reports";
$string['configurable_reports:viewreports'] = "View reports";

$string['exportoptions'] = "Export options";
$string['embedoptions'] = "Embed options";
$string['field'] = "Field";

// Report form.
$string['typeofreport'] = "Type of report";
$string['enablejsordering'] = "Enable JavaScript ordering";
$string['enablejspagination'] = "Enable JavaScript Pagination";
$string['export_csv'] = "Export in CSV format";
$string['export_ods'] = "Export in ODS format";
$string['export_slk'] = "Export in SYLK format";
$string['export_xls'] = "Export in XLS format";
$string['export_json'] = "Export in JSON format";
$string['viewreport'] = "View report";
$string['norecordsfound'] = "No records found";
$string['jsordering'] = 'JavaScript Ordering';
$string['cron'] = 'Auto run daily';
$string['crondescription'] = 'Schedule this query to run each day (At night)';
$string['displaytotalrecords'] = 'Total Records';
$string['displaytotalrecordsdescription'] = 'Displays the total number of results in the report';
$string['displayprintbutton'] = 'Print Button';
$string['displayprintbuttondescription'] = 'Displays the print button at the bottom of the report';
$string['embedlink'] = 'Embed Link';
$string['embedlinkdescription'] = 'You can copy this link to embed the report in an HTML block';
$string['cron_help'] = 'Schedule this query to run each day (At night)';
$string['remote'] = 'Run on remote db';
$string['remotedescription'] = 'Do you want to run this query on the remote db';
$string['remote_help'] = 'Do you want to run this query on the remote db';
$string['setcourseid'] = 'Set courseid';

// Columns.
$string['column'] = "Column";
$string['nocolumnsyet'] = "No columns yet";
$string['tablealign'] = "Table align";
$string['tablecellspacing'] = "Table cellspacing";
$string['tablecellpadding'] = "Table cellpadding";
$string['tableclass'] = "Table class";
$string['tablewidth'] = "Table width";
$string['cellalign'] = "Cell align";
$string['cellwrap'] = "Cell wrap";
$string['cellsize'] = "Cell size";

// Conditions.
$string['conditionexpr'] = "Condition";
$string['conditionexprhelp'] = "Enter a valid condition i.e: (c1 and c2) or (c4 and c3)";
$string['noconditionsyet'] = "No conditions yet";
$string['operator'] = "Operator";
$string['value'] = "Value";

// Filter.
$string['filter'] = "Filter";
$string['nofilteryet'] = "No filters yet";
$string['courses'] = "Courses";
$string['nofiltersyet'] = "No filters yet";
$string['filter_all'] = 'All';
$string['filter_apply'] = 'Apply';
$string['filter_reset'] = 'Reset';
$string['filterconfig_save'] = 'Save';
$string['filter_searchtext'] = 'Search text';
$string['searchtext'] = 'Search text';
$string['filter_searchtext_summary'] = 'Free text filter';
$string['years'] = 'Year (Numeric)';
$string['filteryears'] = 'Year (Numeric)';
$string['filteryears_summary'] = 'Filter by years (numeric representation, 2012...)';
$string['filteryears_list'] = '2010,2011,2012,2013,2014,2015';
$string['semester'] = 'Semester (Hebrew)';
$string['filtersemester'] = 'Semester (Hebrew)';
$string['filtersemester_summary'] = 'מאפשר סינון לפני סמסטרים (בעברית, למשל: סמסטר א,סמסטר ב)';
$string['filtersemester_list'] = 'סמסטר א,סמסטר ב,סמסטר ג,סמינריון';
$string['subcategories'] = 'Category (Include sub categories)';
$string['filtersubcategories'] = 'Category (Include sub categories)';
$string['filtersubcategories_summary'] = 'Use: %%FILTER_SUBCATEGORIES:mdl_course_category.path%%';
$string['yearnumeric'] = 'Year (Numeric)';
$string['filteryearnumeric'] = 'Year (Numeric)';
$string['filteryearnumeric_summary'] = 'Filter is using numeric years (2013,...)';
$string['yearhebrew'] = 'Year (Hebrew)';
$string['filteryearhebrew'] = 'Year (Hebrew)';
$string['filteryearhebrew_list'] = 'תשע,תשעא,תשעב,תשעג,תשעד,תשעה';
$string['filteryearhebrew_summary'] = 'Filter is using Hebrew years (תשעג,...)';
$string['role'] = 'Role';
$string['filterrole'] = 'role';
$string['filterrole_summary'] = 'Filter system Roles (Teacher, Student, ...)';
$string['coursemodules'] = 'Course module';
$string['filtercoursemodules'] = 'Course module';
$string['filtercoursemodules_summary'] = 'Filter course modules';
$string['user'] = 'Course user (id)';
$string['filteruser'] = 'Current course user';
$string['filteruser_summary'] = 'Filter a user (id) from current course users';
$string['users'] = 'System user (id)';
$string['filterusers'] = 'System user';
$string['enrolledstudents'] = 'Enrolled students';
$string['filterusers_summary'] = 'Filter a user (by id) from system user list';
$string['filterenrolledstudents'] = 'Enrolled course students';
$string['filterenrolledstudents_summary'] = 'Filter a user (by id) from enrolled course students';
$string['competencyframeworks'] = 'Competency Frameworks';
$string['filtercompetencyframeworks'] = 'Competency Frameworks';
$string['filtercompetencyframeworks_summary'] = 'Use: %%FILTER_COMPETENCYFRAMEWORKS:prefix_competency_framework.id%%';
$string['competencytemplates'] = 'Competency Templates';
$string['filtercompetencytemplates'] = 'Competency templates';
$string['filtercompetencytemplates_summary'] = 'Use: %%FILTER_COMPETENCYTEMPLATES:prefix_competency_template.id%%';
$string['cohorts'] = 'Cohorts';
$string['filtercohorts'] = 'Cohorts';
$string['filtercohorts_summary'] = 'Use: %%FILTER_COHORTS:prefix_cohort.id%%';
$string['student'] = 'Student';

// Calcs.
$string['nocalcsyet'] = "No calculations yet";

// Plot.
$string['noplotyet'] = "No plots yet";

// Permissions.

$string['nopermissionsyet'] = "No permissions yet";

// Ordering.

$string['noorderingyet'] = "No ordering yet";
$string['userfieldorder'] = "User field order";

// Plugins.
$string['coursefield'] = "Course field";
$string['ccoursefield'] = "Course field condition";
$string['roleusersn'] = "Number of users with role...";
$string['coursecategory'] = "Course in category";
$string['filtercourses'] = "Courses";
$string['filtercourses_summary'] = "This filter shows a list of courses. Only one course can be selected at the same time";
$string['roleincourse'] = "User with the selected role/s in the current report course";
$string['reportscapabilities'] = "Report Capabilities";
$string['reportscapabilities_summary'] = "Users with the capability moodle/site:viewreports enabled";
$string['sum'] = "Sum";
$string['max'] = "Maximum";
$string['min'] = "Minimum";
$string['percent'] = "Percentage";
$string['average'] = "Average";
$string['pie'] = "Pie";
$string['piesummary'] = "A pie graph";
$string['pieareaname'] = "Name";
$string['pieareavalue'] = "Value";
$string['piesummary'] = "A pie graph";

$string['bar'] = "Bar";
$string['barsummary'] = "A bar graph";
$string['label_field'] = "Label field";
$string['label_field_help'] = "The field that provides names for the things represented in the graph";
$string['value_fields'] = "Value fields";
$string['value_fields_help'] = "Fields that should be represented in the graph. Ctrl+click (Cmd+click on Mac) to select multiple.
If you select the Label field or a field with non-numeric values it will be ignored";

$string['width'] = "Width";
$string['height'] = "Height";
$string['head_data'] = "Graph data";
$string['head_size'] = "Graph size";
$string['head_color'] = "Graph background color";

$string['anyone'] = "Anyone";
$string['anyone_summary'] = "Any user in the Campus will be able to view this report";

$string['currentuserfinalgrade'] = "Current user final grade in course";

$string['currentuserfinalgrade_summary'] = "This column shows the final grade of the current user in the row-course";
$string['userfield'] = "User profile field";

$string['cuserfield'] = "User field condition";
$string['direction'] = "Direction";

$string['courseparent'] = "Courses whose parent is";
$string['coursechild'] = "Courses that are children of";

$string['currentusercourses'] = "Current user enrolled courses";
$string['currentusercourses_summary'] = "A list of the current users courses (only visible courses)";
$string['currentreportcourse'] = "Current report course";
$string['currentreportcourse_summary'] = "The course where the report has been created";

$string['coursefieldorder'] = "Course field order";

$string['fcoursefield'] = "Course field filter";
$string['usersincoursereport'] = "Any user in the current report course";

$string['groupvalues'] = "Group same values (sum)";
$string['fuserfield'] = "User field filter";
$string['fsearchuserfield'] = "User field search box";

$string['module'] = "Module";

$string['usersincurrentcourse'] = "Users in current report course";
$string['usersincurrentcourse_summary'] = "Users with the role/s selected in the report course";

$string['usermodoutline'] = "User module outline stats";
$string['donotshowtime'] = "Do not show date information";
$string['usermodactions'] = "User module actions";

$string['currentuser'] = "Current user";
$string['currentuser_summary'] = "The user that is viewing the report";

$string['puserfield'] = "User field value";
$string['puserfield_summary'] = "User with the selected value in the selected field";

$string['startendtime'] = "Start / End date filter";
$string['starttime'] = "Start date";
$string['endtime'] = "End date";

$string['template'] = "Template";
$string['availablemarks'] = "Available marks";
$string['header'] = "Header";
$string['footer'] = "Footer";
$string['templaterecord'] = "Record template";
$string['querysql'] = "SQL Query";
$string['filterstartendtime_summary'] = "Start / End date filter";

$string['pagination'] = "Pagination";
$string['disabled'] = "Disabled";
$string['enabled'] = "Enabled";

$string['reportcolumn'] = "Other report column";

$string['reporttable'] = "Report table";
$string['columnandcellproperties'] = "Column and cell properties";
$string['componenthelp'] = "Component help";

$string['badsize'] = 'Incorrect size, it must be in &#37; or px';
$string['badtablewidth'] = 'Incorrect width, it must be in &#37; or absolute value';
$string['missingcolumn'] = "A column is required";
$string['error_operator'] = "Operator not allowed";

$string['error_field'] = "Field not allowed";
$string['error_value_expected_integer'] = "Expected integer value";
$string['badconditionexpr'] = "Incorrect condition expression";

$string['notallowedwords'] = "Not allowed words";
$string['nosemicolon'] = "No semicolon";
$string['noexplicitprefix'] = "No explicit prefix";
$string['queryfailed'] = 'Query failed <code><pre>{$a}</pre></code>';
$string['norowsreturned'] = "No rows returned";

$string['listofsqlreports'] = 'Press F11 when cursor is in the editor to toggle full screen editing. Esc can also be used to exit
full screen editing.<br/><br/><a href="http://docs.moodle.org/en/ad-hoc_contributed_reports" target="_blank">List of SQL Contributed reports</a>';

$string['usersincoursereport_summary'] = "Any user in the current report course";

$string['printreport'] = 'Print report';

$string['importreport'] = "Import report";
$string['exportreport'] = "Export report";

$string['download'] = "Download";

$string['report_timeline'] = 'Timeline report';
$string['timeline'] = 'Timeline';
$string['timemode'] = 'Time mode';
$string['previousdays'] = 'Previous days';
$string['fixeddate'] = 'Fixed date';
$string['previousstart'] = 'Previous start';
$string['previousend'] = 'Previous end';
$string['forcemidnight'] = 'Force midnight';
$string['timeinterval'] = 'Time interval';
$string['date'] = 'Date';
$string['dateformat'] = 'Date format';
$string['customdateformat'] = 'Custom date format';
$string['custom'] = 'Custom';

$string['line'] = 'Line graph';
$string['userstats'] = 'User statistics';
$string['stat'] = 'Statistic';
$string['statslogins'] = 'Logins in the platform';
$string['activityview'] = 'Activity views';
$string['activitypost'] = 'Activity posts';
$string[''] = '';
$string['globalstatsshouldbeenabled'] = 'Site statistics must be enabled. Go to Admin -> Server -> Statistics';

$string['xaxis'] = 'X Axis';
$string['yaxis'] = 'Y Axis';
$string['serieid'] = 'Serie column';
$string['groupseries'] = 'Group series';
$string['linesummary'] = 'A line graph with multiple series of data';
$string['xandynotequal'] = 'X and Y axis need to be different';

$string['coursestats'] = 'Course stats';
$string['statstotalenrolments'] = 'Total enrolments';
$string['statsactiveenrolments'] = 'Active (last week) enrolments';
$string['youmustselectarole'] = 'At least a role is required';

$string['report_categories'] = 'Categories report';
$string['categoryfield'] = 'Category field';
$string['categoryfieldorder'] = 'Category field order';
$string['categories'] = 'Categories';
$string['parentcategory'] = 'Parent category';
$string['filtercategories'] = 'Filter categories';
$string['filtercategories_summary'] = 'To filter by category';

$string['includesubcats'] = 'Include subcategories';

$string['coursededicationtime'] = 'Course dedication time';

$string['jsordering_help'] = 'JavaScript Ordering allow you to order the report table without reloading the page';
$string['pagination_help'] = 'Number of records to show in each page. Zero means no pagination';
$string['typeofreport_help'] = 'Choose the type of report you want to create.
For security, SQL Report requires an additional capability';
$string['template_marks'] = 'Template marks';
$string['template_marks_help'] = '<p>You can use any of this replacement marks:</p>

<ul>
<li>##reportname## - For including the report name</li>
<li>##reportsummary## - For including the reports summary</li>
<li>##graphs## - For including the graphs</li>
<li>##exportoptions## - For including the export options</li>
<li>##calculationstable## - For including the calculations table</li>
<li>##pagination## - For including the pagination </li>

</ul>';

$string['conditionexpr_conditions'] = 'Condition';
$string['conditionexpr_conditions_help'] = '<p>You can combine conditions using a logic expression</p>

<p>Enter a valid logic expression with these operators: and, or.</p>';

$string['conditionexpr_permissions'] = 'Condition';
$string['conditionexpr_permissions_help'] = '<p>You can combine conditions using a logic expression</p>

<p>Enter a valid logic expression with these operators: and, or.</p>';

$string['reporttable_help'] = '<p>This is the width of the table that will display the report records.</p>

<p>If you use a Template this option has no effect</p>';

$string['comp_calcs'] = 'Calcs';
$string['comp_calcs_help'] = '<p>Here you can add calculations for columns, i.e: average of number of users enrolled in courses</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';

$string['comp_calculations'] = 'Calcs';
$string['comp_calculations_help'] =
    '<p>Here you can add calculations for columns, i.e: average of number of users enrolled in courses</p>';
$string['comp_conditions'] = 'Conditions';
$string['comp_conditions_help'] = '<p>Here you can define the conditions (i.e, only courses from this category, only users from Spain, etc.. </p>

<p>You can add a logical expression if you are using more than one condition.</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_customsql'] = 'Custom SQL';
$string['comp_customsql_help'] = '<p>Add a working SQL query. Do no use the moodle database prefix $CFG->prefix instead use "prefix_" without quotes</p>
<p>Example: SELECT * FROM prefix_course</p>

<p>You can find a lot of SQL Reports here: <a href="http://docs.moodle.org/en/ad-hoc_contributed_reports" target="_blank">ad-hoc contributed reports</a></p>

<p>An updated layout of Moodle\'s tables and their interconnected relations: <a href="https://docs.moodle.org/dev/Database_Schema" target="_blank">Database schema</a></p>

<p>Since this block supports Tim Hunt\'s CustomSQL Queries Reports, you can use any query.</p>

<p>Remember to add a "Time filter" if you are going to use reports with time tokens. </p>

<p>For using filters see: <a href="http://docs.moodle.org/en/blocks/configurable_reports/#Creating_a_SQL_Report" target="_blank">Creating a SQL Report Tutorial</a></p>';

$string['comp_ordering'] = 'Ordering';
$string['comp_ordering_help'] = '<p>Here you can choose how to order the report using fields and directions</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_permissions'] = 'Permissions';
$string['comp_permissions_help'] = '<p>Here you can choose who can view a report.</p>

<p>You can add a logical expression to calculate the final permission if you are using more than one condition.</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_plot'] = 'Plot';
$string['comp_plot_help'] = '<p>Here you can add graphs to your report based on the report columns and values</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_template'] = 'Template';
$string['comp_template_help'] = '<p>You can modify the report\'s layout by creating a template</p>

<p>For creating a template see the replacemnet marks you can use in header, footer and for each report record using the help buttons or the information displayed in the same page.</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_filters'] = 'Filters';
$string['comp_filters_help'] = '<p>Here you can choose which filters will be displayed</p>

<p>A filter lets an user to choose columns from the report to filter the report results</p>

<p>For using filters if your report type is SQL see: <a href="http://docs.moodle.org/en/blocks/configurable_reports/#Creating_a_SQL_Report" target="_blank">Creating a SQL Report Tutorial</a></p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_columns'] = 'Columns';
$string['comp_columns_help'] = '<p>Here you can choose the different columns of your report depending on the type of report</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';

$string['coursecategories'] = 'Category course filter';
$string['filtercoursecategories'] = 'Category course filter';
$string['filtercoursecategories_summary'] = 'Filter courses by their any parent category';

$string['dbhost'] = "DB Host";
$string['dbhostinfo'] = "Remote Database host name (on which, we will be executing our SQL queries)";
$string['dbname'] = "DB Name";
$string['dbnameinfo'] = "Remote Database name (on which, we will be executing our SQL queries)";
$string['dbuser'] = "DB Username";
$string['dbuserinfo'] = "Remote Database username (should have SELECT privileges on above DB)";
$string['dbpass'] = "DB Password";
$string['dbpassinfo'] = "Remote Database password (for above username)";

$string['totalrecords'] = 'Total record count = {$a->totalrecords}';
$string['lastexecutiontime'] = 'Execution time = {$a} (Sec)';

$string['reportcategories'] = '1) Choose a remote report categories';
$string['reportsincategory'] = '2) Choose a report form the list';
$string['remotequerysql'] = 'SQL query';
$string['executeat'] = 'Execute at';
$string['executeatinfo'] = 'Moodle CRON will run scheduled SQL queries after selected time. Once in 24h';
$string['sharedsqlrepository'] = 'Shared sql repository';
$string['sharedsqlrepositoryinfo'] = 'Name of GitHub account owner + slash + repository name';
$string['sqlsyntaxhighlight'] = 'Highlight SQL syntax';
$string['sqlsyntaxhighlightinfo'] = 'Highlight SQL syntax in code editor (CodeMirror JS library)';
$string['datatables'] = 'Enable DataTables JS library';
$string['datatablesinfo'] = 'DataTables JS library (Column sort, fixed header, search, paging...)';
$string['reporttableui'] = 'Report table UI';
$string['reporttableuiinfo'] = 'Display the report table as: Simple scrollable HTML table, jQuery with column sorting Or
DataTables JS library (Column sort, fixed header, search, paging...)';

$string['email_subject'] = 'Subject';
$string['email_message'] = 'Message';
$string['email_send'] = 'Send';

$string['sqlsecurity'] = 'SQL Security';
$string['sqlsecurityinfo'] = 'Disable for executing SQL queries with statements for inserting data';
$string['allowedsqlusers'] = 'SQL report users';
$string['allowedsqlusersinfo'] =
    'If you wish to only allow certain admin users to manage sql reports, add a list of usernames separated by commas. They must also have the block/configurable_reports:managesqlreports capability.';
$string['global'] = 'Global report';
$string['enableglobal'] = 'This is a global report (accesible from any course)';
$string['global_help'] =
    'Global report can be accessed from any course in the platform just appending &courseid=MY_COURSE_ID in the report URL';

$string['crrepository'] = 'Reports repository';
$string['crrepositoryinfo'] =
    'Remote shared repository with sample reports fully functional (Name of GitHub account owner + slash + repository name)';
$string['importfromrepository'] = 'Import report from repository';
$string['repository'] = 'Reports repository';
$string['repository_help'] = 'You can import sample reports from a public shared repository.

Please, notice that there is a daily limit of calls to the repository.

If the connection to the repository is not working, you can download manually here <a href="https://github.com/jleyva/moodle-configurable_reports_repository" target="_blank">https://github.com/jleyva/moodle-configurable_reports_repository</a> a report and then import it using the "Import report" feature displayed bellow
';
$string['reportcreated'] = 'Report successfully created';
$string['usersincohorts'] = 'User who are member of a/several cohorts';
$string['usersincohorts_summary'] = 'Only the users who are members of the selected cohorts';
$string['displayglobalreports'] = 'Display global reports';
$string['displayreportslist'] = 'Display the reports list in the block body';

$string['usercompletion'] = 'User course completion status';
$string['usercompletionsummary'] = 'Course completion status';

$string['finalgradeincurrentcourse'] = 'Final grade in current course';
$string['legacylognotenabled'] = 'Legacy logs must be enabled.
 Go to Site administration / Plugins / Logging Enable the Legacy log and inside the log settings check Log legacy data';

$string['datatables_sortascending'] = ': activate to sort column ascending';
$string['datatables_sortdescending'] = ': activate to sort column descending';
$string['datatables_first'] = 'First';
$string['datatables_last'] = 'Last';
$string['datatables_next'] = 'Next';
$string['datatables_previous'] = 'Previous';
$string['datatables_emptytable'] = 'No data available in table';
$string['datatables_info'] = 'Showing _START_ to _END_ of _TOTAL_ entries';
$string['datatables_infoempty'] = 'Showing 0 to 0 of 0 entries';
$string['datatables_infofiltered'] = '(filtered from _MAX_ total entries)';
$string['datatables_lengthmenu'] = 'Show _MENU_ entries';
$string['datatables_loadingrecords'] = 'Loading...';
$string['datatables_processing'] = 'Processing...';
$string['datatables_search'] = 'Search:';
$string['datatables_zerorecords'] = 'No matching records found';
// New features: Graph new column.

$string['others'] = 'Others';
$string['limitcategories'] = 'Limit categories in a graph';
$string['decimals'] = 'Number of decimals';
$string['sessionlimittime'] = 'Limit between clicks (in minutes)';
$string['sessionlimittime_help'] = 'The limit between clicks defines if two clicks are part of the same session or not';

$string['excludedeletedusers'] = 'Exclude deleted users (only for SQL reports)';

// Privacy provider.
$string['privacy:metadata:block_configurable_reports'] = 'The configurable reports block contains customizable course reports.';
$string['privacy:metadata:block_configurable_reports:courseid'] = 'Course ID';
$string['privacy:metadata:block_configurable_reports:ownerid'] = 'The ID of the user who created the report';
$string['privacy:metadata:block_configurable_reports:visible'] = 'Whether the report is visible or not';
$string['privacy:metadata:block_configurable_reports:global'] = 'Whether the report is accessible from all the courses or not';
$string['privacy:metadata:block_configurable_reports:name'] = 'The name of the report';
$string['privacy:metadata:block_configurable_reports:summary'] = 'The description of the report';
$string['privacy:metadata:block_configurable_reports:type'] = 'The type of the report';
$string['privacy:metadata:block_configurable_reports:components'] = 'The configuration of the report. It contains the query,
 the filters...';
$string['privacy:metadata:block_configurable_reports:lastexecutiontime'] = 'Time this report took to run last time it was executed,
 in milliseconds.';
// Filter forms.
$string['add'] = 'Add';
$string['description'] = 'Description';
$string['description_help'] = 'Text used to describe the filter that will be displayed in the summary on the filters page.';
$string['label'] = 'Label';
$string['label_help'] = 'Text describing the filter to be displayed on the report page.';
$string['idnumber'] = 'ID Number';
$string['idnumber_help'] = 'Used to differentiate between filters of the same type. Case-sensitive.
Example usage: %%FILTER_SEARCHTEXT_username:u.username:~%%';

// Pie Chart Strings.
$string['description'] = 'Description';
$string['legendheader'] = 'Mapped Palette';
$string['legendheaderdesc'] = 'Map color codes to specific keys in the pie chart legend.';
$string['piechart_label'] = 'Key - {$a}';
$string['piechart_label_color'] = 'Color - {$a}';
$string['piechart_add_colors'] = 'Add color';
$string['invalidcolorcode'] = 'Invalid color code';
$string['generalcolorpaletteheader'] = 'General color palette';
$string['generalcolorpalette'] = 'Unmapped Palette';
$string['generalcolorpalette_help'] = 'Hexadecimal color codes for general use in the pie chart. Codes should be separated
by new lines in the order you wish them to be used in the pie chart.';

$string['checksql_execution'] = 'Block Configurable Reports SQL execution';
$string['checksql_execution_ok'] = 'SQL execution is disabled.';

$string['checksql_execution_warning'] = 'It is recommended to disable SQL execution to avoid execution of arbitrary SQL code in
your server.';
$string['checksql_execution_details'] = 'By allowing SQL code execution there is a potential security issue with users adding
arbitrary code. SQL code execution should be disable to only allow SQL queries for reading/retreaving data. SQL execution can
be disabled in your config.php file by setting $CFG->block_configurable_reports_enable_sql_execution to 0';
$string['csvdelimiter'] = 'CSV delimiter';
$string['csvdelimiterinfo'] = 'CSV delimiter: "colon" for ":", "comma" for ",", semicolon for ";",  "tab" for "\t" and "cfg" for character configured in "CFG->CSV_DELIMITER" of the config.php file.';

// Filter SQL analysis and execution behaviour.
$string['filterusage_column'] = 'SQL usage';
$string['filterusage_used'] = 'In SQL: {$a->field}{$a->op}';
$string['filterusage_op'] = ' · {$a->op} ({$a->oplabel})';
$string['filterusage_notfound'] = 'Not found in SQL';
$string['filterusage_duplicate'] = 'Duplicate placeholder binding';
$string['filterusage_detail_field'] = 'Field: {$a->field}';
$string['filterusage_detail_fieldop'] = 'Field: {$a->field}, operator: {$a->operator} ({$a->operatorlabel})';
$string['filtersql_missing_heading'] = 'Placeholders in SQL without a configured filter';
$string['filtersql_placeholder'] = 'Placeholder';
$string['filtersql_detail'] = 'Usage';
$string['filtersql_addfilter'] = 'Add filter';
$string['filtersql_startendtime_notice'] = 'This query uses %%STARTTIME%% / %%ENDTIME%% without the Start/end time filter. At runtime they default to the full date range (1970–2038), which may be slow. Add the Start/end time filter or use %%FILTER_STARTTIME%% / %%FILTER_ENDTIME%% placeholders.';
$string['operator_like'] = 'Contains (LIKE)';
$string['operator_in'] = 'Multiple LIKE values';
$string['operator_exact'] = 'Exact match (=)';
$string['operator_lt'] = 'Less than (<)';
$string['operator_gt'] = 'Greater than (>)';
$string['operator_lte'] = 'Less than or equal (<=)';
$string['operator_gte'] = 'Greater than or equal (>=)';
$string['operator_unknown'] = 'Operator: {$a}';
$string['filtersubmitrequired'] = 'Set filters and click Apply to run this report.';
$string['requirefiltersubmit'] = 'Require filter form before running report';
$string['requirefiltersubmit_help'] = 'When enabled, SQL reports with filters do not run until the user submits the filter form. The site default can be overridden per report.';
$string['requirefiltersubmit_inherit'] = 'Use site default (currently: {$a->current})';
$string['requirefiltersubmit_yes'] = 'Always require Apply';
$string['requirefiltersubmit_no'] = 'Never require Apply';
$string['validate_sql_with_explain'] = 'Validate custom SQL with EXPLAIN';
$string['validate_sql_with_explain_help'] = 'When enabled, saving a SQL report runs EXPLAIN on the restrictive query instead of fetching rows. If EXPLAIN fails, validation falls back to running the query with a limit of 1 row. Does not catch all runtime errors (e.g. permissions, division by zero).';
$string['emptybehavior'] = 'When submitted empty on report page';
$string['emptybehavior_help'] = 'Applies when the user has submitted the report filter form with this field left empty (after Apply).

* **Omit condition** — remove the SQL filter clause (may return a very large result set).
* **Treat as false** — add AND 1=0 so the query returns no rows.';
$string['emptybehavior_omit'] = 'Omit condition (match all)';
$string['emptybehavior_false'] = 'Treat as false (AND 1=0)';
$string['filterdefault_enable'] = 'Use default value';
$string['filterdefault_enable_help'] = 'Provides a starter value for the report filter: pre-fills the form and applies to SQL on first open, before the user submits the filter form. After Apply with an empty field, the default is not used (see empty-filter behaviour above).';
$string['emptyfilter_defaultvalue'] = 'Default value';
$string['emptyfilter_defaultvalue_help'] = 'Used when **Use default value** is enabled above.

Applied only on first open of the report (the filter parameter is not in the request yet). After the user clicks Apply with an empty field, this value is not substituted.

Typical uses:

1. **Representative example** — a clear sample (surname, course idnumber) so users see how to fill in the filter.
2. **Limit the result set** — when an unrestricted query is too heavy; narrows data on first open.

For dropdown filters (user/course field), enter the plain display value; it is encoded like a list selection.';
$string['emptyfilter_defaultvalue_hint'] = 'Sample for users or a limit on first open — not used after Apply with an empty field.';
$string['emptyfilter_defaultvalue_placeholder'] = 'e.g. Smith or course idnumber';

$string['chains'] = 'Chains';
$string['sqloutputcolumns_heading'] = 'Report columns (detected from SQL query)';
$string['show_sql_outputcolumns_diagnostic'] = 'Show SQL output columns on save page';
$string['show_sql_outputcolumns_diagnostic_help'] = 'When enabled, the list of report columns detected from the SQL query is shown below the custom SQL form. Column metadata is still cached for report chains when the query is saved.';
$string['sqloutputcolumns_status_yes'] = 'Columns detected.';
$string['sqloutputcolumns_status_no'] = 'Columns not detected.';
$string['sqloutputcolumns_list'] = 'List: {$a}';
$string['sqloutputcolumns_updated'] = 'Cached on last save: {$a}';
$string['sqloutputcolumns_not_saved_yet'] = 'Save the query to cache the column list.';
$string['sqloutputcolumns_source_metadata'] = 'Column names read from result set metadata (no rows required).';
$string['sqloutputcolumns_source_firstrow'] = 'Column names read from the first result row.';
$string['sqloutputcolumns_reason_metadata_unavailable'] = 'Column metadata could not be read from the result set. Save again after the query returns at least one row, or check the SELECT list.';
$string['sqloutputcolumns_reason_error'] = 'An error occurred while running the query for column detection.';
$string['nochainsyet'] = 'No report chains configured yet';
$string['reportchain'] = 'Report chain';
$string['chainname'] = 'Chain name';
$string['chainname_help'] = 'A short name shown in chain lists to distinguish multiple chains on the same report.';
$string['reportchain_summary_full'] = 'Target report: {$a->target}. Mappings: {$a->mappings}';
$string['reportchain_summary_nomappings'] = 'no mappings configured';
$string['chainexportlistlabel'] = '{$a->chain} (target report: {$a->child})';
$string['chainexportviewreport'] = 'View source report';
$string['chainerror_noname'] = 'Enter a chain name.';
$string['reportchain_summary_empty'] = 'Target report not selected';
$string['reportchain_summary_missing'] = 'Target report missing';
$string['chainenabled'] = 'Chain active';
$string['chainenabled_help'] = 'Active chains are available for bulk export from the source report view. Inactive chains are kept in settings but are not offered during export.';
$string['chainenable'] = 'Activate chain';
$string['chaindisable'] = 'Deactivate chain';
$string['chainchildreport'] = 'Target report';
$string['chainfilenamepattern'] = 'Export filename pattern';
$string['chainfilenamepattern_help'] = 'Placeholders: ##reportname##, ##row##, ##columnname## (from row key columns).';
$string['chainrowkeycolumns'] = 'Row key columns';
$string['chainrowkeycolumns_help'] = 'Source report columns used to identify rows and build filenames. For SQL reports the list is fixed when the query is saved on the SQL tab.';
$string['chainsourcecolumn'] = 'Source report column';
$string['chainsourcecolumn_help'] = 'Source report column whose value is passed to the target report filter parameter.';
$string['chainnocolumnsmetadata'] = 'Column names must be entered manually for this report type.';
$string['chainresavesqlforcolumns'] = 'Output columns are not cached yet. Save the SQL query again on the SQL tab.';
$string['chaintargetfilter'] = 'Target report filter parameter';
$string['chainmappingheader'] = 'Mapping {$a}';
$string['chainmappingcolumnsheader'] = 'Source report column → Target report filter parameter';
$string['chainfilterbindingsheader'] = 'Target report filters (configure each filter explicitly)';
$string['chainfilterbindingsheader_help'] = 'For every filter on the target report, choose how its value is set during chain export: from a source column, explicitly empty, or a fixed constant for all rows. Rows with identical column bindings are merged into one export file.';
$string['chainfilterbindingheader'] = 'Filter {$a}';
$string['chainfiltermode_column'] = 'From source column';
$string['chainfiltermode_empty'] = 'Empty';
$string['chainfiltermode_constant'] = 'Constant value';
$string['chainaddmapping'] = 'Add mapping';
$string['chainremovemapping'] = 'Remove mapping';
$string['chainselectchildhint'] = 'Select a target report and click Continue.';
$string['chaincontinueconfig'] = 'Continue';
$string['chainnofilters'] = 'The selected target report has no filters configured.';
$string['chainexport'] = 'Chain export';
$string['chainexportlink'] = 'Bulk chain export...';
$string['chainexportheadingcontext'] = 'Chain export: {$a->source} → {$a->chain}';
$string['chains_usage_help'] = 'Chains let you parameterise another report using the results of this report. To use a chain, go to {$a->viewreportlink}, configure the desired rows with filters, then click "{$a->exportlink}". See {$a->helplink} for a step-by-step guide.';
$string['chainexportchoose'] = 'Choose a configured chain to export target reports for selected source rows.';
$string['chainexportintro'] = 'Select rows from the source report and download one target report file per row.';
$string['chainexportselectrows'] = 'Row selection';
$string['chainexportrows'] = 'Rows';
$string['chainexportformat'] = 'Export format';
$string['chainexportdownload'] = 'Export selected';
$string['chainexportdownloadzip'] = 'Download ZIP archive';
$string['chainexportdownloadready'] = 'Files exported and archived: {$a}. Available for download:';
$string['chainexportsummaryheading'] = 'Exported files';
$string['chainexportskippedheading'] = 'Skipped rows';
$string['chainexportskippednodata'] = 'No data in child report';
$string['chainexportrownumber'] = 'Row {$a}';
$string['chainexportmergedrows'] = '(merged {$a} rows with identical column mapping)';
$string['chainexportnoexported'] = 'No files were exported because the child reports returned no data for the selected rows.';
$string['chainerror_nozip'] = 'The export archive is no longer available. Please run the export again.';
$string['chainerror_exportempty'] = 'The generated export file is empty.';
$string['chainexportselectall'] = 'Select or deselect all rows';
$string['chainexportnorowsselected'] = 'Select at least one row to export.';
$string['chainexportvalidationfailed'] = 'The export could not be started. Please correct the errors below.';
$string['chainexportsummaryrow'] = 'Row';
$string['chainexportsummaryfile'] = 'File';
$string['chainexportsummaryreason'] = 'Reason';
$string['chainexportzipalreadydownloaded'] = 'The export archive has already been downloaded. Run the export again if you need a new copy.';
$string['chainzipfilenamepattern'] = 'ZIP archive filename pattern';
$string['chainzipfilenamepattern_help'] = 'Placeholders: ##sourcereport##, ##targetreport##, ##chainname##. Leave empty for {sourcereport}-{targetreport}.zip';
$string['chainhelp_title'] = 'How to use chain export';
$string['chainhelp_link'] = 'Help';
$string['chainhelp_intro'] = 'Chain export runs a target report once per selected row of the source report and packs the files into a ZIP archive.';
$string['chainhelp_step1'] = 'Configure at least one chain on the Chains tab of the source report (target report, row key columns, filter bindings).';
$string['chainhelp_step2'] = 'Open the source report, apply filters, and click Bulk chain export.';
$string['chainhelp_step3'] = 'Choose a chain, select the rows to export, pick a format, and start the export.';
$string['chainhelp_step4'] = 'For large exports, progress is shown in the background; you can leave the page and return later from the same chain export screen or the Chains tab.';
$string['chainhelp_step5'] = 'When the export finishes, download the ZIP archive. If the export is interrupted (for example after a server restart), you can resume from the last completed step or download partial results.';
$string['chainhelp_note'] = 'Only one export runs at a time per source report; additional requests are queued in order.';
$string['chainexportmode'] = 'Chain export mode';
$string['chainexportmode_help'] = 'Synchronous export runs in the browser request (suitable for small exports). Asynchronous export runs in the background with progress tracking.';
$string['chainexportmode_inherit'] = 'Inherit site default ({$a->current})';
$string['chainexportmode_sync'] = 'Synchronous';
$string['chainexportmode_async'] = 'Asynchronous (background)';
$string['chainexportmode_default'] = 'Default chain export mode';
$string['chainexportmode_default_help'] = 'Used when a report inherits the site default for chain export mode.';
$string['chainexportdelaythresholdms'] = 'Chain export delay threshold (ms)';
$string['chainexportdelaythresholdms_help'] = 'Pause between child report runs only when the previous run took at least this many milliseconds.';
$string['chainexportdelaypercent'] = 'Chain export delay percent';
$string['chainexportdelaypercent_help'] = 'When the threshold is exceeded, pause for this percentage of the last child report duration before the next run.';
$string['chainexportjobttl'] = 'Chain export download TTL (hours)';
$string['chainexportjobttl_help'] = 'How long completed export archives remain available for download.';
$string['chainexportprogressheading'] = 'Export progress';
$string['chainexportprogresslabel'] = '{$a->done} of {$a->total} completed';
$string['chainexportqueuewait'] = 'Waiting in queue (position {$a->position}). Another export for this report is running. Estimated wait: {$a->eta}.';
$string['chainexportcancelrequest'] = 'Cancel current request';
$string['chainexportcancelconfirm'] = 'Cancel this export request?';
$string['chainexportcancelhint'] = 'Your request will not be queued and will not run.';
$string['chainexportdismiss'] = 'Clear export record';
$string['chainexportdismissconfirm'] = 'Remove this export from the list and allow a new export to start?';
$string['chainexportdismissed'] = 'Export record cleared. You can start a new export.';
$string['chainexportnodownload'] = 'The archive is not available for download. Clear this record and start a new export if needed.';
$string['chainexportstale'] = 'The export stopped responding and was marked as failed.';
$string['chainexportstalerunminutes'] = 'Stale running export timeout (minutes)';
$string['chainexportstalerunminutes_help'] = 'Running exports with no progress longer than this are marked failed so the queue can continue.';
$string['chainexportnewexport'] = 'Start new export';
$string['chainexportjobsheading'] = 'My chain exports for this report';
$string['chainexportjobchain'] = 'Chain';
$string['chainexportjobstatus'] = 'Status';
$string['chainexportjobprogress'] = 'Progress';
$string['chainexportjobcreated'] = 'Created';
$string['chainexportjobfinished'] = 'Finished';
$string['chainexportjobmonitor'] = 'Monitor';
$string['chainexportstatus_queued'] = 'Queued';
$string['chainexportstatus_running'] = 'Running';
$string['chainexportstatus_completed'] = 'Completed';
$string['chainexportstatus_partial'] = 'Partial';
$string['chainexportstatus_failed'] = 'Failed';
$string['chainexportstatus_cancelled'] = 'Cancelled';
$string['chainexportstatus_interrupted'] = 'Interrupted';
$string['chainexportinterrupted'] = 'The export was interrupted ({$a->done} of {$a->total} completed). You can resume from where it stopped or download partial results if available.';
$string['chainexportresume'] = 'Resume export';
$string['chainexportdelete'] = 'Delete';
$string['chainexportdeleteconfirm'] = 'Permanently delete this export record and any files?';
$string['chainexportdeleted'] = 'Export record deleted.';
$string['chainexportclearall'] = 'Clear all';
$string['chainexportclearallconfirm'] = 'Delete all export records for this report (except running exports)?';
$string['chainexportdeletedall'] = 'Deleted {$a} export record(s).';
$string['chainexportclearallskippedrunning'] = 'No records deleted: {$a} export(s) are still running.';
$string['chainexportclearallnothing'] = 'Nothing to delete.';
$string['chainexportjobactions'] = 'Actions';
$string['chainexportstatcounts'] = 'Exported: {$a->exported}, skipped: {$a->skipped}';
$string['chainexportredownloadzip'] = 'Download again';
$string['chainexportredownloadminutes'] = 'Re-download grace period (minutes)';
$string['chainexportredownloadminutes_help'] = 'After the first download, users can download the same archive again within this period (in minutes). Files are removed from disk after the grace period expires. Use 1 or more; default is 15 when empty.';
$string['chainexportjobgone'] = 'This export record is no longer available.';
$string['chainerror_jobnotdeletable'] = 'This export cannot be deleted while it is still running.';
$string['chainexportnoprogressminutes'] = 'No-progress interruption timeout (minutes)';
$string['chainexportnoprogressminutes_help'] = 'Running exports with no progress for longer than this are marked interrupted so users can resume or download partial results.';
$string['chainerror_jobnotfound'] = 'Export job not found.';
$string['chainerror_jobnotready'] = 'The export archive is not ready yet.';
$string['jsonunicode'] = 'JSON export: preserve Unicode characters';
$string['jsonunicodeinfo'] = 'When enabled, JSON exports write non-ASCII characters as-is instead of \\uXXXX escape sequences.';
$string['chainmaxrows'] = 'Chain export row limit';
$string['chainmaxrowsinfo'] = 'Maximum number of source rows that can be exported in one chain download.';
$string['chainsettingsheading'] = 'Report chains';
$string['chainsettingsheading_desc'] = 'Settings for chained reports and bulk chain export (background jobs, delays, download retention).';
$string['chainerror_nochild'] = 'Select a child report.';
$string['chainerror_selfreference'] = 'A report cannot chain to itself.';
$string['chainerror_childmissing'] = 'The selected child report no longer exists.';
$string['chainerror_nomappings'] = 'Configure at least one column to filter mapping.';
$string['chainerror_missingfilterbinding'] = 'Missing filter binding for: {$a}';
$string['chainerror_nocolumnsource'] = 'Select a source column for filter: {$a}';
$string['chainerror_noconstantvalue'] = 'Enter a constant value for filter: {$a}';
$string['chainerror_invalidfiltermode'] = 'Invalid binding mode for filter: {$a}';
$string['chainerror_unknowncolumn'] = 'Unknown source report column: {$a}. Re-save the SQL query or update the chain configuration.';
$string['chainerror_unknownfilter'] = 'Unknown target report filter: {$a}. Update mappings for the selected target report.';
$string['chainerror_norowkeys'] = 'Specify at least one row key column.';
$string['chainerror_cycle'] = 'This chain would create a cycle between reports.';
$string['chainerror_noexport'] = 'The child report has no export formats enabled.';
$string['chainerror_invalid'] = 'Invalid chain configuration: {$a}';
$string['chainerror_exportformat'] = 'The selected export format is not allowed for the child report.';
$string['chainerror_norows'] = 'No rows were selected for export.';
$string['chainerror_toomanyrows'] = 'Too many rows selected. Maximum allowed: {$a}';
$string['chainerror_zip'] = 'Could not create the export archive.';
$string['chainerror_nochains'] = 'This report has no active chains configured.';
$string['chainerror_childfilters'] = 'The child report requires filter submission and cannot be used in a chain.';

