#!/usr/bin/env python3
"""
EP->BP Case 4: Replace 'Password' with 'Senha'.

'Password' is an English loanword commonly used in European Portuguese UI.
Brazilian Portuguese standardly uses 'Senha' instead.

PHP language keys are ASCII-only (e.g. 'adm_password'), so replacing
'Password' (capital P) and 'password' (lower p) in values is safe --
key names use the English word without capitalisation in a different context.

Usage:
    python scripts/ep_to_bp_case4.py            # dry run (no files changed)
    python scripts/ep_to_bp_case4.py --apply    # write changes
"""
import re
import sys
from pathlib import Path

SUBSTITUTIONS = [
    (r'\bPassword\b', 'Senha'),
    (r'\bpassword\b', 'senha'),
]

PT_DIR = Path('language/pt')
EXCLUDE = {'CHANGELOG.php'}


def apply_subs(text):
    report = []
    for pattern, replacement in SUBSTITUTIONS:
        matches = re.findall(pattern, text)
        if matches:
            report.append(f'    {matches[0]!r:12} -> {replacement!r}  ({len(matches)}x)')
            text = re.sub(pattern, replacement, text)
    return text, report


def main():
    apply = '--apply' in sys.argv
    any_changes = False

    for path in sorted(p for p in PT_DIR.glob('*.php') if p.name not in EXCLUDE):
        original = path.read_text(encoding='utf-8')
        modified, report = apply_subs(original)

        if report:
            any_changes = True
            status = '(written)' if apply else '(dry run)'
            print(f'\n{path.name}  {status}')
            for line in report:
                print(line)
            if apply:
                path.write_text(modified, encoding='utf-8')
        else:
            print(f'{path.name}: no changes')

    print()
    if any_changes and not apply:
        print('No files were changed. Pass --apply to write.')
    elif not any_changes:
        print('No changes needed across all files.')


if __name__ == '__main__':
    main()
