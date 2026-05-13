file: api.php
purpose: CSRF-protected JSON dispatcher — turns an action class name plus request payload into an Action::execute_as_request_and_dump_json_and_exit call.
conditions:
  - "requires non-empty action param, else returns JSON error and exits"
  - "requires csrf_token matching $_SESSION['csrf_token'] via hash_equals — timing-safe"
  - "whitelists action name shape to namespaced class names (regex), rejecting traversal-style inputs into the autoloader"
  - "rejects when class_exists is false for the requested action"
  - "rejects when the class is not a subclass of _share\\Action"
  - "strips action and csrf_token from $_REQUEST/$_POST/$_GET before dispatch so they do not leak into Action input binding"
  - "catches every Throwable from the Action and answers with a generic 'internal error' JSON envelope"
