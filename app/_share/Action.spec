file: Action.php
purpose: Abstract base for API actions — bind input to a static execute() and dump JSON for HTTP entry-points.
functions:
  - name: from_array
    does: Bind input array to execute() parameters and invoke it, returning the resulting instance.
    conditions:
      - "throws ActionInputError on missing required field, unknown field, or TypeError/ArgumentCountError from invoke"
      - "throws LogicException if execute() does not return an instance of static"
  - name: try_from_array
    does: Same as from_array, but catches any Throwable and returns it instead of throwing.
  - name: execute_as_request_and_dump_json_and_exit
    does: Run the action against $_REQUEST and exit with a JSON envelope of result or error.
    conditions:
      - "never returns — always exits"
      - "includes trace, input, session and logs only when app::is_debug() is true"
  - name: get_execute_method
    does: Reflect the static execute() method and validate its signature.
    conditions:
      - "throws LogicException if execute() is missing, not public static, has no return type, or does not return static/self/<class>"
  - name: bind_input_to_parameters
    does: Map an input array onto an execute() ReflectionMethod's parameters, filling defaults and nulls.
    conditions:
      - "throws ActionInputError on missing required parameter without default or null-allowance"
      - "throws ActionInputError on input fields not declared as parameters"
