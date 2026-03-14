#!/usr/bin/env python3
"""
EP->BP Case 5: Dropped-letter spelling corrections.

Replaces silent-consonant EP spellings with their BP equivalents.
Substitution table was built from scanner output (ep_to_bp_case5_scan.py)
and manually verified -- only confirmed EP-only words are listed here.

Excluded (valid in both dialects or technical terms):
  pacto, impacto, espectro, espectral, caracteres, conectar,
  intacto, captcha, recaptcha, script, receptor, encriptador,
  contacto/contacta (user decision)

Usage:
    python scripts/ep_to_bp_case5.py            # dry run (no files changed)
    python scripts/ep_to_bp_case5.py --apply    # write changes
"""
import re
import sys
from pathlib import Path

PT_DIR = Path('language/pt')
EXCLUDE = {'CHANGELOG.php'}

SUBSTITUTIONS = [
    # --- actividades family ---
    (r'\bactiividades\b', 'atividades'),   # double-i typo AND EP spelling, fix both
    (r'\bActividades\b',  'Atividades'),
    (r'\bactividades\b',  'atividades'),

    # --- activo family ---
    (r'\bActivos\b',  'Ativos'),
    (r'\bactivos\b',  'ativos'),
    (r'\bActivo\b',   'Ativo'),
    (r'\bactivo\b',   'ativo'),

    # --- activar family ---
    (r'\bActivar\b',  'Ativar'),
    (r'\bactivar\b',  'ativar'),

    # --- actual family ---
    (r'\bActualmente\b',  'Atualmente'),
    (r'\bactualmente\b',  'atualmente'),
    (r'\bActualizado\b',  'Atualizado'),
    (r'\bactualizado\b',  'atualizado'),
    (r'\bActualização\b', 'Atualização'),
    (r'\bactualização\b', 'atualização'),

    # --- desactivar family ---
    (r'\bDesactivar\b',   'Desativar'),
    (r'\bdesactivar\b',   'desativar'),
    (r'\bDesactivado\b',  'Desativado'),
    (r'\bdesactivado\b',  'desativado'),

    # --- inactivo ---
    (r'\bInactivo\b', 'Inativo'),
    (r'\binactivo\b', 'inativo'),

    # --- projecto family ---
    (r'\bProjectos\b', 'Projetos'),
    (r'\bprojectos\b', 'projetos'),
    (r'\bProjecto\b',  'Projeto'),
    (r'\bprojecto\b',  'projeto'),

    # --- correctamente ---
    (r'\bCorrectamente\b', 'Corretamente'),
    (r'\bcorrectamente\b', 'corretamente'),

    # --- factores ---
    (r'\bFactores\b', 'Fatores'),
    (r'\bfactores\b', 'fatores'),

    # --- trajectorias (silent c removed AND accent added) ---
    (r'\bTrajectorias\b', 'Trajetórias'),
    (r'\btrajectorias\b', 'trajetórias'),

    # --- excepto (Family 2: silent p) ---
    (r'\bExcepto\b', 'Exceto'),
    (r'\bexcepto\b', 'exceto'),

    # --- optimização family (Family 2: silent p) ---
    (r'\bOptimizações\b', 'Otimizações'),
    (r'\botimizações\b',  'otimizações'),
    (r'\bOptimização\b',  'Otimização'),
    (r'\boptimização\b',  'otimização'),

    # --- redireccionado (Family 3: double-c) ---
    (r'\bRedireccionado\b', 'Redirecionado'),
    (r'\bredireccionado\b', 'redirecionado'),
]


def apply_subs(text):
    report = []
    for pattern, replacement in SUBSTITUTIONS:
        matches = re.findall(pattern, text, flags=re.UNICODE)
        if matches:
            report.append(f'    {matches[0]!r:22} -> {replacement!r}  ({len(matches)}x)')
            text = re.sub(pattern, replacement, text, flags=re.UNICODE)
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
