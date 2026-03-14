#!/usr/bin/env python3
"""
EP→BP Case 1: Replace 'tu' verb forms with 'você' equivalents.

PHP language keys are always ASCII (letters, digits, underscores), so
Portuguese words with accents or specific conjugations cannot appear in
key names — only in string values. Safe to apply to the whole file.

Usage:
    python3 scripts/ep_to_bp_case1.py            # dry run (no files changed)
    python3 scripts/ep_to_bp_case1.py --apply    # write changes
"""
import re
import sys
from pathlib import Path

SUBSTITUTIONS = [
    # Capitalized first to avoid order-sensitivity issues
    (r'\bEstás\b',   'Está'),
    (r'\bestás\b',   'está'),
    (r'\bTens\b',    'Tem'),
    (r'\btens\b',    'tem'),
    (r'\bPodes\b',   'Pode'),
    (r'\bpodes\b',   'pode'),
    (r'\bPossuis\b', 'Possui'),
    (r'\bpossuis\b', 'possui'),
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
