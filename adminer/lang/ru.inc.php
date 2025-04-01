<?php
namespace Adminer;

Lang::$translations = array(
	'Login' => 'Войти',
	'Logout successful.' => 'Вы успешно покинули систему.',
	'Invalid credentials.' => 'Неправильное имя пользователя или пароль.',
	'Server' => 'Сервер',
	'Username' => 'Имя пользователя',
	'Password' => 'Пароль',
	'Select database' => 'Выбрать базу данных',
	'Invalid database.' => 'Неверная база данных.',
	'Table has been dropped.' => 'Таблица была удалена.',
	'Table has been altered.' => 'Таблица была изменена.',
	'Table has been created.' => 'Таблица была создана.',
	'Alter table' => 'Изменить таблицу',
	'Create table' => 'Создать таблицу',
	'Table name' => 'Название таблицы',
	'engine' => 'Тип таблицы',
	'collation' => 'режим сопоставления',
	'Column name' => 'Название поля',
	'Type' => 'Тип',
	'Length' => 'Длина',
	'Auto Increment' => 'Автоматическое приращение',
	'Options' => 'Действие',
	'Save' => 'Сохранить',
	'Drop' => 'Удалить',
	'Database has been dropped.' => 'База данных была удалена.',
	'Database has been created.' => 'База данных была создана.',
	'Database has been renamed.' => 'База данных была переименована.',
	'Database has been altered.' => 'База данных была изменена.',
	'Alter database' => 'Изменить базу данных',
	'Create database' => 'Создать базу данных',
	'SQL command' => 'SQL-запрос',
	'Logout' => 'Выйти',
	'Use' => 'Выбрать',
	'No tables.' => 'В базе данных нет таблиц.',
	'select' => 'выбрать',
	'Item has been deleted.' => 'Запись удалена.',
	'Item has been updated.' => 'Запись обновлена.',
	'Item%s has been inserted.' => 'Запись%s была вставлена.',
	'Edit' => 'Редактировать',
	'Insert' => 'Вставить',
	'Save and insert next' => 'Сохранить и вставить ещё',
	'Delete' => 'Стереть',
	'Database' => 'База данных',
	'Routines' => 'Хранимые процедуры и функции',
	'Indexes have been altered.' => 'Индексы изменены.',
	'Indexes' => 'Индексы',
	'Alter indexes' => 'Изменить индексы',
	'Add next' => 'Добавить ещё',
	'Language' => 'Язык',
	'Select' => 'Выбрать',
	'New item' => 'Новая запись',
	'Search' => 'Поиск',
	'Sort' => 'Сортировать',
	'descending' => 'по убыванию',
	'Limit' => 'Лимит',
	'No rows.' => 'Нет записей.',
	'Action' => 'Действие',
	'edit' => 'редактировать',
	'Page' => 'Страница',
	'Query executed OK, %d row(s) affected.' => array('Запрос завершён, изменена %d запись.', 'Запрос завершён, изменены %d записи.', 'Запрос завершён, изменено %d записей.'),
	'Error in query' => 'Ошибка в запросe',
	'Execute' => 'Выполнить',
	'Table' => 'Таблица',
	'Foreign keys' => 'Внешние ключи',
	'Triggers' => 'Триггеры',
	'View' => 'Представление',
	'Unable to select the table' => 'Не удалось получить данные из таблицы',
	'Invalid CSRF token. Send the form again.' => 'Недействительный CSRF-токен. Отправите форму ещё раз.',
	'Comment' => 'Комментарий',
	'Default values' => 'Значения по умолчанию',
	'%d byte(s)' => array('%d байт', '%d байта', '%d байтов'),
	'No commands to execute.' => 'Нет команд для выполнения.',
	'Unable to upload a file.' => 'Не удалось загрузить файл на сервер.',
	'File upload' => 'Загрузить файл на сервер',
	'File uploads are disabled.' => 'Загрузка файлов на сервер запрещена.',
	'Routine has been called, %d row(s) affected.' => array('Была вызвана процедура, %d запись была изменена.', 'Была вызвана процедура, %d записи было изменено.', 'Была вызвана процедура, %d записей было изменено.'),
	'Call' => 'Вызвать',
	'No extension' => 'Нет расширений',
	'None of the supported PHP extensions (%s) are available.' => 'Недоступно ни одного расширения из поддерживаемых (%s).',
	'Session support must be enabled.' => 'Сессии должны быть включены.',
	'Session expired, please login again.' => 'Срок действия сессии истёк, нужно снова войти в систему.',
	'Text length' => 'Длина текста',
	'Foreign key has been dropped.' => 'Внешний ключ был удалён.',
	'Foreign key has been altered.' => 'Внешний ключ был изменён.',
	'Foreign key has been created.' => 'Внешний ключ был создан.',
	'Foreign key' => 'Внешний ключ',
	'Target table' => 'Результирующая таблица',
	'Change' => 'Изменить',
	'Source' => 'Источник',
	'Target' => 'Цель',
	'Add column' => 'Добавить поле',
	'Alter' => 'Изменить',
	'Add foreign key' => 'Добавить внешний ключ',
	'ON DELETE' => 'При стирании',
	'ON UPDATE' => 'При обновлении',
	'Index Type' => 'Тип индекса',
	'length' => 'длина',
	'View has been dropped.' => 'Представление было удалено.',
	'View has been altered.' => 'Представление было изменено.',
	'View has been created.' => 'Представление было создано.',
	'Alter view' => 'Изменить представление',
	'Create view' => 'Создать представление',
	'Name' => 'Название',
	'Process list' => 'Список процессов',
	'%d process(es) have been killed.' => array('Был завершён %d процесс.', 'Было завершено %d процесса.', 'Было завершено %d процессов.'),
	'Kill' => 'Завершить',
	'Parameter name' => 'Название параметра',
	'Database schema' => 'Схема базы данных',
	'Create procedure' => 'Создать процедуру',
	'Create function' => 'Создать функцию',
	'Routine has been dropped.' => 'Процедура была удалена.',
	'Routine has been altered.' => 'Процедура была изменена.',
	'Routine has been created.' => 'Процедура была создана.',
	'Alter function' => 'Изменить функцию',
	'Alter procedure' => 'Изменить процедуру',
	'Return type' => 'Возвращаемый тип',
	'Add trigger' => 'Добавить триггер',
	'Trigger has been dropped.' => 'Триггер был удалён.',
	'Trigger has been altered.' => 'Триггер был изменён.',
	'Trigger has been created.' => 'Триггер был создан.',
	'Alter trigger' => 'Изменить триггер',
	'Create trigger' => 'Создать триггер',
	'Time' => 'Время',
	'Event' => 'Событие',
	'%s version: %s through PHP extension %s' => 'Версия %s: %s с PHP-расширением %s',
	'%d row(s)' => array('%d строка', '%d строки', '%d строк'),
	'Remove' => 'Удалить',
	'Are you sure?' => 'Вы уверены?',
	'Privileges' => 'Полномочия',
	'Create user' => 'Создать пользователя',
	'User has been dropped.' => 'Пользователь был удалён.',
	'User has been altered.' => 'Пользователь был изменён.',
	'User has been created.' => 'Пользователь был создан.',
	'Hashed' => 'Хешировано',
	'Column' => 'поле',
	'Routine' => 'Процедура',
	'Grant' => 'Позволить',
	'Revoke' => 'Запретить',
	'Too big POST data. Reduce the data or increase the %s configuration directive.' => 'Слишком большой объем POST-данных. Пошлите меньший объём данных или увеличьте параметр конфигурационной директивы %s.',
	'Logged as: %s' => 'Вы вошли как: %s',
	'Move up' => 'Переместить вверх',
	'Move down' => 'Переместить вниз',
	'Functions' => 'Функции',
	'Aggregation' => 'Агрегация',
	'Export' => 'Экспорт',
	'Output' => 'Выходные данные',
	'open' => 'открыть',
	'save' => 'сохранить',
	'Format' => 'Формат',
	'Tables' => 'Таблицы',
	'Data' => 'Данные',
	'Event has been dropped.' => 'Событие было удалено.',
	'Event has been altered.' => 'Событие было изменено.',
	'Event has been created.' => 'Событие было создано.',
	'Alter event' => 'Изменить событие',
	'Create event' => 'Создать событие',
	'At given time' => 'В данное время',
	'Every' => 'Каждые',
	'Events' => 'События',
	'Schedule' => 'Расписание',
	'Start' => 'Начало',
	'End' => 'Конец',
	'Status' => 'Состояние',
	'On completion preserve' => 'После завершения сохранить',
	'Tables and views' => 'Таблицы и представления',
	'Data Length' => 'Объём данных',
	'Index Length' => 'Объём индексов',
	'Data Free' => 'Свободное место',
	'Collation' => 'Режим сопоставления',
	'Analyze' => 'Анализировать',
	'Optimize' => 'Оптимизировать',
	'Check' => 'Проверить',
	'Repair' => 'Исправить',
	'Truncate' => 'Очистить',
	'Tables have been truncated.' => 'Таблицы были очищены.',
	'Rows' => 'Строк',
	',' => ' ',
	'0123456789' => '0123456789',
	'Tables have been moved.' => 'Таблицы были перемещены.',
	'Move to other database' => 'Переместить в другую базу данных',
	'Move' => 'Переместить',
	'Engine' => 'Тип таблиц',
	'Save and continue edit' => 'Сохранить и продолжить редактирование',
	'original' => 'исходный',
	'%d item(s) have been affected.' => array('Была изменена %d запись.', 'Были изменены %d записи.', 'Было изменено %d записей.'),
	'Whole result' => 'Весь результат',
	'Tables have been dropped.' => 'Таблицы были удалены.',
	'Clone' => 'Клонировать',
	'Partition by' => 'Разделить по',
	'Partitions' => 'Разделы',
	'Partition name' => 'Название раздела',
	'Values' => 'Параметры',
	'%d row(s) have been imported.' => array('Импортирована %d строка.', 'Импортировано %d строки.', 'Импортировано %d строк.'),
	'Import' => 'Импорт',
	'Stop on error' => 'Остановить при ошибке',
	'Maximum number of allowed fields exceeded. Please increase %s.' => 'Достигнуто максимальное значение количества доступных полей. Увеличьте %s.',
	'anywhere' => 'в любом месте',
	'%.3f s' => '%.3f s',
	'$1-$3-$5' => '$5.$3.$1',
	'[yyyy]-mm-dd' => 'дд.мм.[гггг]',
	'History' => 'История',
	'Variables' => 'Переменные',
	'Source and target columns must have the same data type, there must be an index on the target columns and referenced data must exist.' => 'Поля должны иметь одинаковые типы данных, в результирующем поле должен быть индекс, данные для импорта должны существовать.',
	'Relations' => 'Отношения',
	'Run file' => 'Запустить файл',
	'Clear' => 'Очистить',
	'Maximum allowed file size is %sB.' => 'Максимальный разрешённый размер файла — %sB.',
	'Numbers' => 'Числа',
	'Date and time' => 'Дата и время',
	'Strings' => 'Строки',
	'Binary' => 'Двоичный тип',
	'Lists' => 'Списки',
	'Editor' => 'Редактор',
	'E-mail' => 'Эл. почта',
	'From' => 'От',
	'Subject' => 'Тема',
	'Send' => 'Послать',
	'%d e-mail(s) have been sent.' => array('Было отправлено %d письмо.', 'Было отправлено %d письма.', 'Было отправлено %d писем.'),
	'Webserver file %s' => 'Файл %s на вебсервере',
	'File does not exist.' => 'Такого файла не существует.',
	'%d in total' => 'Всего %d',
	'Permanent login' => 'Оставаться в системе',
	'Databases have been dropped.' => 'Базы данных удалены.',
	'Search data in tables' => 'Поиск в таблицах',
	'Schema' => 'Схема',
	'Alter schema' => 'Изменить схему',
	'Create schema' => 'Новая схема',
	'Schema has been dropped.' => 'Схема удалена.',
	'Schema has been created.' => 'Создана новая схема.',
	'Schema has been altered.' => 'Схема изменена.',
	'Sequences' => '«Последовательности»',
	'Create sequence' => 'Создать «последовательность»',
	'Alter sequence' => 'Изменить «последовательность»',
	'Sequence has been dropped.' => '«Последовательность» удалена.',
	'Sequence has been created.' => 'Создана новая «последовательность».',
	'Sequence has been altered.' => '«Последовательность» изменена.',
	'User types' => 'Типы пользователей',
	'Create type' => 'Создать тип',
	'Alter type' => 'Изменить тип',
	'Type has been dropped.' => 'Тип удален.',
	'Type has been created.' => 'Создан новый тип.',
	'Ctrl+click on a value to modify it.' => 'Выполните Ctrl+Щелчок мышью по значению, чтобы его изменить.',
	'Use edit link to modify this value.' => 'Изменить это значение можно с помощью ссылки «изменить».',
	'last' => 'последняя',
	'From server' => 'С сервера',
	'System' => 'Движок',
	'Select data' => 'Выбрать',
	'Show structure' => 'Показать структуру',
	'empty' => 'пусто',
	'Network' => 'Сеть',
	'Geometry' => 'Геометрия',
	'File exists.' => 'Файл уже существует.',
	'Attachments' => 'Прикреплённые файлы',
	'%d query(s) executed OK.' => array('%d запрос выполнен успешно.', '%d запроса выполнено успешно.', '%d запросов выполнено успешно.'),
	'Show only errors' => 'Только ошибки',
	'Refresh' => 'Обновить',
	'Invalid schema.' => 'Неправильная схема.',
	'Please use one of the extensions %s.' => 'Используйте одно из этих расширений %s.',
	'now' => 'сейчас',
	'ltr' => 'ltr',
	'Tables have been copied.' => 'Таблицы скопированы.',
	'Copy' => 'Копировать',
	'Permanent link' => 'Постоянная ссылка',
	'Edit all' => 'Редактировать всё',
	'HH:MM:SS' => 'ЧЧ:ММ:СС',
	'Tables have been optimized.' => 'Таблицы оптимизированы.',
	'Materialized view' => 'Материализованное представление',
	'Vacuum' => 'Вакуум',
	'Selected' => 'Выбранные',
	'File must be in UTF-8 encoding.' => 'Файл должен быть в кодировке UTF-8.',
	'Modify' => 'Изменить',
	'Loading' => 'Загрузка',
	'Load more data' => 'Загрузить ещё данные',
	'ATTACH queries are not supported.' => 'ATTACH-запросы не поддерживаются.',
	'%d / ' => '%d / ',
	'Limit rows' => 'Лимит строк',
	'Default value' => 'Значение по умолчанию',
	'Full table scan' => 'Анализ полной таблицы',
	'Too many unsuccessful logins, try again in %d minute(s).' => array('Слишком много неудачных попыток входа. Попробуйте снова через %d минуту.', 'Слишком много неудачных попыток входа. Попробуйте снова через %d минуты.', 'Слишком много неудачных попыток входа. Попробуйте снова через %d минут.'),
	'Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> %s method to make it permanent.' => 'Мастер-пароль истёк. <a href="https://www.adminer.org/en/extension/"%s>Реализуйте</a> метод %s, чтобы сделать его постоянным.',
	'If you did not send this request from Adminer then close this page.' => 'Если вы не посылали этот запрос из Adminer, закройте эту страницу.',
	'You can upload a big SQL file via FTP and import it from server.' => 'Вы можете закачать большой SQL-файл по FTP и затем импортировать его с сервера.',
	'Size' => 'Размер',
	'Compute' => 'Вычислить',
	'You are offline.' => 'Вы не выполнили вход.',
	'You have no privileges to update this table.' => 'У вас нет прав на обновление этой таблицы.',
	'Saving' => 'Сохранение',
	'yes' => 'Да',
	'no' => 'Нет',
	'Drop %s?' => 'Удалить %s?',
	'overwrite' => 'перезаписать',
	'DB' => 'DB',
	'Warnings' => 'Предупреждения',
	'Adminer does not support accessing a database without a password, <a href="https://www.adminer.org/en/password/"%s>more information</a>.' => 'Adminer не поддерживает доступ к базе данных без пароля, <a href="https://www.adminer.org/en/password/"%s>больше информации</a>.',
	'Thanks for using Adminer, consider <a href="https://www.adminer.org/en/donation/">donating</a>.' => 'Спасибо за использование Adminer, рассмотрите возможность <a href="https://www.adminer.org/en/donation/">пожертвования</a>.',
	'The action will be performed after successful login with the same credentials.' => 'Действие будет выполнено после успешного входа в систему с теми же учетными данными.',
	'Connecting to privileged ports is not allowed.' => 'Подключение к привилегированным портам не допускается.',
	'There is a space in the input password which might be the cause.' => 'В введеном пароле есть пробел, это может быть причиною.',
	'Unknown error.' => 'Неизвестная ошибка.',
	'Database does not support password.' => 'База данных не поддерживает пароль.',
	'Disable %s or enable %s or %s extensions.' => 'Отключите %s или включите расширения %s или %s.',
	'Check has been dropped.' => 'Проверка удалена',
	'Check has been altered.' => 'Проверка изменена',
	'Check has been created.' => 'Проверка создана',
	'Alter check' => 'Изменить проверку',
	'Create check' => 'Создать проверку',
	'Checks' => 'Проверки',
	'Loaded plugins' => 'Загруженные плагины',
	'%s must <a%s>return an array</a>.' => '%s должна <a%s>вернуть массив</a>.',
	'<a%s>Configure</a> %s in %s.' => '<a%s>Настроить</a> %s в %s.',
);

// run `php ../../lang.php ru` to update this file
