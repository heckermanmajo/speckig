# 0002 — code_style.md schreiben

See: pm/decisions/0002-php-infra.md
Blocked by: 0001

Schreibt die Style-Details zu Decision 0002 aus, mit Beispielen aus dem bereits einkopierten Code.

## Done when
- `pm/how-to/code_style.md` existiert.
- Enthält Beispiele für: BSD-Klammern, `$what_cond_means`-Pattern, `error_log`, `strict_types`, Naming.
- Erklärt „konservatives PHP" (keine SPA, kaum CSS, semantische HTML5).
- Verweist auf `app/_share/init.php` als Referenz-Autoloader.
- `pm/how-to/README.md` listet `code_style.md` in der Übersicht.

## Done
- `pm/how-to/code_style.md` angelegt — Header / `strict_types`, BSD-Klammern, `$what_cond_means`, `error_log`, Naming, konservatives PHP, Autoloader-Referenz.
- Beispiele direkt aus `app/api.php`, `app/_share/db.php` und `app/_share/init.php` zitiert statt erfunden.
- `pm/how-to/README.md` um `code_style.md` ergänzt (thematisch nach `commit.md`).
- `strict_types`-Lücke im Altbestand bewusst nicht erwähnt; Doku sagt "neue Files müssen das haben".
