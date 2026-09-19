# Legacy standalone Go example

The repository root now contains the canonical Go production application.

This directory is retained only as a smaller protocol-compatible example for the multilang matrix.

For new deployments use:

```bash
cd ../..
go build -o longurl .
```

Then proxy the complete site to the root Go service as documented in `/README.md`.
