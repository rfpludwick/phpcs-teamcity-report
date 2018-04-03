# Contributing
Please conform to the `phpcs.xml` standard bundled with this repository:

```bash
vendor/bin/phpcs --standard=phpcs.xml
```

You could get really crazy and self-reference (we don't do this by default):

```bash
vendor/bin/phpcs --standard=phpcs.xml --report=src/Report.php
```

Contributions welcome.
