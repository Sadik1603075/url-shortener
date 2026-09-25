# ADR-0001: Base62 short codes that are short *and* non-enumerable

- **Status:** Proposed (confirm at roadmap task D2-T1)
- **Deciders:** Staff engineer (repo owner)
- **Context date:** Phase 1, Day 2

## Context

This is a **private** URL shortener. Short codes are the public surface of every link. Two properties matter:

1. **Short** — codes should be compact. Base62 (`0-9A-Za-z`) packs the most identifiers per character while staying URL-safe and case-legible.
2. **Non-enumerable** — because the service is private, an attacker must not be able to walk `aaaa, aaab, …` and discover other users' links. Sequential/guessable codes leak existence and volume of links and defeat the "private" guarantee.

The current `ShortCodeGenerator` produces a **random** 7-char base62 string and retries on collision via a DB lookup. That is non-enumerable (good) but does a read per attempt and has no hard uniqueness guarantee at scale (birthday collisions grow with table size).

## Options

**A. Random + collision-check (current).**
- ➕ Non-enumerable, simple.
- ➖ DB round-trip per generation; collision probability rises with scale; no monotonic guarantee.

**B. Pure counter → base62 (classic bit.ly-style).**
- ➕ Guaranteed unique, zero collision, no lookup, shortest possible.
- ➖ **Enumerable** — `1,2,3 → b,c,d`. Unacceptable for a private service.

**C. Counter → keyed reversible obfuscation → base62 (recommended).**
- A monotonic counter (DB sequence / dedicated table) guarantees uniqueness.
- Pass the integer through a **keyed reversible permutation** before encoding: either a small **Feistel network** over the id space, or a modular `id * ODD_MULTIPLIER mod 2^k` with a secret additive offset. The output is uniformly scattered → **non-enumerable**, yet still bijective → **collision-free** and **no DB lookup** at generation.
- ➕ Unique *and* non-enumerable *and* lookup-free. ➖ Slightly more code; the key must be treated as a secret and never rotated without a migration plan.

## Decision

Adopt **Option C**. Implement behind the existing `ShortCodeGenerator` seam so no caller changes:

```
generate():
  id     = nextCounter()                 # monotonic, unique
  scrambled = obfuscate(id, SECRET_KEY)  # keyed, reversible, uniform
  code   = Base62.encode(scrambled)      # pad to MIN_LENGTH
  return code
```

- `Support/Base62` — pure encode/decode, unit-tested against known vectors and round-trip properties.
- Obfuscation key comes from config (`SHORTCODE_KEY`), documented in `*.env.example`, never in git.
- Keep a minimum length (e.g. 7) via left-padding so early ids aren't 1–2 chars.

**Fallback:** if the user prefers to ship Option A for MVP, keep it — it satisfies non-enumerability. But target Option C for correctness at scale. Confirm at D2-T1.

## Consequences

- Uniqueness no longer depends on a retry loop → removes a DB read from the create path.
- The obfuscation key becomes a **security-critical secret** (leak ⇒ codes become enumerable). Store in secrets manager (Phase 2), env locally.
- `decode` is available for internal tooling/debugging but is **never** exposed via API.
- Analytics and cache are unaffected (they key on the final code string).
