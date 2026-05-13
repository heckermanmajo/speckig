file: ActionInputError.php
purpose: UserError subclass — input to an Action could not be bound to its execute() signature.
conditions:
  - "thrown by _share\\Action::from_array when invokeArgs hits TypeError or ArgumentCountError"
  - "thrown by _share\\Action::bind_input_to_parameters on missing required parameter without default or null-allowance"
  - "thrown by _share\\Action::bind_input_to_parameters on unknown input fields not declared as parameters"
