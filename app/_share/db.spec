file: db.php
purpose: Thin SQLite/PDO layer — table name from class short name, reflection-based schema sync, fetch/save/delete by class.
functions:
  - name: get_table_name
    does: Return the class short name as the table name.
  - name: get_by_id
    does: Load a row by id and hydrate into the given class, or null if not found.
  - name: select
    does: Prepare and run a SQL statement, fetching all rows hydrated into the given class.
  - name: select_one
    does: Prepare and run a SQL statement, returning the first hydrated row or null.
  - name: delete
    does: Delete the row of the given class by id.
  - name: create_and_update_table
    does: Create the table for a class if missing and ALTER-add columns for each public typed property.
    conditions:
      - "only supports public properties typed string, int or float — other types throw Exception"
      - "swallows ALTER TABLE exceptions so re-adding existing columns is a no-op"
  - name: save
    does: Insert if id <= 0 (assigning lastInsertId back), otherwise update by id; skips id and created_at fields.
    conditions:
      - "requires an int id property on the instance"
      - "skips properties that are not initialized on the instance"
  - name: get_db
    does: Lazy PDO singleton for the sqlite file at app.sqlite with exception error mode.
