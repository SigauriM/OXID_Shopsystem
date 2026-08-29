# OXID Shipping Engine — stage 1 core

Framework-free PHP library that turns a cart of order lines into measured, billable pieces
for German parcel and freight shipping. Stage 1 builds the calculation core: input
validation, canonical dimensions, volumetric weight, billable weight, and fail-closed zone
resolution. Prices, shipment grouping and the OXID module wiring come later and are
deliberately absent.

The engine has no I/O: no database, no HTTP, no filesystem, no globals. Everything enters
through `QuoteRequest` and leaves through `QuoteResult`.

## Status

Pre-release. There is no public API stability yet: result shapes still change between steps.
In particular `Quote::$pieces` is pipeline state on the way to shipment grouping, not a
contract for consumers.

## Requirements

- PHP 8.2 or newer.
- 64-bit PHP. `QuoteEngine` refuses to construct on `PHP_INT_SIZE !== 8` with a
  `RuntimeException`, because the volumetric formula relies on 64-bit integer range.
- Composer.

`composer.json` pins `config.platform.php` to `8.2.0`, so dependency resolution stays valid
for the lowest supported runtime even when the local PHP is newer. PHPStan analyses against
the same version (`phpVersion: 80200` in `phpstan.neon`).

## Install and run

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

On Windows use `vendor\bin\phpunit` and `vendor\bin\phpstan analyse`. PHPStan runs at
level 6 over `src` and `tests`. Both commands must be green before a commit.

## Units

Every quantity in the pipeline is an integer. There is no floating point arithmetic
anywhere, so results are exact and reproducible.

| Quantity | Unit |
|---|---|
| Lengths | millimetres |
| Weights | grams |
| Money | cents (not used in stage 1) |
| DIM factor | cm³/kg, exactly as printed in the carrier tariff (for example 5000 or 6000) |

The DIM factor is the one value that historically gets corrupted by unit confusion, so it
is a type rather than a number: `Domain\VolumetricDivisor::fromDimFactorCmKg()` is the only
way in, it validates the range once, and the scaling to mm³ per gram lives behind
`mmG()`. `VolumetricWeight::grams()` takes a `VolumetricDivisor`, never an `int`, so a
pre-scaled divisor such as `5_000_000` cannot reach the formula at all — the signature
rejects it before any test does.

## Layers and dependency rule

| Namespace | Responsibility | May depend on |
|---|---|---|
| `Domain` | Units and vocabulary: `VolumetricDivisor`, `AddressShape`, `PieceOutcome` (`Shippable`, `Rejected`), `RejectReason`, `ZoneLookup` (`KnownZone`, `UnknownZone`) | `ShippingClass` only |
| `Input` | The request as received: `QuoteRequest`, `OrderLine`, `TariffConfig` | `Domain` |
| `Validation` | Shop policy — what input is accepted: `InputValidator`, `InputLimits`, `ValidationError`, `ValidationErrorCode` | `Input`, `Domain` |
| `Measurement` | Geometry and weights: `Dimensions`, `VolumetricWeight`, `MeasuredPiece`, `PieceFactory` | `Domain`, and `Input\OrderLine` only |
| `Result` | What leaves the engine: `QuoteResult`, `Quote`, `ValidationFailed`, `InputSnapshot` | `Measurement`, `Validation`, `Domain` |
| `Zone` | Resolve a normalised address against the directory: `ZoneResolver` | `Domain` |
| root | `QuoteEngine` (entry point), `ShippingClass` | all of the above |

`Domain` never depends on `Input` or `Measurement`; that is what keeps the units layer
reusable and cycle-free. `Measurement` does not know about `QuoteRequest` either:
`PieceFactory::expand()` takes `list<OrderLine>` and a `VolumetricDivisor`, so measurement
can be exercised without constructing a whole request.

## Pipeline

1. `QuoteEngine::quote()` validates the request. If anything is wrong it returns
   `ValidationFailed` and **nothing is measured**.
2. Country and postal code are normalised once (`AddressShape`); the same values go into
   the snapshot and the zone resolver. The resolver does not normalise.
3. Valid lines are expanded into pieces: one `MeasuredPiece` per unit of `quantity`.
4. Per piece: sides are sorted descending into `Dimensions` (canonical order, so girth and
   later class rules do not depend on how the shop stored length and width), volumetric
   grams are computed with integer ceiling division, and
   `billableGrams = max(actualGrams, volumetricGrams)`.
