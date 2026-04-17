#!/usr/bin/env bash
# Echo all args as JSON inside the "result" field, so tests can assert
# which flags were passed to the CLI.
args_joined=""
for arg in "$@"; do
    if [ -z "$args_joined" ]; then
        args_joined="$arg"
    else
        args_joined="$args_joined|$arg"
    fi
done
printf '{"type":"result","subtype":"success","is_error":false,"result":"%s","usage":{"input_tokens":1,"output_tokens":1}}\n' "$args_joined"
