# 0005 — Spec für app/user/data/

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `app/user/data/User.spec` ← User-DataClass mit Feldern (username, password, email, email_verified, language, is_admin).
- Funktionen-Sektion bleibt leer / weg (DataClass hat keine Methoden).
- `purpose` benennt Domain-Rolle.

## Done

- `app/user/data/User.spec` angelegt: nur `file` + `purpose`, kein
  `functions:`-Block (DataClass ohne Methoden, analog zu
  `app/_share/DataClass.spec`).
- `purpose` benennt Domain-Rolle: "Authenticated platform user; admin
  flag controls privileged endpoints." — Felder bewusst nicht aufgezählt
  (Code-Aufgabe, nicht Spec-Aufgabe).
- YAML-Check via `python3 -c "import yaml; yaml.safe_load(...)"` sauber
  durch — Dict mit `file` und `purpose`.
- Filename-Konvention `User.spec` (kein `.php.spec`), gemäss Decision
  0005 und `spec.md`.
- Kein PHP editiert.
