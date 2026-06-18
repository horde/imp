## Summary

- Address PR review feedback on COUNTER accept/decline handling in IMP iTIP
- Catch `Horde_Exception` with logging on counter-decline errors
- Use `HordeString` instead of legacy `Horde_String` in DECLINECOUNTER mail body
- Use `use` import for `Variables` in the ItipRequest test stub

## Motivation

Review on [#67](https://github.com/horde/imp/pull/67) requested tighter exception handling, modern string API usage, and import style in new code.

## Changes

### `lib/Ajax/Imple/ItipRequest.php`

- `counter-decline`: catch `Horde_Exception` instead of `Exception`; log via `Horde::log($e, Horde_Log::ERR)` before user notification
- `_sendDeclineCounter()`: `HordeString::wrap()` with `use Horde\Util\HordeString`

Expected exceptions on counter-decline:

- `Horde_Exception` — missing attendee address, identity lookup, MIME assembly/send failures

### `test/Imp/Stub/ItipRequest.php`

- Add `use Horde\Util\Variables` for the `handle()` union type parameter

## Test plan

- [ ] `vendor/bin/phpunit -c phpunit.xml.dist test/Imp/Unit/Ajax/Imple/ItipRequestCounterTest.php`
- [ ] Manual: open COUNTER mail in IMP — accept/decline actions unchanged
- [ ] Regression: normal REQUEST accept/decline/tentative unchanged

## Review responses

| Comment | Resolution |
|---------|------------|
| Do not catch random `Exception` without logging | Catch `Horde_Exception`; log with `Horde::log()` before notification |
| Prefer `HordeString` over `Horde_String` | `HordeString::wrap()` in `_sendDeclineCounter()` |
| Use `use` instead of FQCN in stub | `use Horde\Util\Variables` |
