# Tests are the behavioral specification

Executable specifications avoid prose/code drift, accepting the cost of readable test contracts.
They still require [direct implementation review](../../testing-as-spec.md).

Duplicating configuration in checks is justified only for irreversible, silent deployment failures:
loss of persistent production files, an untested PHP series, or broken release-gate and maintenance
ordering. Health checks cannot prove these guarantees; other CI wiring failures are visible and
do not justify their own checks.
