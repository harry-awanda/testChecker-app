<?php
// Inspect indexed bytes rather than working-tree files; never print matching secrets.
exec(in_array('--all', $argv, true) ? 'git ls-files' : 'git diff --cached --name-only --diff-filter=ACMR', $files, $status);
if ($status !== 0) exit(1);
$failed = [];
foreach ($files as $file) {
    if (preg_match('~(^|/)(\.env(?:\..*)?|auth\.json)$~', $file) && !in_array($file, ['.env.example', '.env-example'], true)) $failed[] = $file;
    if (preg_match('~\.(?:sqlite|sql|bak|pem|key|jpe?g|png|webp|pdf|xlsx|csv)$~i', $file)) $failed[] = $file;
    $content = shell_exec('git show '.escapeshellarg(':'.$file));
    if (!is_string($content)) continue;
    foreach ([
        '~AIza[0-9A-Za-z_-]{35}~',
        '~gh[pousr]_[A-Za-z0-9]{30,}~',
        '~github_pat_[A-Za-z0-9_]{30,}~',
        '~sk-(?:proj-|ant-)?[A-Za-z0-9_-]{25,}~',
        '~-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----~',
        '~APP_KEY=base64:[A-Za-z0-9+/]{40,}={0,2}~',
        '~https://[^/\s:@]+:[^/\s@]+@github\.com~',
    ] as $pattern) if (preg_match($pattern, $content)) $failed[] = $file;
}
if ($failed) {
    fwrite(STDERR, "Potential sensitive content or data files blocked:\n".implode("\n", array_unique($failed))."\n");
    exit(1);
}
echo "Staged secret and data-file checks passed (".count($files)." files).\n";
