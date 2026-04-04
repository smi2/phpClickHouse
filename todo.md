# TODO: phpClickHouse Roadmap

## 1. Native Query Parameters

ClickHouse поддерживает типизированные параметры через HTTP: `{name:Type}` — сервер сам парсит значения, SQL injection невозможен на уровне протокола.

### Текущее состояние
- `Query::isUseInUrlBindingsParams()` уже детектит `{p1:UInt8}` синтаксис
- `Query::getUrlBindingsParams()` извлекает params для URL
- `Http::makeRequest()` передаёт их как `param_*` в query string
- НО: нет удобного API в Client, нет валидации типов, нет документации

### План
- [ ] Добавить `Client::selectWithParams(string $sql, array $params, string $format = 'JSON')` 
  - `$params = ['p1' => ['value' => 42, 'type' => 'UInt32']]`
  - Формирует `param_p1=42` в URL, тип уже в SQL: `{p1:UInt32}`
- [ ] Добавить `Client::writeWithParams(string $sql, array $params)` — аналог для DDL/DML
- [ ] Валидация: проверять что все `{name:Type}` из SQL имеют соответствующий param
- [ ] Конвертация PHP-типов в CH-значения (DateTimeInterface → string, array → JSON и т.д.)
- [ ] Не ломать существующий `select()` / `write()` — новые методы параллельно
- [ ] Тесты: unit (без CH) + integration (с CH 21 и 26)
- [ ] Документация: `doc/native-params.md`

### Файлы
- `src/Client.php` — новые методы
- `src/Query/Query.php` — валидация params vs SQL placeholders
- `src/Query/ParamValueConverter.php` — новый: конвертация PHP → CH string
- `tests/NativeParamsTest.php` — unit
- `tests/ClickHouse26/NativeParamsTest.php` — integration (native params лучше тестить на 26.x)

---

## 2. Полная поддержка типов ClickHouse (60+)

Расширить `ValueFormatter` и добавить систему типов для native parameters.

### Текущее состояние
- `ValueFormatter`: int, float, bool, string, null, DateTimeInterface, Expression, Type
- `UInt64` — единственный кастомный тип
- Нет поддержки: DateTime64, Date32, IPv4/IPv6, UUID, Map, Tuple, Enum, Decimal, Geo-типы

### План — Фаза 1: Основные типы
- [ ] `src/Type/` — расширить систему типов:
  - [ ] `Int8`, `Int16`, `Int32`, `Int64`, `Int128`, `Int256`
  - [ ] `UInt8`, `UInt16`, `UInt32`, `UInt64` (уже есть), `UInt128`, `UInt256`
  - [ ] `Float32`, `Float64`
  - [ ] `Decimal(P, S)`, `Decimal32`, `Decimal64`, `Decimal128`, `Decimal256`
  - [ ] `Bool`
- [ ] Тесты на каждый тип: insert + select + сравнение

### План — Фаза 2: Строки и даты
- [ ] `String`, `FixedString(N)`
- [ ] `Date`, `Date32`
- [ ] `DateTime`, `DateTime64(precision, timezone)`
- [ ] `UUID`
- [ ] `IPv4`, `IPv6`
- [ ] `Enum8`, `Enum16`
- [ ] Тесты

### План — Фаза 3: Составные типы
- [ ] `Array(T)` — уже частично работает, формализовать
- [ ] `Tuple(T1, T2, ...)`
- [ ] `Map(K, V)`
- [ ] `Nullable(T)` — уже частично работает
- [ ] `LowCardinality(T)`
- [ ] `Nested(name1 T1, name2 T2)` — уже частично, формализовать
- [ ] Тесты

### План — Фаза 4: Специализированные типы
- [ ] `JSON` / `Object('json')`
- [ ] Geo: `Point`, `Ring`, `LineString`, `Polygon`, `MultiPolygon`
- [ ] `SimpleAggregateFunction`, `AggregateFunction`
- [ ] Тесты

### Архитектура
```
src/Type/
├── Type.php (базовый интерфейс — уже есть)
├── NumericType.php (уже есть)
├── UInt64.php (уже есть)
├── TypeRegistry.php — NEW: маппинг CH type name → PHP class
├── Date32.php
├── DateTime64.php
├── IPv4.php
├── IPv6.php
├── UUIDType.php
├── MapType.php
└── TupleType.php
```

### Принципы
- Каждый тип реализует `Type` интерфейс (`getValue()`)
- `TypeRegistry` — singleton с маппингом `'DateTime64' → DateTime64::class`
- Обратная совместимость: существующий код без типов продолжает работать
- Типы опциональны — можно передавать raw values как раньше

---

## 3. Structured Exceptions

Обогатить исключения информацией из ClickHouse: error code, exception name, stack trace.

### Текущее состояние
- `DatabaseException` — парсит `Code: N. DB::Exception: message`
- `TransportException` — curl ошибки
- `QueryException` — общие ошибки запросов
- НЕТ: CH exception class name, query ID, stack trace от сервера

