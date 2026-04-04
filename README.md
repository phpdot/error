# phpdot/error

Structured error codes with context, translatable messages, and uniform output across every channel. Zero dependencies.

---

## Table of Contents

- [Install](#install)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
  - [Flow](#flow)
  - [Package Structure](#package-structure)
- [Defining Error Codes](#defining-error-codes)
  - [ErrorCodeInterface](#errorcodeinterface)
  - [ErrorCodeTrait](#errorcodetrait)
  - [Module Error Enums](#module-error-enums)
  - [Error Code Convention](#error-code-convention)
- [ErrorEntry (The DTO)](#errorentry-the-dto)
- [ErrorBag (The Collector)](#errorbag-the-collector)
  - [Adding Errors](#adding-errors)
  - [Checking Errors](#checking-errors)
  - [Filtering Errors](#filtering-errors)
  - [Merging Bags](#merging-bags)
  - [HTTP Status](#http-status)
  - [Serialization](#serialization)
- [ErrorType (9 Categories)](#errortype-9-categories)
- [HttpStatus (Typed Enum)](#httpstatus-typed-enum)
- [Context — What the Error Relates To](#context)
- [Translation (i18n)](#translation-i18n)
  - [How It Works](#how-translation-works)
  - [ICU Params](#icu-params)
  - [Frontend Translation](#frontend-translation)
  - [Server-Side Translation](#server-side-translation)
- [Output Formats](#output-formats)
  - [JSON API](#json-api)
  - [HTML Forms](#html-forms)
  - [CLI](#cli)
  - [WebSocket](#websocket)
- [Real-World Usage](#real-world-usage)
  - [Service Validation](#service-validation)
  - [Cross-Module Merge](#cross-module-merge)
  - [Frontend Grouping](#frontend-grouping)
- [API Reference](#api-reference)
  - [ErrorCodeInterface API](#errorcodeinterface-api)
  - [ErrorCodeTrait API](#errorcodetrait-api)
  - [ErrorEntry API](#errorentry-api)
  - [ErrorBag API](#errorbag-api)
  - [ErrorType API](#errortype-api)
  - [HttpStatus API](#httpstatus-api)
- [License](#license)

---

## Install

```bash
composer require phpdot/error
```

| Requirement | Version |
|-------------|---------|
| PHP | >= 8.3 |
| Dependencies | **None** |

---

## Quick Start

**1. Define errors for your module:**

```php
enum UserErrors: string implements ErrorCodeInterface
{
    use ErrorCodeTrait;

    case NOT_FOUND     = '00010001';
    case EMAIL_TAKEN   = '00010002';
    case INVALID_EMAIL = '00010003';
    case WEAK_PASSWORD = '00010004';

    public function getDetails(): array
    {
        return match ($this) {
            self::NOT_FOUND => [
                'message'     => 'User not found',
                'description' => 'errors.user.not_found',
                'type'        => ErrorType::NOT_FOUND,
                'httpStatus'  => HttpStatus::NOT_FOUND->value,
            ],
            self::EMAIL_TAKEN => [
                'message'     => 'Email is already taken',
                'description' => 'errors.user.email_taken',
                'type'        => ErrorType::CONFLICT,
                'httpStatus'  => HttpStatus::CONFLICT->value,
            ],
            // ...
        };
    }
}
```

**2. Collect errors:**

```php
$errors = new ErrorBag();

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors->add(UserErrors::INVALID_EMAIL, 'email');
}

if (strlen($password) < 8) {
    $errors->add(UserErrors::WEAK_PASSWORD, 'password', ['min' => 8]);
}

if ($errors->hasErrors()) {
    return $errors; // same structure for JSON, HTML, WebSocket, CLI
}
```

**3. Same output everywhere:**

```json
{
    "errors": [
        {
            "code": "00010003",
            "message": "Invalid email address",
            "description": "errors.user.invalid_email",
            "type": "validation",
            "httpStatus": 422,
            "context": "email",
            "params": []
        }
    ]
}
```

---

## Architecture

### Flow

```
Module enum (UserErrors, OrderErrors, etc.)
    implements ErrorCodeInterface
    uses ErrorCodeTrait
        │
        │ provides: code, message, description (i18n key), type, httpStatus
        ▼
ErrorBag::add(UserErrors::EMAIL_TAKEN, 'email', ['email' => $email])
        │
        │ creates ErrorEntry DTO
        ▼
ErrorBag collects ErrorEntry objects
        │
        ├──► JSON API:  $bag->toArray() → uniform JSON
        ├──► HTML form: $bag->forContext('email') → show next to field
        ├──► CLI:       $bag->all() → formatted output
        └──► WebSocket: $bag->toArray() → same JSON
```

One error. One code. One structure. Every channel. Every language.

### Package Structure

```
src/
├── ErrorCodeInterface.php   # Interface for module error enums
├── ErrorCodeTrait.php       # Default implementation via getDetails()
├── ErrorEntry.php           # Readonly DTO — single error
├── ErrorBag.php             # Collector — add, filter, merge, serialize
├── ErrorType.php            # 9 error categories
└── HttpStatus.php           # HTTP status codes enum
```

6 files. 409 lines. Zero dependencies.

---

## Defining Error Codes

### ErrorCodeInterface

Every module defines a backed string enum implementing this interface:

```php
interface ErrorCodeInterface
{
    public function getCode(): string;
    public function getMessage(): string;
    public function getDescription(): string;
    public function getType(): ErrorType;
    public function getHttpStatus(): int;
    public function getDetails(): array;
}
```

### ErrorCodeTrait

Provides the default implementation. The trait reads from `getDetails()` — you only implement one method:

```php
trait ErrorCodeTrait
{
    public function getCode(): string       { return $this->value; }
    public function getMessage(): string    { return $this->getDetails()['message']; }
    public function getDescription(): string { return $this->getDetails()['description']; }
    public function getType(): ErrorType    { return $this->getDetails()['type']; }
    public function getHttpStatus(): int    { return $this->getDetails()['httpStatus']; }
}
```

### Module Error Enums

Each module owns its errors. No central error file.

```php
// User module
enum UserErrors: string implements ErrorCodeInterface
{
    use ErrorCodeTrait;

    case NOT_FOUND     = '00010001';
    case EMAIL_TAKEN   = '00010002';
    case INVALID_EMAIL = '00010003';
    case WEAK_PASSWORD = '00010004';
    case LOCKED        = '00010005';

    public function getDetails(): array
    {
        return match ($this) {
            self::NOT_FOUND => [
                'message'     => 'User not found',
                'description' => 'errors.user.not_found',
                'type'        => ErrorType::NOT_FOUND,
                'httpStatus'  => HttpStatus::NOT_FOUND->value,
            ],
            self::EMAIL_TAKEN => [
                'message'     => 'Email is already taken',
                'description' => 'errors.user.email_taken',
                'type'        => ErrorType::CONFLICT,
                'httpStatus'  => HttpStatus::CONFLICT->value,
            ],
            self::INVALID_EMAIL => [
                'message'     => 'Invalid email address',
                'description' => 'errors.user.invalid_email',
                'type'        => ErrorType::VALIDATION,
                'httpStatus'  => HttpStatus::UNPROCESSABLE_ENTITY->value,
            ],
            self::WEAK_PASSWORD => [
                'message'     => 'Password must be at least 8 characters',
                'description' => 'errors.user.weak_password',
                'type'        => ErrorType::VALIDATION,
                'httpStatus'  => HttpStatus::UNPROCESSABLE_ENTITY->value,
            ],
            self::LOCKED => [
                'message'     => 'Account is locked',
                'description' => 'errors.user.account_locked',
                'type'        => ErrorType::AUTHORIZATION,
                'httpStatus'  => HttpStatus::FORBIDDEN->value,
            ],
        };
    }
}

// Order module — separate file, separate team, no conflicts
enum OrderErrors: string implements ErrorCodeInterface
{
    use ErrorCodeTrait;

    case NOT_FOUND       = '00020001';
    case ALREADY_SHIPPED = '00020002';
    case PAYMENT_FAILED  = '00020003';

    public function getDetails(): array { /* ... */ }
}
```

### Error Code Convention

```
Format: MMMMNNNN (8 digits)
        ^^^^              = module ID (0001-9999)
            ^^^^          = error number within module (0001-9999)

Assignments:
    0001 = User / Auth
    0002 = Order
    0003 = Product
    0004 = Payment
    0005 = Event
    ...
```

---

## ErrorEntry (The DTO)

Pure data. No translation, no escaping. Immutable.

```php
final readonly class ErrorEntry
{
    public string $code;         // '00010003'
    public string $message;      // 'Invalid email address' (English fallback)
    public string $description;  // 'errors.user.invalid_email' (i18n key)
    public ErrorType $type;      // ErrorType::VALIDATION
    public int $httpStatus;      // 422
    public ?string $context;     // 'email' (field, param, header, service, path)
    public array $params;        // ['min' => 8] (ICU interpolation params)
}

$entry->toArray(); // serializable array
```

---

## ErrorBag (The Collector)

### Adding Errors

```php
$bag = new ErrorBag();

// From module enum
$bag->add(UserErrors::INVALID_EMAIL, 'email');
$bag->add(UserErrors::WEAK_PASSWORD, 'password', ['min' => 8]);

// Raw entry
$bag->addEntry(new ErrorEntry('CUSTOM', 'msg', 'desc', ErrorType::SERVER, 500));

// Chainable
$bag->add(UserErrors::INVALID_EMAIL, 'email')
    ->add(UserErrors::WEAK_PASSWORD, 'password');
```

### Checking Errors

```php
$bag->hasErrors();                          // bool
$bag->hasError(UserErrors::EMAIL_TAKEN);    // check specific code
$bag->count();                              // int
$bag->first();                              // ?ErrorEntry
$bag->all();                                // list<ErrorEntry>
$bag->codes();                              // list<string> — unique codes
```

### Filtering Errors

```php
// By context (field, param, header, etc.)
$bag->forContext('email');        // list<ErrorEntry>
$bag->forContext('password');     // list<ErrorEntry>
$bag->forContext('Authorization'); // list<ErrorEntry>

// By error type
$bag->ofType(ErrorType::VALIDATION);     // list<ErrorEntry>
$bag->ofType(ErrorType::NOT_FOUND);      // list<ErrorEntry>
$bag->ofType(ErrorType::AUTHENTICATION); // list<ErrorEntry>
```

### Merging Bags

Combine errors from sub-operations:

```php
$userErrors = $userService->validate($data);
$orderErrors = $orderService->validate($data);

$combined = new ErrorBag();
$combined->merge($userErrors)->merge($orderErrors);
```

### HTTP Status

Derived from the first error. If empty, returns 500.

```php
$bag->getHttpStatus(); // 422 (from first error)
```

### Serialization

```php
$bag->toArray();
// [
//     ['code' => '00010003', 'message' => '...', 'description' => '...', 'type' => 'validation', 'httpStatus' => 422, 'context' => 'email', 'params' => []],
//     ['code' => '00010004', 'message' => '...', 'description' => '...', 'type' => 'validation', 'httpStatus' => 422, 'context' => 'password', 'params' => ['min' => 8]],
// ]

json_encode(['errors' => $bag->toArray()]);
```

Clear and reset:

```php
$bag->clear(); // remove all errors, returns self
```

---

## ErrorType (9 Categories)

```php
enum ErrorType: string
{
    case VALIDATION     = 'validation';      // input is wrong
    case AUTHENTICATION = 'authentication';  // who are you?
    case AUTHORIZATION  = 'authorization';   // you can't do this
    case NOT_FOUND      = 'not_found';       // doesn't exist
    case CONFLICT       = 'conflict';        // duplicate, version mismatch
    case RATE_LIMIT     = 'rate_limit';      // too many requests
    case TIMEOUT        = 'timeout';         // took too long
    case UNAVAILABLE    = 'unavailable';     // service down
    case SERVER         = 'server';          // unexpected internal error
}
```

The frontend uses the type to decide presentation (red badge for server, yellow for validation, etc.). The error code gives the specific problem.

---

## HttpStatus (Typed Enum)

IDE autocompletion and compile-time safety for HTTP status codes:

```php
enum HttpStatus: int
{
    case OK                    = 200;
    case CREATED               = 201;
    case NO_CONTENT            = 204;
    case BAD_REQUEST           = 400;
    case UNAUTHORIZED          = 401;
    case FORBIDDEN             = 403;
    case NOT_FOUND             = 404;
    case CONFLICT              = 409;
    case UNPROCESSABLE_ENTITY  = 422;
    case TOO_MANY_REQUESTS     = 429;
    case INTERNAL_SERVER_ERROR = 500;
    case SERVICE_UNAVAILABLE   = 503;
    // ... 25 codes total
}

// In error enums
'httpStatus' => HttpStatus::NOT_FOUND->value, // 404
```

---

## Context

Context is **what the error relates to**. Not just form fields.

```php
$errors->add(UserErrors::INVALID_EMAIL, 'email');              // form field
$errors->add(UserErrors::NOT_FOUND, 'user_id');                // route parameter
$errors->add(AuthErrors::INVALID_TOKEN, 'Authorization');       // HTTP header
$errors->add(PaymentErrors::GATEWAY_DOWN, 'stripe');            // service name
$errors->add(OrderErrors::INVALID_ADDRESS, 'address.city');     // nested path
$errors->add(SystemErrors::MAINTENANCE);                        // no context — global
```

Filter by context to show errors next to the right element:

```php
$errors->forContext('email');          // errors for the email field
$errors->forContext('Authorization');  // errors for the auth header
```

---

## Translation (i18n)

### How Translation Works

The error package stores translation keys, not translated text. Translation happens at render time.

```
Error created → description = 'errors.user.email_taken'
                              (this is a translation key, not text)
                                    │
    ┌───────────────────────────────┤
    │                               │
    ▼                               ▼
JSON API                    HTML (server-rendered)
returns the key →           $i18n->trans($error->description)
frontend translates         → "البريد الإلكتروني مستخدم"
```

### ICU Params

Dynamic values for translation interpolation. Works with any ICU-compatible i18n library — [phpdot/i18n](https://github.com/phpdot/i18n) is one option, but not required.

```php
$errors->add(ProductErrors::INSUFFICIENT_STOCK, 'quantity', [
    'available' => 5,
    'requested' => 10,
]);

// Translation files:
// en: 'errors.product.insufficient_stock' → 'Only {available} items in stock, you requested {requested}'
// ar: 'errors.product.insufficient_stock' → 'يتوفر {available} عناصر فقط، طلبت {requested}'
```

### Frontend Translation

The frontend receives the error code and description key, translates in its own i18n system:

```javascript
response.errors.forEach(error => {
    const translated = i18n.t(error.description, error.params);
    showError(error.context, translated);
});
```

### Server-Side Translation

With any translation library:

```php
foreach ($bag->all() as $error) {
    $translated = $i18n->trans($error->description, $error->params);
    // Show next to the form field identified by $error->context
}
```

---

## Output Formats

### JSON API

```json
{
    "errors": [
        {
            "code": "00010003",
            "message": "Invalid email address",
            "description": "errors.user.invalid_email",
            "type": "validation",
            "httpStatus": 422,
            "context": "email",
            "params": []
        },
        {
            "code": "00010004",
            "message": "Password must be at least 8 characters",
            "description": "errors.user.weak_password",
            "type": "validation",
            "httpStatus": 422,
            "context": "password",
            "params": {"min": 8}
        }
    ]
}
```

### HTML Forms

```php
foreach ($bag->forContext('email') as $error) {
    echo '<span class="error">' . $i18n->trans($error->description, $error->params) . '</span>';
}
```

### CLI

```
[00010003] Invalid email address (context: email)
[00010004] Password must be at least 8 characters (context: password)
```

### WebSocket

Same `$bag->toArray()` serialized as JSON. Identical structure to the API.

---

## Real-World Usage

### Service Validation

```php
final class UserService
{
    public function register(string $email, string $password): User|ErrorBag
    {
        $errors = new ErrorBag();

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors->add(UserErrors::INVALID_EMAIL, 'email');
        }

        if (strlen($password) < 8) {
            $errors->add(UserErrors::WEAK_PASSWORD, 'password', ['min' => 8]);
        }

        if ($errors->hasErrors()) {
            return $errors;
        }

        if ($this->users->emailExists($email)) {
            $errors->add(UserErrors::EMAIL_TAKEN, 'email');
            return $errors;
        }

        return $this->users->create($email, $password);
    }
}
```

### Cross-Module Merge

```php
$userErrors = $userService->validate($data);
$addressErrors = $addressService->validate($data['address']);

$errors = new ErrorBag();
$errors->merge($userErrors)->merge($addressErrors);

if ($errors->hasErrors()) {
    return response()->json(['errors' => $errors->toArray()], $errors->getHttpStatus());
}
```

### Frontend Grouping

```php
$grouped = [];
foreach ($bag->all() as $error) {
    $key = $error->context ?? '_global';
    $grouped[$key][] = $error->toArray();
}
// $grouped['email'] → [{code: '00010003', ...}, {code: '00010002', ...}]
// $grouped['password'] → [{code: '00010004', ...}]
// $grouped['_global'] → [{code: '00060001', ...}]
```

---

## API Reference

### ErrorCodeInterface API

```
interface ErrorCodeInterface

getCode(): string
getMessage(): string
getDescription(): string
getType(): ErrorType
getHttpStatus(): int
getDetails(): array{message: string, description: string, type: ErrorType, httpStatus: int}
```

### ErrorCodeTrait API

```
trait ErrorCodeTrait

getCode(): string            // returns $this->value
getMessage(): string         // returns getDetails()['message']
getDescription(): string     // returns getDetails()['description']
getType(): ErrorType         // returns getDetails()['type']
getHttpStatus(): int         // returns getDetails()['httpStatus']
```

### ErrorEntry API

```
final readonly class ErrorEntry

__construct(
    public string    $code,
    public string    $message,
    public string    $description,
    public ErrorType $type,
    public int       $httpStatus,
    public ?string   $context = null,
    public array<string, mixed> $params = [],
)

toArray(): array{code, message, description, type, httpStatus, context, params}
```

### ErrorBag API

```
final class ErrorBag

add(ErrorCodeInterface $error, ?string $context = null, array $params = []): self
addEntry(ErrorEntry $entry): self
hasErrors(): bool
hasError(ErrorCodeInterface $error): bool
all(): list<ErrorEntry>
first(): ?ErrorEntry
forContext(string $context): list<ErrorEntry>
ofType(ErrorType $type): list<ErrorEntry>
count(): int
merge(self $other): self
clear(): self
getHttpStatus(): int
codes(): list<string>
toArray(): list<array{code, message, description, type, httpStatus, context, params}>
```

### ErrorType API

```
enum ErrorType: string

VALIDATION     = 'validation'
AUTHENTICATION = 'authentication'
AUTHORIZATION  = 'authorization'
NOT_FOUND      = 'not_found'
CONFLICT       = 'conflict'
RATE_LIMIT     = 'rate_limit'
TIMEOUT        = 'timeout'
UNAVAILABLE    = 'unavailable'
SERVER         = 'server'
```

### HttpStatus API

```
enum HttpStatus: int
```

| Case | Value |
|------|-------|
| `OK` | 200 |
| `CREATED` | 201 |
| `ACCEPTED` | 202 |
| `NO_CONTENT` | 204 |
| `MOVED_PERMANENTLY` | 301 |
| `FOUND` | 302 |
| `NOT_MODIFIED` | 304 |
| `TEMPORARY_REDIRECT` | 307 |
| `PERMANENT_REDIRECT` | 308 |
| `BAD_REQUEST` | 400 |
| `UNAUTHORIZED` | 401 |
| `FORBIDDEN` | 403 |
| `NOT_FOUND` | 404 |
| `METHOD_NOT_ALLOWED` | 405 |
| `CONFLICT` | 409 |
| `GONE` | 410 |
| `PAYLOAD_TOO_LARGE` | 413 |
| `UNSUPPORTED_MEDIA` | 415 |
| `UNPROCESSABLE_ENTITY` | 422 |
| `TOO_MANY_REQUESTS` | 429 |
| `INTERNAL_SERVER_ERROR` | 500 |
| `BAD_GATEWAY` | 502 |
| `SERVICE_UNAVAILABLE` | 503 |
| `GATEWAY_TIMEOUT` | 504 |

---

## License

MIT
