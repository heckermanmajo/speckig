# 0003 — Action "neue Idea anlegen"

## Goal
Aus dem UI laesst sich eine neue Idea-Datei anlegen: User gibt einen
Titel/Slug ein, das System legt `pm/ideas/<slug>.md` mit Template-Inhalt
an und oeffnet sie direkt im Edit-Mode.

## Notes
- Template-Form liegt in `pm/how-to/ideas.md` — wenn das How-to die
  Form aendert, soll die Action mitziehen. Quelle der Wahrheit ist das
  How-to, nicht eine Kopie im Code.
- Slug muss kollisionssicher sein: existiert die Datei schon, eindeutig
  fehlschlagen (kein stilles Ueberschreiben).
- Slug-Whitelist konservativ: `[a-z0-9-]+`, kein `..`, keine
  Punkte/Slashes — das Save-Endpoint aus 0001 prueft am Ende
  ebenfalls, aber hier nicht erst durchwinken.
- Keine globalen Nummern — Ideas haben keine Nummerierung (siehe
  ideas.md). Reports/Decisions sind separate Tickets.
- Action-Einstieg gehoert in die UI dort, wo man heute Ideas listet
  (Plan-Sidebar Info-Sektion).

## Done when
- In der Info-Sektion fuer Ideas erscheint ein "Neue Idea"-Button.
- Klick → Inline-Form mit Slug-Eingabe + Submit.
- Submit auf freien Slug → Datei `pm/ideas/<slug>.md` existiert mit
  Template-Inhalt aus dem `pm/how-to/ideas.md`-Beispiel; UI navigiert
  zur neuen Idea im Edit-Mode.
- Submit auf bereits existierenden Slug → klare Fehlermeldung, keine
  Datei veraendert.
- Submit auf ungueltigen Slug → Fehlermeldung, keine Datei angelegt.

## Out of scope
- Bulk-Anlage / Import.
- Tags / Metadaten in einem Frontmatter.
- Loeschung einer Idea.

See: pm/how-to/ideas.md