### План
- [ ] `DatabaseException` — добавить поля:
  - [ ] `getClickHouseExceptionName(): ?string` — `SYNTAX_ERROR`, `TABLE_NOT_FOUND` и т.д.
  - [ ] `getQueryId(): ?string` — из заголовка `X-ClickHouse-Query-Id`
  - [ ] `getServerVersion(): ?string` — из ответа
- [ ] Парсить новый формат ошибок CH 22+: `(EXCEPTION_NAME) (version X.Y.Z)`
- [ ] Расширить regex в Statement: `CLICKHOUSE_ERROR_REGEX`
- [ ] НЕ менять конструктор `DatabaseException` — добавить сеттеры/фабрику
- [ ] Тесты: unit с mock ответами + data provider с разными форматами ошибок
- [ ] Тесты на CH 21 (старый формат) и CH 26 (новый формат)

### Файлы
- `src/Exception/DatabaseException.php` — расширить
- `src/Statement.php` — парсинг в `parseErrorClickHouse()`
- `tests/ExceptionParsingTest.php` — unit тесты с data provider

---

## 4. PHPStan Level Max

Поэтапно поднять PHPStan с level 1 до max.

### Текущее состояние
- `phpstan.neon.dist`: level 1, phpVersion 80406
- PHPStan 2.1, PHP 8.4.6
- 0 ошибок на level 1

### План — поэтапный подъём
- [ ] Level 2 → исправить ошибки → коммит
- [ ] Level 3 → исправить ошибки → коммит
- [ ] Level 4 → исправить ошибки → коммит
- [ ] Level 5 → исправить ошибки → коммит (основные type checks)
- [ ] Level 6 → исправить ошибки → коммит (missing typehints)
- [ ] Level 7 → исправить ошибки → коммит (union types)
- [ ] Level 8 → исправить ошибки → коммит (nullability)
- [ ] Level 9 → исправить ошибки → коммит (mixed type)
- [ ] Level max → финальная проверка

### Принципы
- Каждый уровень = отдельный коммит
- НЕ менять публичные сигнатуры методов (обратная совместимость!)
- Добавлять `@phpstan-*` аннотации только как крайняя мера
- Предпочитать реальные фиксы типов, а не подавление ошибок
- Прогонять тесты после каждого уровня

### Оценка
- Сейчас ~35 PHP файлов в src/ — масштаб управляемый
- Основные проблемы ожидаются на level 5-6 (missing type hints)
- Level 8+ может потребовать добавления `@phpstan-assert` / `@phpstan-param`

---

## 5. Per-Query Settings Override

Передавать настройки ClickHouse на уровне отдельного запроса.

### Текущее состояние
- `Settings` — глобальный объект, общий на все запросы
- Для изменения надо: `$client->settings()->set(...)` → запрос → `$client->settings()->set(...)` обратно
- Неудобно и не thread-safe (если делить client между goroutines/fibers)

### План
- [ ] Добавить `$settings` параметр в существующие методы (с default = `[]`):
  - [ ] `Client::select($sql, $bindings = [], $whereInFile = null, $writeToFile = null, array $settings = [])`
  - [ ] `Client::write($sql, $bindings = [], $exception = true, array $settings = [])`
  - [ ] `Client::selectAsync(...)` — аналогично
- [ ] `Http::select()` / `Http::write()` — пробросить settings в URL params
- [ ] Settings мержатся: глобальные + per-query (per-query приоритет)
- [ ] НЕ ломать обратную совместимость — новый параметр со значением по умолчанию `[]`
- [ ] Тесты: проверить что per-query settings применяются, а глобальные не меняются

### Пример использования
```php
// Глобальные настройки
$db->settings()->set('max_execution_time', 30);

// Один тяжёлый запрос с увеличенным таймаутом
$result = $db->select(
    'SELECT * FROM huge_table',
    [],
    null,
    null,
    ['max_execution_time' => 300, 'max_rows_to_read' => 1000000]
);

// Следующий запрос — снова 30 сек
$db->select('SELECT 1');
```

### Файлы
- `src/Client.php` — добавить параметр
- `src/Transport/Http.php` — merge settings
- `tests/PerQuerySettingsTest.php`
- `tests/ClickHouse26/PerQuerySettingsTest.php`

---

## Приоритеты

| # | Задача | Сложность | Риск ломки API | Приоритет |
|---|--------|-----------|----------------|-----------|
| 5 | Per-query settings | Низкая | Нулевой | **P0** |
| 3 | Structured exceptions | Низкая | Нулевой | **P0** |
| 1 | Native Query Parameters | Средняя | Нулевой (новые методы) | **P1** |
| 4 | PHPStan level max | Средняя | Нулевой | **P1** |
| 2 | 60+ типов (фаза 1-2) | Средняя | Нулевой | **P2** |
| 2 | 60+ типов (фаза 3-4) | Высокая | Нулевой | **P3** |

## Ограничения

- **НЕЛЬЗЯ** менять сигнатуры существующих публичных методов
- **НЕЛЬЗЯ** менять существующие тесты
- **НЕЛЬЗЯ** добавлять внешние зависимости в `require`
- Новые параметры — **ТОЛЬКО** с default значениями
- Каждая фича = отдельная ветка + PR + тесты для CH 21 и CH 26
