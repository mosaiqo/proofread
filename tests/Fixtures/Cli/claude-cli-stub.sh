#!/usr/bin/env bash
# Fake Claude Code CLI stub that emits canned JSON matching --output-format json.
# Args are ignored.
cat <<'JSON'
{
  "type": "result",
  "subtype": "success",
  "is_error": false,
  "duration_ms": 1234,
  "duration_api_ms": 1100,
  "num_turns": 1,
  "result": "Hello from the fake Claude CLI stub",
  "session_id": "test-session-abc",
  "model": "claude-sonnet-4-6",
  "total_cost_usd": 0.0042,
  "usage": {
    "input_tokens": 50,
    "cache_creation_input_tokens": 0,
    "cache_read_input_tokens": 0,
    "output_tokens": 100
  }
}
JSON
