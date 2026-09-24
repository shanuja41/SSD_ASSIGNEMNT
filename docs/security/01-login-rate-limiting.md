# Security Finding: Missing Rate Limiting on Login (Brute-Force Protection)

| | |
|---|---|
| Project | xgenious School Management System (Laravel + Inertia + React) |
| Weakness | CWE-307 Improper Restriction of Excessive Authentication Attempts |
| OWASP | A07:2021 Identification and Authentication Failures |
| Tool used | **Semgrep Community Edition** (open source SAST, white-box) v1.177.0 |
| Status | Fixed, confirmed with Semgrep re-scan |

## 1. Vulnerability (before)

`routes/web.php:57` registered the login endpoint with no rate limiting:

```php
Route::post('/login', [LoginController::class, 'store']);
```

`LoginController::store()` only calls `Auth::attempt()`. Nothing limits failed attempts, so an attacker can
guess unlimited passwords (brute force) or try one password on many accounts (password spraying).

## 2. How it was found (Semgrep CE, white-box)

Install:
```
python -m pip install semgrep
```

Step 1 - stock rulesets:
```
semgrep scan --metrics=off --config p/php --config p/owasp-top-ten --exclude vendor --exclude node_modules app routes
```
Result: 0 findings on 114 files. Stock rules look for dangerous code that is present; they cannot see a
protection that is missing.

Step 2 - custom rule `docs/security/tools/semgrep-login-throttle.yml` (matches `Route::post('/login', ...)`
that is not chained with `->middleware('throttle:login')`):
```
semgrep scan --metrics=off --config docs/security/tools/semgrep-login-throttle.yml routes
```
Result before the fix: **1 blocking finding**
```
routes\web.php
  laravel-login-route-missing-throttle
  57┆ Route::post('/login', [LoginController::class, 'store']);
```

## 3. Fix

Three files changed; nothing else in the app was touched.

1. `app/Providers/AppServiceProvider.php` - named limiter `login`: 5 attempts/minute per email+IP, and
   30 attempts/minute per IP (against password spraying).
2. `routes/web.php` - only the login POST route:
   ```php
   Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
   ```
3. `bootstrap/app.php` - for the React login form the lockout shows as a normal error under the email field
   ("Too many login attempts. Please try again in N seconds."). Other clients get HTTP 429. No frontend change.

Why it does not affect anything else: the limiter is attached to one route, it is keyed per email+IP so one locked
account does not block other users, and the exception handler only acts on Inertia requests to `/login`.

## 4. Result after the fix

Same Semgrep command as step 2:
```
Ran 1 rule on 2 files: 0 findings.
```

## 5. Limits

- Only Semgrep was used to detect and confirm; no dynamic (black-box) scanner was run for this document.
- Limits (5 and 30 per minute) can be tuned in `AppServiceProvider`. Behind a reverse proxy, configure trusted
  proxies so client IPs are read correctly.
- Semgrep's stock rulesets (`p/php`, `p/owasp-top-ten`) come from the Semgrep registry under its own rules license;
  the rule that found this issue is the custom one in this repo.
