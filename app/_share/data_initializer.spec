file: data_initializer.php
purpose: CLI-only database initializer — creates and migrates the table for every registered DataClass. Idempotent.
conditions:
  - "refuses to run when PHP_SAPI is not 'cli' — answers 403 and exits 1"
  - "registers a CLI-flavoured spl_autoload that walks up from this file to the app root (DOCUMENT_ROOT is empty under CLI)"
  - "registry of DataClasses lives inline as $data_classes — currently just user\\data\\User"
  - "calls _share\\db::create_and_update_table for each entry, printing 'ok' or 'FAILED' per line"
  - "collects failures, prints a summary, and exits 1 when failure count > 0; otherwise exits 0"
