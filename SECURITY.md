# Security Policy

## Supported Versions

We release patches for security vulnerabilities for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

We take the security of Luminor seriously. If you believe you have found a security vulnerability, please report it to us as described below.

**Please do not report security vulnerabilities through public GitHub issues.**

### How to Report

1. **Email**: Send a detailed report to [security@luminor-php.org](mailto:security@luminor-php.org)
2. **Include**:
   - Type of issue (e.g., buffer overflow, SQL injection, cross-site scripting, etc.)
   - Full paths of source file(s) related to the manifestation of the issue
   - The location of the affected source code (tag/branch/commit or direct URL)
   - Any special configuration required to reproduce the issue
   - Step-by-step instructions to reproduce the issue
   - Proof-of-concept or exploit code (if possible)
   - Impact of the issue, including how an attacker might exploit it

### What to Expect

- **Acknowledgment**: We will acknowledge receipt of your vulnerability report within 48 hours.
- **Communication**: We will keep you informed of the progress towards a fix and full announcement.
- **Timeline**: We aim to resolve critical issues within 30 days of disclosure.
- **Credit**: We will credit you in the security advisory if you wish (let us know your preference).

### Safe Harbor

We consider security research conducted in accordance with this policy to be:

- Authorized concerning any applicable anti-hacking laws
- Authorized concerning any relevant anti-circumvention laws
- Exempt from restrictions in our Terms of Service that would interfere with conducting security research

We will not pursue civil action or initiate a complaint to law enforcement for accidental, good-faith violations of this policy.

## Security Best Practices

When using Luminor in your applications, we recommend:

1. **Keep dependencies updated**: Regularly run `composer update` to get security patches
2. **Use environment variables**: Never commit sensitive credentials to version control
3. **Enable HTTPS**: Always use HTTPS in production environments
4. **Validate input**: Use the built-in validation features for all user input
5. **Use prepared statements**: The database layer uses prepared statements by default - don't bypass them
6. **Implement rate limiting**: Protect your APIs from abuse
7. **Review authorization policies**: Regularly audit your access control rules

## Known Security Considerations

### Session Management

- Sessions are stored securely by default
- Configure session lifetime appropriately for your use case
- Use secure and httponly flags for session cookies in production

### Authentication

- Use strong password hashing (bcrypt is the default)
- Implement account lockout after failed attempts
- Consider implementing two-factor authentication for sensitive applications

### Multi-tenancy

- Tenant isolation is enforced at the application level
- Review tenant resolution strategies for your deployment model
- Audit cross-tenant data access regularly

## Acknowledgments

We would like to thank the following individuals for responsibly disclosing security issues:

_No reports yet - be the first to help secure Luminor!_
