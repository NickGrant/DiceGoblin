from __future__ import annotations

from collections import defaultdict
from pathlib import Path

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
BASE_DIR = ROOT / 'raw-assets' / 'ui-sheets'


SECTION_BASE_NAMES = {
    'badges': 'badge',
    'buttons': 'button',
    'controls': 'control',
    'decor': 'decor',
    'header_pieces': 'header_piece',
    'navigation_cards': 'navigation_card',
    'nine_slice_parts': 'nine_slice_part',
    'panel_frames': 'panel_frame',
    'resource_chips': 'resource_chip',
    'slots': 'slot',
    'attachments': 'attachment',
    'backplates': 'backplate',
    'content_mats': 'content_mat',
    'damage_decals': 'damage_decal',
    'dividers': 'divider',
    'loading': 'loading',
    'micro_tags': 'micro_tag',
    'mobile_blanks': 'mobile_blank',
    'patterns': 'pattern_tile',
    'slot_interiors': 'slot_interior',
    'state_overlays': 'state_overlay',
    'textures': 'texture_tile',
    'wear_decals': 'wear_decal',
    'large_swatches': 'large_swatch',
    'large_textures': 'large_texture',
}


def orientation_label(width: int, height: int) -> str:
    if width <= 5 or height <= 5:
      return 'separator_vertical' if height > width else 'separator_horizontal'

    ratio = width / height
    if ratio >= 5:
        return 'bar_horizontal'
    if ratio >= 2.2:
        return 'wide'
    if ratio >= 1.25:
        return 'landscape'
    if ratio <= 0.2:
        return 'bar_vertical'
    if ratio <= 0.6:
        return 'tall'
    if ratio <= 0.85:
        return 'portrait'
    return 'square'


def size_label(width: int, height: int) -> str:
    longest = max(width, height)
    if longest >= 280:
        return 'xlarge'
    if longest >= 180:
        return 'large'
    if longest >= 110:
        return 'medium'
    if longest >= 60:
        return 'small'
    return 'tiny'


def specialized_base_name(section_name: str, width: int, height: int, orientation: str) -> str:
    if section_name == 'nine_slice_parts':
        if 'separator' in orientation:
            return 'nine_slice_guide'
        if orientation == 'bar_vertical':
            return 'nine_slice_edge'
        if orientation == 'bar_horizontal':
            return 'nine_slice_cap'
        if orientation == 'square':
            return 'nine_slice_corner'
        if orientation in {'portrait', 'tall'}:
            return 'nine_slice_side'
        return 'nine_slice_frame'

    if section_name == 'dividers':
        if 'vertical' in orientation:
            return 'divider_vertical'
        if 'separator' in orientation:
            return 'divider_strip'
        return 'divider_horizontal'

    if section_name == 'damage_decals':
        if 'separator' in orientation:
            return 'damage_streak'
        if max(width, height) <= 18:
            return 'damage_speck'
        if width >= 70 and height <= 40:
            return 'damage_slash'
        if width <= 40 and height >= 40:
            return 'damage_drip'
        return 'damage_patch'

    if section_name == 'wear_decals':
        if 'separator' in orientation:
            return 'wear_streak'
        if max(width, height) <= 18:
            return 'wear_speck'
        if width >= 70 and height <= 40:
            return 'wear_scrape'
        if width <= 40 and height >= 40:
            return 'wear_drip'
        return 'wear_patch'

    if section_name == 'loading':
        if width >= 220:
            return 'loading_frame'
        if width >= 120 and height <= 28:
            return 'loading_fill'
        if width >= 120:
            return 'loading_panel'
        return 'loading_icon'

    if section_name == 'badges':
        if 'separator' in orientation:
            return 'badge_separator'
        if width <= 40 and height <= 40:
            return 'badge_dot'
        if orientation in {'portrait', 'tall'}:
            return 'badge_tag'
        return 'badge_frame'

    if section_name == 'controls':
        if 'separator' in orientation:
            return 'control_track'
        if width <= 30 and height <= 30:
            return 'control_icon'
        if width <= 55 and height <= 40:
            return 'control_toggle'
        if width >= 180 and height <= 30:
            return 'control_bar'
        if width >= 180:
            return 'control_panel'
        return 'control_widget'

    if section_name == 'slots':
        if width >= 300:
            return 'slot_sheet'
        if width <= 35 or height <= 35:
            return 'slot_marker'
        if orientation == 'square':
            return 'slot_square'
        if orientation in {'portrait', 'tall'}:
            return 'slot_tall'
        if orientation in {'wide', 'landscape'}:
            return 'slot_wide'
        return 'slot_frame'

    if section_name == 'mobile_blanks':
        if width >= 220:
            return 'mobile_hud'
        if width >= 180:
            return 'mobile_header'
        if orientation in {'portrait', 'tall'}:
            return 'mobile_pill'
        if orientation == 'wide':
            return 'mobile_bubble'
        return 'mobile_panel'

    if section_name == 'large_swatches':
        if width >= 180:
            return 'large_swatch_panel'
        return 'large_swatch_tile'

    if section_name == 'large_textures':
        if width >= 180:
            return 'large_texture_panel'
        return 'large_texture_tile'

    return SECTION_BASE_NAMES[section_name]


def rename_section(section_dir: Path) -> None:
    section_name = section_dir.name
    files = sorted(section_dir.glob('*.png'))
    counters: defaultdict[str, int] = defaultdict(int)
    staged: list[tuple[Path, Path]] = []

    for file in files:
        with Image.open(file) as image:
            width, height = image.size

        orientation = orientation_label(width, height)
        size = size_label(width, height)
        base_name = specialized_base_name(section_name, width, height, orientation)
        key = f'{base_name}_{orientation}_{size}'
        counters[key] += 1
        new_name = f'{key}_{counters[key]:02d}.png'
        staged.append((file, file.with_name(f'__rename__{new_name}')))

    for old_path, temp_path in staged:
        old_path.rename(temp_path)

    for _, temp_path in staged:
        final_path = temp_path.with_name(temp_path.name.replace('__rename__', '', 1))
        temp_path.rename(final_path)


def main() -> None:
    for sheet_dir in sorted(path for path in BASE_DIR.iterdir() if path.is_dir()):
        for section_dir in sorted(path for path in sheet_dir.iterdir() if path.is_dir()):
            rename_section(section_dir)
            print(f'Renamed {sheet_dir.name}/{section_dir.name}')


if __name__ == '__main__':
    main()
