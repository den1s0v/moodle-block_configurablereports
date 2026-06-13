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
$string['filter_apply'] = 'Применить';
$string['filter_reset'] = 'Сбросить';
$string['filterconfig_save'] = 'Сохранить';
$string['filtersubmitrequired'] = 'Задайте фильтры и нажмите «Применить», чтобы выполнить отчёт.';
$string['requirefiltersubmit'] = 'Требовать отправку формы фильтров перед запуском';
$string['requirefiltersubmit_help'] = 'Если включено, SQL-отчёты с фильтрами не выполняются, пока пользователь не отправит форму фильтров. Глобальную настройку можно переопределить для каждого отчёта.';
$string['requirefiltersubmit_inherit'] = 'Как на сайте (сейчас: {$a->current})';
$string['requirefiltersubmit_yes'] = 'Всегда требовать нажатия «Применить»';
$string['requirefiltersubmit_no'] = 'Не требовать нажатия «Применить»';
$string['validate_sql_with_explain'] = 'Проверять пользовательский SQL через EXPLAIN';
$string['validate_sql_with_explain_help'] = 'При включении при сохранении SQL-отчёта выполняется EXPLAIN restrictive-запроса вместо выборки строк. Если EXPLAIN не удаётся, проверка откатывается к выполнению запроса с лимитом 1 строка. Не выявляет все ошибки времени выполнения (права, деление на ноль и т.п.).';
$string['emptybehavior'] = 'Если отправлен пустым на странице отчёта';
$string['emptybehavior_help'] = 'Действует, когда пользователь отправил форму фильтров с пустым полем (после «Применить»).

* **Убрать условие** — условие в SQL не применяется (выборка может быть очень большой).
* **Считать ложным** — в SQL добавляется AND 1=0, строк не будет.';
$string['emptybehavior_omit'] = 'Убрать условие (без ограничения)';
$string['emptybehavior_false'] = 'Считать ложным (AND 1=0)';
$string['filterdefault_enable'] = 'Подставлять значение по умолчанию';
$string['filterdefault_enable_help'] = 'Заготовка для фильтра на странице отчёта: предзаполняет форму и подставляется в SQL при первом открытии, пока форма фильтров не отправлена. После «Применить» с пустым полем заготовка не используется (см. настройку выше для пустого значения).';
$string['emptyfilter_defaultvalue'] = 'Значение по умолчанию';
$string['emptyfilter_defaultvalue_help'] = 'Используется при включённом **Подставлять значение по умолчанию** выше.

Подставляется только при первом открытии отчёта (параметр фильтра ещё не был в запросе). После «Применить» с пустым полем значение не используется.

Типичные сценарии:

1. **Понятный пример** — образец (фамилия, idnumber курса), чтобы было ясно, как заполнять фильтр.
2. **Ограничение выборки** — когда полный охват слишком тяжёл; сужает данные при первом открытии.

Для фильтров со списком введите отображаемое значение; кодирование как при выборе из списка.';
$string['emptyfilter_defaultvalue_hint'] = 'Образец для пользователя или ограничение при первом открытии — не после пустого «Применить».';
$string['emptyfilter_defaultvalue_placeholder'] = 'напр. Иванов или idnumber курса';

