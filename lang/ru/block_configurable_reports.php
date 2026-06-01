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
 * Russian language strings for block_configurable_reports (feature additions).
 *
 * @package   block_configurable_reports
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['filterusage_column'] = 'Использование в SQL';
$string['filterusage_used'] = 'В SQL: {$a->field}{$a->op}';
$string['filterusage_op'] = ' · {$a->op} ({$a->oplabel})';
$string['filterusage_notfound'] = 'Не найдено в SQL';
$string['filterusage_duplicate'] = 'Дублирование привязки плейсхолдера';
$string['filterusage_detail_field'] = 'Поле: {$a->field}';
$string['filterusage_detail_fieldop'] = 'Поле: {$a->field}, оператор: {$a->operator} ({$a->operatorlabel})';
$string['filtersql_missing_heading'] = 'Плейсхолдеры в SQL без настроенного фильтра';
$string['filtersql_placeholder'] = 'Плейсхолдер';
$string['filtersql_detail'] = 'Использование';
$string['filtersql_addfilter'] = 'Добавить фильтр';
$string['filtersql_startendtime_notice'] = 'В запросе используются %%STARTTIME%% / %%ENDTIME%% без фильтра «Start/end time». При просмотре подставляется весь диапазон дат (1970–2038), что может быть медленно. Добавьте фильтр Start/end time или используйте плейсхолдеры %%FILTER_STARTTIME%% / %%FILTER_ENDTIME%%.';
$string['operator_like'] = 'Содержит (LIKE)';
$string['operator_in'] = 'Несколько значений LIKE';
$string['operator_exact'] = 'Точное совпадение (=)';
$string['operator_lt'] = 'Меньше (<)';
$string['operator_gt'] = 'Больше (>)';
$string['operator_lte'] = 'Меньше или равно (<=)';
$string['operator_gte'] = 'Больше или равно (>=)';
$string['operator_unknown'] = 'Оператор: {$a}';
$string['filtersubmitrequired'] = 'Задайте фильтры и нажмите «Применить», чтобы выполнить отчёт.';
$string['requirefiltersubmit'] = 'Требовать отправку формы фильтров перед запуском';
$string['requirefiltersubmit_help'] = 'Если включено, SQL-отчёты с фильтрами не выполняются, пока пользователь не отправит форму фильтров. Глобальную настройку можно переопределить для каждого отчёта.';
$string['requirefiltersubmit_inherit'] = 'Как на сайте (сейчас: {$a->current})';
$string['requirefiltersubmit_yes'] = 'Всегда требовать нажатия «Применить»';
$string['requirefiltersubmit_no'] = 'Не требовать нажатия «Применить»';
$string['emptybehavior'] = 'Если не задан на странице отчёта';
$string['emptybehavior_help'] = 'Как обрабатывать фильтр, если пользователь не отправил значение. Omit — убрать условие из SQL (поведение по умолчанию). False — добавить AND 1=0. Значение по умолчанию (пока не поддерживается) зарезервировано для будущей версии.';
$string['emptybehavior_omit'] = 'Убрать условие (без ограничения)';
$string['emptybehavior_false'] = 'Считать ложным (AND 1=0)';
$string['emptybehavior_default'] = 'Использовать значение по умолчанию (пока не поддерживается)';
