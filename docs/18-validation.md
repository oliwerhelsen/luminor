# Validation

Lumina provides a comprehensive validation system to ensure data integrity and security. The validator supports a wide range of validation rules and custom rule creation.

## Table of Contents

- [Introduction](#introduction)
- [Basic Usage](#basic-usage)
- [Available Rules](#available-rules)
- [Custom Rules](#custom-rules)
- [Validation in Commands](#validation-in-commands)
- [Error Messages](#error-messages)
- [Best Practices](#best-practices)

## Introduction

The validation system provides:

- **Comprehensive Rules** - 30+ built-in validation rules
- **Custom Rules** - Create your own validation logic
- **Error Messages** - User-friendly error messages
- **Type Safety** - Strong typing with PHP 8.2+
- **Database Rules** - Unique and exists validation

## Basic Usage

### Creating a Validator

```php
use Lumina\Validation\Validator;

$validator = new Validator(
    [
        'email' => 'user@example.com',
        'password' => 'secret123',
        'age' => 25,
    ],
    [
        'email' => ['required', 'email'],
        'password' => ['required', 'min:8'],
        'age' => ['required', 'integer', 'min:18'],
    ]
);

if ($validator->fails()) {
    $errors = $validator->errors();
    // Handle validation errors
}
```

### Validation with Factory

```php
use Lumina\Validation\ValidatorFactory;

$factory = $container->get(ValidatorFactory::class);

$validator = $factory->make(
    $request->all(),
    [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users'],
    ]
);

if ($validator->fails()) {
    return $this->validationError($validator->errors());
}
```

### Quick Validation

```php
// Throws ValidationException if fails
$validated = Validator::validate(
    $request->all(),
    [
        'title' => ['required', 'string', 'max:200'],
        'content' => ['required', 'string'],
        'status' => ['required', 'in:draft,published'],
    ]
);
```

## Available Rules

### Required Rules

#### required

Field must be present and not empty:

```php
'email' => ['required']
```

#### required_if

Required if another field has specific value:

```php
'phone' => ['required_if:contact_method,phone']
```

#### required_with

Required if another field is present:

```php
'password_confirmation' => ['required_with:password']
```

#### nullable

Field is optional:

```php
'middle_name' => ['nullable', 'string']
```

### String Rules

#### string

Must be a string:

```php
'name' => ['required', 'string']
```

#### email

Must be a valid email address:

```php
'email' => ['required', 'email']
```

#### url

Must be a valid URL:

```php
'website' => ['nullable', 'url']
```

#### alpha

Only alphabetic characters:

```php
'name' => ['required', 'alpha']
```

#### alpha_num

Only alphanumeric characters:

```php
'username' => ['required', 'alpha_num']
```

#### alpha_dash

Only alphanumeric, dashes, and underscores:

```php
'slug' => ['required', 'alpha_dash']
```

#### regex

Must match regular expression:

```php
'phone' => ['required', 'regex:/^\+?[1-9]\d{1,14}$/']
```

### Numeric Rules

#### integer

Must be an integer:

```php
'age' => ['required', 'integer']
```

#### numeric

Must be numeric:

```php
'price' => ['required', 'numeric']
```

#### min

Minimum value (numbers) or length (strings):

```php
'age' => ['required', 'integer', 'min:18']
'password' => ['required', 'string', 'min:8']
```

#### max

Maximum value (numbers) or length (strings):

```php
'age' => ['required', 'integer', 'max:120']
'bio' => ['nullable', 'string', 'max:500']
```

#### between

Between two values or lengths:

```php
'age' => ['required', 'integer', 'between:18,65']
'username' => ['required', 'string', 'between:3,20']
```

#### size

Exact size (numbers) or length (strings):

```php
'pin' => ['required', 'string', 'size:4']
'quantity' => ['required', 'integer', 'size:1']
```

### Array Rules

#### array

Must be an array:

```php
'tags' => ['required', 'array']
```

#### in

Must be one of specified values:

```php
'role' => ['required', 'in:admin,editor,viewer']
'status' => ['required', 'in:draft,published,archived']
```

#### not_in

Must not be one of specified values:

```php
'username' => ['required', 'not_in:admin,root,system']
```

### Date Rules

#### date

Must be a valid date:

```php
'birthday' => ['required', 'date']
```

#### before

Must be before a date:

```php
'start_date' => ['required', 'date', 'before:end_date']
```

#### after

Must be after a date:

```php
'end_date' => ['required', 'date', 'after:start_date']
```

### Boolean Rules

#### boolean

Must be a boolean:

```php
'is_active' => ['required', 'boolean']
```

#### accepted

Must be yes, on, 1, or true:

```php
'terms' => ['required', 'accepted']
```

### File Rules

#### file

Must be a file:

```php
'document' => ['required', 'file']
```

#### image

Must be an image (jpeg, png, gif, bmp, svg, webp):

```php
'avatar' => ['required', 'image']
```

#### mimes

Must have specific MIME type:

```php
'document' => ['required', 'mimes:pdf,doc,docx']
```

#### max (file size)

Maximum file size in kilobytes:

```php
'avatar' => ['required', 'image', 'max:2048'] // 2MB
```

### Database Rules

#### unique

Value must be unique in database table:

```php
use Lumina\Validation\Rules\Unique;

'email' => ['required', 'email', new Unique('users', 'email')]

// Ignore specific ID (for updates)
'email' => ['required', 'email', new Unique('users', 'email', $userId)]

// With additional where clause
'email' => [
    'required',
    'email',
    (new Unique('users', 'email'))->where('active', true)
]
```

#### exists

Value must exist in database table:

```php
use Lumina\Validation\Rules\Exists;

'user_id' => ['required', 'integer', new Exists('users', 'id')]

// With additional where clause
'category_id' => [
    'required',
    'integer',
    (new Exists('categories', 'id'))->where('active', true)
]
```

### Password Rules

#### password

Advanced password validation:

```php
use Lumina\Validation\Rules\Password;

// Basic password
'password' => ['required', new Password()]

// With minimum length
'password' => ['required', Password::min(8)]

// Require letters
'password' => ['required', Password::min(8)->letters()]

// Require mixed case
'password' => ['required', Password::min(8)->mixedCase()]

// Require numbers
'password' => ['required', Password::min(8)->numbers()]

// Require symbols
'password' => ['required', Password::min(8)->symbols()]

// Combination
'password' => [
    'required',
    Password::min(8)
        ->letters()
        ->mixedCase()
        ->numbers()
        ->symbols()
]

// Prevent compromised passwords (requires API)
'password' => [
    'required',
    Password::min(8)->uncompromised()
]
```

### Comparison Rules

#### same

Must match another field:

```php
'password_confirmation' => ['required', 'same:password']
```

#### different

Must be different from another field:

```php
'new_password' => ['required', 'different:current_password']
```

#### confirmed

Must have matching confirmation field:

```php
'password' => ['required', 'confirmed'] // Looks for password_confirmation
```

## Custom Rules

### Creating Custom Rules

Extend the `Rule` class:

```php
use Lumina\Validation\Rule;

class Uppercase extends Rule
{
    public function passes(string $attribute, mixed $value): bool
    {
        return is_string($value) && $value === strtoupper($value);
    }

    public function message(): string
    {
        return 'The :attribute must be uppercase.';
    }
}
```

Usage:

```php
'code' => ['required', new Uppercase()]
```

### Custom Rule with Parameters

```php
class StartsWith extends Rule
{
    public function __construct(
        private string $prefix
    ) {}

    public function passes(string $attribute, mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, $this->prefix);
    }

    public function message(): string
    {
        return "The :attribute must start with {$this->prefix}.";
    }
}
```

Usage:

```php
'reference' => ['required', new StartsWith('REF-')]
```

### Database-Dependent Rule

```php
class UniqueEmail extends Rule
{
    public function __construct(
        private Connection $connection,
        private ?int $excludeId = null
    ) {}

    public function passes(string $attribute, mixed $value): bool
    {
        $query = 'SELECT COUNT(*) as count FROM users WHERE email = ?';
        $params = [$value];

        if ($this->excludeId) {
            $query .= ' AND id != ?';
            $params[] = $this->excludeId;
        }

        $result = $this->connection->select($query, $params);
        return $result[0]['count'] === 0;
    }

    public function message(): string
    {
        return 'The :attribute is already taken.';
    }
}
```

Usage:

```php
'email' => ['required', 'email', new UniqueEmail($connection)]

// For updates
'email' => ['required', 'email', new UniqueEmail($connection, $userId)]
```

## Validation in Commands

### Command Validation

```php
use Lumina\Application\CQRS\Command;
use Lumina\Validation\Validator;

class CreateUserCommand implements Command
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password
    ) {}

    public function validate(): array
    {
        $validator = new Validator(
            [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users'],
                'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            ]
        );

        return $validator->errors();
    }
}
```

### Command Handler with Validation

```php
class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof CreateUserCommand);

        // Validate
        $errors = $command->validate();
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Execute
        $user = new User(
            name: $command->name,
            email: $command->email,
            password: $this->hash->make($command->password)
        );

        $this->users->save($user);

        return $user;
    }
}
```

### Validation Middleware

```php
class ValidationMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $rules = $this->getRulesForRoute($request);

            if ($rules) {
                $validator = new Validator($request->all(), $rules);

                if ($validator->fails()) {
                    return new JsonResponse(
                        ['errors' => $validator->errors()],
                        422
                    );
                }
            }
        }

        return $next($request);
    }
}
```

## Error Messages

### Getting Errors

```php
$validator = new Validator($data, $rules);

if ($validator->fails()) {
    // All errors
    $errors = $validator->errors();

    // Errors for specific field
    $emailErrors = $validator->errors('email');

    // First error for field
    $firstError = $validator->errors('email')[0] ?? null;

    // Check if field has errors
    $hasErrors = isset($validator->errors()['email']);
}
```

### Error Format

```php
[
    'email' => [
        'The email field is required.',
        'The email must be a valid email address.',
    ],
    'password' => [
        'The password must be at least 8 characters.',
    ],
]
```

### Custom Error Messages

```php
$validator = new Validator(
    $data,
    [
        'email' => ['required', 'email'],
        'password' => ['required', 'min:8'],
    ],
    [
        'email.required' => 'Please provide your email address.',
        'email.email' => 'Please provide a valid email address.',
        'password.min' => 'Your password must be at least :min characters long.',
    ]
);
```

### Attribute Names

Customize attribute names in error messages:

```php
$validator = new Validator(
    $data,
    $rules,
    $messages,
    [
        'email' => 'email address',
        'password' => 'account password',
    ]
);

// Error: "The email address field is required."
// Instead of: "The email field is required."
```

## Best Practices

### 1. Validate Early

Validate input as early as possible:

```php
class CreateUserController extends ApiController
{
    public function store(Request $request): Response
    {
        // Validate first
        $validator = new Validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        // Then execute
        $user = $this->commandBus->execute(
            new CreateUserCommand(
                $request->input('name'),
                $request->input('email'),
                $request->input('password')
            )
        );

        return $this->created($user);
    }
}
```

### 2. Use Form Requests (Future Feature)

```php
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
        ];
    }
}
```

### 3. Validate Commands

```php
class UpdateProfileCommand implements Command
{
    public function validate(): array
    {
        $validator = new Validator(
            get_object_vars($this),
            $this->rules()
        );

        return $validator->errors();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url'],
        ];
    }
}
```

### 4. Group Common Rules

```php
class ValidationRules
{
    public static function email(?int $excludeUserId = null): array
    {
        return [
            'required',
            'email',
            'max:255',
            new Unique('users', 'email', $excludeUserId),
        ];
    }

    public static function password(): array
    {
        return [
            'required',
            Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols(),
        ];
    }

    public static function username(?int $excludeUserId = null): array
    {
        return [
            'required',
            'alpha_dash',
            'min:3',
            'max:20',
            new Unique('users', 'username', $excludeUserId),
        ];
    }
}
```

Usage:

```php
$validator = new Validator($data, [
    'email' => ValidationRules::email(),
    'password' => ValidationRules::password(),
    'username' => ValidationRules::username(),
]);
```

### 5. Validate Arrays

```php
'tags' => ['required', 'array'],
'tags.*' => ['required', 'string', 'max:50'],

'users' => ['required', 'array'],
'users.*.name' => ['required', 'string'],
'users.*.email' => ['required', 'email'],
```

### 6. Conditional Validation

```php
class ConditionalValidator
{
    public function validate(array $data): array
    {
        $rules = [
            'contact_method' => ['required', 'in:email,phone'],
        ];

        // Add conditional rules
        if ($data['contact_method'] === 'email') {
            $rules['email'] = ['required', 'email'];
        } else {
            $rules['phone'] = ['required', 'regex:/^\+?[1-9]\d{1,14}$/'];
        }

        $validator = new Validator($data, $rules);
        return $validator->errors();
    }
}
```

### 7. Sanitize Input

```php
class InputSanitizer
{
    public function sanitize(array $data): array
    {
        return [
            'name' => trim($data['name'] ?? ''),
            'email' => strtolower(trim($data['email'] ?? '')),
            'bio' => strip_tags($data['bio'] ?? ''),
        ];
    }
}

// Usage
$sanitized = $sanitizer->sanitize($request->all());
$validator = new Validator($sanitized, $rules);
```

### 8. Return Meaningful Errors

```php
if ($validator->fails()) {
    return new JsonResponse([
        'message' => 'Validation failed',
        'errors' => $validator->errors(),
    ], 422);
}
```

### 9. Test Validation

```php
class CreateUserCommandTest extends TestCase
{
    public function test_validates_required_fields(): void
    {
        $command = new CreateUserCommand('', '', '');
        $errors = $command->validate();

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function test_validates_email_format(): void
    {
        $command = new CreateUserCommand('John', 'invalid', 'password');
        $errors = $command->validate();

        $this->assertArrayHasKey('email', $errors);
    }

    public function test_validates_password_strength(): void
    {
        $command = new CreateUserCommand('John', 'john@example.com', 'weak');
        $errors = $command->validate();

        $this->assertArrayHasKey('password', $errors);
    }
}
```

### 10. Document Validation Rules

```php
/**
 * Create a new user.
 *
 * @param string $name User's full name (max 255 characters)
 * @param string $email Valid email address (must be unique)
 * @param string $password At least 8 characters with mixed case, numbers, and symbols
 */
class CreateUserCommand implements Command
{
    // ...
}
```

## Common Validation Scenarios

### User Registration

```php
$validator = new Validator($request->all(), [
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'max:255', new Unique('users', 'email')],
    'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
    'terms' => ['required', 'accepted'],
]);
```

### User Profile Update

```php
$validator = new Validator($request->all(), [
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', new Unique('users', 'email', $userId)],
    'bio' => ['nullable', 'string', 'max:500'],
    'website' => ['nullable', 'url'],
    'avatar' => ['nullable', 'image', 'max:2048'],
]);
```

### Blog Post Creation

```php
$validator = new Validator($request->all(), [
    'title' => ['required', 'string', 'max:200'],
    'slug' => ['required', 'alpha_dash', new Unique('posts', 'slug')],
    'content' => ['required', 'string'],
    'excerpt' => ['nullable', 'string', 'max:500'],
    'status' => ['required', 'in:draft,published,archived'],
    'category_id' => ['required', 'integer', new Exists('categories', 'id')],
    'tags' => ['nullable', 'array'],
    'tags.*' => ['required', 'string', 'max:50'],
    'published_at' => ['nullable', 'date'],
]);
```

### File Upload

```php
$validator = new Validator($request->all(), [
    'document' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
    'title' => ['required', 'string', 'max:255'],
    'description' => ['nullable', 'string', 'max:1000'],
]);
```

## See Also

- [Application Layer](04-application-layer.md) - Commands and queries
- [HTTP Layer](05-http-layer.md) - Request handling
- [Security](16-security.md) - Input sanitization
- [Database](14-database.md) - Database validation rules
