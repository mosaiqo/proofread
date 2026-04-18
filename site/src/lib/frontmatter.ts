/**
 * Minimal browser-safe YAML frontmatter parser.
 *
 * Supports the subset used by our docs markdown files:
 *   ---
 *   key: value
 *   quoted: "value"
 *   number: 42
 *   flag: true
 *   ---
 *
 * No nested objects, no arrays, no multi-line values. Keys without
 * values are treated as empty strings. Unrecognized lines are
 * skipped with a console warning (caller gets a partial data map
 * rather than a thrown error).
 */

const FRONT_RE = /^---\r?\n([\s\S]*?)\r?\n---\r?\n?/

export interface ParsedFrontmatter<T> {
  data: T
  content: string
}

export function parseFrontmatter<T extends Record<string, unknown>>(
  source: string,
): ParsedFrontmatter<T> {
  const match = FRONT_RE.exec(source)
  if (!match) {
    return { data: {} as T, content: source }
  }

  const block = match[1]
  const content = source.slice(match[0].length)
  const data: Record<string, unknown> = {}

  for (const rawLine of block.split(/\r?\n/)) {
    const line = rawLine.trimEnd()
    if (line.trim() === '' || line.trim().startsWith('#')) continue

    const sep = line.indexOf(':')
    if (sep === -1) continue

    const key = line.slice(0, sep).trim()
    if (!key) continue

    let raw = line.slice(sep + 1).trim()

    if (raw === '') {
      data[key] = ''
      continue
    }

    // Strip surrounding quotes (single or double).
    if (
      (raw.startsWith('"') && raw.endsWith('"') && raw.length >= 2) ||
      (raw.startsWith("'") && raw.endsWith("'") && raw.length >= 2)
    ) {
      raw = raw.slice(1, -1)
    } else if (raw === 'true') {
      data[key] = true
      continue
    } else if (raw === 'false') {
      data[key] = false
      continue
    } else if (raw === 'null' || raw === '~') {
      data[key] = null
      continue
    } else if (/^-?\d+(\.\d+)?$/.test(raw)) {
      data[key] = Number(raw)
      continue
    }

    data[key] = raw
  }

  return { data: data as T, content }
}
