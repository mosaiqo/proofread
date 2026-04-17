#!/usr/bin/env bash
cat <<'JSON'
{
  "type": "result",
  "subtype": "error",
  "is_error": true,
  "result": "something went wrong inside the CLI",
  "session_id": "err-session",
  "usage": {
    "input_tokens": 0,
    "output_tokens": 0
  }
}
JSON
