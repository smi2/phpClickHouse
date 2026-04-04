# Security Audit Report — phpClickHouse `src/`

**Date:** 2026-04-04
**Scope:** Full scan of `src/` directory for supply-chain attacks, data exfiltration, backdoors, and credential leaks.

## Verdict: CLEAN

No backdoors, data exfiltration, or obfuscated code found.

---

## Outbound Network Calls

| Pattern | Found | Location | Status |
|---------|-------|----------|--------|
| `curl_init` / `curl_exec` | Yes | `CurlerRequest.php`, `CurlerRolling.php` | **Safe** — URL built from user-configured `$_host` + `$_port` via `getUri()`. No external hosts. |
| `file_get_contents` / `file_put_contents` to URLs | No | — | **Clean** |
| `fsockopen`, `stream_socket_client`, `socket_create` | No | — | **Clean** |
| `mail()`, `SoapClient`, `XMLReader` | No | — | **Clean** |

## DNS

| Pattern | Found | Location | Status |
|---------|-------|----------|--------|
| `gethostbynamel()` | Yes | `Cluster.php:92` | **Safe** — resolves `$this->defaultHostName` (user's ClickHouse host) for cluster node discovery. |

## File Operations (`fopen`)

| Location | Usage | Status |
|----------|-------|--------|
| `Client.php:502` | `fopen('php://temp', 'r+')` — in-memory temp stream for generators | **Safe** |
| `Http.php:560` | `fopen($writeToFile->fetchFile(), 'w')` — write query results to user-specified file | **Safe** |
| `CurlerRequest.php:214` | `fopen($file_name, 'r')` — read file for batch insert | **Safe** |

## Obfuscation & Code Execution

| Pattern | Found | Status |
|---------|-------|--------|
| `base64_decode`, `gzinflate`, `str_rot13` | No | **Clean** |
| `eval()`, `assert()` | No | **Clean** |
| `preg_replace` with `/e` modifier | No | **Clean** |

## Dynamic Function Calls

| Location | Usage | Status |
|----------|-------|--------|
| `Http.php:512` | `call_user_func_array($this->xClickHouseProgress, [$data])` | **Safe** — invokes user-set progress callback with ClickHouse progress header data only. |
| `Http.php:514` | `call_user_func($this->xClickHouseProgress, $data)` | **Safe** — same as above, non-array callable variant. |

## Debug Functions (`print_r`, `dump`)

| Location | Usage | Status |
|----------|-------|--------|
| `CurlerResponse.php:158` | `dump_json()` — prints JSON to stdout | **Safe** — user-initiated debug only |
| `CurlerResponse.php:178,180` | `dump()` — prints body + headers to stdout | **Safe** — user-initiated debug only |
| `CurlerRequest.php:320-321` | `dump()` — prints URL, params, headers to stdout | **Note:** URL may contain password in `AUTH_METHOD_QUERY_STRING` mode. Not an exfiltration risk (stdout only), but worth documenting. |
| `Conditions.php:35` | `//print_r($matches);` — commented out | **Clean** |

## Environment Variable Access

| Pattern | Found in `src/` | Status |
|---------|-----------------|--------|
| `getenv`, `$_ENV`, `$_SERVER` | No | **Clean** (used only in tests for config) |

## Shell Execution

| Pattern | Found | Status |
|---------|-------|--------|
| `exec()`, `shell_exec()`, `system()`, `passthru()`, `proc_open()`, `popen()` | No | **Clean** |
| `curl_exec`, `curl_multi_exec` | Yes | **Safe** — curl API calls, not shell execution |

---

## Summary

All network calls go exclusively to the user-configured ClickHouse server. No code sends data to external hosts. No obfuscated code, no shell execution, no environment variable harvesting. The codebase is clean for supply-chain security.