$string['chains'] = 'Цепочки';
$string['sqloutputcolumns_heading'] = 'Колонки отчёта (определено по SQL-запросу)';
$string['show_sql_outputcolumns_diagnostic'] = 'Показывать список колонок на странице SQL';
$string['show_sql_outputcolumns_diagnostic_help'] = 'Если включено, под формой SQL-запроса отображается список колонок, определённых при сохранении. Метаданные колонок для цепочек отчётов по-прежнему сохраняются при сохранении запроса.';
$string['sqloutputcolumns_status_yes'] = 'Колонки определены.';
$string['sqloutputcolumns_status_no'] = 'Колонки не определены.';
$string['sqloutputcolumns_list'] = 'Список: {$a}';
$string['sqloutputcolumns_updated'] = 'Зафиксировано при последнем сохранении: {$a}';
$string['sqloutputcolumns_not_saved_yet'] = 'Сохраните запрос, чтобы зафиксировать список колонок.';
$string['sqloutputcolumns_source_metadata'] = 'Имена колонок получены из метаданных результата (строки не нужны).';
$string['sqloutputcolumns_source_firstrow'] = 'Имена колонок получены из первой строки результата.';
$string['sqloutputcolumns_reason_metadata_unavailable'] = 'Не удалось прочитать метаданные колонок. Сохраните снова, когда запрос вернёт хотя бы одну строку, или проверьте список SELECT.';
$string['sqloutputcolumns_reason_error'] = 'Ошибка при выполнении запроса для определения колонок.';
$string['nochainsyet'] = 'Цепочки отчётов ещё не настроены';
$string['reportchain'] = 'Цепочка отчётов';
$string['chainname'] = 'Имя цепочки';
$string['chainname_help'] = 'Краткое имя для списков цепочек, чтобы различать несколько цепочек у одного отчёта.';
$string['reportchain_summary_full'] = 'Целевой отчёт: {$a->target}. Сопоставления: {$a->mappings}';
$string['reportchain_summary_nomappings'] = 'сопоставления не настроены';
$string['chainexportlistlabel'] = '{$a->chain} (целевой отчёт: {$a->child})';
$string['chainexportviewreport'] = 'Перейти к просмотру отчёта';
$string['chainerror_noname'] = 'Укажите имя цепочки.';
$string['reportchain_summary_empty'] = 'Целевой отчёт не выбран';
$string['reportchain_summary_missing'] = 'Целевой отчёт не найден';
$string['chainenabled'] = 'Цепочка активна';
$string['chainenabled_help'] = 'Активные цепочки доступны для множественного экспорта с экрана просмотра отчёта-источника. Неактивные цепочки сохраняются в настройках, но не предлагаются при экспорте.';
$string['chainenable'] = 'Включить цепочку';
$string['chaindisable'] = 'Отключить цепочку';
$string['chainchildreport'] = 'Целевой отчёт';
$string['chainfilenamepattern'] = 'Шаблон имени файла';
$string['chainfilenamepattern_help'] = 'Плейсхолдеры: ##reportname##, ##row##, ##имя_колонки## (из ключевых колонок строки).';
$string['chainrowkeycolumns'] = 'Ключевые колонки строки';
$string['chainrowkeycolumns_help'] = 'Колонки отчёта-источника для идентификации строк и имён файлов. Для SQL-отчётов список фиксируется при сохранении запроса на вкладке «SQL».';
$string['chainsourcecolumn'] = 'Колонка отчёта-источника';
$string['chainsourcecolumn_help'] = 'Колонка отчёта-источника, значение которой передаётся в параметр фильтра целевого отчёта.';
$string['chainnocolumnsmetadata'] = 'Для этого типа отчёта имена колонок нужно вводить вручную.';
$string['chainresavesqlforcolumns'] = 'Список колонок не зафиксирован. Сохраните SQL-запрос на вкладке «SQL» ещё раз.';
$string['chaintargetfilter'] = 'Параметр фильтра целевого отчёта';
$string['chainmappingheader'] = 'Сопоставление {$a}';
$string['chainmappingcolumnsheader'] = 'Колонка отчёта-источника → Параметр фильтра целевого отчёта';
$string['chainaddmapping'] = 'Добавить сопоставление';
$string['chainremovemapping'] = 'Удалить сопоставление';
$string['chainselectchildhint'] = 'Сначала выберите целевой отчёт. Остальные настройки появятся после перезагрузки страницы.';
$string['chainnofilters'] = 'У выбранного целевого отчёта нет настроенных фильтров.';
$string['chainexport'] = 'Экспорт по цепочке';
$string['chainexportlink'] = 'Множественный экспорт по цепочке...';
$string['chainexportheadingcontext'] = 'Экспорт по цепочке: {$a->source} → {$a->chain}';
$string['chains_usage_help'] = 'Цепочки позволяют параметризовать другой отчёт результатами этого отчёта. Чтобы воспользоваться цепочкой, перейдите в {$a->viewreportlink}, настройте фильтрами желаемый набор строк, затем нажмите «{$a->exportlink}».';
$string['chainexportchoose'] = 'Выберите настроенную цепочку для экспорта целевых отчётов по строкам отчёта-источника.';
$string['chainexportintro'] = 'Выберите строки отчёта-источника и скачайте отдельный файл целевого отчёта для каждой строки.';
$string['chainexportselectrows'] = 'Выбор строк';
$string['chainexportrows'] = 'Строки';
$string['chainexportformat'] = 'Формат экспорта';
$string['chainexportdownload'] = 'Экспортировать выбранные';
$string['chainexportdownloadzip'] = 'Скачать ZIP-архив';
$string['chainexportdownloadready'] = 'Файлов экспортировано и архивировано: {$a}. Доступно к скачиванию:';
$string['chainexportsummaryheading'] = 'Экспортированные файлы';
$string['chainexportskippedheading'] = 'Пропущенные строки';
$string['chainexportskippednodata'] = 'В целевом отчёте нет данных';
$string['chainexportrownumber'] = 'Строка {$a}';
$string['chainexportnoexported'] = 'Ни один файл не экспортирован: для выбранных строк целевые отчёты не вернули данных.';
$string['chainerror_nozip'] = 'Архив экспорта больше недоступен. Запустите экспорт снова.';
$string['chainerror_exportempty'] = 'Сформированный файл экспорта пустой.';
$string['chainexportselectall'] = 'Выбрать или снять все строки';
$string['chainexportnorowsselected'] = 'Выберите хотя бы одну строку для экспорта.';
$string['chainexportvalidationfailed'] = 'Экспорт не запущен. Исправьте ошибки в форме ниже.';
$string['chainexportsummaryrow'] = 'Строка';
$string['chainexportsummaryfile'] = 'Файл';
$string['chainexportsummaryreason'] = 'Причина';
$string['chainexportzipalreadydownloaded'] = 'Архив экспорта уже был скачан. Запустите экспорт снова, если нужна новая копия.';
$string['jsonunicode'] = 'JSON-экспорт: сохранять символы Unicode';
$string['jsonunicodeinfo'] = 'Если включено, в JSON не-ASCII символы записываются как есть, без escape-последовательностей \\uXXXX.';
$string['chainmaxrows'] = 'Лимит строк для экспорта по цепочке';
$string['chainmaxrowsinfo'] = 'Максимальное число строк отчёта-источника в одной выгрузке по цепочке.';
$string['chainerror_nochild'] = 'Выберите целевой отчёт.';
$string['chainerror_selfreference'] = 'Отчёт не может ссылаться сам на себя.';
$string['chainerror_childmissing'] = 'Выбранный целевой отчёт больше не существует.';
$string['chainerror_nomappings'] = 'Настройте хотя бы одно сопоставление колонки и фильтра.';
$string['chainerror_unknowncolumn'] = 'Неизвестная колонка отчёта-источника: {$a}. Пересохраните SQL или обновите настройки цепочки.';
$string['chainerror_norowkeys'] = 'Укажите хотя бы одну ключевую колонку строки.';
$string['chainerror_cycle'] = 'Эта цепочка создаёт цикл между отчётами.';
$string['chainerror_noexport'] = 'У целевого отчёта не включены форматы экспорта.';
$string['chainerror_invalid'] = 'Некорректная конфигурация цепочки: {$a}';
$string['chainerror_exportformat'] = 'Выбранный формат экспорта не разрешён для целевого отчёта.';
$string['chainerror_norows'] = 'Не выбрано ни одной строки для экспорта.';
$string['chainerror_toomanyrows'] = 'Выбрано слишком много строк. Максимум: {$a}';
$string['chainerror_zip'] = 'Не удалось создать архив экспорта.';
$string['chainerror_nochains'] = 'У этого отчёта нет активных цепочек.';
$string['chainerror_childfilters'] = 'Целевой отчёт требует отправки фильтров и не может использоваться в цепочке.';
