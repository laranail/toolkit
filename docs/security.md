# Security helpers

Security-focused validation and credential helpers. This page documents the
`RejectCommonPasswords` validation rule today; the `Token`, `Password`, and
`Passphrase` helpers are documented in a later batch.

## `RejectCommonPasswords`

`Simtabi\Laranail\Toolkit\Rules\RejectCommonPasswords` is a Laravel
[`ValidationRule`](https://laravel.com/docs/validation#using-rule-objects) that
screens passwords against weak-credential signals. It follows NIST SP 800-63B
and OWASP ASVS guidance: screen new passwords against known-common and
known-breached values, and prefer length over composition rules.

By default the rule is **fully offline** and case-insensitive — it only checks
the bundled common-password denylist, exactly as before. Every additional gate
is opt-in.

```php
use Simtabi\Laranail\Toolkit\Rules\RejectCommonPasswords;

$request->validate([
    'password' => ['required', 'string', new RejectCommonPasswords()],
]);
```

### Common-password denylist

The rule ships a denylist of the most common passwords at
`resources/data/security/common-passwords.php` — a `list<string>` derived from
the public SecLists `xato-net-10-million-passwords` corpus (HIBP-aligned by
prevalence), augmented with a few historically common app credentials. Every
entry is **lowercased and the whole list is deduplicated**; comparison is
case-insensitive (`Str::lower`) and ignores surrounding whitespace.

The list is **static-cached** — loaded once per process (`require` on first use,
then reused) so repeated validations don't re-read the file.

### Optional gates

The constructor takes opt-in flags; all default to the original behaviour:

```php
new RejectCommonPasswords(
    minLength:  12,      // 0 = off  — reject shorter than N characters
    minEntropy: 60,      // 0 = off  — reject below N bits of estimated entropy
    checkHibp:  false,   //          — opt into the HIBP breach check (see below)
    hibpApiKey: null,    //          — optional HIBP API key for the range request
);
```

Or via the fluent factory:

```php
$rule = RejectCommonPasswords::config()
    ->minLength(12)
    ->minEntropy(60)
    ->withHibp($apiKey) // pass null/omit to skip the key — the range API needs none
    ->rule();
```

**Entropy estimate.** The entropy gate uses a Shannon-style estimate:

```
bits = length * log2(charsetPoolSize)
```

where the pool size is the sum of the character-class pools that actually appear
in the password — lowercase (26), uppercase (26), digits (10), and everything
else / symbols (33). A 12-char password mixing all four classes scores
`12 * log2(95) ≈ 78.8` bits; eight lowercase letters score
`8 * log2(26) ≈ 37.6` bits.

### HIBP k-anonymity breach check (opt-in)

With `checkHibp: true`, the rule queries the
[Have I Been Pwned Pwned Passwords range API](https://haveibeenpwned.com/API/v3#PwnedPasswords)
using **k-anonymity**:

1. SHA-1 the password **locally**.
2. Send **only the first five hex characters** of the hash to
   `https://api.pwnedpasswords.com/range/{prefix}`.
3. The API returns all hash suffixes sharing that prefix with breach counts; the
   rule matches the remaining 35-char suffix **locally**.

The plaintext password is **never transmitted** — only the 5-char SHA-1 prefix
leaves the process. An `Add-Padding: true` header is sent so responses are a
uniform size regardless of matches.

**Fail-open by design.** The breach check **never blocks a sign-up on API
trouble**: any non-200 response (429 rate limit, 5xx), timeout, or transport
error is treated as "not breached" and the password is accepted. The request
uses a short 3-second timeout. This keeps registration available even when HIBP
is down or unreachable.

```php
$rule = new RejectCommonPasswords(checkHibp: true);
// Offline / API down -> password is accepted (fail open).
// API reachable + suffix matches a breached hash -> password is rejected.
```

[← Docs index](../README.md#documentation)
