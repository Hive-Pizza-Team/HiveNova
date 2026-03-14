#!/usr/bin/env python3
"""
EP->BP Case 5: SCANNER for dropped-letter spelling patterns.

Finds words in language/pt/*.php that contain silent consonant clusters
typical of European Portuguese:
  - Family 1: silent 'c' before 't'  (activo, actual, accao...)
  - Family 2: silent 'p' before 't'  (optimo, adoptar...)
  - Family 3: silent 'c' before 'c'  (-ccao endings)

OUTPUT ONLY -- no files are changed. Review the results, then build
ep_to_bp_case5.py with an explicit substitution table.

Usage:
    python scripts/ep_to_bp_case5_scan.py
"""
import re
from collections import defaultdict
from pathlib import Path

PT_DIR = Path('language/pt')

# Files to exclude from all EP->BP scripts.
# CHANGELOG.php is legacy 2Moons history in mixed German/English -- not part
# of the translated language pack and should not be touched.
EXCLUDE = {'CHANGELOG.php'}

# Each family: (label, regex that matches a whole word containing the cluster)
FAMILIES = [
    (
        'Family 1 -- silent c before t  (act-, ect-, oct-)',
        r'\b[a-zA-Za-\u00ff]*[aeiouAEIOU]ct[a-zA-Za-\u00ff]*\b',
    ),
    (
        'Family 2 -- silent p before t  (opt-, apt-, ept-)',
        r'\b[a-zA-Za-\u00ff]*[aeiouAEIOU]pt[a-zA-Za-\u00ff]*\b',
    ),
    (
        'Family 3 -- double-c before ao  (-ccao, -ccoes)',
        r'\b[a-zA-Za-\u00ff]*cc[aeiouAEIOU][a-zA-Za-\u00ff]*\b',
    ),
]


def extract_words(text, pattern):
    """Return unique words (lowercased) matching pattern, with file/line context."""
    return re.findall(pattern, text, flags=re.IGNORECASE | re.UNICODE)


def main():
    # word -> list of (filename, line_number, line_snippet)
    hits = defaultdict(lambda: defaultdict(list))  # family_label -> word -> [locations]

    for path in sorted(p for p in PT_DIR.glob('*.php') if p.name not in EXCLUDE):
        lines = path.read_text(encoding='utf-8').splitlines()
        for lineno, line in enumerate(lines, 1):
            # Skip key side: only look at content after '='
            # Also skip comment lines
            stripped = line.strip()
            if stripped.startswith('//') or stripped.startswith('*') or stripped.startswith('#'):
                continue
            # For assignment lines, take only the value side
            if '=' in line:
                value_part = line[line.index('=') + 1:]
            else:
                value_part = line  # heredoc body lines have no '='

            for label, pattern in FAMILIES:
                words = extract_words(value_part, pattern)
                for word in words:
                    hits[label][word.lower()].append(f'{path.name}:{lineno}')

    # Print results
    any_hits = False
    for label, pattern in FAMILIES:
        words_found = hits[label]
        if not words_found:
            print(f'\n{label}\n  (no matches)')
            continue
        any_hits = True
        print(f'\n{label}')
        for word in sorted(words_found):
            locations = words_found[word]
            loc_str = ', '.join(locations[:3])
            if len(locations) > 3:
                loc_str += f' ... (+{len(locations) - 3} more)'
            print(f'  {word:30s}  found {len(locations):3d}x  eg: {loc_str}')

    if not any_hits:
        print('\nNo EP spelling patterns found.')
    else:
        print('\n---')
        print('Review the words above, then build ep_to_bp_case5.py with an')
        print('explicit substitution table for the ones that need changing.')


if __name__ == '__main__':
    main()
