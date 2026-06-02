# Security Policy

**PLEASE DON'T DISCLOSE SECURITY-RELATED ISSUES PUBLICLY, [SEE BELOW](#reporting-a-vulnerability).**

## Reporting a Vulnerability

If you discover a security vulnerability, please report it privately through GitHub's private vulnerability reporting:

Go to the repository's **Security** tab and click **"Report a vulnerability"**. This creates a private advisory visible only to maintainers and provides a structured workflow for triage, fix coordination, and CVE assignment.

All security vulnerabilities will be promptly addressed.

## Threat model note

This package ships a UI that **reads and writes translation files on the server's filesystem**. By design it:

- is gated by the `viewI18n` authorization gate and restricted to `i18n.enabled_environments` (default `local`);
- only ever touches files inside the configured `i18n.paths.root` (path traversal is rejected);
- writes PHP array files by emitting **escaped string literals only** — user input is never written as executable code, and the package never `eval`s translation content.

Do not expose the UI publicly without putting it behind authentication and a restrictive `viewI18n` gate.