5. The normalised address is resolved against the zone directory: exact match, no default.
   An unserved country, unknown index or forbidden zone becomes a per-piece rejection.
6. A `Quote` is returned with the pieces, the address `destination`, rejections, a
   normalised `InputSnapshot` and the tariff `configVersion`.

The order is load-bearing. `Measurement` assumes validated input, so `Dimensions::canonical()`
and `MeasuredPiece::from()` throw `InvalidArgumentException` on impossible values instead of
producing a plausible number. Reaching those exceptions means a caller skipped validation —
a programming error, not user input.

Volumetric weight is `ceil(length × width × height in mm³ ÷ DIM factor)`, evaluated with
integer arithmetic only. The three weights of a piece cannot be set from outside: they are
derived in `MeasuredPiece::from()`, which makes a lying `billableGrams` unrepresentable.

## Hard limits

Business policy — violations are reported as validation errors:

| Limit | Value | Constant |
|---|---|---|
| Longest side | 15 000 mm | `Validation\InputLimits::MAX_SIDE_MM` |
| Weight per piece | 1 000 000 g | `Validation\InputLimits::MAX_WEIGHT_G` |
| Quantity per line | 999 | `Validation\InputLimits::MAX_QUANTITY` |
| Lines per cart | 100 | `Validation\InputLimits::MAX_LINES` |
| Postal code shape | 2 to 10 characters; first must be a letter or digit, the rest may also be spaces or hyphens | `Domain\AddressShape::POSTAL_CODE_PATTERN` |
| Country shape | two letters A–Z after normalisation | `Domain\AddressShape::COUNTRY_PATTERN` |

Arithmetic safety — violations are exceptions, not user-facing errors:

| Limit | Value | Where |
|---|---|---|
| DIM factor range | 1000 to 10 000 cm³/kg | `Domain\VolumetricDivisor::fromDimFactorCmKg()` |
| Side in the volume formula | 200 000 mm | `Measurement\VolumetricWeight` (private constant) |
| Platform | 64-bit PHP only | `QuoteEngine::__construct()` |

The split matters: 15 000 mm means "we do not ship that", 200 000 mm means "beyond this the
integer product is no longer safe". They are different failures with different audiences and
must not be merged.

## Validation behaviour

- Errors are collected, not fail-fast: one request yields every error it has, in a single
  `ValidationFailed`.
- Field paths are stable and indexed — `lines.0.weightGrams`, `postalCode`, `country` — so a
  caller can attach messages to form fields.
- An oversized cart reports `cart_too_many_lines` first and then validates only lines
  `0` to `99`. Error paths therefore stay deterministic and bounded regardless of cart size.
- A malformed country or postal code is broken input, never a "we do not deliver there"
  decision. `CH` passes the shape gate; the destination is then refused as `country_not_served`.
  `DEU`, `GERMANY`, `D` and `DE1` are `country_invalid`.
- Codes live in the `ValidationErrorCode` enum with stable backing strings
  (`cart_empty`, `dimension_too_long`, …), safe to use in a UI or on the wire.
- `AddressShape` trims the postal code and trims plus upper-cases the country. It does not
  make a value valid: `deu` becomes `DEU` and still fails the alpha-2 check.

## Scope of stage 1

Included: input validation, canonical dimensions, Gurtmaß (`Dimensions::girthMm()`),
volumetric weight, billable weight, the normalised input snapshot, fail-closed zone
resolution (exact match, no default), and the `ShippingClass` vocabulary with its monotonic
`atLeast()` escalation.

Not included, by decision rather than by omission: prices and totals, grouping pieces into
shipments, surcharges, carrier APIs, persistence, HTTP and the OXID module integration.
The `Domain` outcome types (`Shippable`, `Rejected`) exist as vocabulary for classification
and are not yet used by `QuoteEngine` for shipping class. Zone resolution is in the current
step.

## Tests

The suite is behavioural, not coverage-driven. It pins the properties that are easy to break
silently: the integer ceiling boundaries of volumetric weight, `billableGrams` at
`actualGrams` just below, equal to and just above the volumetric value, canonical side order,
indexed and bounded error paths, the DIM factor range, and the 64-bit environment assumption.

`tests/` mirrors `src/` and shares the `OxidShipping\Engine\Tests` namespace via
`autoload-dev`. `tests/bootstrap.php` is the PHPUnit bootstrap.

## License

Proprietary. See `composer.json`.
