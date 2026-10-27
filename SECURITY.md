# Security

## Scope

The web root must be `public/`. Application source, sessions, credential vault, uploads, generated images and rate-limit data must stay outside that directory. Use HTTPS and a dedicated unprivileged PHP execution account. The runtime directory must not be committed or included in source archives.

This application accepts each user's OpenAI API key. It does not contain a developer-provided fallback key. The key is encrypted with Sodium secretbox in server-side session storage. A per-operation encrypted envelope is consumed and removed by the worker; the plaintext key exists in process memory only while required. Expired orphan envelopes are removed by cleanup. Sessions expire their key after eight hours. Deleting a key prevents new operations but cannot recall an operation already running.

The vault encryption key is stored with mode 0600 in the private runtime directory. Encryption does not protect credentials against a compromised application host or its administrator. Use a trusted host and restrict/expire your OpenAI project key.

CSRF tokens, exact configured-origin checks and HttpOnly/SameSite session cookies protect state changes. Strict session IDs prevent fixation. Random job IDs are additionally checked against a server-side owner identity. Public gallery access is opt-in; publishing includes source-derived metadata and prompts. Only the owning session can hide or restore its published covers. Do not publish confidential input.

Uploads are limited to PDF, 25 MiB and ten unencrypted pages, validated with Poppler. File operations use validated identifiers and fixed paths. OpenAI calls use a fixed HTTPS host with redirects disabled. API errors do not log request bodies or credentials.

## Verification

Automated checks cover credential encryption, tampering, expiry, one-time worker transfer, per-session isolation, ownership, CSRF/origin rejection, private-file access, gallery authorization and download validation. Git history is scanned by Gitleaks. Paid model calls are outside the offline security test suite. These checks are not a penetration-test certification or a guarantee of absence of vulnerabilities.

Keep PHP, Poppler and OS packages patched. The cleanup command needs a scheduler supplied by the operator. Backups of runtime data may also contain encrypted credentials and private documents; apply the same access controls and retention policy to them.

## Reporting

Do not post API keys, uploaded PDFs, session cookies or working exploit details in public issues. Use GitHub's private vulnerability reporting when enabled, or ask the maintainer for a private reporting route without including sensitive details.
