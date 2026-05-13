# code_style

Style-Details zu [[decisions/0002-php-infra]]. Beispiele sind aus dem
einkopierten Code in `app/_share/` — der ist die Referenz.

## Header jeder neuen PHP-Datei

```php
<?php

declare(strict_types=1);

namespace _share;
```

`strict_types` ist Pflicht für neue Files. 4 Spaces Indent, keine Tabs.

## BSD-Klammern

`{` und `}` stehen auf eigener Zeile, sowohl bei Funktionen als auch bei
`if`/`foreach`/`try`. Aus `app/_share/db.php`:

```php
foreach ($properties as $prop)
{
    $name = $prop->getName();
    ...
}
```

Einzeiler ohne Block (`if (...) return;`) sind erlaubt, aber sparsam.

## `$what_cond_means`-Pattern

Bedingungen werden zuerst als benannte Variable berechnet, dann verzweigt.
Die Variable erklärt, was die Bedingung *bedeutet*. Aus `app/api.php`
(CSRF-Check):

```php
$csrf_token_is_valid =
    $submitted_csrf_token !== ""
    && $session_csrf_token !== ""
    && hash_equals($session_csrf_token, $submitted_csrf_token);

if (!$csrf_token_is_valid)
{
    app::error_log("api.php csrf mismatch for action: " . $raw_action_name);
    exit(json_encode([
        "err" => true,
        "message" => "csrf token invalid",
    ]));
}
```

Nicht `if ($a !== "" && $b !== "" && hash_equals(...))` — das liest sich
nicht. Der Name trägt die Bedeutung.

## Logging via `error_log()`

Kein eigener Logger, keine Library. Aus `app/api.php`:

```php
app::error_log("api.php rejected malformed action name: " . $raw_action_name);
```

`app::error_log()` ist nur ein dünner Wrapper; landet in PHPs
`error_log`. Wenn das mal nicht reicht, wird das eine eigene Decision.

## Naming

- Klassen: `PascalCase` — `User`, `Action`, `TenantUserAssociation`.
- Funktionen / Methoden: `camelCase` — `bindInputToParameters`,
  `tryFromArray`.
- Variablen: `snake_case` — `$raw_action_name`, `$user_is_admin`,
  `$logged_in_user_or_null`.
- Sprechende Namen vor kurzen: `$logged_in_user_or_null` schlägt `$u`.

`app::error_log` ist `snake_case` als Methode — Altbestand. Neue Methoden
sind `camelCase`.

## Konservatives PHP

Die UI ist klassisch server-rendered PHP:

- **Keine SPA.** Jede Seite ist eine `.php`-Datei, die HTML ausgibt.
  JS nur dort, wo es ohne nicht geht (Form-Submit gegen `/api.php`).
- **Kaum CSS.** Eine geteilte Stylesheet-Route, sonst Default-Browser-
  Styling. Bevor du CSS schreibst, frag dich, ob das nicht ein
  semantisches HTML5-Element schon kann.
- **Semantisches HTML5.** `<nav>`, `<main>`, `<dialog>`, `<form>`,
  `<label>` — siehe `app/_share/html/document.php` und `app/index.php`.

Warum: weniger bewegte Teile, lesbarer für jeden, der HTML/PHP kann,
funktioniert ohne JS-Buildchain. Das Projekt ist klein und soll klein
bleiben.

## Autoloader

Referenz ist `app/_share/init.php`:

```php
spl_autoload_register(
    function ($class)
    {
        require $_SERVER["DOCUMENT_ROOT"] . "/" . str_replace('\\', '/', $class) . '.php';
    }
);
```

Namespace = Pfad unter `app/`. `user\data\User` lebt in
`app/user/data/User.php`. Kein Composer, kein PSR-4-Tooling. Für CLI
gibt es eine eigene Autoloader-Variante in
`app/_share/data_initializer.php`, weil `$_SERVER["DOCUMENT_ROOT"]` im
CLI leer ist.

## See also

- [[decisions/0002-php-infra]] — die Decision, die das hier ausschreibt.
- [[process]] — wo Tickets und Commits hingehören.
