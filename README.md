# Reference Contract

Framework-neutral PHP values for durable cross-package references and authorized resolution.

The serialized `sifrious.cross-package-reference` version 1 representation is preserved from Funes. A reference identifies an object; it is never proof that its holder may resolve that object.

## Verification

```sh
composer test
```

The command runs the v1 nested-provenance/JSON queue round trip and explicit available, unavailable, tombstoned, superseded, unauthorized, and incomplete batch-resolution fixtures.
