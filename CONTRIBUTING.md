# Contributing to Luminor

First off, thank you for considering contributing to Luminor! It's people like you that make Luminor such a great framework for building domain-driven applications.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Workflow](#development-workflow)
- [Style Guidelines](#style-guidelines)
- [Commit Messages](#commit-messages)
- [Pull Request Process](#pull-request-process)

## Code of Conduct

This project and everyone participating in it is governed by our commitment to providing a welcoming and inclusive environment. By participating, you are expected to uphold this standard. Please be respectful and constructive in all interactions.

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Git

### Setting Up Your Development Environment

1. **Fork the repository** on GitHub

2. **Clone your fork** locally:

   ```bash
   git clone https://github.com/YOUR_USERNAME/luminor.git
   cd luminor
   ```

3. **Add the upstream remote**:

   ```bash
   git remote add upstream https://github.com/luminor/ddd-framework.git
   ```

4. **Install dependencies**:

   ```bash
   composer install
   ```

5. **Run tests** to make sure everything is working:
   ```bash
   composer test
   ```

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When creating a bug report, include as many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples** (code snippets, configuration files)
- **Describe the behavior you observed and what you expected**
- **Include your PHP version and OS**

### Suggesting Features

Feature suggestions are welcome! Please provide:

- **A clear and descriptive title**
- **Detailed description of the proposed feature**
- **Explain why this feature would be useful**
- **Examples of how the feature would be used**

### Contributing Code

1. Look for issues labeled `good first issue` or `help wanted`
2. Comment on the issue to let others know you're working on it
3. Follow the development workflow below

### Improving Documentation

Documentation improvements are always welcome! This includes:

- Fixing typos or unclear explanations
- Adding examples
- Improving API documentation
- Translating documentation

## Development Workflow

1. **Create a branch** from `main`:

   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-bug-fix
   ```

2. **Make your changes** following our style guidelines

3. **Write or update tests** as needed:

   ```bash
   composer test
   ```

4. **Run static analysis**:

   ```bash
   composer analyse
   ```

5. **Run the full check**:

   ```bash
   composer check
   ```

6. **Commit your changes** following our commit message guidelines

7. **Push to your fork**:

   ```bash
   git push origin feature/your-feature-name
   ```

8. **Open a Pull Request**

## Style Guidelines

### PHP Code Style

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
- Use strict types: `declare(strict_types=1);`
- Use meaningful variable and method names
- Keep methods focused and concise
- Add PHPDoc blocks for public methods

### Example

```php
<?php

declare(strict_types=1);

namespace Luminor\DDD\Domain;

/**
 * Represents a domain entity with identity.
 */
final class User extends Entity
{
    /**
     * Create a new user instance.
     *
     * @param UserId $id The user's unique identifier
     * @param Email $email The user's email address
     */
    public function __construct(
        private readonly UserId $id,
        private readonly Email $email,
    ) {
    }

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }
}
```

### Testing Guidelines

- Write unit tests for all new functionality
- Use descriptive test method names: `test_it_creates_user_with_valid_email`
- Follow the Arrange-Act-Assert pattern
- Use the provided testing utilities in `src/Testing/`

## Commit Messages

We follow a simplified conventional commit format:

```
type: short description

Optional longer description explaining the change in more detail.

Fixes #123
```

### Types

- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, semicolons, etc.)
- `refactor`: Code changes that neither fix bugs nor add features
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

### Examples

```
feat: add support for custom tenant resolvers

fix: prevent null pointer exception in CommandBus

docs: improve installation guide with troubleshooting section

test: add integration tests for repository pattern
```

## Pull Request Process

1. **Update documentation** if you're changing functionality

2. **Ensure all tests pass** and static analysis shows no errors

3. **Fill out the PR template** completely:

   - Describe what the PR does
   - Reference any related issues
   - List any breaking changes

4. **Request review** from maintainers

5. **Address feedback** promptly and push updates

6. **Squash commits** if requested before merging

### PR Checklist

- [ ] Tests pass locally (`composer test`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] Code follows PSR-12 style guidelines
- [ ] Documentation is updated (if applicable)
- [ ] Commit messages follow guidelines
- [ ] PR description is complete

## Questions?

Feel free to open a discussion on GitHub if you have questions about contributing. We're here to help!

---

Thank you for contributing to Luminor! 🎉
